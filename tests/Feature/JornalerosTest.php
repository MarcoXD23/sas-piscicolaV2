<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Exportacion;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tercero;
use App\Models\User;
use App\Models\Venta;
use App\Support\Asistente\ContextoFinca;
use App\Support\Formato;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Jornaleros (tabla terceros, Fase A): personas sin cuenta en la app. Documento cifrado con huella HMAC, enmascarado
 * para el administrador, anonimización solo del propietario y sin saldo, ventas a crédito a un jornalero, bitácora con
 * solo el id, exportación y asistente. Datos ficticios.
 */
class JornalerosTest extends TestCase
{
    use RefreshDatabase;

    private const DOCUMENTO = '1.234.567.890';

    private const DOCUMENTO_LIMPIO = '1234567890';

    private const TELEFONO = '300 000 0000';

    private Tenant $finca;

    private User $propietario;

    private User $administrador;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-02 08:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-06-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 400, 'biomasa_kg' => 400]);
    }

    /** Agrega un jornalero como lo hace el administrador desde la pantalla. */
    private function agregar(array $datos = [], ?User $quien = null)
    {
        return $this->actingAs($quien ?? $this->administrador)->post(route('admin.jornaleros.store'), $datos + [
            'nombres' => 'Ana', 'apellidos' => 'Prueba Ficticia', 'documento' => self::DOCUMENTO, 'telefono' => self::TELEFONO, 'categoria_habitual' => 'procesador',
        ]);
    }

    /** GET con la sesión limpia: el aviso de la acción anterior (que nombra al jornalero) no cuenta. */
    private function ver(User $quien, string $url)
    {
        $this->flushSession();

        return $this->actingAs($quien)->get($url);
    }

    private function jornalero(): Tercero
    {
        return Tercero::query()->jornaleros()->latest('id')->firstOrFail();
    }

    private function cosecha(): Cosecha
    {
        return Cosecha::create(['siembra_id' => $this->siembra->id, 'lago_id' => $this->lago->id, 'user_id' => $this->administrador->id,
            'fecha' => '2026-11-01', 'peces' => 100, 'kg' => 50, 'peso_promedio_g' => 500]);
    }

    private function vender(array $datos)
    {
        return $this->actingAs($this->administrador)->post(route('admin.ventas.store'), $datos + [
            'cosecha_id' => $this->cosecha()->id, 'fecha' => '2026-11-02', 'kg' => 5, 'precio_kg' => 10000, 'forma_pago' => 'credito',
        ]);
    }

    public function test_solo_propietario_y_administrador_entran(): void
    {
        $this->actingAs($this->propietario)->get(route('admin.jornaleros.index'))->assertOk()->assertSee(__('jornaleros.titulo'));
        $this->actingAs($this->administrador)->get(route('admin.jornaleros.index'))->assertOk();
        foreach ([Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = User::factory()->conRoles($rol)->create(['tenant_id' => $this->finca->id]);
            $this->actingAs($persona)->get(route('admin.jornaleros.index'))->assertForbidden();
            $this->agregar([], $persona)->assertForbidden();
        }
        $this->assertSame(0, Tercero::count());
        // En el menú, para los dos
        $this->actingAs($this->administrador)->get(route('inicio'))->assertSee(route('admin.jornaleros.index'), false);
    }

    public function test_el_documento_se_guarda_cifrado_con_huella_hmac_y_nunca_en_texto_claro(): void
    {
        $this->agregar()->assertRedirect(route('admin.jornaleros.index'));

        $fila = DB::table('terceros')->sole();
        foreach ([self::DOCUMENTO, self::DOCUMENTO_LIMPIO, '7890'] as $texto) {
            $this->assertStringNotContainsString($texto, (string) $fila->documento, 'columna documento');
            $this->assertStringNotContainsString($texto, (string) $fila->documento_huella, 'columna huella');
        }
        $this->assertSame(self::DOCUMENTO_LIMPIO, Crypt::decryptString($fila->documento));
        // La huella es un HMAC con clave derivada de la app: no es el hash simple (que se adivina probando documentos)
        $this->assertSame(64, strlen($fila->documento_huella));
        $this->assertNotSame(hash('sha256', self::DOCUMENTO_LIMPIO), $fila->documento_huella);
        $this->assertSame(hash_hmac('sha256', self::DOCUMENTO_LIMPIO, hash_hmac('sha256', 'terceros.documento_huella', config('app.key'), true)), $fila->documento_huella);
        $this->assertTrue((bool) $fila->es_jornalero);
        // Ni en JSON ni en arreglos
        $this->assertArrayNotHasKey('documento', $this->jornalero()->toArray());
        $this->assertArrayNotHasKey('documento_huella', $this->jornalero()->toArray());
    }

    public function test_el_administrador_ve_solo_los_ultimos_4_digitos_y_el_propietario_el_completo(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();

        foreach ([route('admin.jornaleros.index'), route('admin.jornaleros.edit', $jornalero)] as $url) {
            $this->actingAs($this->administrador)->get($url)->assertOk()
                ->assertSee('•••• 7890')->assertDontSee(self::DOCUMENTO_LIMPIO)->assertDontSee(self::DOCUMENTO)->assertSee(self::TELEFONO);
            $this->actingAs($this->propietario)->get($url)->assertOk()->assertSee(self::DOCUMENTO_LIMPIO);
        }
        // El campo del formulario nunca trae el documento (solo se escribe)
        $this->actingAs($this->propietario)->get(route('admin.jornaleros.edit', $jornalero))->assertDontSee('value="'.self::DOCUMENTO_LIMPIO.'"', false);
    }

    public function test_el_documento_no_se_repite_en_la_finca_pero_si_entre_fincas(): void
    {
        $this->agregar();
        // El mismo documento escrito de otra forma
        $this->agregar(['nombres' => 'Otra', 'documento' => '1234-567 890'])->assertSessionHasErrors(['documento' => __('jornaleros.validacion.documento_repetido')]);
        $this->assertSame(1, Tercero::count());

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $adminOtra = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $otra->id]);
        $this->agregar([], $adminOtra)->assertSessionHasNoErrors();
        $this->assertSame(1, Tercero::count());
    }

    public function test_aislamiento_por_finca(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $adminOtra = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $otra->id]);
        $duenoOtra = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $otra->id]);

        $this->ver($adminOtra, route('admin.jornaleros.index'))->assertOk()->assertDontSee('Prueba Ficticia');
        $this->actingAs($adminOtra)->get(route('admin.jornaleros.edit', $jornalero))->assertNotFound();
        $this->actingAs($adminOtra)->put(route('admin.jornaleros.update', $jornalero), ['nombres' => 'X'])->assertNotFound();
        $this->actingAs($adminOtra)->post(route('admin.jornaleros.activo', $jornalero))->assertNotFound();
        $this->actingAs($duenoOtra)->post(route('admin.jornaleros.anonimizar', $jornalero), ['confirmar' => 'ANONIMIZAR'])->assertNotFound();
        // Buscar su documento desde otra finca no lo encuentra
        $this->ver($duenoOtra, route('admin.jornaleros.index', ['buscar' => self::DOCUMENTO]))->assertDontSee('Prueba Ficticia');

        app(TenantActual::class)->establecer($this->finca->id);
        $this->assertSame('Ana', $jornalero->fresh()->nombres);
    }

    public function test_buscar_por_nombre_o_por_documento_exacto(): void
    {
        $this->agregar();
        $this->agregar(['nombres' => 'Beto', 'apellidos' => 'Ejemplo', 'documento' => '555']);

        $this->ver($this->propietario, route('admin.jornaleros.index', ['buscar' => 'ficticia']))->assertSee('Ana Prueba Ficticia')->assertDontSee('Beto Ejemplo');
        $this->ver($this->administrador, route('admin.jornaleros.index', ['buscar' => '1234567890']))->assertSee('Ana Prueba Ficticia')->assertDontSee('Beto Ejemplo');
        // Un pedazo del documento no sirve: no se puede adivinar por partes
        $this->ver($this->administrador, route('admin.jornaleros.index', ['buscar' => '7890']))->assertDontSee('Ana Prueba Ficticia');
    }

    public function test_editar_cambia_el_documento_solo_si_se_escribe_y_la_bitacora_no_guarda_valores(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();
        $huella = $jornalero->documento_huella;

        // Sin documento en el formulario: queda el que estaba
        $this->actingAs($this->administrador)->put(route('admin.jornaleros.update', $jornalero), ['nombres' => 'Ana María', 'apellidos' => 'Prueba Ficticia', 'telefono' => self::TELEFONO])
            ->assertRedirect(route('admin.jornaleros.index'));
        $this->assertSame($huella, $jornalero->fresh()->documento_huella);
        // Reemplazarlo y quitarlo
        $this->actingAs($this->administrador)->put(route('admin.jornaleros.update', $jornalero), ['nombres' => 'Ana María', 'documento' => '99887766']);
        $this->assertSame('99887766', $jornalero->fresh()->documento);
        $this->actingAs($this->propietario)->put(route('admin.jornaleros.update', $jornalero), ['nombres' => 'Ana María', 'quitar_documento' => '1']);
        $this->assertNull($jornalero->fresh()->documento);
        $this->assertNull($jornalero->fresh()->documento_huella);

        $ediciones = Bitacora::where('accion', 'jornalero_editado')->orderBy('id')->get();
        $this->assertCount(3, $ediciones);
        $this->assertSame($jornalero->id, $ediciones[0]->tercero_id);
        $this->assertStringContainsString(__('jornaleros.campos.nombres'), $ediciones[0]->textoDetalle());
        $this->assertStringContainsString(__('jornaleros.campos.documento'), $ediciones[1]->textoDetalle());
        foreach (Bitacora::all() as $registro) {
            $crudo = json_encode($registro->detalle);
            foreach ([self::DOCUMENTO_LIMPIO, '99887766', '7766', 'Ana', 'Prueba'] as $dato) {
                $this->assertStringNotContainsString($dato, $crudo, 'la bitácora guarda solo el id');
            }
        }
    }

    public function test_desactivar_y_activar(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();

        $this->actingAs($this->administrador)->post(route('admin.jornaleros.activo', $jornalero))->assertRedirect();
        $this->assertFalse($jornalero->fresh()->activo);
        $this->ver($this->administrador, route('admin.jornaleros.index'))->assertDontSee('Ana Prueba Ficticia');
        $this->actingAs($this->administrador)->get(route('admin.jornaleros.index', ['estado' => 'todos']))->assertSee('Ana Prueba Ficticia');
        $this->actingAs($this->propietario)->post(route('admin.jornaleros.activo', $jornalero));
        $this->assertTrue($jornalero->fresh()->activo);
        $this->assertSame(['jornalero_creado', 'jornalero_inactivo', 'jornalero_activado'], Bitacora::orderBy('id')->pluck('accion')->all());
    }

    public function test_solo_el_propietario_anonimiza_y_cubre_lista_ventas_y_bitacora(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();
        $this->vender(['tercero_id' => $jornalero->id, 'forma_pago' => 'contado'])->assertSessionHasNoErrors();

        $this->actingAs($this->administrador)->post(route('admin.jornaleros.anonimizar', $jornalero), ['confirmar' => 'ANONIMIZAR'])->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.jornaleros.anonimizar', $jornalero), ['confirmar' => 'si'])->assertSessionHasErrors('confirmar');
        $this->actingAs($this->propietario)->post(route('admin.jornaleros.anonimizar', $jornalero), ['confirmar' => 'ANONIMIZAR'])->assertRedirect(route('admin.jornaleros.index'));

        $anonimo = __('jornaleros.anonimo', ['id' => $jornalero->id]);
        $fila = DB::table('terceros')->sole();
        $this->assertNull($fila->documento);
        $this->assertNull($fila->documento_huella);
        $this->assertNull($fila->telefono);
        $this->assertNull($fila->apellidos);
        $this->assertNotNull($fila->anonimizado_at);
        $this->assertSame($anonimo, $jornalero->fresh()->nombreCompleto());
        $this->assertSame($anonimo, DB::table('ventas')->value('cliente'));

        // En ninguna pantalla queda el nombre: ni la lista, ni las ventas, ni la bitácora (que resuelve el id al mostrarse)
        foreach ([route('admin.jornaleros.index', ['estado' => 'todos']), route('admin.ventas.index'), route('admin.bitacora.index')] as $url) {
            $this->ver($this->propietario, $url)->assertOk()->assertDontSee('Prueba Ficticia')->assertDontSee(self::TELEFONO);
        }
        $this->actingAs($this->propietario)->get(route('admin.bitacora.index'))->assertSee($anonimo);
        $this->assertSame(['jornalero_creado', 'venta', 'jornalero_anonimo'], Bitacora::orderBy('id')->pluck('accion')->all());
        // Ya no se puede editar ni elegir en una venta
        $this->actingAs($this->propietario)->get(route('admin.jornaleros.edit', $jornalero))->assertNotFound();
        $this->vender(['tercero_id' => $jornalero->id])->assertSessionHasErrors('tercero_id');
    }

    public function test_no_se_anonimiza_con_saldo_por_cobrar(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();
        $this->vender(['tercero_id' => $jornalero->id])->assertSessionHasNoErrors();   // 5 kg × 10.000 = 50.000 a crédito
        $venta = Venta::sole();

        $this->actingAs($this->propietario)->post(route('admin.jornaleros.anonimizar', $jornalero), ['confirmar' => 'ANONIMIZAR'])
            ->assertSessionHasErrors(['confirmar' => __('jornaleros.anonimizar.con_saldo', ['saldo' => Formato::dinero(50000)])]);
        $this->assertNull($jornalero->fresh()->anonimizado_at);
        $this->actingAs($this->propietario)->get(route('admin.jornaleros.edit', $jornalero))->assertSee(__('jornaleros.saldo_por_cobrar', ['saldo' => Formato::dinero(50000)]));

        // Saldado, sí
        $this->actingAs($this->administrador)->post(route('admin.ventas.abonar', $venta), ['fecha' => '2026-11-02', 'valor' => 50000, 'medio' => 'efectivo'])->assertSessionHasNoErrors();
        $this->actingAs($this->propietario)->post(route('admin.jornaleros.anonimizar', $jornalero), ['confirmar' => 'ANONIMIZAR'])->assertSessionHasNoErrors();
        $this->assertNotNull($jornalero->fresh()->anonimizado_at);
    }

    public function test_venta_a_credito_a_un_jornalero_con_su_id_en_la_bitacora(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();

        $this->vender(['tercero_id' => $jornalero->id])->assertSessionHasNoErrors();
        $venta = Venta::sole();
        $this->assertSame($jornalero->id, $venta->tercero_id);
        $this->assertNull($venta->comprador_id);
        $this->assertSame('Ana Prueba Ficticia', $venta->cliente);
        $this->assertSame(50000, $venta->saldo);
        $this->assertSame(50000, $jornalero->saldoPorCobrar());

        $registro = Bitacora::where('accion', 'venta')->sole();
        $this->assertSame($jornalero->id, $registro->tercero_id);
        $this->assertArrayNotHasKey('cliente', $registro->detalle);
        $this->assertStringContainsString('Ana Prueba Ficticia', $registro->textoDetalle());
        $this->actingAs($this->administrador)->get(route('admin.ventas.index'))->assertSee(__('ventas.jornalero'));
    }

    public function test_la_venta_no_acepta_dos_compradores_ni_un_jornalero_ajeno_o_inactivo(): void
    {
        $this->agregar();
        $jornalero = $this->jornalero();

        $this->vender(['tercero_id' => $jornalero->id, 'comprador_id' => $this->administrador->id])->assertSessionHasErrors('tercero_id');
        $this->actingAs($this->administrador)->post(route('admin.jornaleros.activo', $jornalero));
        $this->vender(['tercero_id' => $jornalero->id])->assertSessionHasErrors(['tercero_id' => __('mensajes.venta.ese_jornalero_no_esta_activo')]);

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $ajeno = Tercero::create(['nombres' => 'Ajeno', 'es_jornalero' => true]);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->vender(['tercero_id' => $ajeno->id])->assertSessionHasErrors('tercero_id');
        $this->assertSame(0, Venta::count());
    }

    public function test_el_check_de_ventas_admite_solo_el_nombre_y_rechaza_dos_compradores(): void
    {
        $cosecha = $this->cosecha();
        $base = ['tenant_id' => $this->finca->id, 'cosecha_id' => $cosecha->id, 'user_id' => $this->administrador->id, 'fecha' => '2026-11-02',
            'kg' => 1, 'precio_kg' => 1000, 'total' => 1000, 'forma_pago' => 'contado', 'saldo' => 0, 'created_at' => now(), 'updated_at' => now()];

        // Como las ventas que ya existían: solo el nombre escrito
        DB::table('ventas')->insert($base + ['cliente' => 'Cliente de mostrador']);
        $this->assertSame(1, Venta::count());

        $tercero = Tercero::create(['nombres' => 'Ana', 'es_jornalero' => true]);
        $this->expectException(QueryException::class);
        DB::table('ventas')->insert($base + ['cliente' => 'Dos', 'comprador_id' => $this->administrador->id, 'tercero_id' => $tercero->id]);
    }

    public function test_el_asistente_nunca_recibe_documento_ni_telefono(): void
    {
        $this->agregar();
        $this->vender(['tercero_id' => $this->jornalero()->id]);

        foreach ([$this->propietario, $this->administrador] as $persona) {
            $contexto = ContextoFinca::instrucciones()."\n".ContextoFinca::para($persona);
            foreach ([self::DOCUMENTO_LIMPIO, self::DOCUMENTO, '7890', self::TELEFONO] as $dato) {
                $this->assertStringNotContainsString($dato, $contexto);
            }
        }
    }

    public function test_el_administrador_no_exporta_y_sus_reportes_no_traen_el_documento(): void
    {
        $this->agregar();
        $this->vender(['tercero_id' => $this->jornalero()->id]);

        $this->actingAs($this->administrador)->post(route('admin.datos.exportar'))->assertForbidden();
        $this->actingAs($this->administrador)->get(route('admin.bitacora.index'))->assertForbidden();
        $paginas = [route('admin.ventas.index'), route('admin.reportes.mensual'), route('admin.reportes.libro-campo', ['lago_id' => $this->lago->id])];
        foreach ($paginas as $url) {
            $html = $this->ver($this->administrador, $url)->assertOk()->getContent();
            foreach ([self::DOCUMENTO_LIMPIO, self::DOCUMENTO, '•••• 7890'] as $dato) {
                $this->assertStringNotContainsString($dato, $html, $url);
            }
        }
        // Las descargas en Excel tampoco
        foreach ([route('admin.reportes.mensual.excel'), route('admin.reportes.libro-campo.excel', ['lago_id' => $this->lago->id])] as $url) {
            $archivo = $this->actingAs($this->administrador)->get($url)->assertOk()->baseResponse->getFile()->getPathname();
            $zip = new ZipArchive;
            $zip->open($archivo);
            $texto = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $texto .= (string) $zip->getFromIndex($i);
            }
            $zip->close();
            $this->assertStringNotContainsString(self::DOCUMENTO_LIMPIO, $texto, $url);
            $this->assertStringNotContainsString('7890', $texto, $url);
        }
    }

    public function test_la_exportacion_del_propietario_trae_el_documento_legible_y_sin_huella(): void
    {
        Storage::fake('exportaciones');
        $this->agregar();

        $this->actingAs($this->propietario)->post(route('admin.datos.exportar'))->assertRedirect(route('admin.datos.index'));
        $zip = new ZipArchive;
        $zip->open(Storage::disk('exportaciones')->path(Exportacion::sole()->ruta));
        $terceros = (string) $zip->getFromName('terceros.csv');
        $zip->close();

        $this->assertStringContainsString(self::DOCUMENTO_LIMPIO, $terceros);
        $this->assertStringContainsString('Ana', $terceros);
        $this->assertStringNotContainsString('documento_huella', $terceros);
        $this->assertStringNotContainsString((string) DB::table('terceros')->value('documento'), $terceros);
    }
}
