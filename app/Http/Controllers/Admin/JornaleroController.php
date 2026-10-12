<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Tercero;
use App\Support\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Jornaleros: trabajadores ocasionales sin cuenta en la app (tabla terceros). No son personal ni tienen rol.
 * - Propietario y administrador: lista, agregar, editar, activar y desactivar.
 * - El administrador puede escribir el documento pero solo ve los últimos 4 dígitos; el propietario lo ve completo.
 * - Anonimizar (borrar los datos personales, sin deshacer): solo el propietario, y no con saldo por cobrar.
 * Cada cambio queda en la bitácora con el id del jornalero (nunca su nombre ni su documento).
 */
class JornaleroController extends Controller
{
    private const ROLES = [Rol::PROPIETARIO, Rol::ADMINISTRADOR];

    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = $request->query('estado') === 'todos' ? 'todos' : 'activos';
        $huella = Tercero::huella($buscar);

        $jornaleros = Tercero::query()->jornaleros()
            ->when($estado === 'activos', fn ($consulta) => $consulta->activos())
            ->when($buscar !== '', fn ($consulta) => $consulta->where(fn ($o) => $o
                ->whereRaw("(nombres || ' ' || coalesce(apellidos, '')) ILIKE ?", ['%'.addcslashes($buscar, '%_\\').'%'])
                // Por documento: solo coincidencia exacta, comparando huellas (nunca el documento en claro)
                ->orWhere('documento_huella', $huella)))
            ->orderBy('nombres')->orderBy('apellidos')
            ->paginate(30)->withQueryString();

