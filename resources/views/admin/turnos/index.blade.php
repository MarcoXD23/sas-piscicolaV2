@extends('layouts.app')

@section('titulo', __('turnos.turnos'))

@section('contenido')
    @php
        $horario = function ($turno) {
            $texto = $turno->inicia_at->format('H:i').' – '.$turno->termina_at->format('H:i');

            return $turno->termina_at->isSameDay($turno->inicia_at) ? $texto : $texto.' '.__('turnos.mas_un_dia');
        };
        $diasSemana = array_combine(range(1, 7), __('comun.dias_cortos'));
        $diasMarcados = array_map('intval', old('dias', [1, 2, 3, 4, 5, 6]));
    @endphp

    <x-encabezado :titulo="__('comun.personal')" :subtitulo="__('turnos.turnos_rotativos_trabajadores_celadores')">
        <x-boton tipo="button" data-abrir-modal="modal-turnos" :disabled="$personal->isEmpty()">
            <x-icono nombre="mas" class="h-5 w-5" /> {{ __('turnos.programar_turnos') }}
        </x-boton>
    </x-encabezado>

    <x-pestanas-personal />

    @if ($personal->isEmpty())
        <x-vacio icono="personal" :mensaje="__('turnos.programar_turnos_primero_aprueba_personas')" class="mb-6" />
    @endif

    <x-tarjeta :titulo="__('turnos.turno_ahora')" class="mb-6">
        @forelse ($enCurso as $turno)
            <div class="flex items-center justify-between gap-3 border-t border-renglon py-3 first:border-t-0 first:pt-0">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-marca-500" aria-hidden="true"></span>
                    <span class="truncate font-semibold text-tinta">{{ $turno->user->name }}</span>
                    <span class="rounded-full bg-papel-hondo px-2.5 py-0.5 text-sm font-semibold capitalize text-tinta">{{ $turno->rol }}</span>
                </div>
                <span class="shrink-0 text-sm text-tinta-suave">{{ __('turnos.hasta', ['hora' => $turno->termina_at->format('H:i')]) }}</span>
            </div>
        @empty
            <p class="text-base text-tinta-suave">{{ __('turnos.nadie_esta_turno_este_momento') }}</p>
        @endforelse
    </x-tarjeta>

    <div class="mb-4 flex items-center justify-between gap-2">
        <x-boton variante="secundario" class="w-12 !px-0" :href="route('admin.turnos.index', ['semana' => $semana->subWeek()->toDateString()])" :aria-label="__('turnos.semana_anterior')"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /></x-boton>
        <h2 class="text-center text-lg font-semibold text-tinta">
            {{ $semana->translatedFormat('j M') }} – {{ $semana->addDays(6)->translatedFormat('j M Y') }}
        </h2>
        <x-boton variante="secundario" class="w-12 !px-0" :href="route('admin.turnos.index', ['semana' => $semana->addWeek()->toDateString()])" :aria-label="__('turnos.semana_siguiente')"><x-icono nombre="abajo" class="h-5 w-5 -rotate-90" /></x-boton>
    </div>

    <div class="space-y-3">
        @foreach ($dias as $dia)
            @php $turnosDelDia = $turnosPorDia->get($dia->toDateString(), collect()); @endphp

            <section @class([
                'rounded-ficha bg-ficha p-4 shadow-ficha ring-1',
                'ring-marca-300' => $dia->isToday(),
                'ring-renglon' => ! $dia->isToday(),
            ])>
                <h3 class="mb-2 flex items-center gap-2 font-semibold capitalize text-tinta">
                    {{ $dia->translatedFormat('l j') }}
                    @if ($dia->isToday())
                        <span class="rounded-full bg-bien-fondo px-2.5 py-0.5 text-sm font-semibold normal-case text-bien">{{ __('turnos.hoy') }}</span>
                    @endif
                </h3>

                @forelse ($turnosDelDia as $turno)
                    <div class="flex items-center justify-between gap-3 border-t border-renglon py-2.5">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-tinta">{{ $turno->user->name }}</p>
                            <p class="flex items-center gap-1.5 text-sm text-tinta-suave">
                                <x-icono nombre="reloj" class="h-4 w-4" /> {{ $horario($turno) }}
                                <span class="capitalize">· {{ $turno->rol }}</span>
                            </p>
                        </div>

                        @unless ($turno->haEmpezado())
                            <x-formulario :action="route('admin.turnos.destroy', $turno)" metodo="DELETE" class="!space-y-0">
                                <button type="submit" class="flex h-12 w-12 items-center justify-center rounded-ficha text-tinta-suave hover:bg-alerta-fondo hover:text-alerta"
                                        aria-label="{{ __('turnos.eliminar_turno_de', ['nombre' => $turno->user->name]) }}" onclick="return confirm(@js(__('turnos.confirmar_eliminar')))">
                                    <x-icono nombre="basura" class="h-5 w-5" />
                                </button>
                            </x-formulario>
                        @endunless
                    </div>
                @empty
                    <p class="text-sm text-tinta-suave">{{ __('turnos.sin_turnos') }}</p>
                @endforelse
            </section>
        @endforeach
    </div>

    @if ($personal->isNotEmpty())
        <x-modal id="modal-turnos" :titulo="__('turnos.programar_turnos')">
            <x-formulario :action="route('admin.turnos.store')">
                <x-seleccion nombre="user_id" :etiqueta="__('turnos.persona')" required
                             :opciones="$personal->mapWithKeys(fn ($persona) => [
                                 $persona->id => $persona->name.' ('.$persona->roles->pluck('nombre')->intersect(\App\Models\Turno::ROLES)->join(' y ').')',
                             ])->all()" />
                <x-seleccion nombre="rol" :etiqueta="__('turnos.turno')" :opciones="\App\Models\Turno::ROLES" required />

                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="desde" :etiqueta="__('comun.desde')" tipo="date" :valor="\App\Support\Region::ahora()->toDateString()" :min="\App\Support\Region::ahora()->toDateString()" required />
                    <x-campo nombre="hasta" :etiqueta="__('comun.hasta')" tipo="date" :valor="\App\Support\Region::ahora()->addDays(6)->toDateString()" :min="\App\Support\Region::ahora()->toDateString()" required />
                </div>

                <fieldset>
                    <legend class="mb-1.5 text-base font-medium text-tinta">{{ __('turnos.dias') }}</legend>
                    <div class="grid grid-cols-7 gap-1">
                        @foreach ($diasSemana as $numero => $nombre)
                            <label class="flex min-h-12 cursor-pointer flex-col items-center justify-center rounded-ficha border border-renglon-fuerte text-sm font-semibold text-tinta-suave has-checked:border-marca-700 has-checked:bg-bien-fondo has-checked:text-bien">
                                <input type="checkbox" name="dias[]" value="{{ $numero }}" class="sr-only" @checked(in_array($numero, $diasMarcados, true))>
                                {{ $nombre }}
                            </label>
                        @endforeach
                    </div>
                    @error('dias')
                        <p class="mt-1.5 text-sm font-medium text-alerta">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="hora_inicio" :etiqueta="__('turnos.entrada')" tipo="time" valor="06:00" required />
                    <x-campo nombre="hora_fin" :etiqueta="__('turnos.salida')" tipo="time" valor="14:00" required
                             :ayuda="__('turnos.si_es_antes_entrada_termina')" />
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('turnos.programar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
