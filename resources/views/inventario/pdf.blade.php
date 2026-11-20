<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 92px 34px 48px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #21152e; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        header { position: fixed; top: -68px; left: 0; right: 0; height: 58px; border-bottom: 2px solid #9700cf; }
        .brand { float: left; }
        .tooth { display: inline-block; width: 28px; margin-right: 8px; vertical-align: top; }
        .tooth svg { width: 22px; height: 25px; }
        .brand-name { display: inline-block; padding-top: 2px; font-size: 15px; font-weight: bold; }
        .brand-subtitle { display: block; margin-top: 2px; color: #7a6984; font-size: 7px; font-weight: normal; letter-spacing: 1px; text-transform: uppercase; }
        .meta { float: right; padding-top: 3px; color: #6b5778; text-align: right; line-height: 1.5; }
        footer { position: fixed; bottom: -30px; left: 0; right: 0; border-top: 1px solid #ead2fb; padding-top: 7px; color: #8a7399; font-size: 8px; }
        .page { float: right; }
        .page:after { content: "Pagina " counter(page); }
        h1 { margin: 0; font-size: 21px; }
        .description { margin-top: 5px; color: #6b5778; font-size: 10px; }
        .summary { margin: 12px 0 18px; }
        .badge { display: inline-block; margin-right: 7px; border: 1px solid #ead2fb; border-radius: 12px; background: #fbf0ff; padding: 5px 10px; color: #512569; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { border: 1px solid #512569; background: #512569; padding: 7px 5px; color: #fff; font-size: 8px; text-align: left; text-transform: uppercase; }
        td { border: 1px solid #e7ddec; padding: 6px 5px; vertical-align: top; overflow-wrap: break-word; }
        tbody tr:nth-child(even) td { background: #fbf8fc; }
        .number { text-align: right; white-space: nowrap; }
        .empty { padding: 28px; color: #8a7399; text-align: center; }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <span class="tooth"><svg viewBox="0 0 24 26" xmlns="http://www.w3.org/2000/svg"><path d="M7.1 2.5c1.85-.78 3.15.45 4.9.45s3.05-1.23 4.9-.45c3.05 1.28 3.72 5.18 2.45 8.28-.97 2.34-1.55 4.07-1.76 7.65-.12 2.05-1.05 4.07-2.55 4.07-2.1 0-1.42-5.83-3.04-5.83s-.94 5.83-3.04 5.83c-1.5 0-2.43-2.02-2.55-4.07-.21-3.58-.79-5.31-1.76-7.65C3.38 7.68 4.05 3.78 7.1 2.5Z" fill="none" stroke="#9700cf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span class="brand-name">DentiFlow<span class="brand-subtitle">Gestion clinica dental</span></span>
        </div>
        <div class="meta">Generado: {{ $generatedAt->format('d/m/Y H:i') }}<br>Por: {{ $generatedBy }}</div>
    </header>

    <footer>Documento generado por DentiFlow<span class="page"></span></footer>

    <main>
        <h1>{{ $title }}</h1>
        <div class="description">{{ $description }}</div>
        <div class="summary">
            <span class="badge">{{ $summary['records'] }} {{ $summary['records'] === 1 ? 'registro' : 'registros' }}</span>
            <span class="badge">{{ number_format($summary['units']) }} unidades</span>
            @if($summary['value'] !== null)<span class="badge">Valor estimado: ${{ number_format($summary['value'], 2, '.', ',') }}</span>@endif
        </div>

        <table>
            <thead><tr>@foreach($headers as $header)<th>{{ $labels[$header] ?? ucfirst($header) }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach($headers as $header)
                            <td class="{{ in_array($header, $moneyColumns, true) || in_array($header, ['stock', 'reposicion', 'faltante', 'materiales', 'unidades'], true) ? 'number' : '' }}">
                                @if(in_array($header, $moneyColumns, true))
                                    ${{ number_format((float) ($row[$header] ?? 0), 2, '.', ',') }}
                                @else
                                    {{ ($row[$header] ?? '') !== '' ? $row[$header] : 'No disponible' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}" class="empty">No hay informacion disponible para este reporte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
