/*
 * Pesaje de la cosecha, como en el cuaderno (Alpine: x-data="pesaje({...})").
 * Cada pesada: descuento = cantidad × peso de UNA vacía (se multiplica); peso entero = peso pesado − descuento.
 * Todo se calcula en vivo y en centésimas de kg (enteros), igual que el servidor, que vuelve a hacer las cuentas al guardar.
 */
const t = (clave, datos = {}) => (window.textos?.[clave] ?? clave).replace(/:(\w+)/g, (marca, nombre) => datos[nombre] ?? marca);

export default (config) => ({
    modo: config.modo,
    vacias: { canasta: config.canasta, canastilla: config.canastilla },
    filas: config.filas.length > 0 ? config.filas : [{ tipo: 'canasta', cantidad: '', peso: '' }],
    meta: '',
    aviso: '',

    // Números escritos con coma o punto decimal
    numero(valor) {
        const n = Number.parseFloat(String(valor ?? '').replace(',', '.'));
        return Number.isFinite(n) ? n : null;
    },
    centesimas(valor) {
        const n = this.numero(valor);
        return n === null ? null : Math.round(n * 100);
    },
    cantidad(fila) {
        const n = this.numero(fila.cantidad);
        return n !== null && Number.isInteger(n) && n >= 1 ? n : null;
    },

    descuentoC(fila) {
        const cantidad = this.cantidad(fila);
        const vacia = this.centesimas(this.vacias[fila.tipo]);
        return cantidad === null || vacia === null ? null : cantidad * vacia;
    },
    enteroC(fila) {
        const pesado = this.centesimas(fila.peso);
        const descuento = this.descuentoC(fila);
        return pesado === null || descuento === null ? null : pesado - descuento;
    },

    // Kg con los separadores de la finca y sin ceros de más (2; 35,5; 1.234,25)
    kg(centesimas) {
        if (centesimas === null) return '—';
        const [entero, decimales] = (Math.abs(centesimas) / 100).toFixed(2).split('.');
        const miles = entero.replace(/\B(?=(\d{3})+(?!\d))/g, config.miles);
        const resto = decimales.replace(/0+$/, '');
        return (centesimas < 0 ? '-' : '') + miles + (resto ? config.decimal + resto : '');
    },

    get completas() {
        return this.filas.filter((fila) => this.enteroC(fila) !== null);
    },
    get totales() {
        const suma = (calculo) => this.completas.reduce((total, fila) => total + calculo(fila), 0);
        const porTipo = { canasta: 0, canastilla: 0 };
        const grupos = new Map();
        this.completas.forEach((fila) => {
            porTipo[fila.tipo] += this.cantidad(fila);
            const vacia = this.centesimas(this.vacias[fila.tipo]);
            const clave = `${fila.tipo}|${vacia}`;
            const grupo = grupos.get(clave) ?? { tipo: fila.tipo, vacia, cantidad: 0 };
            grupo.cantidad += this.cantidad(fila);
            grupos.set(clave, grupo);
        });
        return {
            pesadas: this.completas.length,
            porTipo,
            cantidad: porTipo.canasta + porTipo.canastilla,
            grupos: [...grupos.values()],
            pesado: suma((fila) => this.centesimas(fila.peso)),
            descuento: suma((fila) => this.descuentoC(fila)),
            entero: suma((fila) => this.enteroC(fila)),
        };
    },
    get linea() {
        const totales = this.totales;
        if (totales.pesadas === 0) return '';
        const grupos = totales.grupos
            .map((g) => t(`pesaje_grupo_${g.tipo}_${g.cantidad === 1 ? 'uno' : 'varios'}`, { cantidad: g.cantidad, kg: this.kg(g.vacia) }))
            .join(' + ');
        return t('pesaje_linea', { grupos, descuento: this.kg(totales.descuento), pesado: this.kg(totales.pesado), entero: this.kg(totales.entero) });
    },

    // Meta opcional: se compara con el total ENTERO; solo ayuda en pantalla
    get metaC() {
        const meta = this.centesimas(this.meta);
        return meta !== null && meta > 0 ? meta : null;
    },
    get avance() {
        return this.metaC === null ? 0 : Math.min(100, Math.max(0, (this.totales.entero / this.metaC) * 100));
    },
    get metaTexto() {
        const meta = this.metaC;
        if (meta === null) return [];
        const { entero, pesadas } = this.totales;
        if (entero >= meta) {
            return [entero === meta ? t('meta_llegaste', { meta: this.kg(meta) }) : t('meta_sobran', { meta: this.kg(meta), kg: this.kg(entero - meta) })];
        }
        const faltan = meta - entero;
        const textos = [t('meta_llevas', { entero: this.kg(entero), meta: this.kg(meta) }), t('meta_faltan', { kg: this.kg(faltan) })];
        if (pesadas >= 2 && entero > 0) {
            const promedio = Math.round(entero / pesadas);
            const restantes = Math.ceil(faltan / (entero / pesadas));
            textos.push(t(restantes === 1 ? 'meta_promedio_una' : 'meta_promedio_varias', { promedio: this.kg(promedio), pesadas: restantes }));
        }
        return textos;
    },

    agregar() {
        const anterior = this.filas[this.filas.length - 1];
        this.filas.push({ tipo: anterior?.tipo ?? 'canasta', cantidad: '', peso: '' });
        this.enfocar(this.filas.length - 1);
    },
    quitar(indice) {
        if (this.filas.length > 1) this.filas.splice(indice, 1);
        else this.filas = [{ tipo: this.filas[0].tipo, cantidad: '', peso: '' }];
    },
    // Enter en el peso pesado: pasa a la siguiente pesada, o la añade si es la última
    siguiente(indice) {
        if (indice === this.filas.length - 1) this.agregar();
        else this.enfocar(indice + 1);
    },
    enfocar(indice) {
        this.$nextTick(() => this.$root.querySelector(`[data-cantidad="${indice}"]`)?.focus());
    },

    // No se envía con una fila incompleta: avisa cuál es (el servidor valida todo de nuevo)
    validar(evento) {
        this.aviso = '';
        if (this.modo !== 'pesadas') return;
        if (!(this.centesimas(this.vacias.canasta) > 0) || !(this.centesimas(this.vacias.canastilla) > 0)) {
            this.aviso = t('pesaje_vacias');
        } else {
            const indice = this.filas.findIndex((fila) => this.enteroC(fila) === null);
            if (indice >= 0) {
                this.aviso = t('pesaje_incompleta', { numero: indice + 1 });
                this.enfocar(indice);
            } else {
                const bajo = this.filas.findIndex((fila) => this.enteroC(fila) <= 0);
                if (bajo >= 0) this.aviso = t('pesaje_bajo_descuento', { numero: bajo + 1 });
            }
        }
        if (this.aviso) evento.preventDefault();
    },
});
