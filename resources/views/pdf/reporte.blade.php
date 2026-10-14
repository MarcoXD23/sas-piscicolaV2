<!DOCTYPE html>
<html lang="{{ \App\Support\Region::etiquetaIdioma() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    {{-- Plantilla para dompdf: CSS sencillo dentro del archivo (sin Tailwind ni recursos externos) --}}
    <style>
        @page { margin: 18mm 14mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1e293b; }
        .encabezado { border-bottom: 2px solid #0f655f; padding-bottom: 6px; margin-bottom: 14px; }
        .finca { font-size: 9pt; color: #64748b; margin: 0; }
        h1 { font-size: 16pt; margin: 2px 0; color: #0f172a; }
        .periodo { font-size: 10pt; color: #475569; margin: 0; }
        h2 { font-size: 11pt; color: #0f655f; margin: 16px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e6f2f1; text-align: left; font-weight: bold; padding: 4px 5px; border-bottom: 1px solid #94a3b8; }
        td { padding: 3px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .numero { text-align: right; white-space: nowrap; }
        .vacio { color: #64748b; font-style: italic; }
        .notas { margin-top: 14px; font-size: 8.5pt; color: #64748b; }
        .pie { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5pt; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="pie">{{ $generado }}</div>

    <div class="encabezado">
        <p class="finca">{{ $finca }}</p>
        <h1>{{ $titulo }}</h1>
        <p class="periodo">{{ $periodo }}</p>
    </div>

    @foreach ($tablas as $tabla)
        <h2>{{ $tabla->nombre }}</h2>
        @if ($tabla->filas === [])
            <p class="vacio">{{ __('reportes.exportar.sin_registros') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        @foreach ($tabla->columnas as [$encabezado, $decimales])
                            <th @class(['numero' => $decimales !== null])>{{ $encabezado }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tabla->filas as $fila)
                        <tr>
                            @foreach (array_values($fila) as $posicion => $valor)
                                @php $decimales = $tabla->columnas[$posicion][1] ?? null; @endphp
                                @if (is_int($valor) || is_float($valor))
                                    <td class="numero">{{ \App\Support\Formato::numero($valor, $decimales ?? (is_int($valor) ? 0 : 2)) }}</td>
                                @elseif ($valor instanceof \DateTimeInterface)
                                    {{-- Fecha con hora: en el formato de hora de la persona (12 o 24 horas) --}}
                                    <td>{{ \App\Support\Hora::formatear(\Carbon\CarbonImmutable::instance($valor), 'd/m/Y H:i') }}</td>
                                @else
                                    <td>{{ $valor }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @foreach ($notas as $nota)
        <p class="notas">{{ $nota }}</p>
    @endforeach
</body>
</html>
