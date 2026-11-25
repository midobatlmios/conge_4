<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The year-end HR report (bilan social simplifié) as an Excel workbook: a
 * summary sheet, then one sheet per indicator, from HrIndicators::compute().
 */
class HrIndicatorsReport
{
    /**
     * @param  array<string, mixed>  $data  HrIndicators::compute()
     * @return string Path of a temporary .xlsx file
     */
    public function toXlsx(array $data, ?string $departmentName, string $companyName): string
    {
        $spreadsheet = new Spreadsheet;
        $scope = $departmentName ? "Département {$departmentName}" : 'Tous les départements';
        $until = date('d/m/Y', strtotime($data['until']));
        $s = $data['summary'];
        $b = $data['balances'];

        $summary = $spreadsheet->getActiveSheet()->setTitle('Synthèse');
        $this->title($summary, "Bilan RH {$data['year']} — {$companyName}", "{$scope}. Période du 01/01/{$data['year']} au {$until}. Exporté le ".now()->format('d/m/Y H:i').'.');
        $this->table($summary, 4, ['Indicateur', 'Valeur'], [
            ['Effectif au 1er janvier', $s['headcount_start']],
            ['Effectif en fin de période', $s['headcount_end']],
            ['Effectif moyen (fin de mois)', $s['average_headcount']],
            ['Embauches', $s['hires']],
            ['Départs', $s['departures']],
            ['Taux de rotation (%)', $s['turnover']],
            ['Ancienneté moyenne (années)', $s['average_seniority']],
            ['Jours ouvrés théoriques', $s['theoretical_days']],
            ['Jours de maladie', $s['sick_days']],
            ['Arrêts maladie', $s['sick_stops']],
            ['Jours sans solde', $s['unpaid_days']],
            ["Taux d'absentéisme (%) — maladie + sans solde", $s['absenteeism']],
            ['Congés planifiés pris (annuel, récupération, événements familiaux)', $s['planned_days']],
            ['Jours de congé non pris (fin de période)', $b['total_remaining']],
            ['Moyenne de jours non pris par employé', $b['average_remaining']],
            ['Jours reportés expirant le 31 mars', $b['carry_expiring']],
        ]);

        $months = $spreadsheet->createSheet()->setTitle('Mensuel');
        $this->title($months, "Effectifs et absentéisme par mois — {$data['year']}", $scope);
        $this->table($months, 4, ['Mois', 'Effectif fin de mois', 'Embauches', 'Départs', 'Jours théoriques', 'Jours maladie', 'Arrêts maladie', 'Jours sans solde', 'Absentéisme (%)', 'Congés planifiés (j)'], array_map(fn (array $m) => [
            $m['label'], $m['headcount'], $m['hires'], $m['departures'], $m['theoretical_days'], $m['sick_days'], $m['sick_stops'], $m['unpaid_days'], $m['absenteeism'], $m['planned_days'],
        ], $data['months']));

        $departments = $spreadsheet->createSheet()->setTitle('Départements');
        $this->title($departments, "Absentéisme par département — {$data['year']}", 'Maladie + sans solde ÷ jours ouvrés théoriques.');
        $this->table($departments, 4, ['Département', 'Effectif', 'Jours théoriques', 'Jours maladie', 'Jours sans solde', 'Absentéisme (%)'], array_map(fn (array $d) => [
            $d['name'] ?? 'Sans département', $d['headcount'], $d['theoretical_days'], $d['sick_days'], $d['unpaid_days'], $d['absenteeism'],
        ], $data['departments']));

        $balances = $spreadsheet->createSheet()->setTitle('Soldes de congés');
        $this->title($balances, "Jours de congé non pris au {$until}", 'Congé annuel acquis (avec report) moins jours pris ou posés.');
        $this->table($balances, 4, ['Employé', 'Département', 'Jours non pris', 'Dont reportés expirant le 31 mars'], array_map(fn (array $row) => [
            $row['name'], $row['department'] ?? '', $row['remaining'], $row['carry_left'],
        ], $b['all']));

        $spreadsheet->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'bilan');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public static function fileName(int $year, ?string $departmentName): string
    {
        return 'bilan-rh-'.$year.($departmentName ? '-'.str($departmentName)->slug() : '').'.xlsx';
    }

    private function title(Worksheet $sheet, string $title, string $subtitle): void
    {
        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', $subtitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);
    }

    /** @param list<list<mixed>> $rows */
    private function table(Worksheet $sheet, int $firstRow, array $headings, array $rows): void
    {
        $last = chr(ord('A') + count($headings) - 1);
        $sheet->fromArray($headings, null, "A{$firstRow}");
        $sheet->getStyle("A{$firstRow}:{$last}{$firstRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$firstRow}:{$last}{$firstRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        // Empty values (a rate with nothing to divide by) stay empty cells.
        $sheet->fromArray(array_map(fn (array $row) => array_map(fn ($value) => $value ?? '', $row), $rows), null, 'A'.($firstRow + 1), true);

        foreach (range('A', $last) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A'.($firstRow + 1));
    }
}
