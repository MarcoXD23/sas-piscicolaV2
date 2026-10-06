@extends('layouts.app')

@section('titulo', __('comun.personal'))

@section('contenido')
    <x-encabezado :titulo="__('comun.personal')" :subtitulo="__('personal.aprueba_solicitudes_asigna_uno_varios')" />

    <x-pestanas-personal />

    <x-tabla :columnas="[__('personal.persona'), __('personal.estado'), __('personal.roles')]" :vacio="__('personal.aun_no_hay_personal_registrado')">
        @foreach ($personal as $persona)
            @php
                $rolesActuales = $persona->roles->pluck('nombre')->all();
                $conErrores = (int) old('usuario_id') === $persona->id;
            @endphp
            <tr>
                <td data-label="{{ __('personal.persona') }}">
                    <p class="font-semibold text-slate-900">{{ $persona->name }}</p>
                    <p class="break-all text-sm text-slate-500">{{ $persona->email }}</p>
                </td>
                <td data-label="{{ __('personal.estado') }}">
                    @if ($persona->estaAprobado())
                        <span class="inline-block rounded-full bg-marca-100 px-3 py-1 text-sm font-semibold text-marca-800">{{ __('personal.aprobado') }}</span>
                    @else
                        <span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800">{{ __('personal.pendiente') }}</span>
                    @endif
                </td>
                <td data-label="{{ __('personal.roles') }}">
                    <x-formulario :action="route('admin.personal.update', $persona->id)" metodo="PUT" class="space-y-3">
                        <input type="hidden" name="usuario_id" value="{{ $persona->id }}">

                        <fieldset class="flex flex-wrap gap-2">
                            <legend class="sr-only">{{ __('personal.roles_de', ['nombre' => $persona->name]) }}</legend>
                            @foreach ($rolesAsignables as $rol)
                                @php
                                    $marcado = $conErrores ? in_array($rol, old('roles', []), true) : in_array($rol, $rolesActuales, true);
                                @endphp
                                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-base capitalize has-checked:border-marca-500 has-checked:bg-marca-50">
                                    <input type="checkbox" name="roles[]" value="{{ $rol }}" @checked($marcado)
                                           class="h-5 w-5 rounded accent-marca-600">
                                    {{ $rol }}
                                </label>
                            @endforeach
                        </fieldset>

                        @if ($conErrores && $errors->any())
                            <p class="text-sm font-medium text-red-600">{{ $errors->first() }}</p>
                        @endif

                        <x-boton class="w-full sm:w-auto">
                            {{ $persona->estaAprobado() ? __('personal.guardar_roles') : __('personal.aprobar_asignar') }}
                        </x-boton>
                    </x-formulario>
                </td>
            </tr>
        @endforeach
    </x-tabla>
@endsection

