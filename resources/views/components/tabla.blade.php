@props(['columnas' => [], 'vacio' => null])

{{--
    Tabla responsiva: en celular cada fila se muestra como bloque apilado.
    Cada <td> debe llevar data-label="{{ __('componentes.nombre_columna') }}" para verse en celular.
--}}
<div data-revelar {{ $attributes->class('overflow-hidden rounded-2xl bg-white shadow-suave ring-1 ring-slate-200') }}>
    <table class="w-full text-left text-base
        movil:[&_thead]:hidden movil:[&_tr]:block movil:[&_tr]:border-b movil:[&_tr]:border-slate-100 movil:[&_tr]:p-4 movil:[&_tbody_tr:last-child]:border-b-0
        movil:[&_td]:block movil:[&_td]:py-1.5
        movil:[&_td[data-label]]:before:mb-0.5 movil:[&_td[data-label]]:before:block movil:[&_td[data-label]]:before:text-[0.8125rem]
        movil:[&_td[data-label]]:before:font-semibold movil:[&_td[data-label]]:before:uppercase movil:[&_td[data-label]]:before:tracking-wide
        movil:[&_td[data-label]]:before:text-texto-suave movil:[&_td[data-label]]:before:content-[attr(data-label)]
        ancho:[&_td]:px-5 ancho:[&_td]:py-4 ancho:[&_td]:align-top ancho:[&_tbody_tr]:border-t ancho:[&_tbody_tr]:border-slate-100
        [&_tbody_tr]:transition-colors [&_tbody_tr]:duration-200 ancho:[&_tbody_tr:hover]:bg-marca-50/50">
        <thead class="bg-gradient-to-r from-marca-50 to-sky-50/70 text-sm font-semibold text-marca-900">
            <tr>
                @foreach ($columnas as $columna)
                    <th scope="col" class="px-5 py-3">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if (trim($slot) === '')
                <tr>
                    <td colspan="{{ max(count($columnas), 1) }}" class="px-5 py-10 text-center text-texto-suave">{{ $vacio ?? __('componentes.no_hay_registros') }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
