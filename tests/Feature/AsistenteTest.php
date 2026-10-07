<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\ClaudeAsistente;
use App\Support\Asistente\ClienteAsistente;
use App\Support\Asistente\ContextoFinca;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsistenteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    /** Doble del proveedor: guarda lo que recibe y responde sin llamar a la API. */
    private object $falso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->falso = new class implements ClienteAsistente
        {
            public array $llamadas = [];

            public ?string $error = null;

            public bool $configurado = true;

            public function configurado(): bool
            {
                return $this->configurado;
            }

            public function responder(string $instrucciones, string $contexto, array $mensajes): string
            {
                if ($this->error) {
                    throw new AsistenteNoDisponible($this->error);
                }
                $this->llamadas[] = compact('instrucciones', 'contexto', 'mensajes');

                return 'Respuesta '.count($this->llamadas);
            }
        };
        $this->app->instance(ClienteAsistente::class, $this->falso);

        $this->tenant = Tenant::create(['nombre' => 'Finca El Lago', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
        StockAlimento::create(['tipo_concentrado' => 'Engorde secreto', 'cantidad_kg' => 80]);
    }

    private function persona(string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([$rol]);
    }

    private function preguntar(User $usuario, string $mensaje = '¿Cuánto alimento doy hoy?')
    {
        return $this->actingAs($usuario)->postJson(route('asistente.enviar'), ['mensaje' => $mensaje]);
    }

    public function test_responde_y_guarda_la_conversacion_en_la_sesion(): void
    {
        $propietario = $this->persona(Rol::PROPIETARIO);

        $this->preguntar($propietario)->assertOk()->assertJson(['respuesta' => 'Respuesta 1']);
        $this->preguntar($propietario, '¿Y mañana?')->assertOk();

        $this->assertSame([
            ['role' => 'user', 'content' => '¿Cuánto alimento doy hoy?'],
            ['role' => 'assistant', 'content' => 'Respuesta 1'],
            ['role' => 'user', 'content' => '¿Y mañana?'],
        ], $this->falso->llamadas[1]['mensajes']);

        $this->actingAs($propietario)->getJson(route('asistente.historial'))
            ->assertOk()->assertJsonPath('configurado', true)->assertJsonCount(4, 'mensajes');

        $this->actingAs($propietario)->deleteJson(route('asistente.reiniciar'))->assertNoContent();
        $this->actingAs($propietario)->getJson(route('asistente.historial'))->assertJsonCount(0, 'mensajes');
    }

    public function test_el_contexto_respeta_lo_que_cada_rol_puede_ver(): void
    {
        $this->preguntar($this->persona(Rol::PROPIETARIO));
        $propietario = $this->falso->llamadas[0]['contexto'];
        $this->assertStringContainsString('Bodega de alimento', $propietario);
        $this->assertStringContainsString('Engorde secreto', $propietario);
        $this->assertStringContainsString('Ventas del mes', $propietario);
        $this->assertStringContainsString('ración sugerida', $propietario);

        $this->preguntar($this->persona(Rol::ADMINISTRADOR));
        $administrador = $this->falso->llamadas[1]['contexto'];
        $this->assertStringContainsString('Producción hoy', $administrador);
        // El administrador opera la bodega y las ventas, pero no ve lo exclusivo del propietario (personal y agenda)
        $this->assertStringContainsString('Engorde secreto', $administrador);
        $this->assertStringContainsString('Ventas del mes', $administrador);
        $this->assertStringNotContainsString('Solicitudes de personal', $administrador);

        $this->preguntar($this->persona(Rol::TRABAJADOR));
        $trabajador = $this->falso->llamadas[2]['contexto'];
        $this->assertStringContainsString('ración sugerida', $trabajador);
        $this->assertStringContainsString('Está fuera de turno', $trabajador);
        $this->assertStringNotContainsString('Engorde secreto', $trabajador);
        $this->assertStringNotContainsString('Ventas del mes', $trabajador);
        $this->assertStringNotContainsString('Producción hoy', $trabajador);

        $this->preguntar($this->persona(Rol::CELADOR));
        $celador = $this->falso->llamadas[3]['contexto'];
        $this->assertStringContainsString('aireador apagado', $celador);
        $this->assertStringContainsString('Seguridad: sin novedades', $celador);
        $this->assertStringNotContainsString('ración sugerida', $celador);
        $this->assertStringNotContainsString('kg/día', $celador);
        $this->assertStringNotContainsString('Engorde secreto', $celador);
    }

    public function test_el_contexto_no_incluye_datos_de_otra_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra finca', 'codigo' => 'F02']);
        Lago::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'nombre' => 'LAGO-AJENO', 'tipo' => 'tierra']);

        $contexto = ContextoFinca::para($this->persona(Rol::PROPIETARIO));

        $this->assertStringContainsString('Finca El Lago', $contexto);
        $this->assertStringNotContainsString('LAGO-AJENO', $contexto);
    }

    public function test_la_conversacion_enviada_se_limita_y_empieza_por_el_usuario(): void
    {
        $usuario = $this->persona(Rol::TRABAJADOR);
        for ($i = 1; $i <= 8; $i++) {
            $this->preguntar($usuario, "Pregunta {$i}")->assertOk();
        }

        $ultima = end($this->falso->llamadas)['mensajes'];
        $this->assertCount(9, $ultima);   // 10 como máximo, y la primera debe ser del usuario
        $this->assertSame('user', $ultima[0]['role']);
        $this->assertSame('Pregunta 8', end($ultima)['content']);
    }

    public function test_si_el_servicio_falla_responde_503_y_no_guarda_la_pregunta(): void
    {
        $usuario = $this->persona(Rol::PROPIETARIO);
        $this->falso->error = 'El asistente está muy ocupado. Intenta de nuevo en un minuto.';

        $this->preguntar($usuario)->assertStatus(503)->assertJson(['error' => 'El asistente está muy ocupado. Intenta de nuevo en un minuto.']);
        $this->actingAs($usuario)->getJson(route('asistente.historial'))->assertJsonCount(0, 'mensajes');
    }

    public function test_valida_el_mensaje_y_limita_las_preguntas_por_minuto(): void
    {
        $usuario = $this->persona(Rol::PROPIETARIO);

        $this->preguntar($usuario, '')->assertUnprocessable();
        $this->preguntar($usuario, str_repeat('a', 1001))->assertUnprocessable();

        for ($i = 0; $i < 8; $i++) {
            $this->preguntar($usuario)->assertOk();
        }
        $this->preguntar($usuario)->assertStatus(429); // 11.ª petición en el mismo minuto
    }

    public function test_sin_clave_el_widget_avisa_y_el_cliente_real_no_llama_a_la_api(): void
    {
        $this->falso->configurado = false;
        $this->actingAs($this->persona(Rol::PROPIETARIO))->getJson(route('asistente.historial'))->assertJsonPath('configurado', false);

        $this->expectException(AsistenteNoDisponible::class);
        (new ClaudeAsistente(null, 'claude-opus-5-5'))->responder('x', 'y', [['role' => 'user', 'content' => 'hola']]);
    }

    public function test_las_instrucciones_incluyen_las_reglas_y_las_especies(): void
    {
        $instrucciones = ContextoFinca::instrucciones();

        $this->assertStringContainsString('no lo inventes', $instrucciones);
        $this->assertStringContainsString('Pseudoplatystoma magdaleniatum', $instrucciones);
        // No asume país ni normativa: remite a la autoridad del país del usuario
        $this->assertStringNotContainsString('Tolima', $instrucciones);
        $this->assertStringNotContainsString('Colombia', $instrucciones);
        $this->assertStringContainsString('No asumas el país', $instrucciones);
        $this->assertStringContainsString('autoridad sanitaria o agropecuaria de su país', $instrucciones);
        // Las instrucciones no cambian entre preguntas (prefijo cacheable)
        $this->assertSame($instrucciones, ContextoFinca::instrucciones());
    }

    public function test_un_solo_widget_para_usuarios_con_sesion_y_ninguno_para_invitados(): void
    {
        $pagina = $this->actingAs($this->persona(Rol::CELADOR))->get(route('inicio'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($pagina, 'data-asistente-formulario'));

        auth()->logout();
        $this->get(route('login'))->assertDontSee('data-asistente-formulario', false);
        $this->postJson(route('asistente.enviar'), ['mensaje' => 'hola'])->assertUnauthorized();
    }
}
