<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tercero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Proveedores de la finca (tabla terceros, con es_proveedor): de alevinos, de alimento u otros, uno o varios.
 * - El administrador agrega, edita, activa y desactiva; el propietario solo consulta (la ruta lo protege).
 * - Si el documento escrito ya es de otro tercero de la finca (por ejemplo, un jornalero), no se crea otro: esa misma
 *   persona queda también como proveedor.
 * - Revisión: las siembras viejas con el proveedor escrito a mano se ligan a un proveedor del catálogo. El
 *   administrador elige cada uno (nada viene preseleccionado) y el texto escrito nunca se borra.
 * Cada cambio queda en la bitácora con el id del tercero (nunca su documento).
 */
class ProveedorController extends Controller
{
    private const ROLES = [Rol::ADMINISTRADOR];

    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = $request->query('estado') === 'todos' ? 'todos' : 'activos';
        $provee = in_array($request->query('provee'), Tercero::PROVEE, true) ? $request->query('provee') : null;
        $huella = Tercero::huella($buscar);

        $proveedores = Tercero::query()->proveedores()
            ->when($estado === 'activos', fn ($consulta) => $consulta->activos())
            ->when($provee !== null, fn ($consulta) => $consulta->whereJsonContains('provee', $provee))
            ->when($buscar !== '', fn ($consulta) => $consulta->where(fn ($o) => $o
                ->whereRaw("(nombres || ' ' || coalesce(apellidos, '')) ILIKE ?", ['%'.addcslashes($buscar, '%_\\').'%'])
                // Por documento: solo coincidencia exacta, comparando huellas (nunca el documento en claro)
                ->orWhere('documento_huella', $huella)))
            ->orderBy('nombres')->orderBy('apellidos')
            ->paginate(30)->withQueryString();

