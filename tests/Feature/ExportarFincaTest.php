<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\EspecieFoto;
use App\Models\Exportacion;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Exportar los datos de una finca: ZIP con CSV y fotos, solo de esa finca y sin nada sensible,
 * una vez por hora, solo para el propietario y con vencimiento a las 24 horas.
 */
class ExportarFincaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('fotos');
        Storage::fake('exportaciones');

        // Otra finca con datos que nunca deben salir
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $otra->id, 'email' => 'ajeno@otra.test']);
        Lago::create(['nombre' => 'LagoAjeno', 'tipo' => 'tierra']);

        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'NORTE']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id, 'email' => 'dueno@norte.test', 'password' => 'clave-segura-123']);
        $this->propietario->forceFill(['dos_pasos_clave' => 'CLAVESECRETAEXPORTAR', 'dos_pasos_activado_at' => now(), 'dos_pasos_recuperacion' => ['hash-secreto']])->save();
        $this->propietario->createToken('celular');
        Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Bitacora::registrar($this->propietario, 'roles', null, [Rol::PROPIETARIO], ['persona' => 'x', 'antes' => 'a', 'despues' => 'b']);

        Storage::disk('fotos')->put($this->finca->id.'/1/original-secreto.jpg', 'BYTES-DE-LA-FOTO');
        EspecieFoto::create(['especie_id' => Especie::deClave('mojarra roja')->id, 'user_id' => $this->propietario->id,
            'ruta_original' => $this->finca->id.'/1/original-secreto.jpg', 'ruta_ligera' => $this->finca->id.'/1/ligera.webp',
            'tipo_original' => 'image/jpeg', 'bytes_original' => 16, 'bytes_ligera' => 5, 'ancho' => 1, 'alto' => 1, 'orden' => 1]);
    }

    /** @return array<string, string> nombre del archivo => contenido */
    private function abrirZip(Exportacion $exportacion): array
    {
        $zip = new ZipArchive;
        $zip->open(Storage::disk('exportaciones')->path($exportacion->ruta));
        $archivos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $archivos[$zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }
        $zip->close();

        return $archivos;
    }

    public function test_el_paquete_trae_solo_los_datos_de_la_finca_y_nada_sensible(): void
    {
        $this->actingAs($this->propietario)->post(route('admin.datos.exportar'))->assertRedirect(route('admin.datos.index'));

        $exportacion = Exportacion::sole();
        $this->assertSame(Exportacion::LISTA, $exportacion->estado);
        $this->assertMatchesRegularExpression('#^'.$this->finca->id.'/[0-9a-f]{40}\.zip$#', $exportacion->ruta);
        $archivos = $this->abrirZip($exportacion);

        foreach (['LEEME.txt', 'finca.csv', 'lagos.csv', 'users.csv', 'bitacora.csv', 'especies.csv', 'especie_fotos.csv', 'fotos/especie-'.Especie::deClave('mojarra roja')->id.'-foto-1.jpg'] as $archivo) {
            $this->assertArrayHasKey($archivo, $archivos, $archivo);
        }
        $this->assertStringStartsWith("\xEF\xBB\xBF", $archivos['lagos.csv']);   // UTF-8 con BOM
        $this->assertStringContainsString('L01', $archivos['lagos.csv']);
        $this->assertStringContainsString('dueno@norte.test', $archivos['users.csv']);
        $this->assertSame('BYTES-DE-LA-FOTO', $archivos['fotos/especie-'.Especie::deClave('mojarra roja')->id.'-foto-1.jpg']);
        $this->assertArrayNotHasKey('exportaciones.csv', $archivos);

        $todo = implode("\n", $archivos);
        // Nada de otra finca
        $this->assertStringNotContainsString('LagoAjeno', $todo);
        $this->assertStringNotContainsString('ajeno@otra.test', $todo);
        // Nada sensible: contraseña, recordarme, dos pasos, tokens ni rutas internas
        $usuario = $this->propietario->fresh();
        foreach (['password', 'remember_token', 'dos_pasos_clave', 'dos_pasos_recuperacion', 'ruta_original', 'ruta_ligera', 'original-secreto', 'hash-secreto', $usuario->getAuthPassword(), (string) $usuario->remember_token] as $sensible) {
            $this->assertStringNotContainsString($sensible, $todo, $sensible);
        }
        $this->assertArrayNotHasKey('personal_access_tokens.csv', $archivos);
        $this->assertArrayNotHasKey('sessions.csv', $archivos);

        // Queda en la bitácora
        $this->assertSame(1, Bitacora::where('accion', 'exportacion')->count());
    }

    public function test_una_exportacion_por_hora(): void
    {
        $this->actingAs($this->propietario)->post(route('admin.datos.exportar'))->assertSessionHasNoErrors();
        $this->actingAs($this->propietario)->post(route('admin.datos.exportar'))->assertSessionHasErrors(['exportar' => __('datos.exportar.espera', ['minutos' => 60])]);
        $this->assertSame(1, Exportacion::count());

        $this->travel(61)->minutes();
        $this->actingAs($this->propietario)->post(route('admin.datos.exportar'))->assertSessionHasNoErrors();
        $this->assertSame(2, Exportacion::count());
    }

    public function test_solo_el_propietario_la_pide_y_la_descarga_y_vence_a_las_24_horas(): void
    {
        $administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
        $this->actingAs($administrador)->post(route('admin.datos.exportar'))->assertForbidden();
        $this->actingAs($administrador)->get(route('admin.datos.index'))->assertForbidden();

        $this->actingAs($this->propietario)->post(route('admin.datos.exportar'));
        $exportacion = Exportacion::sole();
        $this->actingAs($this->propietario)->get(route('admin.datos.index'))->assertOk()->assertSee(route('admin.datos.descargar', $exportacion));

        $descarga = $this->actingAs($this->propietario)->get(route('admin.datos.descargar', $exportacion))->assertOk();
        $this->assertSame('application/zip', $descarga->headers->get('Content-Type'));
        $this->assertStringContainsString('datos-finca-NORTE-', $descarga->headers->get('Content-Disposition'));
        $this->actingAs($administrador)->get(route('admin.datos.descargar', $exportacion))->assertForbidden();

        // Otra finca: 404
        $otraFinca = Tenant::where('codigo', 'F02')->sole();
        app(TenantActual::class)->establecer($otraFinca->id);
        $ajeno = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $otraFinca->id]);
        $this->actingAs($ajeno)->get(route('admin.datos.descargar', $exportacion))->assertNotFound();
        app(TenantActual::class)->establecer($this->finca->id);

        // Vencida: ya no se descarga y el comando la borra (archivo y registro)
        $this->travel(25)->hours();
        $this->actingAs($this->propietario)->get(route('admin.datos.descargar', $exportacion))->assertStatus(410);
        $this->artisan('piscicola:limpiar-exportaciones')->assertSuccessful();
        $this->assertNull(Exportacion::find($exportacion->id));
        Storage::disk('exportaciones')->assertMissing($exportacion->ruta);
    }
}
