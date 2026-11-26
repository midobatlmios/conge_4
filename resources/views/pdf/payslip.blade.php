@php
    /**
     * @var array $p  Payslip figures (PayslipCalculator::compute + employee)
     * @var \Illuminate\Support\Carbon $month
     * @var \App\Models\CompanySetting $company
     * @var string|null $logo
     */
    $e = $p['employee'];
    $color = $company->color();
    $dh = fn ($v) => number_format((float) $v, 2, ',', ' ');
    $pct = fn ($v) => rtrim(rtrim(number_format($v * 100, 2, ',', ' '), '0'), ',') . ' %';
    $date = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') : '—';
    $period = ucfirst($month->locale('fr')->translatedFormat('F Y'));
    $name = $company->name ?: config('app.name');
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie {{ $period }}</title>
    <style>
        @page { margin: 1.5cm 1.5cm 1.8cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; line-height: 1.35; color: #111; }
        .head { width: 100%; border-bottom: 2px solid {{ $color }}; padding-bottom: 6px; }
        .head td { vertical-align: middle; }
        .logo { max-height: 45px; max-width: 140px; }
        .company { font-size: 8pt; color: #444; }
        .company strong { font-size: 10pt; color: #111; }
        h1 { font-size: 13pt; text-align: right; margin: 0; }
        .period { text-align: right; color: #555; }
        .boxes { width: 100%; margin: 10px 0; border-collapse: separate; border-spacing: 0; }
        .boxes td { width: 50%; vertical-align: top; border: 1px solid #ccc; padding: 6px 8px; }
        .boxes .label { color: #666; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th { background: #f1f1f1; border-bottom: 1px solid #bbb; padding: 4px 5px; text-align: left; }
        table.lines td { padding: 3px 5px; border-bottom: 1px solid #eee; }
        .r { text-align: right; }
        tr.sub td { font-weight: bold; background: #fafafa; border-top: 1px solid #bbb; }
        .net { margin-top: 10px; width: 100%; }
        .net td { padding: 8px 10px; font-size: 12pt; font-weight: bold; background: {{ $color }}; color: #fff; }
        .small { font-size: 7.5pt; color: #666; }
        .footer { position: fixed; bottom: -1.1cm; left: 0; right: 0; font-size: 7pt; color: #777; border-top: 1px solid #ddd; padding-top: 3px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                @if ($logo)<img class="logo" src="{{ $logo }}" alt=""><br>@endif
                <span class="company"><strong>{{ $name }}</strong><br>
                {{ collect([$company->address, $company->city])->filter()->implode(', ') }}<br>
                {{ collect([$company->ice ? "ICE {$company->ice}" : null, $company->cnss_number ? "CNSS {$company->cnss_number}" : null])->filter()->implode(' · ') }}</span>
            </td>
            <td>
                <h1>Bulletin de paie</h1>
                <div class="period">{{ $period }}</div>
            </td>
        </tr>
    </table>

    <table class="boxes">
        <tr>
            <td>
                <strong>{{ $e['name'] }}</strong><br>
                <span class="label">Matricule :</span> {{ $e['matricule'] ?? '—' }}<br>
                <span class="label">CIN :</span> {{ $e['cin'] ?? '—' }} · <span class="label">N° CNSS :</span> {{ $e['cnss_number'] ?? '—' }}
            </td>
            <td>
                <span class="label">Emploi :</span> {{ $e['job_title'] ?? '—' }}{{ $e['category'] ? " ({$e['category']})" : '' }}<br>
                <span class="label">Service :</span> {{ $e['department'] ?? '—' }}<br>
                <span class="label">Embauche :</span> {{ $date($e['hire_date']) }} · <span class="label">Ancienneté :</span> {{ $p['seniority_years'] }} an(s)
                · <span class="label">Personnes à charge :</span> {{ $p['dependents'] }}
                · <span class="label">Jours CNSS :</span> {{ $p['cnss_days'] ?? 26 }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <tr><th>Rubrique</th><th class="r">Base</th><th class="r">Taux</th><th class="r">Gains</th><th class="r">Retenues</th></tr>
        <tr>
            <td>Salaire de base{{ $p['employed_days'] < $p['month_days'] ? " ({$p['employed_days']} / {$p['month_days']} jours ouvrés)" : '' }}</td>
            <td class="r">{{ $dh($p['base_salary']) }}</td><td class="r"></td><td class="r">{{ $dh($p['prorated'] ?? $p['base']) }}</td><td></td>
        </tr>
        @if (($p['absence_deduction'] ?? 0) > 0)
            <tr>
                <td>Absences non payées ({{ collect([($p['unpaid_days'] ?? 0) > 0 ? $dh($p['unpaid_days']) . ' j sans solde' : null, ($p['absent_days'] ?? 0) > 0 ? $dh($p['absent_days']) . ' j d\'absence' : null])->filter()->implode(', ') }})</td>
                <td class="r">{{ $dh(($p['unpaid_days'] ?? 0) + ($p['absent_days'] ?? 0)) }} j</td><td class="r">{{ $dh($p['day_value']) }}</td><td></td><td class="r">{{ $dh($p['absence_deduction']) }}</td>
            </tr>
        @endif
        @if ($p['seniority'] > 0)
            <tr><td>Prime d'ancienneté</td><td class="r">{{ $dh($p['base']) }}</td><td class="r">{{ $pct($p['seniority_rate']) }}</td><td class="r">{{ $dh($p['seniority']) }}</td><td></td></tr>
        @endif
        @foreach ($p['overtime_lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="r">{{ $dh($line['hours']) }} h</td><td class="r">{{ $dh($line['rate']) }}</td><td class="r">{{ $dh($line['amount']) }}</td><td></td></tr>
        @endforeach
        @foreach (collect($p['bonus_lines'] ?? [])->where('taxable', true) as $line)
            <tr><td>{{ $line['label'] }}</td><td></td><td></td><td class="r">{{ $dh($line['amount']) }}</td><td></td></tr>
        @endforeach
        <tr class="sub"><td>Salaire brut</td><td></td><td></td><td class="r">{{ $dh($p['gross']) }}</td><td></td></tr>
        <tr><td>CNSS prestations sociales</td><td class="r">{{ $dh($p['cnss_base']) }}</td><td class="r">{{ $pct(config('payroll.cnss_employee_rate')) }}</td><td></td><td class="r">{{ $dh($p['cnss']) }}</td></tr>
        <tr><td>AMO</td><td class="r">{{ $dh($p['gross']) }}</td><td class="r">{{ $pct(config('payroll.amo_employee_rate')) }}</td><td></td><td class="r">{{ $dh($p['amo']) }}</td></tr>
        <tr><td>Impôt sur le revenu (IR)</td><td class="r">{{ $dh($p['taxable']) }}</td><td class="r">{{ $pct($p['ir_rate']) }}</td><td></td><td class="r">{{ $dh($p['ir']) }}</td></tr>
        @foreach (collect($p['bonus_lines'] ?? [])->where('taxable', false) as $line)
            <tr><td>{{ $line['label'] }} (non imposable)</td><td></td><td></td><td class="r">{{ $dh($line['amount']) }}</td><td></td></tr>
        @endforeach
        @foreach ($p['advance_lines'] ?? [] as $line)
            <tr><td>{{ $line['label'] }}</td><td></td><td></td><td></td><td class="r">{{ $dh($line['amount']) }}</td></tr>
        @endforeach
        <tr class="sub"><td>Total</td><td></td><td></td><td class="r">{{ $dh($p['gross'] + ($p['exempt_allowances'] ?? 0)) }}</td><td class="r">{{ $dh($p['deductions'] + ($p['advances'] ?? 0)) }}</td></tr>
    </table>
    <p class="small">
        Net imposable : {{ $dh($p['taxable']) }} DH (brut − frais professionnels {{ $pct($p['professional_expenses_rate']) }} soit {{ $dh($p['professional_expenses']) }} DH − CNSS − AMO).
        @if ($p['family_deduction'] > 0) Déduction pour charges de famille : {{ $dh($p['family_deduction']) }} DH. @endif
        Taux horaire : {{ $dh($p['hourly_rate']) }} DH.
    </p>

    <table class="net"><tr><td>Net à payer</td><td class="r">{{ $dh($p['net']) }} DH</td></tr></table>

    <table class="lines" style="margin-top: 14px;">
        <tr><th>Charges patronales</th><th class="r">Taux</th><th class="r">Montant</th></tr>
        @foreach ($p['employer'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="r">{{ $pct($line['rate']) }}</td><td class="r">{{ $dh($line['amount']) }}</td></tr>
        @endforeach
        <tr class="sub"><td>Total charges patronales · coût total employeur {{ $dh($p['cost']) }} DH</td><td></td><td class="r">{{ $dh($p['employer_total']) }}</td></tr>
    </table>

    <div class="footer">{{ $name }} · Bulletin de paie {{ $period }} · À conserver sans limitation de durée.</div>
</body>
</html>
