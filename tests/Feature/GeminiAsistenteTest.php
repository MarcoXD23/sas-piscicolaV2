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
        config([
            'services.asistente.proveedor' => 'gemini',
            'services.gemini.key' => self::CLAVE,
            'services.gemini.modelo' => 'gemini-3.8-flash',
        ]);

        $tenant = Tenant::create(['nombre' => 'Finca Prueba', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::PROPIETARIO]);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subDays(30),
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

            return $peticion->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent'
                && $peticion->hasHeader('x-goog-api-key', self::CLAVE)
                && ! str_contains($peticion->url(), self::CLAVE)
                // Mismas instrucciones (con la regla de no asumir país) y mismo contexto de la finca
                && str_contains($cuerpo['systemInstruction']['parts'][0]['text'], 'No asumas el país')
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
            [Http::response(['error' => ['code' => 500, 'status' => 'INTERNAL', 'message' => 'Internal error']], 500), 'El asistente no pudo responder'],
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
            ->push(['error' => ['code' => 500, 'message' => 'Internal']], 500)]);

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
