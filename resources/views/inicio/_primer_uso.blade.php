{{-- Primeros pasos de una finca nueva. Recibe $pasos (App\Support\PrimerUso::paraInicio) y $usuario. --}}
@php
    $hechos = collect($pasos)->where('hecho', true)->count();
    $textos = __('primer_uso.pasos');
@endphp

<x-tarjeta class="mb-8" :titulo="__('primer_uso.titulo')" :subtitulo="__('primer_uso.subtitulo', ['hechos' => $hechos, 'total' => count($pasos)])">
    {{-- El código para invitar al personal, siempre a mano mientras se ve la lista (monoespaciado: es un código para
         dictar), con «Copiar» (data-copiar-grupo, resources/js/app.js) --}}
    <div class="mb-3" data-copiar-grupo>
        <div class="flex flex-wrap items-center gap-3">
            <label for="primer-uso-codigo" class="font-semibold text-tinta">{{ __('primer_uso.codigo_finca_etiqueta') }}</label>
            <input id="primer-uso-codigo" type="text" readonly value="{{ $usuario->tenant->codigo }}" data-copiar-texto size="{{ max(6, mb_strlen($usuario->tenant->codigo)) }}"
                   class="min-h-11 rounded-ficha border border-renglon bg-ficha px-3 font-mono text-base font-bold tracking-wider text-marca-800">
            <x-boton tipo="button" variante="secundario" data-copiar :data-copiado="__('primer_uso.copiado')">{{ __('primer_uso.copiar') }}</x-boton>
        </div>
        <p class="mt-1 text-sm text-tinta-suave">{{ __('primer_uso.codigo_ayuda') }}</p>
    </div>
    <div class="mb-5 h-2 overflow-hidden rounded-full bg-papel-hondo" aria-hidden="true">
        <div class="h-full rounded-full bg-marca-700" style="width: {{ round($hechos / count($pasos) * 100) }}%"></div>
    </div>

    {{-- Una secuencia real: el número de cada paso informa el orden --}}
    <ol class="divide-y divide-renglon">
        @foreach ($pasos as $paso)
            <li class="flex items-start gap-3 py-3">
                <span @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                    'bg-marca-700 text-white' => $paso['hecho'],
                    'bg-ficha text-tinta ring-1 ring-renglon-fuerte' => ! $paso['hecho'],
                ])>
                    @if ($paso['hecho'])
                        <x-icono nombre="ok" class="h-5 w-5" /><span class="sr-only">{{ __('primer_uso.hecho') }}</span>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p @class(['font-bold', 'text-tinta-suave line-through decoration-renglon-fuerte' => $paso['hecho']])>{{ $textos[$paso['clave']]['titulo'] }}</p>
                    @unless ($paso['hecho'])
                        <p class="text-sm text-tinta-suave">{{ $textos[$paso['clave']]['ayuda'] }}</p>
                        @if ($paso['ruta'])
                            <a href="{{ $paso['ruta'] }}" class="mt-1 inline-flex min-h-12 items-center font-bold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">{{ __('primer_uso.ir') }}</a>
                        @else
                            <p class="mt-1 text-sm text-tinta-suave">{{ __($paso['clave'] === 'pesos' ? 'primer_uso.lo_hace_administrador' : 'primer_uso.lo_hace_propietario') }}</p>
                        @endif
                    @endunless
                </div>
            </li>
        @endforeach
    </ol>

    <form method="POST" action="{{ route('admin.primer-uso.ocultar') }}" class="mt-2 text-right">
        @csrf
        <button class="inline-flex min-h-12 items-center text-sm text-tinta-suave underline decoration-renglon-fuerte underline-offset-4 hover:text-tinta">{{ __('primer_uso.ocultar') }}</button>
    </form>
</x-tarjeta>
