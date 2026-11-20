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
        .brand-mark { display: inline-block; width: 29px; height: 29px; margin-right: 8px; padding-top: 3px; text-align: center; }
        .brand-mark svg { display: block; width: 21px; height: 23px; margin: 0 auto; }
        .brand-name { display: inline-block; vertical-align: top; padding-top: 2px; font-size: 15px; font-weight: bold; }
        .brand-subtitle { display: block; margin-top: 2px; color: #7a6984; font-size: 7px; font-weight: normal; letter-spacing: 1px; text-transform: uppercase; }
        .header-meta { float: right; padding-top: 3px; color: #6b5778; text-align: right; line-height: 1.5; }
        footer { position: fixed; bottom: -30px; left: 0; right: 0; border-top: 1px solid #ead2fb; padding-top: 7px; color: #8a7399; font-size: 8px; }
        .page-number { float: right; }
        .page-number:after { content: "Pagina " counter(page); }
        h1 { margin: 0; color: #21152e; font-size: 22px; }
        .intro { margin-bottom: 18px; }
        .period { margin-top: 5px; color: #6b5778; font-size: 10px; }
        .summary { margin-top: 12px; }
        .badge { display: inline-block; margin-right: 7px; border: 1px solid #ead2fb; border-radius: 12px; background: #fbf0ff; padding: 5px 10px; color: #512569; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { border: 1px solid #512569; background: #512569; padding: 7px 5px; color: #fff; font-size: 8px; text-align: left; text-transform: uppercase; }
        td { border: 1px solid #e7ddec; padding: 6px 5px; vertical-align: top; overflow-wrap: break-word; }
        tbody tr:nth-child(even) td { background: #fbf8fc; }
        tfoot td { border-color: #9700cf; background: #9700cf; padding: 8px 6px; color: #fff; font-weight: bold; }
        .total-label { text-align: right; text-transform: uppercase; }
        .money { text-align: right; white-space: nowrap; }
        .empty { padding: 28px; color: #8a7399; text-align: center; }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <span class="brand-mark" aria-label="DentiFlow">
                <svg viewBox="0 0 24 26" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M7.1 2.5c1.85-.78 3.15.45 4.9.45s3.05-1.23 4.9-.45c3.05 1.28 3.72 5.18 2.45 8.28-.97 2.34-1.55 4.07-1.76 7.65-.12 2.05-1.05 4.07-2.55 4.07-2.1 0-1.42-5.83-3.04-5.83s-.94 5.83-3.04 5.83c-1.5 0-2.43-2.02-2.55-4.07-.21-3.58-.79-5.31-1.76-7.65C3.38 7.68 4.05 3.78 7.1 2.5Z" fill="none" stroke="#9700cf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="brand-name">DentiFlow<span class="brand-subtitle">Gestion clinica dental</span></span>
        </div>
        <div class="header-meta">
            Generado: {{ $generatedAt->format('d/m/Y H:i') }}<br>
            Por: {{ $generatedBy }}
        </div>
    </header>

    <footer>
        Documento generado por DentiFlow
        <span class="page-number"></span>
    </footer>

    <main>
        <section class="intro">
            <h1>{{ $title }}</h1>
            <div class="period">Periodo: {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}</div>
            <div class="summary">
                <span class="badge">{{ $rows->count() }} {{ $rows->count() === 1 ? 'registro' : 'registros' }}</span>
                @if($reportType === 'ganancias')
                    <span class="badge">Total: ${{ number_format($rows->sum('ganancia'), 2, '.', ',') }}</span>
                @elseif($reportType === 'pacientes')
                    <span class="badge">Total generado: ${{ number_format($rows->sum('ganancias'), 2, '.', ',') }}</span>
                @elseif($reportType === 'tratamientos')
                    <span class="badge">Costo total: ${{ number_format($rows->sum('costo'), 2, '.', ',') }}</span>
                @endif
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $columnLabels[$header] ?? ucfirst($header) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach($headers as $header)
                            <td class="{{ in_array($header, $moneyColumns, true) ? 'money' : '' }}">
                                @if(in_array($header, $moneyColumns, true))
                                    ${{ number_format((float) ($row[$header] ?? 0), 2, '.', ',') }}
                                @else
                                    {{ ($row[$header] ?? '') !== '' ? $row[$header] : 'No disponible' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}" class="empty">No hay informacion para el periodo seleccionado.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="{{ max(count($headers) - 1, 1) }}" class="total-label">Ganancias totales</td>
                    <td class="money">${{ number_format($totalEarnings, 2, '.', ',') }}</td>
                </tr>
            </tfoot>
        </table>
    </main>
</body>
</html>
