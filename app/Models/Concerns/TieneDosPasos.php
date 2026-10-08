<?php

namespace App\Models\Concerns;

/**
 * Verificación en dos pasos (App\Support\DosPasos) para personas de las fincas (User) y del panel de plataforma
 * (AdministradorPlataforma). El modelo declara los casts: dos_pasos_clave «encrypted» y dos_pasos_recuperacion «array».
 */
trait TieneDosPasos
{
    /** ¿Tiene activa la verificación en dos pasos? */
    public function tieneDosPasos(): bool
    {
        return $this->dos_pasos_activado_at !== null && filled($this->dos_pasos_clave);
    }
}
