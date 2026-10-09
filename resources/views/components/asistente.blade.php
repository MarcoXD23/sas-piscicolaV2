{{-- Único widget del asistente IA (se incluye una vez en el layout). La lógica está en resources/js/app.js. --}}
<button type="button" data-abrir-modal="asistente" aria-label="{{ __('componentes.abrir_asistente') }}"
        data-onda class="group presionable fixed right-4 bottom-20 z-20 flex h-14 animate-aparecer-escala items-center gap-2 rounded-full bg-gradient-to-br from-marca-500 to-marca-800 px-4 text-base font-semibold text-white shadow-brillo ring-1 ring-white/15 [animation-delay:600ms] hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-700 md:bottom-6 print:hidden">
    <x-icono nombre="chat" class="h-6 w-6 transition group-hover:scale-110" /> <span class="hidden sm:inline">{{ __('componentes.asistente') }}</span>
</button>

<x-modal id="asistente" :titulo="__('componentes.asistente_finca')" data-asistente
         data-url="{{ route('asistente.enviar') }}" data-historial="{{ route('asistente.historial') }}">
    <div data-asistente-mensajes class="-mx-1 mb-4 max-h-[50dvh] min-h-32 space-y-3 overflow-y-auto px-1" aria-live="polite">
        <div data-asistente-vacio class="text-base text-slate-600">
            <p class="mb-3">{{ __('componentes.preguntame_tus_lagos_racion_dia') }}</p>
            <div class="escalonado flex flex-wrap gap-2">
                @foreach (__('componentes.sugerencias_asistente') as $sugerencia)
                    <button type="button" data-asistente-sugerencia class="presionable min-h-12 rounded-full bg-marca-50 px-3 text-sm font-medium text-marca-800 ring-1 ring-marca-200 hover:-translate-y-px hover:bg-marca-100 hover:shadow-suave">{{ $sugerencia }}</button>
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
                  class="block min-h-12 w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-suave transition duration-200 hover:border-slate-400 focus:border-marca-500 focus:outline-none focus:ring-4 focus:ring-marca-100"
                  placeholder="{{ __('componentes.escribe_tu_pregunta') }}"></textarea>
        <x-boton class="shrink-0 !px-4" :aria-label="__('componentes.enviar')">{{ __('componentes.enviar') }}</x-boton>
    </form>
    <div class="mt-2 flex items-center justify-between gap-3 text-sm text-texto-suave">
        <span>{{ __('componentes.respuestas_son_orientativas_verifica_importante') }}</span>
        <button type="button" data-asistente-reiniciar class="min-h-12 shrink-0 font-semibold text-marca-700 hover:underline">{{ __('componentes.nueva_conversacion') }}</button>
    </div>
</x-modal>
