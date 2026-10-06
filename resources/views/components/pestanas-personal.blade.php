{{-- Pestañas de la sección Personal (exclusiva del propietario) --}}
<x-pestanas class="overflow-x-auto" :enlaces="[
    ['ruta' => 'admin.personal.index', 'texto' => __('personal.personas')],
    ['ruta' => 'admin.turnos.index', 'texto' => __('personal.turnos')],
    ['ruta' => 'admin.solicitudes.index', 'texto' => __('personal.solicitudes')],
    ['ruta' => 'admin.nomina.index', 'texto' => __('personal.nomina')],
]" />
