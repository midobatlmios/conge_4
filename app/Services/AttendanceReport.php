<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** The monthly attendance report as an Excel file, one row per employee (hours as decimals). */
class AttendanceReport
{
    /**
     * @param  list<array<string, mixed>>  $rows  AttendanceReportController rows
     * @return string Path of a temporary .xlsx file
     */
    public function toXlsx(Carbon $month, ?string $departmentName, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Pointage');
        $company = CompanySetting::current()->name ?? config('app.name');
        $hours = fn (int $minutes) => round($minutes / 60, 2);

        $sheet->setCellValue('A1', 'Pointage — '.ucfirst($month->locale('fr')->translatedFormat('F Y'))." — {$company}");
        $sheet->setCellValue('A2', ($departmentName ? "Département {$departmentName}" : 'Tous les départements')
            .'. Heures en décimal (7,5 = 7 h 30). Heures supplémentaires à titre indicatif, par taux du Code du travail. Exporté le '.now()->format('d/m/Y H:i').'.');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $headings = ['Employé', 'Département', 'Heures prévues', 'Heures travaillées', 'Jours présents', 'Absences non justifiées', 'Retards', 'Minutes de retard', 'Départs non pointés', 'H. sup. +25 %', 'H. sup. +50 %', 'H. sup. +100 %'];
        $sheet->fromArray($headings, null, 'A4');
        $sheet->getStyle('A4:L4')->getFont()->setBold(true);
        $sheet->getStyle('A4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');

        $sheet->fromArray(array_map(fn (array $r) => [
            $r['name'], $r['department'] ?? '', $hours($r['expected']), $hours($r['worked']), $r['present_days'], $r['absent_days'],
            $r['late_days'], $r['late_minutes'], $r['missing_out'], $hours($r['overtime_25']), $hours($r['overtime_50']), $hours($r['overtime_100']),
        ], $rows), null, 'A5', true);

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A5');

        $path = tempnam(sys_get_temp_dir(), 'pointage');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public static function fileName(Carbon $month, ?string $departmentName): string
    {
        return 'pointage-'.$month->format('Y-m').($departmentName ? '-'.str($departmentName)->slug() : '').'.xlsx';
    }
}
