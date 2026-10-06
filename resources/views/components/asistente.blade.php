{{-- Único widget del asistente IA (se incluye una vez en el layout). La lógica está en resources/js/app.js. --}}
<button type="button" data-abrir-modal="asistente" aria-label="{{ __('componentes.abrir_asistente') }}"
        class="fixed right-4 bottom-20 z-20 flex h-14 items-center gap-2 rounded-full bg-marca-700 px-4 text-base font-semibold text-white shadow-lg hover:bg-marca-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-700 md:bottom-6 print:hidden">
    <x-icono nombre="chat" /> <span class="hidden sm:inline">{{ __('componentes.asistente') }}</span>
</button>

<x-modal id="asistente" :titulo="__('componentes.asistente_finca')" data-asistente
         data-url="{{ route('asistente.enviar') }}" data-historial="{{ route('asistente.historial') }}">
    <div data-asistente-mensajes class="-mx-1 mb-4 max-h-[50dvh] min-h-32 space-y-3 overflow-y-auto px-1" aria-live="polite">
        <div data-asistente-vacio class="text-base text-slate-600">
            <p class="mb-3">{{ __('componentes.preguntame_tus_lagos_racion_dia') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach (__('componentes.sugerencias_asistente') as $sugerencia)
                    <button type="button" data-asistente-sugerencia class="min-h-11 rounded-full bg-marca-50 px-3 text-sm font-medium text-marca-800 ring-1 ring-marca-200 hover:bg-marca-100">{{ $sugerencia }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <p data-asistente-no-configurado hidden class="mb-4 rounded-xl bg-amber-50 p-3 text-base text-amber-900">
        {{ __('componentes.asistente_aun_no_esta_configurado') }}
    </p>

    <form data-asistente-formulario class="flex items-end gap-2">
        <label for="asistente-mensaje" class="sr-only">{{ __('componentes.tu_pregunta') }}</label>
        <textarea id="asistente-mensaje" name="mensaje" rows="2" maxlength="1000" required
                  class="block min-h-12 w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200"
                  placeholder="{{ __('componentes.escribe_tu_pregunta') }}"></textarea>
        <x-boton class="shrink-0 !px-4" :aria-label="__('componentes.enviar')">{{ __('componentes.enviar') }}</x-boton>
    </form>
    <div class="mt-2 flex items-center justify-between gap-3 text-sm text-slate-500">
        <span>{{ __('componentes.respuestas_son_orientativas_verifica_importante') }}</span>
        <button type="button" data-asistente-reiniciar class="min-h-11 shrink-0 font-semibold text-marca-700 hover:underline">{{ __('componentes.nueva_conversacion') }}</button>
    </div>
</x-modal>
