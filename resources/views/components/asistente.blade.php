{{-- Único widget del asistente IA (se incluye una vez en el layout). La lógica está en resources/js/app.js. --}}
<button type="button" data-abrir-modal="asistente" aria-label="{{ __('componentes.abrir_asistente') }}"
        class="fixed right-4 bottom-20 z-20 flex h-14 items-center gap-2 rounded-full bg-tinta px-4 text-base font-bold text-white shadow-levantada ring-1 ring-white/10 transition-colors hover:bg-marca-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-700 md:bottom-6 print:hidden">
    <x-icono nombre="chat" class="h-6 w-6 text-marca-300" /> <span class="hidden sm:inline">{{ __('componentes.asistente') }}</span>
</button>

<x-modal id="asistente" :titulo="__('componentes.asistente_finca')" data-asistente
         data-url="{{ route('asistente.enviar') }}" data-historial="{{ route('asistente.historial') }}">
    <div data-asistente-mensajes class="-mx-1 mb-4 max-h-[50dvh] min-h-32 space-y-3 overflow-y-auto px-1" aria-live="polite">
        <div data-asistente-vacio class="text-base text-tinta-suave">
            <p class="mb-3">{{ __('componentes.preguntame_tus_lagos_racion_dia') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach (__('componentes.sugerencias_asistente') as $sugerencia)
                    <button type="button" data-asistente-sugerencia class="min-h-12 rounded-ficha bg-ficha px-3 text-sm font-semibold text-tinta ring-1 ring-renglon-fuerte transition-colors hover:bg-papel-hondo">{{ $sugerencia }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <p data-asistente-no-configurado hidden class="mb-4 rounded-ficha border border-atencion-borde bg-atencion-fondo p-3 text-base font-semibold text-atencion">
        {{ __('componentes.asistente_aun_no_esta_configurado') }}
    </p>

    <form data-asistente-formulario class="flex items-end gap-2">
        <label for="asistente-mensaje" class="sr-only">{{ __('componentes.tu_pregunta') }}</label>
        <textarea id="asistente-mensaje" name="mensaje" rows="2" maxlength="1000" required
                  class="block min-h-12 w-full resize-none rounded-ficha border border-renglon-fuerte bg-ficha px-4 py-3 text-base text-tinta transition-[border-color,box-shadow] duration-150 placeholder:text-tinta-suave/80 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70"
                  placeholder="{{ __('componentes.escribe_tu_pregunta') }}"></textarea>
        <x-boton class="shrink-0 !px-4" :aria-label="__('componentes.enviar')">{{ __('componentes.enviar') }}</x-boton>
    </form>
    <div class="mt-2 flex items-center justify-between gap-3 text-sm text-tinta-suave">
        <span>{{ __('componentes.respuestas_son_orientativas_verifica_importante') }}</span>
        <button type="button" data-asistente-reiniciar class="min-h-12 shrink-0 font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">{{ __('componentes.nueva_conversacion') }}</button>
    </div>
</x-modal>
