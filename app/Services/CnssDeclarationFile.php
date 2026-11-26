<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Support\Str;

/**
 * The monthly CNSS salary declaration as a Damancom e-BDS style text file:
 * fixed-width lines of 260 characters, numbers right-aligned with zeros,
 * texts left-aligned with spaces (upper case, no accents), amounts in
 * centimes, CRLF line ends.
 *
 *   B00 transfer header · B01 company header
 *   B02 one line per declared employee (has a CNSS number) · B03 their totals
 *   B04 one line per new employee without a CNSS number (CIN) · B05 their totals
 *   B06 overall totals
 *
 * BUILT FROM THE PUBLICLY KNOWN LAYOUT, NOT FROM AN OFFICIAL SPECIFICATION:
 * the transfer identifier, agency code, family allowance fields (left at 0:
 * they come from the CNSS pre-filled file) and the control sums must be
 * checked on Damancom before the first real declaration.
 */
class CnssDeclarationFile
{
    public const LINE = 260;

    public function build(PayrollRun $run): string
    {
        $company = CompanySetting::current();
        $affiliate = $this->num($company->cnss_number, 7);
        $period = $run->month->format('Ym');
        $payslips = $run->payslips()->get()->sortBy(fn (Payslip $p) => $p->figures['employee']['name'])->values();
        $known = $payslips->filter(fn (Payslip $p) => filled($p->figures['employee']['cnss_number'] ?? null))->values();
        $new = $payslips->reject(fn (Payslip $p) => filled($p->figures['employee']['cnss_number'] ?? null))->values();
        $lines = [];

        // B00: transfer header (identifier given by the CNSS: zeros until known).
        $lines[] = $this->line('B00', $this->num(0, 14), 'B0');

        // B01: company header.
        $lines[] = $this->line('B01', $affiliate, $period,
            $this->alpha($company->name, 40), $this->alpha($company->legal_form, 40), $this->alpha($company->address, 120),
            $this->alpha($company->city, 20), $this->num(0, 6), $this->num(0, 2),
            now()->format('Ymd'), $run->month->copy()->addMonth()->day(10)->format('Ymd'));

        // B02: declared employees.
        $t = ['count' => 0, 'ids' => 0, 'days' => 0, 'real' => 0, 'capped' => 0, 'ctr' => 0];
        foreach ($known as $p) {
            [$id, $days, $real, $capped] = $this->amounts($p);
            $ctr = $id + $days + $real + $capped;
            $lines[] = $this->line('B02', $affiliate, $period, $this->num($id, 9), $this->alpha($p->figures['employee']['name'], 60),
                $this->num(0, 2), $this->num(0, 6), $this->num(0, 6), $this->num(0, 6), $this->num(0, 6),
                $this->num($days, 2), $this->num($real, 13), $this->num($capped, 9), $this->situation($p), $this->num($ctr, 19));
            $t = ['count' => $t['count'] + 1, 'ids' => $t['ids'] + $id, 'days' => $t['days'] + $days, 'real' => $t['real'] + $real, 'capped' => $t['capped'] + $capped, 'ctr' => $t['ctr'] + $ctr];
        }
        $lines[] = $this->line('B03', $affiliate, $period, $this->num($t['count'], 6), $this->num(0, 6), $this->num(0, 12), $this->num(0, 12), $this->num(0, 12),
            $this->num($t['ids'], 15), $this->num(0, 12), $this->num($t['days'], 6), $this->num($t['real'], 15), $this->num($t['capped'], 13), $this->num($t['ctr'], 19));

        // B04: new employees, not registered yet (identified by their CIN).
        $n = ['count' => 0, 'days' => 0, 'real' => 0, 'capped' => 0, 'ctr' => 0];
        foreach ($new as $p) {
            [, $days, $real, $capped] = $this->amounts($p);
            $ctr = $days + $real + $capped;
            $lines[] = $this->line('B04', $affiliate, $period, $this->num(0, 9), $this->alpha($p->figures['employee']['name'], 60),
                $this->alpha($p->figures['employee']['cin'] ?? '', 8), $this->num($days, 2), $this->num($real, 13), $this->num($capped, 9), $this->num($ctr, 19));
            $n = ['count' => $n['count'] + 1, 'days' => $n['days'] + $days, 'real' => $n['real'] + $real, 'capped' => $n['capped'] + $capped, 'ctr' => $n['ctr'] + $ctr];
        }
        $lines[] = $this->line('B05', $affiliate, $period, $this->num($n['count'], 6), $this->num(0, 15), $this->num($n['days'], 6),
            $this->num($n['real'], 15), $this->num($n['capped'], 13), $this->num($n['ctr'], 19));

        // B06: everything declared.
        $lines[] = $this->line('B06', $affiliate, $period, $this->num($t['count'] + $n['count'], 6), $this->num($t['ids'], 15), $this->num($t['days'] + $n['days'], 6),
            $this->num($t['real'] + $n['real'], 15), $this->num($t['capped'] + $n['capped'], 13), $this->num($t['ctr'] + $n['ctr'], 19));

        return implode("\r\n", $lines)."\r\n";
    }

    public static function fileName(PayrollRun $run): string
    {
        return 'BDS_'.preg_replace('/\D/', '', (string) CompanySetting::current()->cnss_number).'_'.$run->month->format('Ym').'.txt';
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} CNSS number, declared days, real and capped salary in centimes. */
    private function amounts(Payslip $p): array
    {
        $f = $p->figures;
        $gross = (float) $f['gross'];

        return [
            (int) preg_replace('/\D/', '', (string) ($f['employee']['cnss_number'] ?? '0')),
            min(26, (int) ($f['cnss_days'] ?? 26)),
            (int) round($gross * 100),
            (int) round(min($gross, (float) config('payroll.cnss_ceiling')) * 100),
        ];
    }

    /** SO: normal; CS: no day paid this month (unpaid leave / absence). */
    private function situation(Payslip $p): string
    {
        return ($p->figures['cnss_days'] ?? 26) === 0 ? 'CS' : 'SO';
    }

    /** A record padded to 260 characters. */
    private function line(string ...$fields): string
    {
        return str_pad(substr(implode('', $fields), 0, self::LINE), self::LINE, ' ');
    }

    private function num(int|float|string|null $value, int $length): string
    {
        $digits = preg_replace('/\D/', '', (string) (is_numeric($value) ? (int) $value : $value)) ?: '0';

        return str_pad(substr($digits, -$length), $length, '0', STR_PAD_LEFT);
    }

    private function alpha(?string $value, int $length): string
    {
        $text = strtoupper(Str::ascii((string) $value));
        $text = preg_replace('/[^A-Z0-9 \-\.\/\']/', ' ', $text);

        return str_pad(substr($text, 0, $length), $length, ' ');
    }
}
