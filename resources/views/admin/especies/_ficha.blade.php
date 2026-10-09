{{-- Ficha de cultivo y de procesamiento de una especie (texto libre, todo opcional). Recibe $especie. --}}
@php
    use App\Models\Especie;
    $area = 'block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-suave transition duration-200 hover:border-slate-400 focus:border-marca-500 focus:outline-none focus:ring-4 focus:ring-marca-100';
@endphp

<p class="text-sm text-texto-suave">{{ __('especies.ficha.ayuda') }}</p>

@foreach (['cultivo' => [Especie::CAMPOS_CULTIVO, __('especies.ficha.campos_cultivo')], 'procesamiento' => [Especie::CAMPOS_PROCESAMIENTO, __('especies.ficha.campos_procesamiento')]] as $bloque => [$campos, $etiquetas])
    <fieldset class="space-y-4 border-t border-slate-200 pt-4">
        <legend class="text-base font-semibold text-slate-900">{{ __('especies.ficha.'.$bloque) }}</legend>
        @foreach ($campos as $campo)
            <label class="block">
                <span class="mb-1.5 block text-base font-medium text-slate-700">{{ $etiquetas[$campo] }}</span>
                <textarea name="{{ $bloque }}[{{ $campo }}]" rows="2" maxlength="2000" class="{{ $area }}">{{ old($bloque.'.'.$campo, $especie->{$bloque}[$campo] ?? '') }}</textarea>
                @error($bloque.'.'.$campo)
                    <span class="mt-1.5 block text-sm font-medium text-red-600">{{ $message }}</span>
                @enderror
            </label>
        @endforeach
    </fieldset>
@endforeach

<fieldset class="space-y-4 border-t border-slate-200 pt-4">
    <legend class="text-base font-semibold text-slate-900">{{ __('especies.ficha.descripcion') }}</legend>
    <x-campo nombre="color" :etiqueta="__('especies.ficha.color')" :valor="$especie->color" maxlength="200" />
    <label class="block">
        <span class="mb-1.5 block text-base font-medium text-slate-700">{{ __('especies.ficha.aspecto') }}</span>
        <textarea name="aspecto" rows="2" maxlength="500" class="{{ $area }}">{{ old('aspecto', $especie->aspecto) }}</textarea>
    </label>
</fieldset>

<label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-base text-slate-800">
    <input type="hidden" name="ficha_orientativa" value="0">
    <input type="checkbox" name="ficha_orientativa" value="1" class="mt-1 h-5 w-5 rounded border-slate-400" @checked(old('ficha_orientativa', $especie->ficha_orientativa))>
    <span>{{ __('especies.ficha.marcar_orientativa') }}</span>
</label>
