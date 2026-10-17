{{-- Pestañas de Especies: la guía (todos los roles) y el catálogo de la finca (el propietario consulta, el administrador
     edita). El trabajador y el celador solo tienen la guía: no ven pestañas. --}}
@if (auth()->user()?->tieneRol(\App\Models\Rol::PROPIETARIO, \App\Models\Rol::ADMINISTRADOR))
    {{-- Sin partir «Catálogo de la finca» en el teléfono: «Guía» es corta y las dos caben en una línea --}}
    <x-pestanas class="overflow-x-auto [&>a]:whitespace-nowrap" :enlaces="[
        ['ruta' => 'especies.index', 'texto' => __('especies.pestanas.guia')],
        ['ruta' => 'admin.especies.index', 'texto' => __('especies.pestanas.catalogo')],
        ['ruta' => 'admin.presentaciones.index', 'texto' => __('especies.pestanas.presentaciones')],
    ]" />
@endif
