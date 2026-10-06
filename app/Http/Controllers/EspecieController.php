<?php

namespace App\Http\Controllers;

use App\Support\CatalogoEspecies;
use Illuminate\View\View;

class EspecieController extends Controller
{
    public function index(): View
    {
        return view('especies.index', ['especies' => CatalogoEspecies::todas()]);
    }

    public function show(string $slug): View
    {
        $especie = CatalogoEspecies::porSlug($slug);
        abort_if($especie === null, 404);

        return view('especies.show', ['especie' => $especie]);
    }
}
