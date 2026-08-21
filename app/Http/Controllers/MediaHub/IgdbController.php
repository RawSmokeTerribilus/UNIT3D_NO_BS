<?php

declare(strict_types=1);

namespace App\Http\Controllers\MediaHub;

use App\Http\Controllers\Controller;
use App\Models\IgdbCompany;
use App\Models\IgdbGenre;
use App\Models\IgdbPlatform;

/**
 * Los hubs del catálogo de juegos.
 *
 * El esquema de IGDB llevaba desde marzo de 2025 en la base, con sus tres
 * pivotes llenos, y no había ni una vista que lo enseñara: el MediaHub sólo
 * sabía de TMDB. Aquí no hace falta ninguna migración, sólo pintar lo que ya
 * está.
 *
 * Plataforma es lo que en cine no existe y aquí manda: el mismo juego para PC
 * y para Switch son dos cosas distintas para quien busca.
 */
class IgdbController extends Controller
{
    public function genres(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.game.genre', [
            'genres' => IgdbGenre::query()
                ->withCount('games')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function platforms(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.game.platform', [
            'platforms' => IgdbPlatform::query()
                ->withCount('games')
                ->orderByDesc('games_count')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function companies(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.game.company', [
            'companies' => IgdbCompany::query()
                ->withCount('games')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