        return view('admin.proveedores.index', [
            'proveedores' => $proveedores,
            'buscar' => $buscar,
            'estado' => $estado,
            'provee' => $provee,
            'opcionesProvee' => Tercero::opcionesProvee(),
            'puedeEditar' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
            'veDocumento' => $request->user()->tieneRol(Rol::PROPIETARIO),
            'sinLigar' => self::textosSinLigar()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        [$proveedor, $yaExistia] = DB::transaction(function () use ($datos, $request) {
            // El mismo documento ya es de otro tercero de la finca: esa persona queda también como proveedor
            $huella = Tercero::huella($datos['documento'] ?? null);
            $existente = $huella === null ? null : Tercero::query()->where('documento_huella', $huella)->lockForUpdate()->first();
            if ($existente !== null) {
                if ($existente->anonimizado() || $existente->es_proveedor) {
                    throw ValidationException::withMessages(['documento' => __('proveedores.validacion.documento_repetido')]);
                }
                $existente->asignarProvee($datos['provee']);
                $existente->telefono ??= $datos['telefono'] ?? null;
                $existente->save();
                Bitacora::registrar($request->user(), 'proveedor_creado', null, self::ROLES, ['provee' => $existente->textoProvee()], tercero: $existente);

                return [$existente, true];
            }

            $proveedor = new Tercero(['nombres' => $datos['nombre'], 'telefono' => $datos['telefono'] ?? null]);
            $proveedor->asignarProvee($datos['provee']);
            $proveedor->asignarDocumento($datos['documento'] ?? null);
            $proveedor->save();
            Bitacora::registrar($request->user(), 'proveedor_creado', null, self::ROLES, ['provee' => $proveedor->textoProvee()], tercero: $proveedor);

            return [$proveedor, false];
        });

        return redirect()->route('admin.proveedores.index')
            ->with('estado', __($yaExistia ? 'proveedores.mensajes.ya_existia' : 'proveedores.mensajes.agregado', ['nombre' => $proveedor->nombreCompleto()]));
    }

    public function edit(Request $request, Tercero $proveedor): View
    {
        $this->soloProveedorVigente($proveedor);

        return view('admin.proveedores.editar', [
            'proveedor' => $proveedor,
            'opcionesProvee' => Tercero::opcionesProvee(),
        ]);
    }

    public function update(Request $request, Tercero $proveedor): RedirectResponse
    {
        $this->soloProveedorVigente($proveedor);
        $datos = $this->validar($request, $proveedor);

        DB::transaction(function () use ($datos, $request, $proveedor) {
            // Si también es jornalero, su nombre se cambia en Jornaleros (allí tiene nombres y apellidos)
            if (! $proveedor->es_jornalero) {
                $proveedor->nombres = $datos['nombre'];
            }
            $proveedor->telefono = $datos['telefono'] ?? null;
            $proveedor->asignarProvee($datos['provee']);
            if (filled($datos['documento'] ?? null)) {
                $proveedor->asignarDocumento($datos['documento']);
            } elseif ($request->boolean('quitar_documento')) {
                $proveedor->asignarDocumento(null);
            }

            $cambios = collect(array_keys($proveedor->getDirty()))
                ->map(fn (string $campo) => in_array($campo, ['documento_huella', 'es_proveedor'], true) ? null : __('proveedores.campos.'.$campo))
                ->filter()->values();
            if ($cambios->isEmpty()) {
                return;
            }
            $proveedor->save();

            Bitacora::registrar($request->user(), 'proveedor_editado', null, self::ROLES, ['campos' => $cambios->implode(', ')], tercero: $proveedor);
        });

        return redirect()->route('admin.proveedores.index')->with('estado', __('proveedores.mensajes.guardado', ['nombre' => $proveedor->nombreCompleto()]));
    }

    /** Activo es uno solo por tercero: si también es jornalero, queda activo o inactivo en los dos. */
    public function cambiarActivo(Request $request, Tercero $proveedor): RedirectResponse
    {
        $this->soloProveedorVigente($proveedor);

        DB::transaction(function () use ($request, $proveedor) {
            $proveedor->activo = ! $proveedor->activo;
            $proveedor->save();
            Bitacora::registrar($request->user(), $proveedor->activo ? 'proveedor_activado' : 'proveedor_inactivo', null, self::ROLES, [], tercero: $proveedor);
        });

        return back()->with('estado', __($proveedor->activo ? 'proveedores.mensajes.activado' : 'proveedores.mensajes.desactivado', ['nombre' => $proveedor->nombreCompleto()]));
    }

    /** Proveedores escritos a mano en siembras viejas, aún sin ligar al catálogo. */
    public function revision(Request $request): View
    {
        return view('admin.proveedores.revision', [
            'textos' => self::textosSinLigar(),
            'proveedores' => Tercero::query()->queProveen(Tercero::PROVEE_ALEVINOS)->activos()->orderBy('nombres')->get()
                ->mapWithKeys(fn (Tercero $proveedor) => [$proveedor->id => $proveedor->nombreCompleto()])->all(),
            'puedeEditar' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
        ]);
    }

    /**
     * Liga todas las siembras sin proveedor del catálogo cuyo texto (sin espacios de más) es exactamente el elegido.
     * Con «crear», agrega un proveedor de alevinos con ese nombre y liga a él. El texto escrito se conserva.
     */
    public function ligar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'texto' => ['required', 'string', 'max:255'],
            'proveedor_id' => ['required_without:crear', 'nullable', 'integer'],
            'crear' => ['nullable', 'boolean'],
        ], [
            'proveedor_id.required_without' => __('proveedores.revision.elige_proveedor'),
        ]);
        $texto = self::normalizar($datos['texto']);

        $resultado = DB::transaction(function () use ($datos, $texto, $request) {
            if ($request->boolean('crear')) {
                $proveedor = new Tercero(['nombres' => $texto]);
                $proveedor->asignarProvee([Tercero::PROVEE_ALEVINOS]);
                $proveedor->save();
                Bitacora::registrar($request->user(), 'proveedor_creado', null, self::ROLES, ['provee' => $proveedor->textoProvee()], tercero: $proveedor);
            } else {
                $proveedor = Tercero::query()->queProveen(Tercero::PROVEE_ALEVINOS)->activos()->whereKey($datos['proveedor_id'])->first();
                if ($proveedor === null) {
                    throw ValidationException::withMessages(['proveedor_id' => __('proveedores.revision.elige_proveedor')]);
                }
            }

            $ids = Siembra::query()->whereNull('proveedor_id')
                ->whereRaw("regexp_replace(btrim(proveedor_alevinos), '\\s+', ' ', 'g') = ?", [$texto])
                ->lockForUpdate()->pluck('id');
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['texto' => __('proveedores.revision.ya_ligado')]);
            }
            Siembra::query()->whereKey($ids)->update(['proveedor_id' => $proveedor->id]);

            Bitacora::registrar($request->user(), 'proveedor_ligado', null, self::ROLES, ['texto' => $texto, 'siembras' => $ids->count()], tercero: $proveedor);

            return ['proveedor' => $proveedor, 'siembras' => $ids->count()];
        });

        return redirect()->route('admin.proveedores.revision')->with('estado', __('proveedores.revision.ligado', [
            'texto' => $texto, 'proveedor' => $resultado['proveedor']->nombreCompleto(), 'siembras' => $resultado['siembras'],
        ]));
    }

    /**
     * Textos distintos de proveedor escritos a mano en siembras sin proveedor del catálogo, con cuántas siembras lo usan.
     * Se agrupan solo los textos idénticos sin espacios de más (nunca por parecido).
     *
     * @return \Illuminate\Support\Collection<int, object{texto: string, siembras: int}>
     */
    private static function textosSinLigar(): \Illuminate\Support\Collection
    {
        return Siembra::query()->whereNull('proveedor_id')
            ->whereRaw("btrim(coalesce(proveedor_alevinos, '')) <> ''")
            ->selectRaw("regexp_replace(btrim(proveedor_alevinos), '\\s+', ' ', 'g') AS texto, COUNT(*) AS siembras")
            ->groupByRaw("regexp_replace(btrim(proveedor_alevinos), '\\s+', ' ', 'g')")
            ->orderBy('texto')
            ->toBase()->get()
            ->map(fn ($fila) => (object) ['texto' => (string) $fila->texto, 'siembras' => (int) $fila->siembras]);
    }

    private static function normalizar(string $texto): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($texto));
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Tercero $actual = null): array
    {
        $datos = $request->validate([
            'nombre' => [$actual?->es_jornalero ? 'nullable' : 'required', 'string', 'max:120'],
            'documento' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-\s]+$/'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'provee' => ['required', 'array', 'min:1'],
            'provee.*' => ['string', Rule::in(Tercero::PROVEE)],
        ], [
            'documento.regex' => __('jornaleros.validacion.documento'),
            'telefono.regex' => __('jornaleros.validacion.telefono'),
            'provee.required' => __('proveedores.validacion.provee'),
            'provee.min' => __('proveedores.validacion.provee'),
        ]);

        // Al editar, el documento no puede ser el de otro tercero (al crear, se reutiliza ese tercero: ver store)
        $huella = Tercero::huella($datos['documento'] ?? null);
        if ($actual !== null && $huella !== null && Tercero::query()->where('documento_huella', $huella)->whereKeyNot($actual->id)->exists()) {
            throw ValidationException::withMessages(['documento' => __('jornaleros.validacion.documento_repetido')]);
        }

        // El nombre de una empresa se guarda como se escribió (sin cambiar mayúsculas), solo sin espacios de más
        $datos['nombre'] = filled($datos['nombre'] ?? null) ? self::normalizar($datos['nombre']) : null;

        return $datos;
    }

    private function soloProveedorVigente(Tercero $tercero): void
    {
        abort_unless($tercero->es_proveedor && ! $tercero->anonimizado(), 404);
    }
}