        return view('admin.jornaleros.index', [
            'jornaleros' => $jornaleros,
            'buscar' => $buscar,
            'estado' => $estado,
            'categorias' => Tercero::categorias(),
            'veDocumento' => $this->veDocumentoCompleto($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $jornalero = DB::transaction(function () use ($datos, $request) {
            $jornalero = new Tercero([
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'] ?? null,
                'telefono' => $datos['telefono'] ?? null,
                'categoria_habitual' => $datos['categoria_habitual'] ?? null,
                'es_jornalero' => true,
            ]);
            $jornalero->asignarDocumento($datos['documento'] ?? null);
            $jornalero->save();

            Bitacora::registrar($request->user(), 'jornalero_creado', null, self::ROLES, [
                'categoria' => $jornalero->categoria_habitual ? __('jornaleros.categorias.'.$jornalero->categoria_habitual) : __('jornaleros.sin_categoria'),
            ], tercero: $jornalero);

            return $jornalero;
        });

        return redirect()->route('admin.jornaleros.index')->with('estado', __('jornaleros.mensajes.agregado', ['nombre' => $jornalero->nombreCompleto()]));
    }

    public function edit(Request $request, Tercero $jornalero): View
    {
        $this->soloJornaleroVigente($jornalero);

        return view('admin.jornaleros.editar', [
            'jornalero' => $jornalero,
            'categorias' => Tercero::categorias(),
            'veDocumento' => $this->veDocumentoCompleto($request),
            'puedeAnonimizar' => $request->user()->tieneRol(Rol::PROPIETARIO),
            'saldo' => $jornalero->saldoPorCobrar(),
        ]);
    }

    public function update(Request $request, Tercero $jornalero): RedirectResponse
    {
        $this->soloJornaleroVigente($jornalero);
        $datos = $this->validar($request, $jornalero);

        DB::transaction(function () use ($datos, $request, $jornalero) {
            $jornalero->fill([
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'] ?? null,
                'telefono' => $datos['telefono'] ?? null,
                'categoria_habitual' => $datos['categoria_habitual'] ?? null,
            ]);
            // El documento solo cambia si se escribe uno nuevo o se pide quitarlo (el administrador no lo lee)
            if (filled($datos['documento'] ?? null)) {
                $jornalero->asignarDocumento($datos['documento']);
            } elseif ($request->boolean('quitar_documento')) {
                $jornalero->asignarDocumento(null);
            }

            // Qué campos cambiaron (sin sus valores: el documento nunca va a la bitácora)
            $cambios = collect(array_keys($jornalero->getDirty()))
                ->map(fn (string $campo) => $campo === 'documento_huella' ? null : __('jornaleros.campos.'.$campo))
                ->filter()->values();
            if ($cambios->isEmpty()) {
                return;
            }
            $jornalero->save();

            Bitacora::registrar($request->user(), 'jornalero_editado', null, self::ROLES, ['campos' => $cambios->implode(', ')], tercero: $jornalero);
        });

        return redirect()->route('admin.jornaleros.index')->with('estado', __('jornaleros.mensajes.guardado', ['nombre' => $jornalero->nombreCompleto()]));
    }

    public function cambiarActivo(Request $request, Tercero $jornalero): RedirectResponse
    {
        $this->soloJornaleroVigente($jornalero);

        DB::transaction(function () use ($request, $jornalero) {
            $jornalero->activo = ! $jornalero->activo;
            $jornalero->save();
            Bitacora::registrar($request->user(), $jornalero->activo ? 'jornalero_activado' : 'jornalero_inactivo', null, self::ROLES, [], tercero: $jornalero);
        });

        return back()->with('estado', __($jornalero->activo ? 'jornaleros.mensajes.activado' : 'jornaleros.mensajes.desactivado', ['nombre' => $jornalero->nombreCompleto()]));
    }

    /** Solo el propietario (ruta con rol:propietario). Irreversible: se pide escribir la palabra de confirmación. */
    public function anonimizar(Request $request, Tercero $jornalero): RedirectResponse
    {
        $this->soloJornaleroVigente($jornalero);
        $request->validate(['confirmar' => ['required', 'string', Rule::in([__('jornaleros.anonimizar.palabra')])]], [
            'confirmar.required' => __('jornaleros.anonimizar.escribe_palabra', ['palabra' => __('jornaleros.anonimizar.palabra')]),
            'confirmar.in' => __('jornaleros.anonimizar.escribe_palabra', ['palabra' => __('jornaleros.anonimizar.palabra')]),
        ]);

        DB::transaction(function () use ($request, $jornalero) {
            $bloqueado = Tercero::whereKey($jornalero->id)->lockForUpdate()->first();
            // Con saldo por cobrar no: hay que saldarlo antes (la deuda debe poder identificarse)
            $saldo = $bloqueado->saldoPorCobrar();
            if ($saldo > 0) {
                throw ValidationException::withMessages(['confirmar' => __('jornaleros.anonimizar.con_saldo', ['saldo' => Formato::dinero($saldo)])]);
            }
            $bloqueado->anonimizar();
            Bitacora::registrar($request->user(), 'jornalero_anonimo', null, [Rol::PROPIETARIO], [], tercero: $bloqueado);
        });

        return redirect()->route('admin.jornaleros.index')->with('estado', __('jornaleros.mensajes.anonimizado'));
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Tercero $actual = null): array
    {
        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['nullable', 'string', 'max:120'],
            'documento' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-\s]+$/'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'categoria_habitual' => ['nullable', Rule::in(Tercero::CATEGORIAS)],
        ], [
            'documento.regex' => __('jornaleros.validacion.documento'),
            'telefono.regex' => __('jornaleros.validacion.telefono'),
        ]);

        // El mismo documento no se repite en la finca (se compara la huella, no el documento)
        $huella = Tercero::huella($datos['documento'] ?? null);
        if ($huella !== null && Tercero::query()->where('documento_huella', $huella)->when($actual, fn ($q) => $q->whereKeyNot($actual->id))->exists()) {
            throw ValidationException::withMessages(['documento' => __('jornaleros.validacion.documento_repetido')]);
        }

        // Mayúscula inicial en cada palabra, solo al guardar (lo ya guardado no se toca hasta que se edite)
        $datos['nombres'] = Tercero::formatearNombre($datos['nombres']);
        $datos['apellidos'] = Tercero::formatearNombre($datos['apellidos'] ?? null);

        return $datos;
    }

    private function veDocumentoCompleto(Request $request): bool
    {
        return $request->user()->tieneRol(Rol::PROPIETARIO);
    }

    /** Solo jornaleros (no clientes ni proveedores) y que no estén anonimizados. */
    private function soloJornaleroVigente(Tercero $tercero): void
    {
        abort_unless($tercero->es_jornalero && ! $tercero->anonimizado(), 404);
    }
}
