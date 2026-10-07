{{-- Casillas de roles asignables de una persona. $persona, $rolesAsignables, $marcados y $boton --}}
<x-formulario :action="route('admin.personal.update', $persona->id)" metodo="PUT" class="space-y-3">
    <input type="hidden" name="usuario_id" value="{{ $persona->id }}">

    <fieldset class="flex flex-wrap gap-2">
        <legend class="sr-only">{{ __('personal.roles_de', ['nombre' => $persona->name]) }}</legend>
        @foreach ($rolesAsignables as $rol)
            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-base has-checked:border-marca-500 has-checked:bg-marca-50">
                <input type="checkbox" name="roles[]" value="{{ $rol }}" @checked(in_array($rol, $marcados, true))
                       class="h-5 w-5 rounded accent-marca-600">
                {{ __('bitacora.roles.'.$rol) }}
            </label>
        @endforeach
    </fieldset>

    <x-boton class="w-full sm:w-auto">{{ $boton }}</x-boton>
</x-formulario>
