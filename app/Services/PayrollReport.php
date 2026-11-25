<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Monthly leave summary for payroll: for each employee, the working days of
 * accepted leave in the month, by kind (annual, paid exceptional, unpaid to
 * deduct, récupération), and their balance at the end of the month.
 */
class PayrollReport
{
    /** Columns of the export, in order: key => French heading. */
    public const COLUMNS = [
        'matricule' => 'Matricule',
        'name' => 'Nom',
        'department' => 'Département',
        'job_title' => 'Poste',
        'annual' => 'Congé annuel (j)',
        'exceptional' => 'Congés exceptionnels payés (j)',
        'unpaid' => 'Sans solde – à déduire (j)',
        'recovery' => 'Récupération (j)',
        'sick' => 'Maladie (j)',
        'total' => 'Total absences (j)',
        'balance' => 'Solde fin de mois (j)',
    ];

    /** Which column each leave type is counted in. */
    private const TYPE_COLUMNS = [
        'annuel' => 'annual',
        'mariage' => 'exceptional',
        'naissance' => 'exceptional',
        'deces' => 'exceptional',
        'sans_solde' => 'unpaid',
        'recuperation' => 'recovery',
        'maladie' => 'sick',
    ];

    public function __construct(private readonly LeaveCalculator $calculator) {}

    /**
     * One row per employee on the payroll that month: active then, and hired by
     * the end of it (deactivated during the month still counts).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->startOfDay();

        $employees = User::with('department:id,name')
            ->where(fn ($q) => $q->whereNull('deactivated_at')->orWhere('deactivated_at', '>=', $start))
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $end))
            ->orderBy('name')
            ->get();

        $leaves = Demande::query()
            ->whereIn('user_id', $employees->modelKeys())
            ->whereIn('etat', LeaveStatus::booked())
            ->whereDate('date_debut', '<=', $end)
            ->whereDate('date_fin', '>=', $start)
            ->get()
            ->groupBy('user_id');

        return $employees->map(function (User $employee) use ($leaves, $start, $end) {
            $days = ['annual' => 0, 'exceptional' => 0, 'unpaid' => 0, 'recovery' => 0, 'sick' => 0];

            foreach ($leaves[$employee->id] ?? [] as $leave) {
                $days[self::TYPE_COLUMNS[$leave->type_conge->value]] += $this->calculator->leaveDays(
                    $leave->date_debut, $leave->date_fin, $leave->start_half, $leave->end_half, $start, $end
                );
            }

            return [
                'id' => $employee->id,
                'matricule' => $employee->matricule,
                'name' => $employee->name,
                'department' => $employee->department?->name,
                'job_title' => $employee->job_title,
                ...$days,
                'total' => array_sum($days),
                'balance' => $this->calculator->availableDays($employee, $end->year, $end)
                    - $this->calculator->usedDaysUntil($employee->id, $end->year, $end),
            ];
        })->values();
    }

    /** The month's report as an Excel workbook, saved to a temporary file whose path is returned. */
    public function toXlsx(Carbon $month): string
    {
        $rows = $this->rows($month);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($month->format('Y-m'));
        $lastColumn = chr(ord('A') + count(self::COLUMNS) - 1);

        $sheet->setCellValue('A1', 'Congés du mois de '.$month->locale('fr')->translatedFormat('F Y'));
        $sheet->setCellValue('A2', 'Jours ouvrés de congé accepté, hors week-ends et jours fériés. Exporté le '.now()->format('d/m/Y H:i').'.');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $sheet->fromArray(array_values(self::COLUMNS), null, 'A4');
        $sheet->getStyle("A4:{$lastColumn}4")->getFont()->setBold(true);
        $sheet->getStyle("A4:{$lastColumn}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');

        $line = 5;
        foreach ($rows as $row) {
            $sheet->fromArray(array_map(fn (string $key) => $row[$key], array_keys(self::COLUMNS)), null, "A{$line}", true);
            $line++;
        }

        // Totals of the day columns.
        $sheet->setCellValue("A{$line}", 'Total');
        foreach (['annual', 'exceptional', 'unpaid', 'recovery', 'sick', 'total'] as $key) {
            $column = chr(ord('A') + array_search($key, array_keys(self::COLUMNS), true));
            $sheet->setCellValue("{$column}{$line}", $rows->sum($key));
        }
        $sheet->getStyle("A{$line}:{$lastColumn}{$line}")->getFont()->setBold(true);

        // Unpaid days stand out: they change the salary.
        $sheet->getStyle('G4:G'.$line)->getFont()->getColor()->setRGB('B91C1C');

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('C5');

        $path = tempnam(sys_get_temp_dir(), 'paie');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /** Download file name, e.g. conges-paie-2026-03.xlsx. */
    public static function fileName(Carbon $month): string
    {
        return 'conges-paie-'.$month->format('Y-m').'.xlsx';
    }

    /** @return list<string> Every leave type is mapped to a column. */
    public static function unmappedTypes(): array
    {
        return array_values(array_diff(array_column(LeaveType::cases(), 'value'), array_keys(self::TYPE_COLUMNS)));
    }
}
