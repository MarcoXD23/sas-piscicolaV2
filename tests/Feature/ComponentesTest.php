<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** Componentes de estado de la libreta: el estado nunca se comunica solo con color. */
class ComponentesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        view()->share('errors', new ViewErrorBag);
    }

    private function contarIconos(string $html): int
    {
        return substr_count($html, '<svg');
    }

    public function test_cada_insignia_de_estado_lleva_icono_y_texto(): void
    {
        foreach (['bien', 'atencion', 'alerta', 'info', 'trucha'] as $tono) {
            $html = Blade::render('<x-insignia :tono="$tono">Texto del estado</x-insignia>', ['tono' => $tono]);

            $this->assertSame(1, $this->contarIconos($html), "La insignia «{$tono}» debe llevar ícono");
            $this->assertStringContainsString('Texto del estado', $html);
        }
    }

    public function test_la_insignia_como_pestana_puede_ser_el_titulo_del_bloque(): void
    {
        $html = Blade::render('<x-insignia tono="alerta" pestana como="h2" id="titulo">Dos alertas</x-insignia>');

        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('id="titulo"', $html);
        $this->assertStringContainsString('absolute', $html);
    }

    public function test_el_aviso_lleva_icono_y_el_de_alerta_se_anuncia_de_inmediato(): void
    {
        $alerta = Blade::render('<x-aviso tono="alerta">Algo salió mal</x-aviso>');
        $estado = Blade::render('<x-aviso tono="bien">Guardado</x-aviso>');

        $this->assertSame(1, $this->contarIconos($alerta));
        $this->assertStringContainsString('role="alert"', $alerta);
        $this->assertStringContainsString('role="status"', $estado);
        $this->assertStringContainsString('Guardado', $estado);
    }

    public function test_el_aviso_muestra_su_accion(): void
    {
        $html = Blade::render('<x-aviso tono="atencion">Falta algo<x-slot:accion><a href="/x">Resolver</a></x-slot:accion></x-aviso>');

        $this->assertStringContainsString('Resolver', $html);
    }

    public function test_el_esqueleto_se_anuncia_como_cargando(): void
    {
        $html = Blade::render('<x-esqueleto :renglones="2" cifra />');

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString(__('componentes.cargando'), $html);
        $this->assertSame(3, substr_count($html, 'esqueleto h-'));
    }
}
