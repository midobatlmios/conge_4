@php
    /** @var array $data  PersonalDataExport::data() */
    $label = fn (string $key) => ucfirst(str_replace('_', ' ', $key));
    $value = fn ($v) => $v === null || $v === '' ? '—' : (is_bool($v) ? ($v ? 'oui' : 'non') : $v);
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mes données — {{ $data['compte']['nom'] }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 860px; margin: 2rem auto; padding: 0 1rem; color: #111; line-height: 1.5; }
        h1 { font-size: 1.6rem; } h2 { font-size: 1.15rem; margin-top: 2rem; border-bottom: 1px solid #ddd; padding-bottom: .25rem; }
        table { border-collapse: collapse; width: 100%; font-size: .9rem; }
        th, td { text-align: left; padding: .3rem .5rem; border-bottom: 1px solid #eee; vertical-align: top; }
        th { background: #f5f5f5; }
        dl { display: grid; grid-template-columns: 14rem 1fr; gap: .2rem 1rem; font-size: .9rem; }
        dt { color: #555; }
        .muted { color: #666; font-size: .85rem; }
    </style>
</head>
<body>
    <h1>Mes données personnelles</h1>
    <p class="muted">
        Export du {{ \Illuminate\Support\Carbon::parse($data['exporte_le'])->format('d/m/Y à H:i') }}, au titre du droit d'accès (loi 09-08, art. 7).
        Les mêmes données figurent dans <code>donnees.json</code> ; vos fichiers sont dans le dossier <code>documents/</code>.
    </p>

    <h2>Compte et dossier</h2>
    <dl>
        @foreach ($data['compte'] as $key => $v)
            <dt>{{ $label($key) }}</dt><dd>{{ $value($v) }}</dd>
        @endforeach
    </dl>

    <h2>Données personnelles</h2>
    @if ($data['donnees_personnelles'])
        <dl>
            @foreach ($data['donnees_personnelles'] as $key => $v)
                <dt>{{ $label($key) }}</dt><dd>{{ $value($v) }}</dd>
            @endforeach
        </dl>
    @else
        <p class="muted">Aucune donnée personnelle enregistrée.</p>
    @endif

    @foreach (['contrats' => 'Contrats', 'demandes_de_conge' => 'Demandes de congé', 'attestations' => 'Attestations', 'documents' => 'Documents du dossier', 'consultations' => 'Consultations et modifications par le service RH'] as $section => $title)
        <h2>{{ $title }}</h2>
        @if (count($data[$section]) === 0)
            <p class="muted">Aucun élément.</p>
        @else
            <table>
                <tr>@foreach (array_keys($data[$section][0]) as $key)<th>{{ $label($key) }}</th>@endforeach</tr>
                @foreach ($data[$section] as $row)
                    <tr>@foreach ($row as $v)<td>{{ $value($v) }}</td>@endforeach</tr>
                @endforeach
            </table>
        @endif
    @endforeach
</body>
</html>
