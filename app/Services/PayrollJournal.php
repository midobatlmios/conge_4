<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\PayrollRun;
use App\Models\Payslip;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The month's payroll as an Excel workbook for the accountant: the payroll
 * journal (one line per payslip, with totals) and the CNSS list (declared
 * days and salaries). The CNSS list helps fill the declaration on Damancom;
 * it is not the official electronic file (BDS).
 */
class PayrollJournal
{
    /** @return string Path of a temporary .xlsx file */
    public function toXlsx(PayrollRun $run): string
    {
        $payslips = $run->payslips()->get()->sortBy(fn (Payslip $p) => $p->figures['employee']['name'])->values();
        $company = CompanySetting::current();
        $period = ucfirst($run->month->locale('fr')->translatedFormat('F Y'));
        $status = PayrollRun::STATUS_LABELS[$run->status];
        $spreadsheet = new Spreadsheet;

        $journal = $spreadsheet->getActiveSheet()->setTitle('Journal de paie');
        $this->title($journal, "Journal de paie — {$period} — ".($company->name ?? config('app.name')), "Paie {$status}. Montants en DH. Exporté le ".now()->format('d/m/Y H:i').'.');
        $columns = [
            'Matricule' => fn ($e, $f) => $e['matricule'] ?? '',
            'Nom' => fn ($e, $f) => $e['name'],
            'Salaire de base' => fn ($e, $f) => $f['base_salary'],
            'Jours sans solde' => fn ($e, $f) => $f['unpaid_days'] ?? 0,
            'Jours d\'absence' => fn ($e, $f) => $f['absent_days'] ?? 0,
            'Retenue absences' => fn ($e, $f) => $f['absence_deduction'] ?? 0,
            'Prime d\'ancienneté' => fn ($e, $f) => $f['seniority'],
            'Heures supplémentaires' => fn ($e, $f) => $f['overtime'],
            'Primes imposables' => fn ($e, $f) => $f['taxable_bonuses'] ?? 0,
            'Salaire brut' => fn ($e, $f) => $f['gross'],
            'CNSS salarié' => fn ($e, $f) => $f['cnss'],
            'AMO salarié' => fn ($e, $f) => $f['amo'],
            'Frais professionnels' => fn ($e, $f) => $f['professional_expenses'],
            'Net imposable' => fn ($e, $f) => $f['taxable'],
            'IR' => fn ($e, $f) => $f['ir'],
            'Indemnités non imposables' => fn ($e, $f) => $f['exempt_allowances'] ?? 0,
            'Avances et prêts' => fn ($e, $f) => $f['advances'] ?? 0,
            'Net à payer' => fn ($e, $f) => $f['net'],
            'Charges patronales' => fn ($e, $f) => $f['employer_total'],
            'Coût employeur' => fn ($e, $f) => $f['cost'],
        ];
        $rows = $payslips->map(fn (Payslip $p) => array_map(fn ($value) => $value($p->figures['employee'], $p->figures), array_values($columns)))->all();
        $this->table($journal, array_keys($columns), $rows, totalsFrom: 2);

        $cnss = $spreadsheet->createSheet()->setTitle('CNSS');
        $this->title($cnss, "Déclaration CNSS — {$period}", 'Aide à la déclaration sur Damancom (pas le fichier officiel BDS). Jours déclarés : 26 pour un mois complet, moins les jours non payés.');
        $ceiling = (float) config('payroll.cnss_ceiling');
        $this->table($cnss, ['N° CNSS', 'Nom', 'CIN', 'Jours déclarés', 'Salaire réel', 'Salaire plafonné', 'Part salariale (CNSS + AMO)', 'Part patronale'], $payslips->map(fn (Payslip $p) => [
            $p->figures['employee']['cnss_number'] ?? '',
            $p->figures['employee']['name'],
            $p->figures['employee']['cin'] ?? '',
            $p->figures['cnss_days'] ?? 26,
            $p->figures['gross'],
            min($p->figures['gross'], $ceiling),
            round($p->figures['cnss'] + $p->figures['amo'], 2),
            $p->figures['employer_total'],
        ])->all(), totalsFrom: 3);

        $spreadsheet->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'journal');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public static function fileName(PayrollRun $run): string
    {
        return 'journal-paie-'.$run->month->format('Y-m').'.xlsx';
    }

    private function title(Worksheet $sheet, string $title, string $subtitle): void
    {
        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', $subtitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  int  $totalsFrom  0-based index of the first column summed in the total line
     */
    private function table(Worksheet $sheet, array $headings, array $rows, int $totalsFrom): void
    {
        $last = $this->column(count($headings) - 1);
        $sheet->fromArray($headings, null, 'A4');
        $sheet->getStyle("A4:{$last}4")->getFont()->setBold(true);
        $sheet->getStyle("A4:{$last}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->fromArray($rows, null, 'A5', true);

        $totalRow = 5 + count($rows);
        $sheet->setCellValue("A{$totalRow}", 'Total');
        for ($i = $totalsFrom; $i < count($headings); $i++) {
            $col = $this->column($i);
            $sheet->setCellValue("{$col}{$totalRow}", $rows === [] ? 0 : "=SUM({$col}5:{$col}".($totalRow - 1).')');
        }
        $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("C5:{$last}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        for ($i = 0; $i < count($headings); $i++) {
            $sheet->getColumnDimension($this->column($i))->setAutoSize(true);
        }
        $sheet->freezePane('C5');
    }

    private function column(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    }
}
