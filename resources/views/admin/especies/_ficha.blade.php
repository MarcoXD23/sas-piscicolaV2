{{-- Ficha de cultivo y de procesamiento de una especie (texto libre, todo opcional). Recibe $especie. --}}
@php
    use App\Models\Especie;
    $area = 'block w-full rounded-ficha border border-renglon-fuerte bg-ficha px-4 py-3 text-base text-tinta transition-[border-color,box-shadow] duration-150 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70';
@endphp

<p class="text-sm text-tinta-suave">{{ __('especies.ficha.ayuda') }}</p>

@foreach (['cultivo' => [Especie::CAMPOS_CULTIVO, __('especies.ficha.campos_cultivo')], 'procesamiento' => [Especie::CAMPOS_PROCESAMIENTO, __('especies.ficha.campos_procesamiento')]] as $bloque => [$campos, $etiquetas])
    <fieldset class="space-y-4 border-t border-renglon pt-5">
        <legend class="text-seccion font-bold">{{ __('especies.ficha.'.$bloque) }}</legend>
        @foreach ($campos as $campo)
            <label class="block">
                <span class="mb-1.5 block text-base font-semibold text-tinta">{{ $etiquetas[$campo] }}</span>
                <textarea name="{{ $bloque }}[{{ $campo }}]" rows="2" maxlength="2000" class="{{ $area }}">{{ old($bloque.'.'.$campo, $especie->{$bloque}[$campo] ?? '') }}</textarea>
                @error($bloque.'.'.$campo)
                    <span class="mt-1.5 flex items-start gap-1.5 text-sm font-semibold text-alerta"><x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}</span>
                @enderror
            </label>
        @endforeach
    </fieldset>
@endforeach

<fieldset class="space-y-4 border-t border-renglon pt-5">
    <legend class="text-seccion font-bold">{{ __('especies.ficha.descripcion') }}</legend>
    <x-campo nombre="color" :etiqueta="__('especies.ficha.color')" :valor="$especie->color" maxlength="200" />
    <label class="block">
        <span class="mb-1.5 block text-base font-semibold text-tinta">{{ __('especies.ficha.aspecto') }}</span>
        <textarea name="aspecto" rows="2" maxlength="500" class="{{ $area }}">{{ old('aspecto', $especie->aspecto) }}</textarea>
    </label>
</fieldset>

<label class="flex min-h-12 items-start gap-3 rounded-ficha border border-renglon bg-papel-hondo p-4 text-base font-semibold">
    <input type="hidden" name="ficha_orientativa" value="0">
    <input type="checkbox" name="ficha_orientativa" value="1" class="mt-0.5 h-5 w-5 shrink-0 rounded" @checked(old('ficha_orientativa', $especie->ficha_orientativa))>
    <span>{{ __('especies.ficha.marcar_orientativa') }}</span>
</label>
