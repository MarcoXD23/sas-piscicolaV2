<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Asistente\ClaudeAsistente;
use App\Support\Asistente\ClienteAsistente;
use App\Support\Asistente\GeminiAsistente;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiAsistenteTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'clave-gemini-secreta-de-prueba-9f3a';

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba puede llamar a la API real
        Http::preventStrayRequests();
        // Las esperas entre reintentos se registran sin esperar de verdad
        Sleep::fake();
        config([
            'services.asistente.proveedor' => 'gemini',
            'services.gemini.key' => self::CLAVE,
            'services.gemini.modelo' => 'gemini-3.5-flash-lite',
        ]);

        $tenant = Tenant::create(['nombre' => 'Finca Prueba', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::PROPIETARIO]);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
    }

    private function respuestaGemini(string $texto): array
    {
        return ['candidates' => [[
            'content' => ['role' => 'model', 'parts' => [
                ['text' => 'Razonamiento interno que no se muestra', 'thought' => true],
                ['text' => $texto],
            ]],
            'finishReason' => 'STOP',
        ]]];
    }

    private function preguntar(string $mensaje = '¿Cuánto alimento doy hoy?')
    {
        return $this->actingAs($this->propietario)->postJson(route('asistente.enviar'), ['mensaje' => $mensaje]);
    }

    public function test_responde_con_gemini_usando_el_mismo_contexto_y_la_clave_en_la_cabecera(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($this->respuestaGemini('Dale 2,0 kg al día en 4 tomas.'))
            ->push($this->respuestaGemini('Mañana igual.'))]);

        $this->preguntar()->assertOk()->assertExactJson(['respuesta' => 'Dale 2,0 kg al día en 4 tomas.']);
        $this->preguntar('¿Y mañana?')->assertOk()->assertExactJson(['respuesta' => 'Mañana igual.']);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $peticion) {
            $cuerpo = $peticion->data();

            return $peticion->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent'
                && $peticion->hasHeader('x-goog-api-key', self::CLAVE)
                && ! str_contains($peticion->url(), self::CLAVE)
                // Mismas instrucciones (sin país configurado no asume ninguno) y mismo contexto de la finca
                && str_contains($cuerpo['systemInstruction']['parts'][0]['text'], __('asistente.sin_pais'))
                && str_contains($cuerpo['systemInstruction']['parts'][0]['text'], 'Puedes responder cualquier pregunta con tu conocimiento general.')
                && str_contains($cuerpo['systemInstruction']['parts'][1]['text'], 'Lago L01')
                && $cuerpo['contents'][0] === ['role' => 'user', 'parts' => [['text' => '¿Cuánto alimento doy hoy?']]];
        });

        // En la segunda pregunta, la respuesta anterior viaja con el rol "model" de Gemini
        $segunda = Http::recorded()[1][0]->data()['contents'];
        $this->assertSame(['user', 'model', 'user'], array_column($segunda, 'role'));
        $this->assertSame('Dale 2,0 kg al día en 4 tomas.', $segunda[1]['parts'][0]['text']);
    }

    public function test_errores_de_la_api_dan_mensajes_claros_y_no_guardan_la_pregunta(): void
    {
        $casos = [
            [Http::response(['error' => ['code' => 502, 'status' => 'UNKNOWN', 'message' => 'Bad gateway']], 502), 'El asistente no pudo responder'],
            [Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded']], 429), 'muy ocupado'],
            [Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key not valid. Please pass a valid API key.']], 400), 'La clave del asistente no es válida'],
            [Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]), 'no puede responder esa pregunta'],
            [Http::response(['candidates' => [['content' => ['parts' => []], 'finishReason' => 'SAFETY']]]), 'no puede responder esa pregunta'],
            [Http::response(['candidates' => [['content' => ['parts' => [['text' => '   ']]], 'finishReason' => 'STOP']]]), 'no devolvió una respuesta'],
        ];

        // Una respuesta falsa por caso, en orden (Http::fake no reemplaza las respuestas ya registradas)
        $secuencia = Http::sequence();
        foreach ($casos as [$respuesta]) {
            $secuencia->pushResponse($respuesta);
        }
        Http::fake(['generativelanguage.googleapis.com/*' => $secuencia]);

        foreach ($casos as [, $mensaje]) {
            $this->assertStringContainsString($mensaje, $this->preguntar()->assertStatus(503)->json('error'));
        }

        $this->actingAs($this->propietario)->getJson(route('asistente.historial'))->assertJsonCount(0, 'mensajes');
    }

    public function test_sin_conexion_avisa_revisar_el_internet(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $this->preguntar()->assertStatus(503)->assertJson(['error' => 'No hay conexión con el asistente. Revisa el internet e intenta de nuevo.']);
    }

    public function test_sin_clave_no_llama_a_la_api(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->actingAs($this->propietario)->getJson(route('asistente.historial'))->assertJsonPath('configurado', false);
        $this->preguntar()->assertStatus(503)->assertJson(['error' => 'El asistente no está configurado. Pide al propietario que agregue la clave de Gemini.']);

        Http::assertNothingSent();
    }

    public function test_la_clave_nunca_aparece_en_respuestas_ni_vistas(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($this->respuestaGemini('Todo bien.'))
            ->push(['error' => ['code' => 400, 'message' => 'API key not valid.']], 400)
            ->push(['error' => ['code' => 502, 'message' => 'Bad gateway']], 502)]);

        $respuestas = [
            $this->preguntar(),
            $this->preguntar(),
            $this->preguntar(),
            $this->actingAs($this->propietario)->getJson(route('asistente.historial')),
            $this->actingAs($this->propietario)->get(route('inicio')),
            $this->actingAs($this->propietario)->get(route('admin.lagos.index')),
        ];

        foreach ($respuestas as $respuesta) {
            $this->assertStringNotContainsString(self::CLAVE, $respuesta->getContent());
        }
    }

    private function errorGemini(int $estado): \GuzzleHttp\Promise\PromiseInterface
    {
        $estados = [400 => 'INVALID_ARGUMENT', 401 => 'UNAUTHENTICATED', 403 => 'PERMISSION_DENIED',
            429 => 'RESOURCE_EXHAUSTED', 500 => 'INTERNAL', 503 => 'UNAVAILABLE'];

        return Http::response(['error' => ['code' => $estado, 'status' => $estados[$estado], 'message' => 'Error de prueba']], $estado);
    }

    private function urlModelo(string $modelo): string
    {
        return sprintf(GeminiAsistente::URL, $modelo);
    }

    public function test_reintenta_un_503_y_responde(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->pushResponse($this->errorGemini(503))
            ->push($this->respuestaGemini('Dale 2,0 kg al día.'))]);

        $this->preguntar()->assertOk()->assertExactJson(['respuesta' => 'Dale 2,0 kg al día.']);

        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(1)->seconds()]);
    }

    public function test_reintenta_dos_veces_con_espera_creciente(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->pushResponse($this->errorGemini(500))
            ->pushResponse($this->errorGemini(503))
            ->push($this->respuestaGemini('Todo en orden.'))]);

        $this->preguntar()->assertOk()->assertExactJson(['respuesta' => 'Todo en orden.']);

        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(1)->seconds(), Sleep::for(3)->seconds()]);
        // Todos los intentos fueron al modelo principal
        foreach (Http::recorded() as [$peticion]) {
            $this->assertSame($this->urlModelo('gemini-3.5-flash-lite'), $peticion->url());
        }
    }

    public function test_usa_el_modelo_de_respaldo_si_el_principal_sigue_saturado(): void
    {
        config(['services.gemini.modelo_respaldo' => 'gemini-respaldo-lite']);
        Http::fake([
            $this->urlModelo('gemini-3.5-flash-lite') => Http::sequence()
                ->pushResponse($this->errorGemini(503))
                ->pushResponse($this->errorGemini(503))
                ->pushResponse($this->errorGemini(500)),
            $this->urlModelo('gemini-respaldo-lite') => Http::response($this->respuestaGemini('Respuesta del respaldo.')),
        ]);

        $this->preguntar()->assertOk()->assertExactJson(['respuesta' => 'Respuesta del respaldo.']);

        $urls = array_map(fn ($par) => $par[0]->url(), Http::recorded()->all());
        $this->assertSame([
            $this->urlModelo('gemini-3.5-flash-lite'),
            $this->urlModelo('gemini-3.5-flash-lite'),
            $this->urlModelo('gemini-3.5-flash-lite'),
            $this->urlModelo('gemini-respaldo-lite'),
        ], $urls);
        // El respaldo también lleva la clave en la cabecera, nunca en la URL
        $this->assertTrue(Http::recorded()[3][0]->hasHeader('x-goog-api-key', self::CLAVE));
    }

    public function test_si_el_respaldo_tambien_falla_se_intenta_una_sola_vez_y_avisa_saturacion(): void
    {
        config(['services.gemini.modelo_respaldo' => 'gemini-respaldo-lite']);
        Http::fake(['generativelanguage.googleapis.com/*' => $this->errorGemini(503)]);

        $this->preguntar()->assertStatus(503)
            ->assertJson(['error' => 'El servicio del asistente está saturado. Intenta en un minuto.']);

        Http::assertSentCount(4);
        Http::assertSent(fn (Request $peticion) => $peticion->url() === $this->urlModelo('gemini-respaldo-lite'));
        Sleep::assertSleptTimes(2);
    }

    public function test_sin_respaldo_avisa_saturacion_tras_los_reintentos(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->errorGemini(503)]);

        $error = $this->preguntar()->assertStatus(503)->json('error');

        $this->assertSame('El servicio del asistente está saturado. Intenta en un minuto.', $error);
        $this->assertNotSame(__('asistente.errores.no_pudo_responder'), $error);
        Http::assertSentCount(3);
        $this->actingAs($this->propietario)->getJson(route('asistente.historial'))->assertJsonCount(0, 'mensajes');
    }

    public function test_no_reintenta_errores_de_clave_cuota_ni_peticion_mala(): void
    {
        // Aun con respaldo configurado: estos errores no son pasajeros
        config(['services.gemini.modelo_respaldo' => 'gemini-respaldo-lite']);
        $casos = [
            401 => __('asistente.errores.clave_invalida'),
            403 => __('asistente.errores.clave_invalida'),
            429 => __('asistente.errores.ocupado'),
            400 => __('asistente.errores.no_pudo_responder'),
        ];

        foreach ($casos as $estado => $mensaje) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::preventStrayRequests();
            Http::fake(['generativelanguage.googleapis.com/*' => $this->errorGemini($estado)]);

            $error = $this->preguntar()->assertStatus(503)->json('error');

            $this->assertSame($mensaje, $error, "Estado {$estado}");
            $this->assertNotSame(__('asistente.errores.saturado'), $error);
            Http::assertSentCount(1);
            Http::assertNotSent(fn (Request $peticion) => str_contains($peticion->url(), 'gemini-respaldo-lite'));
        }

        Sleep::assertNeverSlept();
    }

    public function test_los_reintentos_no_guardan_la_clave_en_los_logs(): void
    {
        config(['services.gemini.modelo_respaldo' => 'gemini-respaldo-lite']);
        Log::spy();
        Http::fake(['generativelanguage.googleapis.com/*' => $this->errorGemini(503)]);

        $this->preguntar()->assertStatus(503);

        $registros = 0;
        foreach (['warning', 'error'] as $nivel) {
            Log::shouldHaveReceived($nivel)->withArgs(function (string $mensaje, array $datos = []) use (&$registros) {
                $registros++;
                $this->assertStringNotContainsString(self::CLAVE, $mensaje.json_encode($datos));

                return true;
            });
        }
        // Tres avisos de saturación (2 reintentos y el paso al respaldo) y el error final
        $this->assertSame(4, $registros);
    }

    public function test_el_modelo_por_defecto_es_gemini_3_5_flash_lite(): void
    {
        // Sin GEMINI_MODEL (vacío) la configuración usa el modelo nuevo, sea cual sea el .env local
        $antes = [getenv('GEMINI_MODEL'), $_ENV['GEMINI_MODEL'] ?? null, $_SERVER['GEMINI_MODEL'] ?? null];
        putenv('GEMINI_MODEL=');
        $_ENV['GEMINI_MODEL'] = $_SERVER['GEMINI_MODEL'] = '';

        try {
            $modelo = (require config_path('services.php'))['gemini']['modelo'];
        } finally {
            $antes[0] === false ? putenv('GEMINI_MODEL') : putenv('GEMINI_MODEL='.$antes[0]);
            foreach (['_ENV' => $antes[1], '_SERVER' => $antes[2]] as $global => $valor) {
                if ($valor === null) {
                    unset($GLOBALS[$global]['GEMINI_MODEL']);
                } else {
                    $GLOBALS[$global]['GEMINI_MODEL'] = $valor;
                }
            }
        }

        $this->assertSame('gemini-3.5-flash-lite', $modelo);

        // Y la llamada va a ese modelo
        config(['services.gemini.modelo' => $modelo]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->respuestaGemini('Listo.'))]);
        $this->preguntar()->assertOk();
        Http::assertSent(fn (Request $peticion) => $peticion->url() === $this->urlModelo('gemini-3.5-flash-lite'));
    }

    public function test_el_proveedor_se_elige_con_asistente_proveedor(): void
    {
        config(['services.asistente.proveedor' => 'gemini']);
        $this->assertInstanceOf(GeminiAsistente::class, app(ClienteAsistente::class));

        config(['services.asistente.proveedor' => 'claude']);
        $this->assertInstanceOf(ClaudeAsistente::class, app(ClienteAsistente::class));

        // Un valor desconocido no adivina proveedor: queda como no configurado
        config(['services.asistente.proveedor' => 'otro']);
        $this->assertFalse(app(ClienteAsistente::class)->configurado());
        $this->actingAs($this->propietario)->getJson(route('asistente.historial'))->assertJsonPath('configurado', false);
    }
}
