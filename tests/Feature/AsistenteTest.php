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
use App\Support\Menu;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => now()->subDays(30),
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

        // Los lagos son los de su finca (el nombre de la finca ya no se envía)
        $this->assertStringContainsString('Lago L01', $contexto);
        $this->assertStringNotContainsString('LAGO-AJENO', $contexto);
        $this->assertStringNotContainsString('Otra finca', $contexto);
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

        $this->assertStringContainsString('Nunca inventes datos de la finca', $instrucciones);
        $this->assertStringContainsString('Pseudoplatystoma magdaleniatum', $instrucciones);
        // Sin país configurado no asume ninguno; leyes y trámites, a la autoridad de su país
        $this->assertStringContainsString(__('asistente.sin_pais'), $instrucciones);
        $this->assertStringNotContainsString('Tolima', $instrucciones);
        $this->assertStringNotContainsString('Colombia', $instrucciones);
        $this->assertStringContainsString('remite a la autoridad correspondiente de su país', $instrucciones);
        // Las instrucciones no cambian entre preguntas (prefijo cacheable)
        $this->assertSame($instrucciones, ContextoFinca::instrucciones());
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $this->assertSame(ContextoFinca::instrucciones($trabajador), ContextoFinca::instrucciones($trabajador));
    }

    public function test_con_pais_configurado_las_instrucciones_llevan_el_pais_de_la_finca(): void
    {
        $this->tenant->update(['pais' => 'CO']);

        $instrucciones = ContextoFinca::instrucciones();

        $this->assertStringContainsString('La finca está en Colombia.', $instrucciones);
        $this->assertStringNotContainsString(__('asistente.sin_pais'), $instrucciones);
    }

    public function test_las_instrucciones_permiten_el_conocimiento_general_y_separan_los_dos_avisos(): void
    {
        $instrucciones = ContextoFinca::instrucciones();

        // Conocimiento general, con la piscicultura como especialidad, y las fichas primero
        $this->assertStringContainsString('Puedes responder cualquier pregunta con tu conocimiento general.', $instrucciones);
        $this->assertStringContainsString('Tu especialidad es la piscicultura', $instrucciones);
        $this->assertStringContainsString('aireadores, bombas, oxímetros, redes, balanzas', $instrucciones);
        $this->assertStringContainsString('usa ese valor antes que tu conocimiento general', $instrucciones);
        $this->assertStringNotContainsString('No uses conocimiento general', $instrucciones);
        // Medicamentos: orientación general, sin dosis exactas ni marcas, y siempre al técnico o al veterinario
        $this->assertStringContainsString('sin dosis exactas ni marcas comerciales', $instrucciones);
        $this->assertStringContainsString('asistente técnico o al veterinario', $instrucciones);
        // Dato ausente: solo para un dato concreto de la finca
        $this->assertSame('No tengo ese dato registrado en la finca.', __('asistente.avisos.dato_ausente'));
        $this->assertStringContainsString('Si un dato concreto de la finca no está, responde exactamente: «'.__('asistente.avisos.dato_ausente').'»', $instrucciones);
        // Aviso de rol: no cambia, y nunca para un dato ausente
        $this->assertSame('No tengo esa información para tu rol.', __('asistente.avisos.sin_acceso_rol'));
        $this->assertStringContainsString('Solo si la pregunta es sobre uno de esos temas, responde exactamente: «'.__('asistente.avisos.sin_acceso_rol').'»', $instrucciones);
        $this->assertStringContainsString('No uses ese aviso para un dato que simplemente no está', $instrucciones);
        // Respuestas cortas para el celular, sin tablas ni Markdown pesado
        $this->assertStringContainsString('No uses tablas ni Markdown', $instrucciones);
    }

    public function test_una_pregunta_general_de_piscicultura_va_con_la_especialidad_y_las_fichas(): void
    {
        $this->preguntar($this->persona(Rol::TRABAJADOR), '¿Qué oxígeno necesita la mojarra roja y qué hago si baja?')->assertOk();
        $instrucciones = $this->falso->llamadas[0]['instrucciones'];

        $this->assertStringContainsString('Tu especialidad es la piscicultura', $instrucciones);
        $this->assertStringContainsString('- Mojarra roja (nombre científico: Oreochromis sp. (híbrido)).', $instrucciones);
    }

    public function test_una_pregunta_de_uso_de_la_app_va_con_la_guia_de_su_rol(): void
    {
        $this->preguntar($this->persona(Rol::TRABAJADOR), '¿Cómo registro una mortalidad?')->assertOk();
        $instrucciones = $this->falso->llamadas[0]['instrucciones'];

        $this->assertStringContainsString(__('asistente.guia.titulo'), $instrucciones);
        $this->assertStringContainsString('- Registro rápido: registrar desde el celular, también sin conexión: la ración de un lago, los peces muertos y el aviso de que un lago está listo para pescar. '.__('asistente.guia.en_turno'), $instrucciones);
        $this->assertStringContainsString('Nunca inventes pantallas, botones ni funciones.', $instrucciones);
    }

    public function test_un_dato_de_la_finca_que_no_existe_lleva_el_aviso_de_dato_ausente(): void
    {
        $this->preguntar($this->persona(Rol::ADMINISTRADOR), '¿Cuánto le toca hoy al lago precian1?')->assertOk();
        $enviado = $this->falso->llamadas[0];

        // El lago no existe: no está en los datos, y no es un tema oculto para el administrador
        $this->assertStringNotContainsStringIgnoringCase('precian1', $enviado['contexto']);
        $this->assertStringContainsString('Lago L01', $enviado['contexto']);
        $this->assertStringContainsString('Nunca inventes datos de la finca', $enviado['instrucciones']);
        $this->assertStringContainsString(__('asistente.avisos.dato_ausente'), $enviado['instrucciones']);
    }

    public function test_la_nomina_es_un_tema_oculto_para_el_trabajador(): void
    {
        $this->preguntar($this->persona(Rol::TRABAJADOR), '¿Cuánto se pagó de nómina?')->assertOk();
        $contexto = $this->falso->llamadas[0]['contexto'];

        $this->assertStringContainsString('Datos que existen en la finca pero tu rol no puede ver:', $contexto);
        $this->assertStringContainsString(__('asistente.temas.nomina'), $contexto);
    }

    public function test_preguntas_fuera_de_tema_y_precios_de_mercado_tienen_su_regla(): void
    {
        $this->preguntar($this->persona(Rol::PROPIETARIO), '¿A cómo está el kilo de cachama?')->assertOk();
        $instrucciones = $this->falso->llamadas[0]['instrucciones'];

        // Precios y datos que cambian: no se inventan
        $this->assertStringContainsString('Precios de mercado, cotizaciones y datos que cambian con el tiempo (precio del pescado, del concentrado, del dólar): no los inventes ni des cifras.', $instrucciones);
        $this->assertStringContainsString('Di que no tienes datos actuales', $instrucciones);
        // Fuera de tema: breve y amable, sin negarse
        $this->assertStringContainsString('responde breve y amable, sin negarte', $instrucciones);
    }

    public function test_la_guia_de_cada_rol_solo_nombra_las_pantallas_de_su_menu(): void
    {
        foreach ([Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = $this->persona($rol);
            $guia = ContextoFinca::guia($persona);
            preg_match_all('/^- ([^:]+):/m', $guia, $nombradas);

            // Exactamente las entradas de su menú, en el mismo orden y con su nombre real
            $this->assertSame(Menu::para($persona)->pluck('texto')->all(), $nombradas[1], $rol);
        }

        // El trabajador no ve Bodega, Ventas ni Personal: su guía no las nombra
        $trabajador = ContextoFinca::guia($this->persona(Rol::TRABAJADOR));
        foreach (['layout.menu.bodega', 'layout.menu.ventas', 'layout.menu.personal', 'layout.menu.noche'] as $clave) {
            $this->assertStringNotContainsString('- '.__($clave).':', $trabajador, $clave);
        }
        // Registro rápido: lo mismo que el paquete de trabajo de cada rol
        $this->assertStringContainsString('las rondas de seguridad y la medición del agua', ContextoFinca::guia($this->persona(Rol::CELADOR)));
        $this->assertStringContainsString('el muestreo de peso', ContextoFinca::guia($this->persona(Rol::ADMINISTRADOR)));
        $this->assertStringNotContainsString(__('asistente.guia.en_turno'), ContextoFinca::guia($this->persona(Rol::ADMINISTRADOR)));
        $this->assertStringNotContainsString('- '.__('layout.menu.registro_rapido').':', ContextoFinca::guia($this->persona(Rol::PROPIETARIO)));

        // Sin persona (por ejemplo, fuera de una petición) no va guía
        $this->assertStringNotContainsString(__('asistente.guia.titulo'), ContextoFinca::instrucciones());
    }

    public function test_cada_entrada_del_menu_tiene_su_texto_en_la_guia(): void
    {
        $pantallas = __('asistente.guia.pantallas');
        foreach (Menu::entradas() as $entrada) {
            if ($entrada['ruta'] === 'campo') {
                // Registro rápido se arma con las secciones del paquete de trabajo: cada una tiene su texto
                foreach (['racion', 'mortalidad', 'muestreo', 'aviso_pesca', 'ronda', 'medicion'] as $seccion) {
                    $this->assertNotSame('asistente.guia.acciones.'.$seccion, __('asistente.guia.acciones.'.$seccion), $seccion);
                }

                continue;
            }

            $this->assertArrayHasKey($entrada['ruta'], $pantallas, 'Falta el texto de la guía para '.$entrada['ruta']);
            $texto = $pantallas[$entrada['ruta']];
            if (is_array($texto)) {
                // Un texto por rol: cada rol que ve la entrada tiene el suyo
                $this->assertNotNull($entrada['roles'], $entrada['ruta']);
                foreach ($entrada['roles'] as $rol) {
                    $this->assertArrayHasKey($rol, $texto, "Falta el texto de {$entrada['ruta']} para {$rol}");
                }
            }
        }
        // Y no hay textos de pantallas que no están en el menú
        $this->assertSame([], array_values(array_diff(array_keys($pantallas), array_column(Menu::entradas(), 'ruta'))));
    }

    public function test_una_pregunta_de_dinero_de_un_rol_sin_acceso_lleva_el_tema_como_oculto_para_su_rol(): void
    {
        $this->preguntar($this->persona(Rol::TRABAJADOR), '¿Cuánto vendimos este mes?')->assertOk();
        $trabajador = $this->falso->llamadas[0]['contexto'];

        // Las ventas no van en sus datos, pero se nombran como tema existente que su rol no ve: corresponde el aviso de rol
        $this->assertStringNotContainsString('Ventas del mes', $trabajador);
        $this->assertStringContainsString('Datos que existen en la finca pero tu rol no puede ver:', $trabajador);
        $this->assertStringContainsString(__('asistente.temas.dinero'), $trabajador);
        $this->assertStringContainsString(__('asistente.temas.bodega'), $trabajador);
        $this->assertStringContainsString(__('asistente.temas.nomina'), $trabajador);

        // El propietario ve todo: no hay temas ocultos
        $this->preguntar($this->persona(Rol::PROPIETARIO), '¿Cuánto vendimos este mes?')->assertOk();
        $propietario = $this->falso->llamadas[1]['contexto'];
        $this->assertStringContainsString('Ventas del mes', $propietario);
        $this->assertStringNotContainsString('tu rol no puede ver', $propietario);

        // El administrador ve el dinero, pero no lo exclusivo del propietario
        $this->assertSame(['personal', 'nomina', 'agenda'], ContextoFinca::temasOcultos($this->persona(Rol::ADMINISTRADOR)));
        // El celador no ve raciones ni dinero, pero sí la seguridad nocturna
        $celador = ContextoFinca::temasOcultos($this->persona(Rol::CELADOR));
        $this->assertContains('raciones', $celador);
        $this->assertContains('dinero', $celador);
        $this->assertNotContains('seguridad', $celador);
    }

    public function test_un_dato_que_no_esta_en_la_ficha_no_se_confunde_con_un_dato_oculto_por_rol(): void
    {
        $this->preguntar($this->persona(Rol::TRABAJADOR), '¿Qué vacuna necesita la mojarra roja?')->assertOk();
        $enviado = $this->falso->llamadas[0];

        // Ni las fichas ni los datos hablan de vacunas, y no es un tema oculto para el rol: corresponde el aviso de dato ausente
        $fichas = Str::after($enviado['instrucciones'], 'Fichas de referencia de las especies');
        $this->assertNotSame($enviado['instrucciones'], $fichas);
        $this->assertStringNotContainsStringIgnoringCase('vacuna', $fichas);
        $this->assertStringNotContainsStringIgnoringCase('vacuna', $enviado['contexto']);
        $this->assertStringContainsString(__('asistente.avisos.dato_ausente'), $enviado['instrucciones']);
    }

    public function test_una_pregunta_cubierta_por_la_ficha_lleva_la_ficha_y_se_responde(): void
    {

        $this->preguntar($this->persona(Rol::TRABAJADOR), '¿Qué temperatura necesita la mojarra roja?')
            ->assertOk()->assertJson(['respuesta' => 'Respuesta 1']);

        $instrucciones = $this->falso->llamadas[0]['instrucciones'];
        $this->assertStringContainsString('- Mojarra roja (nombre científico: Oreochromis sp. (híbrido)). Clima: Agua cálida. Temperatura: 26 a 30 °C.', $instrucciones);
    }

    public function test_un_solo_widget_para_usuarios_con_sesion_y_ninguno_para_invitados(): void
    {
        $pagina = $this->actingAs($this->persona(Rol::CELADOR))->get(route('inicio'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($pagina, 'data-asistente-formulario'));

        auth()->logout();
        $this->get(route('login'))->assertDontSee('data-asistente-formulario', false);
        $this->postJson(route('asistente.enviar'), ['mensaje' => 'hola'])->assertUnauthorized();
    }

    public function test_no_se_envian_el_nombre_de_la_persona_ni_el_de_la_finca(): void
    {
        foreach ([Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $indice => $rol) {
            $persona = tap($this->persona($rol))->update(['name' => 'Persona Muy Única '.$indice]);
            $this->preguntar($persona)->assertOk();
            $enviado = $this->falso->llamadas[$indice];

            // Al asistente le basta el rol
            $this->assertStringContainsString('Rol de quien pregunta: '.$rol, $enviado['contexto'], $rol);
            foreach (['Persona Muy Única', 'Finca El Lago', $persona->email] as $dato) {
                $this->assertStringNotContainsString($dato, $enviado['contexto'], "{$rol}: {$dato}");
                $this->assertStringNotContainsString($dato, $enviado['instrucciones'], "{$rol}: {$dato}");
            }
        }
    }

    public function test_el_respaldo_del_servidor_solo_va_con_los_modelos_que_lo_admiten(): void
    {
        // Haiku no admite fallbacks: con él la petición se rechazaría
        $this->assertSame([], (new ClaudeAsistente('clave', 'claude-haiku-5-5'))->opcionesRespaldo());
        $this->assertSame([], (new ClaudeAsistente('clave', 'un-modelo-nuevo'))->opcionesRespaldo());

        foreach (config('services.anthropic.modelos_con_respaldo') as $modelo) {
            $this->assertSame(['fallbacks' => 'default', 'betas' => ['server-side-fallback-2026-07-01']],
                (new ClaudeAsistente('clave', $modelo))->opcionesRespaldo(), $modelo);
        }
        $this->assertContains('claude-opus-5-5', config('services.anthropic.modelos_con_respaldo'));
        $this->assertNotContains('claude-haiku-5-5', config('services.anthropic.modelos_con_respaldo'));
    }

    public function test_el_modelo_de_claude_por_defecto_es_haiku(): void
    {
        // Sin ANTHROPIC_MODEL (vacío) la configuración usa Haiku, sea cual sea el .env local
        $antes = [getenv('ANTHROPIC_MODEL'), $_ENV['ANTHROPIC_MODEL'] ?? null, $_SERVER['ANTHROPIC_MODEL'] ?? null];
        putenv('ANTHROPIC_MODEL=');
        $_ENV['ANTHROPIC_MODEL'] = $_SERVER['ANTHROPIC_MODEL'] = '';

        try {
            $modelo = (require config_path('services.php'))['anthropic']['modelo'];
        } finally {
            $antes[0] === false ? putenv('ANTHROPIC_MODEL') : putenv('ANTHROPIC_MODEL='.$antes[0]);
            foreach (['_ENV' => $antes[1], '_SERVER' => $antes[2]] as $global => $valor) {
                if ($valor === null) {
                    unset($GLOBALS[$global]['ANTHROPIC_MODEL']);
                } else {
                    $GLOBALS[$global]['ANTHROPIC_MODEL'] = $valor;
                }
            }
        }

        $this->assertSame('claude-haiku-5-5', $modelo);
        // Y Haiku va sin el respaldo del servidor
        $this->assertSame([], (new ClaudeAsistente('clave', $modelo))->opcionesRespaldo());
    }
}
