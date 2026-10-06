@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', 'Nómina')

@section('contenido')
    <x-encabezado titulo="Personal" subtitulo="Nómina semanal del personal de campo." />
    <x-pestanas-personal />

    <div class="mb-4 flex items-center justify-between gap-2">
        <x-boton variante="secundario" class="!px-3" :href="route('admin.nomina.index', ['semana' => $lunes->subWeek()->toDateString()])" aria-label="Semana anterior">←</x-boton>
        <h2 class="text-center text-lg font-semibold text-slate-900">
            Semana del {{ $lunes->translatedFormat('j M') }} al {{ $lunes->addDays(6)->translatedFormat('j M Y') }}
        </h2>
        <x-boton variante="secundario" class="!px-3" :href="route('admin.nomina.index', ['semana' => $lunes->addWeek()->toDateString()])" aria-label="Semana siguiente">→</x-boton>
    </div>

    <p class="mb-4 rounded-xl bg-slate-100 p-3 text-sm text-slate-700">
        Se pagan los turnos terminados de la semana (sin los días de permiso aprobado) y las horas extra aprobadas, con las tarifas de cada persona.
        Los recargos de ley (nocturno, dominical, festivo) no se calculan solos: inclúyelos en las tarifas según te indique tu contador.
        @unless ($semanaTerminada)
            <strong class="font-semibold">Esta semana aún no termina:</strong> solo cuentan los turnos que ya terminaron.
        @endunless
    </p>

    @if ($personal->isEmpty())
        <x-vacio icono="personal" mensaje="No hay trabajadores ni celadores aprobados." />
    @endif

    <div class="space-y-4">
        @foreach ($personal as $persona)
            @php
                $liquidacion = $liquidaciones->get($persona->id);
                $previa = $previas->get($persona->id);
                $saldoPescado = (int) ($saldosPescado[$persona->id] ?? 0);
                $sinTarifas = $persona->valor_hora === null || $persona->valor_hora_extra === null;
            @endphp

            <x-tarjeta>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ $persona->name }}</h3>
                        <p class="text-sm capitalize text-slate-500">{{ $persona->roles->pluck('nombre')->intersect(\App\Models\Turno::ROLES)->join(' y ') }}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            @if ($sinTarifas)
                                <span class="font-semibold text-amber-700">Sin tarifas configuradas</span>
                            @else
                                Hora {{ Formato::pesos($persona->valor_hora) }} · extra {{ Formato::pesos($persona->valor_hora_extra) }}
                            @endif
                            · <button type="button" data-abrir-modal="tarifas-{{ $persona->id }}" class="min-h-11 font-semibold text-marca-700 hover:underline">{{ $sinTarifas ? 'Configurar' : 'Cambiar' }}</button>
                        </p>
                    </div>

                    @if ($liquidacion)
                        <span @class([
                            'rounded-full px-3 py-1 text-sm font-semibold',
                            'bg-marca-100 text-marca-800' => $liquidacion->estaPagada(),
                            'bg-amber-100 text-amber-900' => ! $liquidacion->estaPagada(),
                        ])>{{ $liquidacion->estaPagada() ? 'Pagada el '.$liquidacion->pagada_at->format('d/m') : 'Por pagar' }}</span>
                    @endif
                </div>

                @php $fila = $liquidacion ?? $previa; @endphp
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-base sm:grid-cols-4">
                    <div><dt class="text-sm text-slate-500">Horas de turno</dt><dd class="font-semibold tabular-nums">{{ Formato::numero($fila['horas_turnos'], 1) }} h</dd></div>
                    <div><dt class="text-sm text-slate-500">Horas extra</dt><dd class="font-semibold tabular-nums">{{ Formato::numero($fila['horas_extra'], 1) }} h</dd></div>
                    <div><dt class="text-sm text-slate-500">Bruto</dt><dd class="font-semibold tabular-nums">{{ $sinTarifas && ! $liquidacion ? '—' : Formato::pesos($fila['bruto']) }}</dd></div>
                    <div>
                        <dt class="text-sm text-slate-500">{{ $liquidacion ? 'Total a pagar' : 'Pescado por descontar' }}</dt>
                        <dd class="text-lg font-bold tabular-nums text-slate-900">{{ Formato::pesos($liquidacion ? $liquidacion->total_pagar : $saldoPescado) }}</dd>
                    </div>
                </dl>

                @if ($liquidacion)
                    @if ($liquidacion->descuento_pescado > 0)
                        <p class="mt-2 text-sm text-slate-600">Incluye un descuento de pescado de {{ Formato::pesos($liquidacion->descuento_pescado) }}.</p>
                    @endif
                    @unless ($liquidacion->estaPagada())
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <x-formulario :action="route('admin.nomina.destroy', $liquidacion)" metodo="DELETE" class="!space-y-0">
                                <x-boton variante="secundario" class="w-full" onclick="return confirm('¿Anular esta nómina? Si tenía descuento de pescado, el saldo vuelve a quedar pendiente.')">Anular</x-boton>
                            </x-formulario>
                            <x-formulario :action="route('admin.nomina.pagar', $liquidacion)" class="!space-y-0">
                                <x-boton class="w-full"><x-icono nombre="ok" class="h-5 w-5" /> Marcar pagada</x-boton>
                            </x-formulario>
                        </div>
                    @endunless
                @else
                    @if ($previa['turnos_en_permiso']->isNotEmpty() || $previa['turnos_sin_terminar'] > 0)
                        <p class="mt-2 text-sm text-slate-600">
                            @if ($previa['turnos_en_permiso']->isNotEmpty()) {{ $previa['turnos_en_permiso']->count() }} turno(s) en días de permiso no se pagan. @endif
                            @if ($previa['turnos_sin_terminar'] > 0) {{ $previa['turnos_sin_terminar'] }} turno(s) aún no terminan. @endif
                        </p>
                    @endif

                    @if (! $sinTarifas && $previa['bruto'] > 0 && $lunes->lte(today()))
                        <x-formulario :action="route('admin.nomina.store')" class="mt-4 !space-y-3">
                            <input type="hidden" name="user_id" value="{{ $persona->id }}">
                            <input type="hidden" name="semana" value="{{ $lunes->toDateString() }}">
                            @if ($saldoPescado > 0)
                                <label class="flex min-h-11 items-center gap-3 text-base text-slate-700">
                                    <input type="checkbox" name="descontar_pescado" value="1" class="h-5 w-5 rounded accent-marca-600">
                                    Descontar el pescado que debe (hasta {{ Formato::pesos(min($saldoPescado, $previa['bruto'])) }})
                                </label>
                            @endif
                            <x-boton class="w-full sm:w-auto">Liquidar semana</x-boton>
                        </x-formulario>
                    @endif
                @endif
            </x-tarjeta>

            <x-modal id="tarifas-{{ $persona->id }}" titulo="Tarifas de {{ $persona->name }}">
                <x-formulario :action="route('admin.nomina.tarifas', $persona)" metodo="PUT">
                    <div class="grid grid-cols-2 gap-4">
                        <x-campo nombre="valor_hora" id="hora-{{ $persona->id }}" etiqueta="Hora ordinaria ($)" tipo="number" min="1" inputmode="numeric" :valor="$persona->valor_hora" required />
                        <x-campo nombre="valor_hora_extra" id="extra-{{ $persona->id }}" etiqueta="Hora extra ($)" tipo="number" min="1" inputmode="numeric" :valor="$persona->valor_hora_extra" required />
                    </div>
                    <p class="text-sm text-slate-500">Valores en pesos, sin decimales. Las liquidaciones ya hechas conservan la tarifa con que se calcularon.</p>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                        <x-boton>Guardar</x-boton>
                    </div>
                </x-formulario>
            </x-modal>
        @endforeach
    </div>
@endsection
