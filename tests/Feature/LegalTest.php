<?php

namespace Tests\Feature;

use App\Models\AceptacionLegal;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Términos de uso y política de privacidad: páginas públicas en borrador, aceptación obligatoria en el registro
 * y en el primer ingreso tras cambiar la versión (quién, qué versión y cuándo; sin la IP).
 */
class LegalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
    }

    private function registro(array $datos = [])
    {
        return $this->post(route('registro'), $datos + [
            'codigo_finca' => 'F01', 'name' => 'Persona Nueva', 'email' => 'nueva@finca.test',
            'password' => 'Clave-Segura-123', 'password_confirmation' => 'Clave-Segura-123',
        ]);
    }

    public function test_las_paginas_son_publicas_en_borrador_y_sin_leyes_ni_paises(): void
    {
        foreach (['legal.terminos' => 'Términos de uso', 'legal.privacidad' => 'Política de privacidad'] as $ruta => $titulo) {
            $html = $this->get(route($ruta))->assertOk()
                ->assertSee($titulo)->assertSee(__('legal.borrador'))->assertSee(config('legal.version'))
                ->assertSee(__('legal.pendiente_entidad'))->assertSee(__('legal.pendiente_correo'))
                ->getContent();
            $this->assertDoesNotMatchRegularExpression('/\b(ley|leyes|decreto|resoluci[oó]n|superintendencia|ministerio|colombia|m[eé]xico|per[uú]|ecuador|chile|argentina|espa[nñ]a)\b/iu', strip_tags($html));
        }

        // Con LEGAL_ENTIDAD y LEGAL_CORREO configurados, aparecen en lugar del aviso
        config(['legal.entidad' => 'Piscícola Servicios', 'legal.correo' => 'contacto@servicio.test']);
        $this->get(route('legal.privacidad'))->assertSee('Piscícola Servicios')->assertSee('contacto@servicio.test')
            ->assertDontSee(__('legal.pendiente_correo'));
    }

    public function test_el_registro_exige_aceptar_y_guarda_quien_que_version_y_cuando_sin_la_ip(): void
    {
        $this->registro()->assertInvalid(['acepto' => __('legal.aceptar.marca_la_casilla')]);
        $this->assertSame(0, TenantActual::sinFiltro(fn () => User::where('email', 'nueva@finca.test')->count()));

        $this->travelTo(now()->setTimezone('UTC')->setTime(15, 30));
        $this->registro(['acepto' => '1'])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $usuario = TenantActual::sinFiltro(fn () => User::where('email', 'nueva@finca.test')->sole());
        $aceptacion = AceptacionLegal::query()->withoutGlobalScopes()->where('user_id', $usuario->id)->sole();
        $this->assertSame([config('legal.version'), $this->finca->id], [$aceptacion->version, $aceptacion->tenant_id]);
        $this->assertSame(now()->utc()->format('Y-m-d H:i'), $aceptacion->aceptado_at->utc()->format('Y-m-d H:i'));
        $this->assertFalse(Schema::hasColumn('aceptaciones_legales', 'ip'));
        $this->assertFalse(Schema::hasColumn('aceptaciones_legales', 'ip_address'));
    }

    public function test_sin_aceptar_no_sigue_hasta_aceptar_y_puede_cerrar_sesion(): void
    {
        $persona = User::factory()->sinAceptar()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->finca->id]);
        $this->assertFalse(AceptacionLegal::vigente($persona));

        $this->actingAs($persona)->get(route('inicio'))->assertRedirect(route('legal.aceptar'));
        $this->actingAs($persona)->get(route('mi-cuenta.index'))->assertRedirect(route('legal.aceptar'));
        $this->actingAs($persona)->get(route('legal.aceptar'))->assertOk()->assertSee(__('legal.aceptar.casilla'))
            ->assertSee(route('legal.terminos'))->assertSee(route('legal.privacidad'));
        // Las páginas legales siguen abiertas
        $this->actingAs($persona)->get(route('legal.terminos'))->assertOk();

        $this->actingAs($persona)->post(route('legal.aceptar.store'), [])->assertInvalid(['acepto']);
        $this->actingAs($persona)->post(route('legal.aceptar.store'), ['acepto' => '1'])->assertRedirect(route('inicio'));
        $this->assertTrue(AceptacionLegal::vigente($persona));
        $this->actingAs($persona)->get(route('inicio'))->assertOk();

        // Quien no acepta puede cerrar sesión
        $otra = User::factory()->sinAceptar()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->finca->id]);
        $this->actingAs($otra)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_una_version_nueva_obliga_a_aceptar_de_nuevo(): void
    {
        $persona = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]);
        $this->actingAs($persona)->get(route('inicio'))->assertOk();
        $vigente = config('legal.version');

        config(['legal.version' => '2027-01-15']);
        $this->actingAs($persona)->get(route('inicio'))->assertRedirect(route('legal.aceptar'));
        $this->actingAs($persona)->get(route('legal.aceptar'))->assertSee('2027-01-15');
        $this->actingAs($persona)->post(route('legal.aceptar.store'), ['acepto' => '1']);

        $this->assertSame([$vigente, '2027-01-15'], AceptacionLegal::query()->where('user_id', $persona->id)->orderBy('version')->pluck('version')->all());
        $this->actingAs($persona)->get(route('inicio'))->assertOk();
    }

    public function test_la_api_responde_terminos_pendientes_y_permite_aceptar(): void
    {
        $persona = User::factory()->sinAceptar()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->finca->id]);
        $token = $persona->createToken('celular')->plainTextToken;

        $this->withToken($token)->getJson('/api/yo')->assertForbidden()->assertJson(['terminos_pendientes' => true, 'version' => config('legal.version')]);
        $this->withToken($token)->postJson('/api/terminos/aceptar', ['acepto' => true, 'version' => '1999-01-01'])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/terminos/aceptar', ['acepto' => true, 'version' => config('legal.version')])->assertOk();
        $this->withToken($token)->getJson('/api/yo')->assertOk();
    }

    public function test_los_enlaces_estan_en_el_login_el_registro_y_el_pie(): void
    {
        foreach ([route('login'), route('registro')] as $url) {
            $this->get($url)->assertOk()->assertSee(route('legal.terminos'))->assertSee(route('legal.privacidad'));
        }
        $this->get(route('registro'))->assertSee('name="acepto"', false);

        $persona = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->finca->id]);
        $this->actingAs($persona)->get(route('inicio'))->assertSee(route('legal.terminos'))->assertSee(route('legal.privacidad'));
    }

    public function test_la_aceptacion_es_de_cada_persona_en_su_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $deOtra = User::factory()->create(['tenant_id' => $otra->id]);
        $deEsta = User::factory()->create(['tenant_id' => $this->finca->id]);

        // La finca activa solo ve las aceptaciones de su gente
        $this->assertSame([$deEsta->id], AceptacionLegal::query()->pluck('user_id')->all());
        $this->assertSame($otra->id, AceptacionLegal::query()->withoutGlobalScopes()->where('user_id', $deOtra->id)->value('tenant_id'));
    }
}
