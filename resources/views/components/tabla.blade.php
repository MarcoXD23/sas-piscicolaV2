@props(['columnas' => [], 'vacio' => 'No hay registros.'])

{{--
    Tabla responsiva: en celular cada fila se muestra como bloque apilado.
    Cada <td> debe llevar data-label="Nombre de la columna" para verse en celular.
--}}
<div {{ $attributes->class('overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200') }}>
    <table class="w-full text-left text-base
        max-md:[&_thead]:hidden max-md:[&_tr]:block max-md:[&_tr]:border-b max-md:[&_tr]:border-slate-200 max-md:[&_tr]:p-4
        max-md:[&_td]:block max-md:[&_td]:py-1.5
        max-md:[&_td[data-label]]:before:mb-0.5 max-md:[&_td[data-label]]:before:block max-md:[&_td[data-label]]:before:text-xs
        max-md:[&_td[data-label]]:before:font-semibold max-md:[&_td[data-label]]:before:uppercase max-md:[&_td[data-label]]:before:tracking-wide
        max-md:[&_td[data-label]]:before:text-slate-500 max-md:[&_td[data-label]]:before:content-[attr(data-label)]
        md:[&_td]:px-5 md:[&_td]:py-4 md:[&_td]:align-top md:[&_tbody_tr]:border-t md:[&_tbody_tr]:border-slate-100">
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

