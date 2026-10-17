@extends('layouts.app')

@section('titulo', __('jornadas.titulo_crear'))

@php
    use App\Support\Formato;
    use App\Support\Region;

    $elegidos = collect(old('lagos', []))->map(fn ($id) => (int) $id)->all();
@endphp

@section('contenido')
    <x-encabezado :titulo="__('jornadas.titulo_crear')" :subtitulo="__('jornadas.subtitulo_crear', ['dias' => $diasAyuno])" />

    @if ($lagos->isEmpty())
        <x-vacio icono="lagos" :mensaje="__('jornadas.sin_lagos_con_peces')" />
    @else
        <x-formulario :action="route('admin.jornadas.store')" class="max-w-2xl">
            <x-tarjeta>
                <div class="space-y-5">
                    <x-campo nombre="fecha" :etiqueta="__('jornadas.fecha')" tipo="date" :valor="old('fecha', Region::hoy()->addDay()->toDateString())"
                             :ayuda="__('jornadas.fecha_ayuda')" required />

                    <fieldset>
                        <legend class="mb-1.5 text-base font-semibold text-tinta">{{ __('jornadas.lagos_a_pescar') }}</legend>
                        <p class="mb-3 text-sm text-tinta-suave">{{ __('jornadas.lagos_a_pescar_ayuda') }}</p>
                        @error('lagos')
                            <p class="mb-3 text-base font-semibold text-alerta" role="alert">{{ $message }}</p>
                        @enderror
                        <ul class="space-y-2">
                            @foreach ($lagos as $lago)
                                <li>
                                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-ficha bg-papel px-4 py-2 ring-1 ring-renglon has-checked:ring-2 has-checked:ring-marca-700">
                                        <input type="checkbox" name="lagos[]" value="{{ $lago->id }}" class="h-5 w-5 shrink-0 accent-marca-700" @checked(in_array($lago->id, $elegidos, true))>
                                        <span class="min-w-0">
                                            <span class="block font-bold">{{ __('lagos.lago_nombre', ['lago' => $lago->nombre]) }}</span>
                                            <span class="block text-sm text-tinta-suave">
                                                {{ $lago->siembrasActivas->map(fn ($s) => $s->nombre_especie.' · '.Formato::numero($s->peces_vivos))->implode(' — ') }}
                                                @isset($enAyuno[$lago->id]) · {{ __('jornadas.ya_en_ayuno', ['fecha' => $enAyuno[$lago->id]->format('d/m')]) }}@endisset
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>

                    <x-campo nombre="observaciones" :etiqueta="__('jornadas.observaciones')" maxlength="500" />
                </div>
            </x-tarjeta>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-boton variante="secundario" :href="route('admin.jornadas.index')">{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('jornadas.programar') }}</x-boton>
            </div>
        </x-formulario>
    @endif
@endsection
