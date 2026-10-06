@props(['columnas' => [], 'vacio' => 'No hay registros.'])

{{--
    Tabla responsiva: en celular cada fila se muestra como bloque apilado.
    Cada <td> debe llevar data-label="Nombre de la columna" para verse en celular.
--}}
<div {{ $attributes->class('overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200') }}>
    <table class="w-full text-left text-base
        movil:[&_thead]:hidden movil:[&_tr]:block movil:[&_tr]:border-b movil:[&_tr]:border-slate-200 movil:[&_tr]:p-4
        movil:[&_td]:block movil:[&_td]:py-1.5
        movil:[&_td[data-label]]:before:mb-0.5 movil:[&_td[data-label]]:before:block movil:[&_td[data-label]]:before:text-xs
        movil:[&_td[data-label]]:before:font-semibold movil:[&_td[data-label]]:before:uppercase movil:[&_td[data-label]]:before:tracking-wide
        movil:[&_td[data-label]]:before:text-slate-500 movil:[&_td[data-label]]:before:content-[attr(data-label)]
        ancho:[&_td]:px-5 ancho:[&_td]:py-4 ancho:[&_td]:align-top ancho:[&_tbody_tr]:border-t ancho:[&_tbody_tr]:border-slate-100">
        <thead class="bg-marca-50 text-sm font-semibold text-marca-900">
            <tr>
                @foreach ($columnas as $columna)
                    <th scope="col" class="px-5 py-3">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if (trim($slot) === '')
                <tr>
                    <td colspan="{{ max(count($columnas), 1) }}" class="px-5 py-8 text-center text-slate-500">{{ $vacio }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>

