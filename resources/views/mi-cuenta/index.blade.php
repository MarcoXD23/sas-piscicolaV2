@extends('layouts.app')

@section('titulo', __('cuenta.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('cuenta.titulo')" :subtitulo="__('cuenta.subtitulo')" />

    <div class="grid max-w-3xl gap-6">
        {{-- Mis datos: el nombre se edita; el correo todavía no (falta el envío de correos para verificarlo) --}}
        <x-tarjeta :titulo="__('seguridad.mi_cuenta.datos_titulo')">
            <dl class="mb-5 grid grid-cols-1 gap-3 text-base sm:grid-cols-3">
                <div><dt class="text-sm text-tinta-suave">{{ __('seguridad.mi_cuenta.correo') }}</dt><dd class="break-all font-semibold text-tinta">{{ $usuario->email }}</dd></div>
                <div><dt class="text-sm text-tinta-suave">{{ __('seguridad.mi_cuenta.finca') }}</dt><dd class="font-semibold text-tinta">{{ $usuario->tenant?->nombre }}</dd></div>
                <div><dt class="text-sm text-tinta-suave">{{ trans_choice('seguridad.mi_cuenta.roles', $usuario->roles->count()) }}</dt>
                    <dd class="font-semibold text-tinta">{{ $usuario->roles->map(fn ($rol) => __('bitacora.roles.'.$rol->nombre))->join(', ') }}</dd></div>
            </dl>
            <p class="mb-4 text-sm text-tinta-suave">{{ __('seguridad.mi_cuenta.correo_ayuda') }}</p>
            <x-formulario :action="route('mi-cuenta.nombre')" metodo="PUT">
                <x-campo nombre="name" :etiqueta="__('seguridad.mi_cuenta.nombre')" :valor="$usuario->name" autocomplete="name" maxlength="255" required />
                <x-boton variante="secundario">{{ __('seguridad.mi_cuenta.guardar_nombre') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        <x-tarjeta :titulo="__('cuenta.formato_hora.titulo')" :subtitulo="__('cuenta.formato_hora.ayuda')">
            <x-formulario :action="route('mi-cuenta.formato-hora')" metodo="PUT">
                <x-seleccion nombre="formato_hora" :etiqueta="__('cuenta.formato_hora.etiqueta')" :valor="$usuario->formato_hora" :opciones="$formatosHora" />
                <x-boton variante="secundario">{{ __('cuenta.formato_hora.guardar') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        <x-tarjeta :titulo="__('cuenta.contrasena.titulo')" :subtitulo="__('cuenta.contrasena.ayuda')">
            <x-formulario :action="route('mi-cuenta.contrasena')" metodo="PUT">
                <x-contrasena nombre="actual" :etiqueta="__('cuenta.contrasena.actual')" autocomplete="current-password" required :ayuda="__('cuenta.contrasena.actual_ayuda')" />
                <x-contrasena nombre="password" :etiqueta="__('cuenta.contrasena.nueva')" autocomplete="new-password" minlength="10" required nueva />
                <x-contrasena nombre="password_confirmation" :etiqueta="__('cuenta.contrasena.confirmar')" autocomplete="new-password" minlength="10" required />
                <x-boton>{{ __('cuenta.contrasena.guardar') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        <x-tarjeta :titulo="__('cuenta.dos_pasos.titulo')" :subtitulo="__('cuenta.dos_pasos.que_es')">
            @if ($usuario->tieneDosPasos())
                <p class="mb-2 text-base font-semibold text-bien">{{ __('cuenta.dos_pasos.activa', ['fecha' => $usuario->dos_pasos_activado_at->format('d/m/Y')]) }}</p>
                <p class="mb-5 text-sm text-tinta-suave">{{ trans_choice('cuenta.dos_pasos.recuperacion_quedan', count($usuario->dos_pasos_recuperacion ?? [])) }}</p>

                @if ($dosPasosObligatoria)
                    {{-- La finca la exige a su rol (Ajustes › Seguridad): no se puede quitar --}}
                    <p class="border-t border-renglon pt-5 text-sm text-tinta-suave">{{ __('seguridad.dos_pasos_obligatoria.no_se_quita') }}</p>
                @else
                    <x-formulario :action="route('mi-cuenta.dos-pasos.desactivar')" metodo="DELETE" class="border-t border-renglon pt-5">
                        <p class="text-sm text-tinta-suave">{{ __('cuenta.dos_pasos.desactivar_ayuda') }}</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-contrasena nombre="actual" id="desactivar-actual" :etiqueta="__('cuenta.contrasena.actual')" autocomplete="current-password" required />
                            <x-campo nombre="codigo" id="desactivar-codigo" :etiqueta="__('cuenta.dos_pasos.ingreso_codigo')" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required />
                        </div>
                        <x-boton variante="peligro">{{ __('cuenta.dos_pasos.desactivar') }}</x-boton>
                    </x-formulario>
                @endif
            @else
                <p class="mb-4 text-base text-tinta-suave">{{ __('cuenta.dos_pasos.inactiva') }}</p>
                <x-boton :href="route('mi-cuenta.dos-pasos.activar')">{{ __('cuenta.dos_pasos.activar') }}</x-boton>
            @endif
        </x-tarjeta>

        {{-- Mis sesiones abiertas (tabla sessions) --}}
        <x-tarjeta id="sesiones" :titulo="__('seguridad.sesiones.titulo')" :subtitulo="__('seguridad.sesiones.subtitulo')">
            <ul class="divide-y divide-renglon">
                @foreach ($sesiones as $sesion)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-semibold text-tinta">
                                {{ $sesion['equipo'] }}
                                @if ($sesion['actual'])
                                    <x-insignia tono="bien" class="ml-1">{{ __('seguridad.sesiones.esta') }}</x-insignia>
                                @endif
                            </p>
                            <p class="text-sm text-tinta-suave">
                                {{ __('seguridad.sesiones.actividad', ['momento' => $sesion['actividad']->formatoHora('d/m/Y H:i')]) }}
                                @if ($sesion['red']) · {{ __('seguridad.sesiones.red', ['red' => $sesion['red']]) }} @endif
                            </p>
                        </div>
                        @unless ($sesion['actual'])
                            <x-formulario :action="route('mi-cuenta.sesiones.cerrar', $sesion['id'])" metodo="DELETE" class="!space-y-0"
                                          :confirmar="__('seguridad.sesiones.cerrar_confirmar', ['equipo' => $sesion['equipo']])">
                                <x-boton variante="secundario">{{ __('seguridad.sesiones.cerrar_esta') }}</x-boton>
                            </x-formulario>
                        @endunless
                    </li>
                @endforeach
            </ul>
            @if ($sesiones->where('actual', false)->isEmpty())
                <p class="mt-2 text-sm text-tinta-suave">{{ __('seguridad.sesiones.ninguna_mas') }}</p>
            @endif

            <x-formulario :action="route('mi-cuenta.sesiones.cerrar-demas')" class="mt-5 border-t border-renglon pt-5">
                <p class="text-sm text-tinta-suave">{{ __('seguridad.sesiones.cerrar_demas_ayuda') }}</p>
                <x-contrasena nombre="actual" id="cerrar-demas-actual" :etiqueta="__('cuenta.contrasena.actual')" autocomplete="current-password" required />
                <x-boton variante="secundario">{{ __('seguridad.sesiones.cerrar_demas') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        {{-- Mis últimos ingresos (exitosos y fallidos), sin la IP completa --}}
        <x-tarjeta :titulo="__('seguridad.ingresos.titulo')" :subtitulo="__('seguridad.ingresos.subtitulo')">
            <ul class="divide-y divide-renglon">
                @forelse ($ingresos as $ingreso)
                    <li class="flex flex-wrap items-start justify-between gap-2 py-3">
                        <div>
                            <p class="font-semibold text-tinta">{{ $ingreso->equipo }}
                                @if ($ingreso->equipo_nuevo) <x-insignia tono="atencion" class="ml-1">{{ __('seguridad.ingresos.equipo_nuevo') }}</x-insignia> @endif
                            </p>
                            <p class="text-sm text-tinta-suave">
                                {{ \App\Support\Region::local($ingreso->created_at)->formatoHora('d/m/Y H:i') }} · {{ __('seguridad.ingresos.canales.'.$ingreso->canal) }}
                                @if ($ingreso->red) · {{ __('seguridad.ingresos.red', ['red' => $ingreso->red]) }} @endif
                            </p>
                        </div>
                        @if ($ingreso->exitoso)
                            <x-insignia tono="bien">{{ __('seguridad.ingresos.exitoso') }}</x-insignia>
                        @else
                            <x-insignia tono="alerta">{{ __('seguridad.ingresos.fallido') }}: {{ __('seguridad.ingresos.motivos.'.$ingreso->motivo) }}</x-insignia>
                        @endif
                    </li>
                @empty
                    <li class="py-3 text-base text-tinta-suave">{{ __('seguridad.ingresos.vacio') }}</li>
                @endforelse
            </ul>
        </x-tarjeta>
    </div>
@endsection
