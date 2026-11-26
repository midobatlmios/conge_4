@php
    /**
     * @var array $d  HrIndicators::compute()
     * @var string|null $department
     * @var \App\Models\CompanySetting $company
     * @var string|null $logo
     */
    $s = $d['summary'];
    $b = $d['balances'];
    $color = $company->color();
    $num = fn ($value, int $digits = 1) => $value === null ? '—' : number_format((float) $value, $digits, ',', ' ');
    $int = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $pct = fn ($value) => $value === null ? '—' : $num($value) . ' %';
    $days = fn ($value) => $num($value) . ' j';
    $dh = fn ($value) => $value === null ? '—' : number_format((float) $value, 0, ',', ' ') . ' DH';
    $until = \Illuminate\Support\Carbon::parse($d['until'])->format('d/m/Y');
    $scope = $department ? "Département {$department}" : 'Tous les départements';
    $name = $company->name ?: config('app.name');
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bilan RH {{ $d['year'] }}</title>
    <style>
        @page { margin: 1.6cm 1.5cm 2cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; line-height: 1.4; color: #111; }
        .head { width: 100%; border-bottom: 2px solid {{ $color }}; padding-bottom: 8px; }
        .head td { vertical-align: middle; }
        .logo { max-height: 50px; max-width: 150px; }
        .company { text-align: right; font-size: 8pt; color: #444; }
        .company strong { font-size: 10pt; color: #111; }
        h1 { font-size: 15pt; margin: 18px 0 2px; }
        .sub { color: #555; margin: 0 0 14px; }
        h2 { font-size: 10.5pt; margin: 18px 0 6px; color: {{ $color }}; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f1f1f1; text-align: left; font-weight: bold; padding: 4px 5px; border-bottom: 1px solid #ccc; }
        table.data td { padding: 3px 5px; border-bottom: 1px solid #e6e6e6; }
        table.data .r { text-align: right; }
        table.data tr.total td { font-weight: bold; border-top: 1px solid #999; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px; }
        .kpis td { border: 1px solid #ddd; border-radius: 4px; padding: 6px 8px; width: 20%; vertical-align: top; }
        .kpis .label { color: #555; font-size: 7.5pt; }
        .kpis .value { font-size: 13pt; font-weight: bold; margin-top: 2px; }
        .kpis .hint { color: #666; font-size: 7pt; margin-top: 2px; }
        .note { color: #666; font-size: 7.5pt; margin-top: 4px; }
        .warn { color: #b45309; }
        .page-break { page-break-before: always; }
        .footer { position: fixed; bottom: -1.2cm; left: 0; right: 0; font-size: 7pt; color: #777; border-top: 1px solid #ddd; padding-top: 3px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="">
                @else
                    <strong style="font-size: 12pt;">{{ $name }}</strong>
                @endif
            </td>
            <td class="company">
                <strong>{{ $name }}</strong><br>
                {{ collect([$company->address, $company->city])->filter()->implode(', ') }}
            </td>
        </tr>
    </table>

    <h1>Bilan RH {{ $d['year'] }}</h1>
    <p class="sub">{{ $scope }} · du 01/01/{{ $d['year'] }} au {{ $until }}</p>

    <table class="kpis">
        <tr>
            <td>
                <div class="label">Effectif</div>
                <div class="value">{{ $s['headcount_end'] }}</div>
                <div class="hint">{{ $s['headcount_start'] }} au 1er janvier · moyenne {{ $num($s['average_headcount']) }}</div>
            </td>
            <td>
                <div class="label">Taux de rotation</div>
                <div class="value">{{ $pct($s['turnover']) }}</div>
                <div class="hint">{{ $s['hires'] }} embauche(s) · {{ $s['departures'] }} départ(s)</div>
            </td>
            <td>
                <div class="label">Absentéisme</div>
                <div class="value">{{ $pct($s['absenteeism']) }}</div>
                <div class="hint">{{ $days($s['sick_days']) }} maladie · {{ $days($s['unpaid_days']) }} sans solde</div>
            </td>
            <td>
                <div class="label">Ancienneté moyenne</div>
                <div class="value">{{ $s['average_seniority'] === null ? '—' : $num($s['average_seniority']) . ' ans' }}</div>
                <div class="hint">{{ $days($s['planned_days']) }} de congés planifiés pris</div>
            </td>
            <td>
                <div class="label">Masse salariale brute</div>
                <div class="value">{{ $dh($s['payroll']) }}</div>
                <div class="hint">
                    {{ $dh($s['average_payroll']) }} par mois
                    @if ($s['missing_salaries'] > 0)
                        <br><span class="warn">{{ $s['missing_salaries'] }} salaire(s) non renseigné(s)</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <h2>Synthèse</h2>
    <table class="data">
        <tr><th>Indicateur</th><th class="r">Valeur</th></tr>
        <tr><td>Effectif au 1er janvier</td><td class="r">{{ $s['headcount_start'] }}</td></tr>
        <tr><td>Effectif en fin de période</td><td class="r">{{ $s['headcount_end'] }}</td></tr>
        <tr><td>Effectif moyen (fin de mois)</td><td class="r">{{ $num($s['average_headcount']) }}</td></tr>
        <tr><td>Embauches / départs</td><td class="r">{{ $s['hires'] }} / {{ $s['departures'] }}</td></tr>
        <tr><td>Taux de rotation</td><td class="r">{{ $pct($s['turnover']) }}</td></tr>
        <tr><td>Jours ouvrés théoriques</td><td class="r">{{ $int($s['theoretical_days']) }}</td></tr>
        <tr><td>Jours de maladie ({{ $s['sick_stops'] }} arrêt(s))</td><td class="r">{{ $days($s['sick_days']) }}</td></tr>
        <tr><td>Jours sans solde</td><td class="r">{{ $days($s['unpaid_days']) }}</td></tr>
        <tr><td>Taux d'absentéisme (maladie + sans solde)</td><td class="r">{{ $pct($s['absenteeism']) }}</td></tr>
        <tr><td>Congés planifiés pris (annuel, récupération, événements familiaux)</td><td class="r">{{ $days($s['planned_days']) }}</td></tr>
        <tr><td>Jours de congé non pris (fin de période)</td><td class="r">{{ $days($b['total_remaining']) }}</td></tr>
        <tr><td>Moyenne de jours non pris par employé</td><td class="r">{{ $days($b['average_remaining']) }}</td></tr>
        @if ($b['carry_expiry'])
            <tr><td>Jours reportés expirant le {{ \Illuminate\Support\Carbon::parse($b['carry_expiry'])->format('d/m/Y') }}</td><td class="r">{{ $days($b['carry_expiring']) }}</td></tr>
        @endif
        <tr><td>Masse salariale brute de la période</td><td class="r">{{ $dh($s['payroll']) }}</td></tr>
        <tr><td>Masse salariale brute mensuelle moyenne</td><td class="r">{{ $dh($s['average_payroll']) }}</td></tr>
        <tr><td>Salaire brut mensuel moyen</td><td class="r">{{ $dh($s['average_salary']) }}</td></tr>
    </table>
    <p class="note">
        Absentéisme : jours de maladie et sans solde ÷ jours ouvrés théoriques. Rotation : départs ÷ effectif moyen.
        Masse salariale : salaires bruts mensuels des contrats en cours le dernier jour de chaque mois, hors primes, jours sans solde et charges patronales.
    </p>

    <h2>Par mois</h2>
    <table class="data">
        <tr>
            <th>Mois</th><th class="r">Effectif</th><th class="r">Embauches</th><th class="r">Départs</th>
            <th class="r">Jours théoriques</th><th class="r">Maladie</th><th class="r">Sans solde</th>
            <th class="r">Absentéisme</th><th class="r">Congés planifiés</th><th class="r">Masse salariale</th>
        </tr>
        @foreach ($d['months'] as $m)
            <tr>
                <td>{{ $m['label'] }}</td>
                <td class="r">{{ $m['headcount'] }}</td>
                <td class="r">{{ $m['hires'] }}</td>
                <td class="r">{{ $m['departures'] }}</td>
                <td class="r">{{ $int($m['theoretical_days']) }}</td>
                <td class="r">{{ $days($m['sick_days']) }}</td>
                <td class="r">{{ $days($m['unpaid_days']) }}</td>
                <td class="r">{{ $pct($m['absenteeism']) }}</td>
                <td class="r">{{ $days($m['planned_days']) }}</td>
                <td class="r">{{ $dh($m['payroll']) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="r"></td>
            <td class="r">{{ $s['hires'] }}</td>
            <td class="r">{{ $s['departures'] }}</td>
            <td class="r">{{ $int($s['theoretical_days']) }}</td>
            <td class="r">{{ $days($s['sick_days']) }}</td>
            <td class="r">{{ $days($s['unpaid_days']) }}</td>
            <td class="r">{{ $pct($s['absenteeism']) }}</td>
            <td class="r">{{ $days($s['planned_days']) }}</td>
            <td class="r">{{ $dh($s['payroll']) }}</td>
        </tr>
    </table>

    <h2>Par département</h2>
    <table class="data">
        <tr>
            <th>Département</th><th class="r">Effectif</th><th class="r">Maladie</th><th class="r">Sans solde</th>
            <th class="r">Absentéisme</th><th class="r">Masse salariale</th>
        </tr>
        @foreach ($d['departments'] as $row)
            <tr>
                <td>{{ $row['name'] ?? 'Sans département' }}</td>
                <td class="r">{{ $row['headcount'] }}</td>
                <td class="r">{{ $days($row['sick_days']) }}</td>
                <td class="r">{{ $days($row['unpaid_days']) }}</td>
                <td class="r">{{ $pct($row['absenteeism']) }}</td>
                <td class="r">{{ $dh($row['payroll']) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="page-break"></div>
    <h2>Soldes de congés au {{ $until }}</h2>
    <p class="note" style="margin-bottom: 6px;">Congé annuel acquis (avec report) moins jours pris ou posés, du plus élevé au plus faible.</p>
    <table class="data">
        <tr><th>Employé</th><th>Département</th><th class="r">Jours non pris</th><th class="r">Dont reportés</th></tr>
        @foreach ($b['all'] as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['department'] ?? '—' }}</td>
                <td class="r">{{ $days($row['remaining']) }}</td>
                <td class="r">{{ $row['carry_left'] > 0 ? $days($row['carry_left']) : '' }}</td>
            </tr>
        @endforeach
    </table>

    <div class="footer">{{ $name }} · Bilan RH {{ $d['year'] }} · {{ $scope }} · Document interne et confidentiel, édité le {{ now()->format('d/m/Y à H:i') }}</div>
</body>
</html>
