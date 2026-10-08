{{-- Primeros pasos de una finca nueva. Recibe $pasos (App\Support\PrimerUso::paraInicio) y $usuario. --}}
@php
    $hechos = collect($pasos)->where('hecho', true)->count();
    $textos = __('primer_uso.pasos');
@endphp

<x-tarjeta class="mb-6 ring-2 ring-marca-200" :titulo="__('primer_uso.titulo')" :subtitulo="__('primer_uso.subtitulo', ['hechos' => $hechos, 'total' => count($pasos)])">
    {{-- El código para invitar al personal, siempre a mano mientras se ve la lista --}}
    <p class="mb-3 select-all font-mono text-base font-semibold text-marca-800">{{ __('primer_uso.codigo_finca', ['codigo' => $usuario->tenant->codigo]) }}</p>
    <div class="mb-4 h-2 overflow-hidden rounded-full bg-slate-200">
        <div class="h-full rounded-full bg-marca-600" style="width: {{ round($hechos / count($pasos) * 100) }}%"></div>
    </div>

    <ol class="space-y-3">
        @foreach ($pasos as $paso)
            <li @class(['flex items-start gap-3 rounded-xl p-3', 'bg-marca-50' => $paso['hecho'], 'bg-slate-50' => ! $paso['hecho']])>
                <span @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                    'bg-marca-600 text-white' => $paso['hecho'],
                    'bg-white text-slate-500 ring-1 ring-slate-300' => ! $paso['hecho'],
                ])>
                    @if ($paso['hecho'])
                        <x-icono nombre="ok" class="h-5 w-5" /><span class="sr-only">{{ __('primer_uso.hecho') }}</span>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p @class(['font-semibold', 'text-slate-500 line-through' => $paso['hecho'], 'text-slate-900' => ! $paso['hecho']])>{{ $textos[$paso['clave']]['titulo'] }}</p>
                    @unless ($paso['hecho'])
                        <p class="text-sm text-slate-600">{{ $textos[$paso['clave']]['ayuda'] }}</p>
                        @if ($paso['ruta'])
                            <a href="{{ $paso['ruta'] }}" class="mt-1 inline-flex min-h-11 items-center font-semibold text-marca-700 underline">{{ __('primer_uso.ir') }}</a>
                        @else
                            <p class="mt-1 text-sm text-slate-500">{{ __($paso['clave'] === 'pesos' ? 'primer_uso.lo_hace_administrador' : 'primer_uso.lo_hace_propietario') }}</p>
                        @endif
                    @endunless
                </div>
            </li>
        @endforeach
    </ol>

    <form method="POST" action="{{ route('admin.primer-uso.ocultar') }}" class="mt-4 text-right">
        @csrf
        <button class="text-sm text-slate-500 underline">{{ __('primer_uso.ocultar') }}</button>
    </form>
</x-tarjeta>
