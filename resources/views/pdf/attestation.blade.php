@php
    /** @var array $a  frozen snapshot (AttestationIssuer::snapshot) */
    $c = $a['company'];
    // Attestations issued before branding existed keep the original red.
    $color = $c['brand_color'] ?? \App\Models\CompanySetting::DEFAULT_COLOR;
    $e = $a['employee'];
    $date = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') : '';
    $money = fn (float $amount) => number_format($amount, 2, ',', ' ') . ' DH';
    $who = trim(($e['civility'] ?? '') . ' ' . $e['name']);
    // "employé(e)" agrees with the civility when it is known.
    $suffix = match ($e['civility'] ?? null) { 'Madame' => 'e', 'Monsieur' => '', default => '(e)' };

    $companyLine = collect([
        $c['legal_form'] ? "{$c['name']}, {$c['legal_form']}" : $c['name'],
        "sise {$c['address']}, {$c['city']}",
        $c['rc'] ? "immatriculée au registre de commerce sous le n° {$c['rc']}" : null,
        $c['ice'] ? "ICE {$c['ice']}" : null,
        $c['cnss_number'] ? "affiliée à la CNSS sous le n° {$c['cnss_number']}" : null,
    ])->filter()->implode(', ');

    $employment = $e['left']
        ? "a été employé{$suffix} au sein de notre société du {$date($e['start'])} au {$date($e['left'])}"
            . ($e['job_title'] ? ", en qualité de {$e['job_title']}" : '') . '.'
        : "est employé{$suffix} au sein de notre société depuis le {$date($e['start'])}"
            . ($e['job_title'] ? " en qualité de {$e['job_title']}" : '')
            . ($e['contract'] ? ", dans le cadre d'un {$e['contract']}" : '') . '.';

    $salary = null;
    if ($a['salary']) {
        $s = $a['salary'];
        $salary = ($e['left'] ? 'Son dernier salaire mensuel brut était de ' : "L'intéressé{$suffix} perçoit un salaire mensuel brut de ")
            . "<strong>{$money($s['gross'])}</strong> (" . e($s['gross_words']) . ')'
            . ($s['net'] ? ", soit un salaire mensuel net de <strong>{$money($s['net'])}</strong> (" . e($s['net_words']) . ')' : '')
            . '.';
    }

    $closing = "Cette attestation est délivrée à l'intéressé{$suffix}, sur sa demande, pour servir et valoir ce que de droit"
        . ($a['purpose'] ? " ({$a['purpose']})" : '') . '.';

    $contact = collect([$c['phone'] ? "Tél. : {$c['phone']}" : null, $c['email']])->filter()->implode(' · ');
    $footer = collect([
        $c['name'],
        $c['ice'] ? "ICE {$c['ice']}" : null,
        $c['rc'] ? "RC {$c['rc']}" : null,
        $c['if_number'] ? "IF {$c['if_number']}" : null,
        $c['cnss_number'] ? "CNSS {$c['cnss_number']}" : null,
    ])->filter()->implode(' · ');
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $a['title'] }} {{ $a['number'] }}</title>
    <style>
        @page { margin: 2.2cm 2.2cm 2.6cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; line-height: 1.6; color: #111; }
        .head { width: 100%; border-bottom: 2px solid {{ $color }}; padding-bottom: 10px; }
        .head td { vertical-align: middle; }
        .logo { max-height: 70px; max-width: 180px; }
        .company { text-align: right; font-size: 9pt; line-height: 1.4; color: #444; }
        .company strong { font-size: 11pt; color: #111; }
        .ref { margin-top: 18px; font-size: 9pt; color: #555; }
        h1 { text-align: center; font-size: 17pt; letter-spacing: 1px; text-transform: uppercase; margin: 40px 0 30px; }
        p { text-align: justify; margin: 0 0 14px; }
        .facts { margin: 6px 0 18px 20px; }
        .facts td { padding: 2px 12px 2px 0; }
        .facts td.label { color: #555; }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { vertical-align: top; }
        .stamp { height: 90px; }
        .footer { position: fixed; bottom: -1.6cm; left: 0; right: 0; text-align: center; font-size: 8pt; color: #777; border-top: 1px solid #ddd; padding-top: 4px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="">
                @endif
            </td>
            <td class="company">
                <strong>{{ $c['name'] }}</strong><br>
                {{ $c['address'] }}, {{ $c['city'] }}<br>
                {{ $contact }}
            </td>
        </tr>
    </table>

    <div class="ref">Réf. : {{ $a['number'] }}</div>

    <h1>{{ $a['title'] }}</h1>

    <p>Nous soussignés, {{ $companyLine }}, attestons par la présente que&nbsp;:</p>

    <table class="facts">
        <tr><td class="label">Nom et prénom</td><td><strong>{{ $who }}</strong></td></tr>
        <tr><td class="label">CIN n°</td><td>{{ $e['cin'] }}</td></tr>
        @if ($e['cnss_number'])
            <tr><td class="label">N° CNSS</td><td>{{ $e['cnss_number'] }}</td></tr>
        @endif
        @if ($e['matricule'])
            <tr><td class="label">Matricule</td><td>{{ $e['matricule'] }}</td></tr>
        @endif
    </table>

    <p>{{ $employment }}</p>

    @if ($salary)
        {{-- Built from escaped values above; only the <strong> tags are markup. --}}
        <p>{!! $salary !!}</p>
    @endif

    <p>{{ $closing }}</p>

    <table class="sign">
        <tr>
            <td></td>
            <td style="width: 55%">
                Fait à {{ $c['city'] }}, le {{ $date($a['issued_on']) }}<br><br>
                <strong>{{ $c['signatory_name'] }}</strong><br>
                {{ $c['signatory_title'] }}
                <div class="stamp"></div>
                <span style="font-size: 9pt; color: #777;">Cachet et signature</span>
            </td>
        </tr>
    </table>

    <div class="footer">{{ $footer }}</div>
</body>
</html>
