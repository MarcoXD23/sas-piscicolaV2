<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Models\Lago;
use App\Models\PesajeEntero;
use App\Models\PesajeLimpio;
use App\Models\Presentacion;
use App\Models\ReproduccionJornada;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tratamiento;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\CalculoPesaje;
use App\Support\Proceso\Ayuno;
use App\Support\Proceso\CierreJornada;
use App\Support\Proceso\Rendimiento;
use App\Support\Proceso\RepartoCosecha;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Jornadas de proceso (docs/diseno/dia-de-proceso.md, temas 2 a 5): el administrador las programa, agrega lagos,
 * registra el entero por lago y especie y las cierra (CierreJornada crea las cosechas); el propietario las consulta.
 * Solo existen con el ajuste proceso.jornadas encendido: apagado, todas sus rutas responden 404.
 */
class JornadaController extends Controller implements HasMiddleware
{
    /** Hasta cuántos días adelante se puede programar una jornada. */
    public const DIAS_ADELANTE = 90;

    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                abort_unless(Ayuno::jornadasEncendidas(), 404);

                return $next($request);
            }),
        ];
    }

    public function index(): View
    {
        return view('admin.jornadas.index', [
            'jornadas' => Jornada::query()->with(['lagos.lago'])->withSum('cosechas', 'kg')
                ->orderByDesc('fecha')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.jornadas.crear', [
            'lagos' => $this->lagosConPeces(),
            'enAyuno' => Ayuno::lagos(),
            'diasAyuno' => (int) AjustesFinca::valor(CatalogoAjustes::PROCESO_DIAS_AYUNO),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->addDays(self::DIAS_ADELANTE)->toDateString()],
            'lagos' => ['required', 'array', 'min:1', 'max:200'],
            'lagos.*' => ['integer', 'distinct', Rule::exists('lagos', 'id')->where('tenant_id', $tenantId)],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'fecha.before_or_equal' => __('jornadas.errores.fecha_muy_lejana', ['dias' => self::DIAS_ADELANTE]),
            'lagos.required' => __('jornadas.errores.elige_lagos'),
        ]);

        $fecha = CarbonImmutable::parse($datos['fecha']);
        $lagos = Lago::query()->whereIn('id', $datos['lagos'])->orderBy('nombre')->get();
        foreach ($lagos as $lago) {
            $this->revisarLago($lago, $fecha, 'lagos');
        }

        if (Jornada::query()->activas()->whereDate('fecha', $fecha->toDateString())->exists()) {
            throw ValidationException::withMessages(['fecha' => __('jornadas.errores.ya_hay_jornada', ['fecha' => $fecha->format('d/m/Y')])]);
        }

        try {
            $jornada = DB::transaction(function () use ($request, $datos, $fecha, $lagos) {
                $jornada = Jornada::create([
                    'fecha' => $fecha->toDateString(),
                    'estado' => Jornada::ABIERTA,
                    'sin_alimentacion' => AjustesFinca::valor(CatalogoAjustes::PROCESO_SIN_ALIMENTACION) === CatalogoAjustes::ENCENDIDA,
                    'observaciones' => $datos['observaciones'] ?? null,
                    'user_id' => $request->user()->id,
                ]);
                foreach ($lagos->values() as $indice => $lago) {
                    $jornada->lagos()->create(['lago_id' => $lago->id, 'orden' => $indice + 1]);
                }

                Bitacora::registrar($request->user(), 'jornada_creada', null, [Rol::ADMINISTRADOR], [
                    'fecha' => $fecha->toDateString(), 'lagos' => $lagos->pluck('nombre')->implode(', '),
                ]);

                return $jornada;
            });
        } catch (UniqueConstraintViolationException) {
            // Dos administradores crearon la jornada del mismo día a la vez: la base deja solo una
            throw ValidationException::withMessages(['fecha' => __('jornadas.errores.ya_hay_jornada', ['fecha' => $fecha->format('d/m/Y')])]);
        }

        return redirect()->route('admin.jornadas.show', $jornada)->with('estado', __('jornadas.creada', ['fecha' => $fecha->format('d/m/Y')]));
    }

    public function show(Request $request, Jornada $jornada): View
    {
        $jornada->load(['lagos.lago.siembrasActivas', 'lagos.pesajes.especie', 'lagos.reproducciones.especie', 'usuario', 'cerradaPor', 'canceladaPor',
            'cosechas.siembra', 'cosechas.lago', 'pesajesLimpio.presentacion.especie', 'pesajesLimpio.presentacion.tamano', 'pesajesLimpio.presentacion.proceso',
            'rendimientos.especie']);
        $puedeOperar = $request->user()->tieneRol(Rol::ADMINISTRADOR) && $jornada->estaAbierta();
        $especiesPorLago = $jornada->lagos->mapWithKeys(fn (JornadaLago $jl) => [$jl->id => $this->especiesDelLago($jl->lago)]);

        return view('admin.jornadas.show', [
            'jornada' => $jornada,
            'puedeOperar' => $puedeOperar,
            'puedePesar' => $puedeOperar && $jornada->yaEsSuDia(),
            'lagosParaAgregar' => $puedeOperar ? $this->lagosConPeces()->whereNotIn('id', $jornada->lagos->pluck('lago_id'))->values() : collect(),
            'especiesPorLago' => $especiesPorLago,
            'presentaciones' => $this->presentacionesDe($especiesPorLago->flatMap(fn ($especies) => $especies->keys())->unique()->all()),
            'conReproduccion' => $this->reproduccionEncendida(),
            'rendimientoAhora' => $jornada->estaAbierta() ? Rendimiento::deJornada($jornada) : null,
            'nombresEspecie' => Especie::query()->pluck('nombre', 'id'),
            'pesoCanasta' => $request->user()->tenant->peso_canasta_kg,
            'pesoCanastilla' => $request->user()->tenant->peso_canastilla_kg,
        ]);
    }

    /** Pesada del limpio por presentación: en la jornada abierta y desde su día, con el mismo bloqueo que el entero. */
    public function guardarLimpio(Request $request, Jornada $jornada): RedirectResponse
    {
        $datos = $request->validate($this->reglasPesadas() + [
            'presentacion_id' => ['required', 'integer', Rule::exists('presentaciones', 'id')->where('tenant_id', $request->user()->tenant_id)->where('activo', true)],
        ], $this->mensajesPesadas() + [
            'presentacion_id.required' => __('jornadas.errores.elige_presentacion'),
            'presentacion_id.exists' => __('jornadas.errores.elige_presentacion'),
        ]);

        $jornada->load('lagos.lago.siembrasActivas');
        $presentacion = Presentacion::with(['especie', 'tamano', 'proceso'])->findOrFail($datos['presentacion_id']);
        $especies = $jornada->lagos->flatMap(fn (JornadaLago $jl) => $this->especiesDelLago($jl->lago)->keys())->unique();
        if (! $especies->contains((int) $presentacion->especie_id)) {
            throw ValidationException::withMessages(['presentacion_id' => __('jornadas.errores.presentacion_sin_especie', ['especie' => $presentacion->especie->nombre])]);
        }
        $filas = $this->filasPesaje($datos);

        $kg = DB::transaction(function () use ($request, $jornada, $presentacion, $filas) {
            $bloqueada = $this->bloquearAbierta($jornada);
            if (! $bloqueada->yaEsSuDia()) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
            }
            foreach ($filas as $fila) {
                $limpio = $fila['entero_kg'];
                unset($fila['entero_kg']);
                $bloqueada->pesajesLimpio()->create($fila + ['limpio_kg' => $limpio, 'presentacion_id' => $presentacion->id, 'user_id' => $request->user()->id]);
            }

            return array_sum(array_column($filas, 'entero_kg'));
        });

        return back()->with('estado', __('jornadas.limpio_guardado', ['kg' => CalculoPesaje::kg($kg), 'presentacion' => $presentacion->nombre()]));
    }

    public function eliminarLimpio(Jornada $jornada, PesajeLimpio $pesajeLimpio): RedirectResponse
    {
        abort_unless($pesajeLimpio->jornada_id === $jornada->id, 404);

        DB::transaction(function () use ($jornada, $pesajeLimpio) {
            $this->bloquearAbierta($jornada);
            $pesajeLimpio->delete();
        });

        return back()->with('estado', __('jornadas.pesaje_eliminado', ['kg' => CalculoPesaje::kg($pesajeLimpio->limpio_kg)]));
    }

    /** «Reproducción» de un lago y una especie (solo con el ajuste encendido): kg que vuelven al lago. */
    public function guardarReproduccion(Request $request, Jornada $jornada): RedirectResponse
    {
        abort_unless($this->reproduccionEncendida(), 404);
        $datos = $request->validate([
            'jornada_lago_id' => ['required', 'integer', Rule::exists('jornada_lagos', 'id')->where('jornada_id', $jornada->id)],
            'especie_id' => ['required', 'integer'],
            'kg' => ['required', 'numeric', 'min:0.1', 'max:9999999'],
        ], [
            'jornada_lago_id.required' => __('jornadas.errores.elige_lago'),
            'jornada_lago_id.exists' => __('jornadas.errores.elige_lago'),
            'especie_id.required' => __('jornadas.errores.elige_especie'),
            'kg.required' => __('jornadas.errores.kg_invalido'),
            'kg.min' => __('jornadas.errores.kg_invalido'),
        ]);

        $jornadaLago = JornadaLago::with('lago.siembrasActivas')->findOrFail($datos['jornada_lago_id']);
        if (! $this->especiesDelLago($jornadaLago->lago)->has((int) $datos['especie_id'])) {
            throw ValidationException::withMessages(['especie_id' => __('jornadas.errores.especie_no_esta', ['lago' => $jornadaLago->lago->nombre])]);
        }

        DB::transaction(function () use ($request, $jornada, $jornadaLago, $datos) {
            $bloqueada = $this->bloquearAbierta($jornada);
            if (! $bloqueada->yaEsSuDia()) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
            }
            $jornadaLago->reproducciones()->create([
                'especie_id' => (int) $datos['especie_id'], 'kg' => round((float) $datos['kg'], 2), 'user_id' => $request->user()->id,
            ]);
        });

        return back()->with('estado', __('jornadas.reproduccion_guardada', ['kg' => CalculoPesaje::kg(round((float) $datos['kg'], 2)), 'lago' => $jornadaLago->lago->nombre]));
    }

    public function eliminarReproduccion(Jornada $jornada, ReproduccionJornada $reproduccion): RedirectResponse
    {
        abort_unless($this->reproduccionEncendida(), 404);
        abort_unless($reproduccion->jornadaLago?->jornada_id === $jornada->id, 404);

        DB::transaction(function () use ($jornada, $reproduccion) {
            $this->bloquearAbierta($jornada);
            $reproduccion->delete();
        });

        return back()->with('estado', __('jornadas.pesaje_eliminado', ['kg' => CalculoPesaje::kg($reproduccion->kg)]));
    }

    public function agregarLago(Request $request, Jornada $jornada): RedirectResponse
    {
        $datos = $request->validate([
            'lago_id' => ['required', 'integer', Rule::exists('lagos', 'id')->where('tenant_id', $request->user()->tenant_id)],
        ], ['lago_id.required' => __('jornadas.errores.elige_lago')]);
        $lago = Lago::findOrFail($datos['lago_id']);

        DB::transaction(function () use ($request, $jornada, $lago) {
            $bloqueada = $this->bloquearAbierta($jornada);
            $this->revisarLago($lago, $bloqueada->fecha, 'lago_id');
            if ($bloqueada->lagos()->where('lago_id', $lago->id)->exists()) {
                throw ValidationException::withMessages(['lago_id' => __('jornadas.errores.lago_ya_esta', ['lago' => $lago->nombre])]);
            }

            $bloqueada->lagos()->create(['lago_id' => $lago->id, 'orden' => (int) $bloqueada->lagos()->max('orden') + 1]);
            Bitacora::registrar($request->user(), 'jornada_lago_nuevo', $lago, [Rol::ADMINISTRADOR], ['fecha' => $bloqueada->fecha->toDateString()]);
        });

        return back()->with('estado', __('jornadas.lago_agregado', ['lago' => $lago->nombre]));
    }

    public function guardarPesaje(Request $request, Jornada $jornada): RedirectResponse
    {
        $datos = $request->validate($this->reglasPesadas() + [
            'jornada_lago_id' => ['required', 'integer', Rule::exists('jornada_lagos', 'id')->where('jornada_id', $jornada->id)],
            'especie_id' => ['required', 'integer'],
        ], $this->mensajesPesadas() + [
            'jornada_lago_id.required' => __('jornadas.errores.elige_lago'),
            'jornada_lago_id.exists' => __('jornadas.errores.elige_lago'),
            'especie_id.required' => __('jornadas.errores.elige_especie'),
        ]);

        $jornadaLago = JornadaLago::with('lago')->findOrFail($datos['jornada_lago_id']);
        if (! $this->especiesDelLago($jornadaLago->lago)->has((int) $datos['especie_id'])) {
            throw ValidationException::withMessages(['especie_id' => __('jornadas.errores.especie_no_esta', ['lago' => $jornadaLago->lago->nombre])]);
        }
        $filas = $this->filasPesaje($datos);

        $kg = DB::transaction(function () use ($request, $jornada, $jornadaLago, $datos, $filas) {
            $bloqueada = $this->bloquearAbierta($jornada);
            if (! $bloqueada->yaEsSuDia()) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
            }
            foreach ($filas as $fila) {
                $jornadaLago->pesajes()->create($fila + ['especie_id' => (int) $datos['especie_id'], 'user_id' => $request->user()->id]);
            }

            return array_sum(array_column($filas, 'entero_kg'));
        });

        return back()->with('estado', __('jornadas.pesaje_guardado', ['kg' => CalculoPesaje::kg($kg), 'lago' => $jornadaLago->lago->nombre]));
    }

    public function eliminarPesaje(Jornada $jornada, PesajeEntero $pesaje): RedirectResponse
    {
        abort_unless($pesaje->jornadaLago?->jornada_id === $jornada->id, 404);

        DB::transaction(function () use ($jornada, $pesaje) {
            $this->bloquearAbierta($jornada);
            $pesaje->delete();
        });

        return back()->with('estado', __('jornadas.pesaje_eliminado', ['kg' => CalculoPesaje::kg($pesaje->entero_kg)]));
    }

    /** Vista previa del cierre: el reparto según la biomasa, que el administrador puede ajustar. */
    public function cerrar(Jornada $jornada): View|RedirectResponse
    {
        if (! $jornada->estaAbierta()) {
            return redirect()->route('admin.jornadas.show', $jornada);
        }
        if (! $jornada->yaEsSuDia()) {
            return redirect()->route('admin.jornadas.show', $jornada)->withErrors(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
        }

        $jornada->load(['lagos.lago.siembrasActivas', 'lagos.pesajes.especie', 'lagos.reproducciones', 'pesajesLimpio.presentacion']);

        return view('admin.jornadas.cerrar', [
            'jornada' => $jornada,
            'propuestas' => $jornada->lagos->mapWithKeys(fn (JornadaLago $jl) => [
                $jl->id => RepartoCosecha::propuestaLago($jl, RepartoCosecha::siembrasDelDia($jornada, $jl->lago->siembrasActivas)),
            ]),
            // Rendimiento con lo pesado: avisa si sale bajo (con motivo opcional) o si no se pesó limpio
            'rendimiento' => Rendimiento::deJornada($jornada),
            'nombresEspecie' => Especie::query()->pluck('nombre', 'id'),
        ]);
    }

    public function guardarCierre(Request $request, Jornada $jornada): RedirectResponse
    {
        $datos = $request->validate([
            'lagos' => ['required', 'array'],
            'lagos.*.resultado' => ['nullable', 'string', Rule::in(JornadaLago::RESULTADOS)],
            'lagos.*.kg' => ['nullable', 'array'],
            'lagos.*.kg.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lagos.*.restante_kg' => ['nullable', 'array'],
            'lagos.*.restante_kg.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            // Muestra al pescar: peces y gramos totales por siembra (CierreJornada exige los dos o ninguno)
            'lagos.*.muestra_peces' => ['nullable', 'array'],
            'lagos.*.muestra_peces.*' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'lagos.*.muestra_g' => ['nullable', 'array'],
            'lagos.*.muestra_g.*' => ['nullable', 'numeric', 'min:0.1', 'max:999999'],
            'rendimientos' => ['nullable', 'array'],
            'rendimientos.*.motivo' => ['nullable', 'string', 'max:300'],
        ], [
            'lagos.*.muestra_peces.*.integer' => __('jornadas.errores.muestra_invalida'),
            'lagos.*.muestra_peces.*.min' => __('jornadas.errores.muestra_invalida'),
            'lagos.*.muestra_g.*.numeric' => __('jornadas.errores.muestra_invalida'),
            'lagos.*.muestra_g.*.min' => __('jornadas.errores.muestra_invalida'),
            'rendimientos.*.motivo.max' => __('jornadas.errores.motivo_largo', ['maximo' => 300]),
            'lagos.required' => __('jornadas.errores.elige_resultado_todos'),
            'lagos.*.kg.*.numeric' => __('jornadas.errores.kg_invalido'),
            'lagos.*.kg.*.min' => __('jornadas.errores.kg_invalido'),
            'lagos.*.restante_kg.*.numeric' => __('jornadas.errores.kg_invalido'),
            'lagos.*.restante_kg.*.min' => __('jornadas.errores.kg_invalido'),
        ]);

        $cerrada = CierreJornada::cerrar($jornada, $request->user(), $datos['lagos'], $datos['rendimientos'] ?? []);
        if ($cerrada === null) {
            // Doble clic o ya cerrada (o cancelada) por otra persona: no se hace nada otra vez
            return redirect()->route('admin.jornadas.show', $jornada)->with('estado', __('jornadas.ya_no_abierta'));
        }

        return redirect()->route('admin.jornadas.show', $jornada)->with('estado', __('jornadas.cerrada', [
            'cosechas' => $cerrada->cosechas()->count(), 'kg' => CalculoPesaje::kg((float) $cerrada->cosechas()->sum('kg')),
        ]));
    }

    /** Cancelar: solo si está abierta. Los pesajes se conservan; no crea cosechas y el ayuno se quita. */
    public function cancelar(Request $request, Jornada $jornada): RedirectResponse
    {
        DB::transaction(function () use ($request, $jornada) {
            $bloqueada = $this->bloquearAbierta($jornada);
            $bloqueada->update(['estado' => Jornada::CANCELADA, 'cancelada_at' => now(), 'cancelada_por' => $request->user()->id]);
            Bitacora::registrar($request->user(), 'jornada_cancelada', null, [Rol::ADMINISTRADOR], [
                'fecha' => $bloqueada->fecha->toDateString(), 'pesadas' => PesajeEntero::query()->whereIn('jornada_lago_id', $bloqueada->lagos()->select('id'))->count(),
            ]);
        });

        return redirect()->route('admin.jornadas.show', $jornada)->with('estado', __('jornadas.cancelada'));
    }

    /** Reglas de una pesada (con canastas o el total escrito), las mismas para el entero y el limpio. */
    private function reglasPesadas(): array
    {
        return [
            'kg' => ['nullable', 'required_without:pesadas', 'numeric', 'min:0.1', 'max:9999999'],
            'pesadas' => ['nullable', 'array', 'min:1', 'max:'.CalculoPesaje::MAX_PESADAS],
            'pesadas.*.tipo' => ['required', 'string', Rule::in(CalculoPesaje::TIPOS)],
            'pesadas.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.CalculoPesaje::MAX_CANTIDAD],
            'pesadas.*.peso_pesado' => ['required', 'numeric', 'gt:0', 'max:'.CalculoPesaje::MAX_PESO_PESADA_KG],
            'peso_canasta_kg' => ['nullable', 'required_with:pesadas', 'numeric', 'min:'.CalculoPesaje::MIN_PESO_VACIA_KG, 'max:'.CalculoPesaje::MAX_PESO_VACIA_KG],
            'peso_canastilla_kg' => ['nullable', 'required_with:pesadas', 'numeric', 'min:'.CalculoPesaje::MIN_PESO_VACIA_KG, 'max:'.CalculoPesaje::MAX_PESO_VACIA_KG],
        ];
    }

    private function mensajesPesadas(): array
    {
        return [
            'kg.required_without' => __('mensajes.cosecha.registra_peso_entero_o_pesadas'),
            'kg.min' => __('mensajes.cosecha.registra_peso_cosechado'),
            'pesadas.max' => __('mensajes.cosecha.maximo_pesadas', ['maximo' => CalculoPesaje::MAX_PESADAS]),
        ];
    }

    private function reproduccionEncendida(): bool
    {
        return AjustesFinca::valor(CatalogoAjustes::PROCESO_REPRODUCCION) === CatalogoAjustes::ENCENDIDA;
    }

    /**
     * Presentaciones activas de las especies dadas, para pesar el limpio (presentacion_id => nombre).
     *
     * @param  list<int>  $especies
     * @return Collection<int, string>
     */
    private function presentacionesDe(array $especies): Collection
    {
        return Presentacion::query()->activas()->whereIn('especie_id', $especies)
            ->whereHas('tamano', fn ($q) => $q->where('activo', true))->whereHas('proceso', fn ($q) => $q->where('activo', true))
            ->with(['especie', 'tamano', 'proceso'])->get()
            ->sortBy(fn (Presentacion $p) => [$p->especie->nombre, $p->tamano->orden, $p->proceso->orden])
            ->mapWithKeys(fn (Presentacion $p) => [$p->id => $p->nombre()]);
    }

    /** Bloquea la jornada; si ya no está abierta (cerrada o cancelada), no deja seguir. */
    private function bloquearAbierta(Jornada $jornada): Jornada
    {
        $bloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();
        if (! $bloqueada->estaAbierta()) {
            throw ValidationException::withMessages(['jornada' => __('jornadas.ya_no_abierta')]);
        }

        return $bloqueada;
    }

    /** Un lago entra a la jornada si tiene peces y no está en retiro de tratamiento en la fecha de la jornada. */
    private function revisarLago(Lago $lago, \DateTimeInterface $fecha, string $campo): void
    {
        if (! $lago->siembrasActivas()->exists()) {
            throw ValidationException::withMessages([$campo => __('jornadas.errores.lago_sin_peces', ['lago' => $lago->nombre])]);
        }

        $retiro = Tratamiento::query()->where('lago_id', $lago->id)->enRetiro(CarbonImmutable::instance($fecha))->get()
            ->sortByDesc(fn (Tratamiento $t) => $t->finRetiro())->first();
        if ($retiro) {
            throw ValidationException::withMessages([$campo => __('jornadas.errores.lago_en_retiro', [
                'lago' => $lago->nombre, 'fecha' => $retiro->finRetiro()->format('d/m/Y'), 'producto' => $retiro->producto,
            ])]);
        }
    }

    /** @return Collection<int, Lago> */
    private function lagosConPeces(): Collection
    {
        return Lago::query()->whereHas('siembrasActivas')->with('siembrasActivas')->orderBy('nombre')->get();
    }

    /**
     * Especies del catálogo que tienen siembra activa en el lago (la siembra vieja sin especie del catálogo no se
     * puede pesar: hay que ligarla primero).
     *
     * @return Collection<int, string> especie_id => nombre
     */
    private function especiesDelLago(Lago $lago): Collection
    {
        return $lago->siembrasActivas->filter(fn (Siembra $s) => $s->especie_id !== null)
            ->mapWithKeys(fn (Siembra $s) => [(int) $s->especie_id => $s->nombre_especie])->sort();
    }

    /**
     * Filas para pesajes_entero: las pesadas con las cuentas de CalculoPesaje (rehechas en el servidor), o el total.
     *
     * @return list<array<string, mixed>>
     */
    private function filasPesaje(array $datos): array
    {
        if (empty($datos['pesadas'])) {
            $kg = round((float) $datos['kg'], 2);

            return [['tipo' => PesajeEntero::TOTAL, 'canastas' => 0, 'tara_unitaria_kg' => 0, 'bruto_kg' => $kg, 'tara_kg' => 0, 'entero_kg' => $kg]];
        }

        $pesaje = CalculoPesaje::calcular(array_values($datos['pesadas']), (float) $datos['peso_canasta_kg'], (float) $datos['peso_canastilla_kg']);
        $errores = [];
        foreach ($pesaje['filas'] as $indice => $fila) {
            if ($fila['peso_pesado'] <= $fila['descuento']) {
                $errores["pesadas.{$indice}.peso_pesado"] = __('mensajes.cosecha.pesada_bajo_descuento', [
                    'numero' => $fila['numero'], 'pesado' => CalculoPesaje::kg($fila['peso_pesado']), 'descuento' => CalculoPesaje::kg($fila['descuento']),
                ]);
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return array_map(fn (array $fila) => [
            'tipo' => $fila['tipo'], 'canastas' => $fila['cantidad'], 'tara_unitaria_kg' => $fila['peso_vacia'],
            'bruto_kg' => $fila['peso_pesado'], 'tara_kg' => $fila['descuento'], 'entero_kg' => $fila['entero'],
        ], $pesaje['filas']);
    }
}
