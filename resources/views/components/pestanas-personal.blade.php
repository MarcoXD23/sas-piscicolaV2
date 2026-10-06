{{-- Pestañas de la sección Personal (exclusiva del propietario) --}}
<x-pestanas :enlaces="[
    ['ruta' => 'admin.personal.index', 'texto' => 'Personas'],
    ['ruta' => 'admin.turnos.index', 'texto' => 'Turnos'],
    ['ruta' => 'admin.solicitudes.index', 'texto' => 'Solicitudes'],
]" />
