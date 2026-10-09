@extends('layouts.app')

@section('titulo', __('comun.personal'))

@section('contenido')
    <x-encabezado :titulo="__('comun.personal')" :subtitulo="__('personal.aprueba_solicitudes_asigna_uno_varios')" />

    <x-pestanas-personal />

    {{-- Finca pequeña: el dueño también puede ser el administrador --}}
    <x-tarjeta :titulo="__('personal.tu_cuenta')" class="mb-6">
        <x-formulario :action="route('admin.personal.mi-rol-administrador')" metodo="PUT" class="!space-y-3">
            <input type="hidden" name="administrador" value="0">
            <label class="flex min-h-12 cursor-pointer items-start gap-3 text-base text-tinta">
                <input type="checkbox" name="administrador" value="1" @checked($soyAdministrador) class="mt-1 h-5 w-5 rounded accent-marca-600">
                <span>
                    <span class="block font-semibold">{{ __('personal.tambien_administrador') }}</span>
                    <span class="block text-sm text-tinta-suave">{{ __('personal.tambien_administrador_ayuda') }}</span>
                </span>
            </label>
            <x-boton variante="secundario">{{ __('personal.guardar_mi_rol') }}</x-boton>
        </x-formulario>
        @include('admin.personal._historial', ['registros' => $historial->get(auth()->id(), collect())])
    </x-tarjeta>

    <x-tabla :columnas="[__('personal.persona'), __('personal.estado'), __('personal.roles')]" :vacio="__('personal.aun_no_hay_personal_registrado')">
        @foreach ($personal as $persona)
            @php
                $rolesActuales = $persona->roles->pluck('nombre')->all();
                $conErrores = (int) old('usuario_id') === $persona->id;
                $idVentana = 'roles-'.$persona->id;
            @endphp
            <tr>
                <td data-label="{{ __('personal.persona') }}">
                    <p class="font-semibold text-tinta">{{ $persona->name }}</p>
                    <p class="break-all text-sm text-tinta-suave">{{ $persona->email }}</p>
                    @include('admin.personal._historial', ['registros' => $historial->get($persona->id, collect())])
                </td>
                <td data-label="{{ __('personal.estado') }}">
                    @if ($persona->estaAprobado())
                        <span class="inline-block rounded-full bg-bien-fondo px-3 py-1 text-sm font-semibold text-bien">{{ __('personal.aprobado') }}</span>
                    @else
                        <span class="inline-block rounded-full bg-atencion-fondo px-3 py-1 text-sm font-semibold text-atencion">{{ __('personal.pendiente') }}</span>
                    @endif
                    @if ($persona->tieneDosPasos())
                        {{-- Si perdió su teléfono y sus códigos de recuperación, el propietario le quita los dos pasos --}}
                        <span class="mt-2 inline-block rounded-full bg-info-fondo px-3 py-1 text-sm font-semibold text-info">{{ __('cuenta.dos_pasos.activa_corto') }}</span>
                        <form method="POST" action="{{ route('admin.personal.quitar-dos-pasos', $persona->id) }}" class="mt-2"
                              onsubmit="return confirm(@js(__('cuenta.dos_pasos.confirmar_quitar', ['nombre' => $persona->name])))">
                            @csrf
                            <button class="inline-flex min-h-12 items-center text-sm font-semibold text-alerta underline">{{ __('cuenta.dos_pasos.quitar') }}</button>
                        </form>
                    @endif
                </td>
                <td data-label="{{ __('personal.roles') }}">
                    @if ($persona->estaAprobado())
                        {{-- Aprobada: los roles quedan en solo lectura; se cambian en una ventana aparte --}}
                        <ul class="mb-3 flex flex-wrap gap-2" aria-label="{{ __('personal.roles_de', ['nombre' => $persona->name]) }}" data-roles-actuales>
                            @forelse (array_values(array_filter(\App\Models\Rol::TODOS, fn ($rol) => in_array($rol, $rolesActuales, true))) as $rol)
                                <li class="rounded-full bg-papel-hondo px-3 py-1 text-sm font-semibold text-tinta">{{ __('bitacora.roles.'.$rol) }}</li>
                            @empty
                                <li class="text-sm text-tinta-suave">{{ __('personal.sin_roles') }}</li>
                            @endforelse
                        </ul>
                        <x-boton tipo="button" variante="secundario" class="!min-h-12 !py-2" data-abrir-modal="{{ $idVentana }}">{{ __('personal.cambiar_roles') }}</x-boton>

                        <x-modal :id="$idVentana" :titulo="__('personal.roles_de', ['nombre' => $persona->name])">
                            @include('admin.personal._formulario_roles', ['marcados' => $conErrores ? old('roles', []) : $rolesActuales, 'boton' => __('personal.guardar_roles')])
                        </x-modal>
                    @else
                        @include('admin.personal._formulario_roles', ['marcados' => $conErrores ? old('roles', []) : $rolesActuales, 'boton' => __('personal.aprobar_asignar')])
                        @if ($conErrores && $errors->any())
                            <p class="mt-2 text-sm font-medium text-alerta">{{ $errors->first() }}</p>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
    </x-tabla>
@endsection
