@props(['columnas' => [], 'vacio' => null])

{{--
    Tabla de la libreta: cabecera en papel hondo y renglones finos. En celular cada fila se apila como una ficha corta.
    Cada <td> debe llevar data-label="{{ __('componentes.nombre_columna') }}" para verse en celular.
--}}
<div {{ $attributes->class('overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon') }}>
    <table class="w-full text-left text-base
        movil:[&_thead]:hidden movil:[&_tr]:block movil:[&_tr]:border-b movil:[&_tr]:border-renglon movil:[&_tr]:p-4 movil:[&_tbody_tr:last-child]:border-b-0
        movil:[&_td]:block movil:[&_td]:py-1
        movil:[&_td[data-label]]:before:block movil:[&_td[data-label]]:before:text-sm
        movil:[&_td[data-label]]:before:text-tinta-suave movil:[&_td[data-label]]:before:content-[attr(data-label)]
        ancho:[&_td]:px-5 ancho:[&_td]:py-3.5 ancho:[&_td]:align-top ancho:[&_tbody_tr]:border-t ancho:[&_tbody_tr]:border-renglon
        [&_tbody_tr]:transition-colors ancho:[&_tbody_tr:hover]:bg-papel">
        <thead class="border-b border-renglon bg-papel-hondo text-sm font-bold text-tinta">
            <tr>
                @foreach ($columnas as $columna)
                    <th scope="col" class="px-5 py-3">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if (trim($slot) === '')
                <tr>
                    <td colspan="{{ max(count($columnas), 1) }}" class="px-5 py-10 text-center text-tinta-suave">{{ $vacio ?? __('componentes.no_hay_registros') }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
