{{-- Qué provee: una o varias casillas (alevinos, alimento, otros). $marcados: los códigos marcados. --}}
<fieldset>
    <legend class="mb-1.5 block text-base font-semibold text-tinta">{{ __('proveedores.campos.provee') }}<span class="ml-0.5 font-bold text-alerta" aria-hidden="true">*</span></legend>
    <div class="flex flex-wrap gap-x-6">
        @foreach ($opcionesProvee as $codigo => $texto)
            <label class="flex min-h-11 items-center gap-3 text-base text-tinta">
                <input type="checkbox" name="provee[]" value="{{ $codigo }}" class="h-5 w-5 accent-marca-700" @checked(in_array($codigo, (array) old('provee', $marcados), true))> {{ $texto }}
            </label>
        @endforeach
    </div>
    @error('provee')
        <p class="mt-1 flex items-start gap-1.5 text-sm font-semibold text-alerta"><x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}</p>
    @enderror
</fieldset>
