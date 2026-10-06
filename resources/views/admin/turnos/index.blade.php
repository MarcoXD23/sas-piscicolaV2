@extends('layouts.app')

@section('titulo', 'Turnos')

@section('contenido')
    @php
        $horario = function ($turno) {
            $texto = $turno->inicia_at->format('H:i').' – '.$turno->termina_at->format('H:i');

            return $turno->termina_at->isSameDay($turno->inicia_at) ? $texto : $texto.' (+1 día)';
        };
        $diasSemana = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $diasMarcados = array_map('intval', old('dias', [1, 2, 3, 4, 5, 6]));
    @endphp

    <x-encabezado titulo="Personal" subtitulo="Turnos rotativos de trabajadores y celadores.">
        <x-boton tipo="button" data-abrir-modal="modal-turnos" :disabled="$personal->isEmpty()">
            <x-icono nombre="mas" class="h-5 w-5" /> Programar turnos
        </x-boton>
    </x-encabezado>

    <x-pestanas-personal />

    @if ($personal->isEmpty())
        <x-vacio icono="personal" mensaje="Para programar turnos, primero aprueba personas con rol de trabajador o celador en la pestaña Personas." class="mb-6" />
    @endif

    <x-tarjeta titulo="En turno ahora" class="mb-6">
        @forelse ($enCurso as $turno)
            <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-marca-500" aria-hidden="true"></span>
                    <span class="truncate font-semibold text-slate-900">{{ $turno->user->name }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-700">{{ $turno->rol }}</span>
                </div>
                <span class="shrink-0 text-sm text-slate-500">hasta {{ $turno->termina_at->format('H:i') }}</span>
            </div>
        @empty
            <p class="text-base text-slate-500">Nadie está en turno en este momento.</p>
        @endforelse
    </x-tarjeta>

    <div class="mb-4 flex items-center justify-between gap-2">
        <x-boton variante="secundario" class="!px-3" :href="route('admin.turnos.index', ['semana' => $semana->subWeek()->toDateString()])" aria-label="Semana anterior">←</x-boton>
        <h2 class="text-center text-lg font-semibold text-slate-900">
            {{ $semana->translatedFormat('j M') }} – {{ $semana->addDays(6)->translatedFormat('j M Y') }}
        </h2>
        <x-boton variante="secundario" class="!px-3" :href="route('admin.turnos.index', ['semana' => $semana->addWeek()->toDateString()])" aria-label="Semana siguiente">→</x-boton>
    </div>

    <div class="space-y-3">
        @foreach ($dias as $dia)
            @php $turnosDelDia = $turnosPorDia->get($dia->toDateString(), collect()); @endphp

            <section @class([
                'rounded-2xl bg-white p-4 shadow-sm ring-1',
                'ring-marca-300' => $dia->isToday(),
                'ring-slate-200' => ! $dia->isToday(),
            ])>
                <h3 class="mb-2 flex items-center gap-2 font-semibold capitalize text-slate-900">
                    {{ $dia->translatedFormat('l j') }}
                    @if ($dia->isToday())
                        <span class="rounded-full bg-marca-100 px-2.5 py-0.5 text-xs font-semibold normal-case text-marca-800">Hoy</span>
                    @endif
                </h3>

                @forelse ($turnosDelDia as $turno)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ $turno->user->name }}</p>
                            <p class="flex items-center gap-1.5 text-sm text-slate-500">
                                <x-icono nombre="reloj" class="h-4 w-4" /> {{ $horario($turno) }}
                                <span class="capitalize">· {{ $turno->rol }}</span>
                            </p>
                        </div>

                        @unless ($turno->haEmpezado())
                            <x-formulario :action="route('admin.turnos.destroy', $turno)" metodo="DELETE" class="!space-y-0">
                                <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        aria-label="Eliminar turno de {{ $turno->user->name }}" onclick="return confirm('¿Eliminar este turno?')">
                                    <x-icono nombre="basura" class="h-5 w-5" />
                                </button>
                            </x-formulario>
                        @endunless
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Sin turnos.</p>
                @endforelse
            </section>
        @endforeach
    </div>

    @if ($personal->isNotEmpty())
        <x-modal id="modal-turnos" titulo="Programar turnos">
            <x-formulario :action="route('admin.turnos.store')">
                <x-seleccion nombre="user_id" etiqueta="Persona" required
                             :opciones="$personal->mapWithKeys(fn ($persona) => [
                                 $persona->id => $persona->name.' ('.$persona->roles->pluck('nombre')->intersect(\App\Models\Turno::ROLES)->join(' y ').')',
                             ])->all()" />
                <x-seleccion nombre="rol" etiqueta="Turno de" :opciones="\App\Models\Turno::ROLES" required />

                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="desde" etiqueta="Desde" tipo="date" :valor="now()->toDateString()" :min="now()->toDateString()" required />
                    <x-campo nombre="hasta" etiqueta="Hasta" tipo="date" :valor="now()->addDays(6)->toDateString()" :min="now()->toDateString()" required />
                </div>

                <fieldset>
                    <legend class="mb-1.5 text-base font-medium text-slate-700">Días</legend>
                    <div class="grid grid-cols-7 gap-1">
                        @foreach ($diasSemana as $numero => $nombre)
                            <label class="flex min-h-12 cursor-pointer flex-col items-center justify-center rounded-xl border border-slate-300 text-sm font-semibold text-slate-600 has-checked:border-marca-500 has-checked:bg-marca-50 has-checked:text-marca-800">
                                <input type="checkbox" name="dias[]" value="{{ $numero }}" class="sr-only" @checked(in_array($numero, $diasMarcados, true))>
                                {{ $nombre }}
                            </label>
                        @endforeach
                    </div>
                    @error('dias')
                        <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="hora_inicio" etiqueta="Entrada" tipo="time" valor="06:00" required />
                    <x-campo nombre="hora_fin" etiqueta="Salida" tipo="time" valor="14:00" required
                             ayuda="Si es antes de la entrada, termina al día siguiente." />
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                    <x-boton>Programar</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
