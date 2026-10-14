/*
 * Horas en el formato de la persona (12 horas «10:24 p. m.» o 24 horas «22:24»), con la misma regla que el servidor
 * (App\Support\Hora): casos compartidos en tests/fixtures/horas.json.
 * - El formato llega ya resuelto (persona → finca → país → 24): en <body data-formato-hora> o, en Registro rápido, en
 *   el paquete de trabajo. Sin dato (un paquete viejo), 24 horas.
 * - Se arma a mano (no con toLocaleString: cada navegador escribe «p.m.», «p. m.» o «PM» distinto). «a. m.» y «p. m.»
 *   vienen de lang/es (window.textos) y van con espacios que no se parten.
 * - Bajo los campos donde se escribe una hora (data-ver-hora) muestra la hora elegida en ese formato.
 * Sin navegador para la lógica: se prueba con node --test (tests/js/hora.test.mjs).
 */

const DURO = ' ';
const dos = (n) => String(n).padStart(2, '0');

/** Formato válido o 24. */
export const normalizar = (formato) => (formato === '12' ? '12' : '24');

/** «10:24 p. m.» o «22:24» para unas horas y minutos dados. textos: { am, pm }. */
export const horaDe = (horas, minutos, formato, textos) => {
    if (normalizar(formato) === '24') return `${dos(horas)}:${dos(minutos)}`;
    const meridiano = String(horas < 12 ? textos.am : textos.pm).replace(/ /g, DURO);
    return `${horas % 12 === 0 ? 12 : horas % 12}:${dos(minutos)}${DURO}${meridiano}`;
};

/** Hora de una fecha (en la hora del teléfono, como hasta ahora). */
export const hora = (fecha, formato, textos) => horaDe(fecha.getHours(), fecha.getMinutes(), formato, textos);

/** «15/10 10:24 p. m.» o «15/10 22:24». */
export const diaMesHora = (fecha, formato, textos) => `${dos(fecha.getDate())}/${dos(fecha.getMonth() + 1)} ${hora(fecha, formato, textos)}`;

/** Lo escrito en un campo time («22:24») o datetime-local («2026-10-15T22:24») en el formato de la persona; '' si no hay. */
export const deCampo = (valor, formato, textos) => {
    const coincide = /^(?:(\d{4})-(\d{2})-(\d{2})T)?(\d{2}):(\d{2})/.exec(String(valor ?? ''));
    if (!coincide) return '';
    const [, anio, mes, dia, horas, minutos] = coincide;
    const texto = horaDe(Number(horas), Number(minutos), formato, textos);
    return anio ? `${dia}/${mes}/${anio} ${texto}` : texto;
};

/** Formato de esta página: el del <body>; si no hay, 24. */
export const formatoPagina = (documento = document) => normalizar(documento.body?.dataset.formatoHora);

/** Textos «a. m.» / «p. m.» de la página (lang/es). */
export const textosHora = (t) => ({ am: t('hora_am'), pm: t('hora_pm') });

/** Ayuda bajo cada campo de hora: «Hora elegida: 10:24 p. m.» (el selector lo pone el teléfono a su manera). */
export const iniciarAyudaHora = (t, documento = document) => {
    documento.querySelectorAll('input[data-ver-hora]').forEach((campo) => {
        const ayuda = documento.createElement('p');
        ayuda.className = 'mt-1.5 text-sm text-tinta-suave';
        ayuda.setAttribute('aria-live', 'polite');
        ayuda.dataset.ayudaHora = '';
        campo.insertAdjacentElement('afterend', ayuda);
        const pintar = () => {
            const texto = deCampo(campo.value, formatoPagina(documento), textosHora(t));
            ayuda.textContent = texto ? t('hora_elegida', { hora: texto }) : '';
            ayuda.hidden = texto === '';
        };
        campo.addEventListener('input', pintar);
        campo.addEventListener('change', pintar);
        pintar();
    });
};
