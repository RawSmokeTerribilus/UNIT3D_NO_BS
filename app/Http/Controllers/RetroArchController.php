<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RetroArchController extends Controller
{
    /**
     * Slugs de sistema permitidos. Whitelist estricta — se inyecta en
     * URLs y paths de filesystem, así que cualquier carácter exótico es no.
     */
    private const SYSTEM_REGEX = '/^[a-z][a-z0-9]*$/';

    /**
     * Lee el catálogo persistido por `php artisan retroarch:scan-roms`.
     * Forma:
     *   { "generated_at": "...", "systems": { "<sys>": { "label", "core", "icon",
     *     "rom_count", "roms": [ {"slug","title","filename","size","cover"}... ] } } }
     *
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $path = storage_path('app/retroarch/catalog.json');

        if (! is_file($path)) {
            return ['systems' => []];
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw === false ? '' : $raw, true);

        return is_array($data) ? $data : ['systems' => []];
    }

    /**
     * Landing — grid de sistemas disponibles.
     */
    public function index(): View
    {
        $catalog = $this->catalog();
        $systems = $catalog['systems'] ?? [];

        // Filtrar a sistemas declarados en config + que tengan ROMs reales.
        $configured = config('retroarch.systems', []);
        $visible = [];

        foreach ($configured as $slug => $meta) {
            if (preg_match(self::SYSTEM_REGEX, $slug) !== 1) {
                continue;
            }
            if (! isset($systems[$slug]) || ($systems[$slug]['rom_count'] ?? 0) === 0) {
                continue;
            }
            $visible[$slug] = $systems[$slug] + [
                'slug'               => $slug,
                'unavailable'        => $meta['unavailable']        ?? false,
                'unavailable_reason' => $meta['unavailable_reason'] ?? null,
            ];
        }

        return view('retroarch.index', [
            'systems' => $visible,
        ]);
    }

    /**
     * Catálogo paginado de un sistema. Soporta búsqueda por título.
     */
    public function system(Request $request, string $system): View|Response
    {
        if (preg_match(self::SYSTEM_REGEX, $system) !== 1) {
            abort(404);
        }

        $catalog = $this->catalog();
        $entry = $catalog['systems'][$system] ?? null;
        $config = config('retroarch.systems', [])[$system] ?? null;

        if ($entry === null || $config === null) {
            abort(404, 'Sistema no encontrado.');
        }

        // Sistema marcado como cerrado: vista informativa, sin catálogo.
        if (! empty($config['unavailable'])) {
            return view('retroarch.closed', [
                'system' => $system,
                'meta'   => $entry + ['slug' => $system],
                'reason' => $config['unavailable_reason'] ?? null,
            ]);
        }

        $roms = $entry['roms'] ?? [];

        // Búsqueda — case-insensitive sobre el título limpio.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $roms = array_values(array_filter(
                $roms,
                fn ($r) => str_contains(mb_strtolower((string) ($r['title'] ?? '')), $needle),
            ));
        }

        $perPage = (int) config('retroarch.page_size', 60);
        $page = max(1, (int) $request->query('page', 1));
        $total = count($roms);
        $slice = array_slice($roms, ($page - 1) * $perPage, $perPage);

        $hasMore = ($page * $perPage) < $total;

        if ($request->ajax()) {
            return response()->json([
                'html'      => view('retroarch.partials.rom-items', [
                    'roms'   => $slice,
                    'system' => $system,
                ])->render(),
                'has_more'  => $hasMore,
                'next_page' => $page + 1,
            ]);
        }

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ],
        );

        return view('retroarch.system', [
            'system'   => $system,
            'meta'     => $entry,
            'roms'     => $paginator,
            'hasMore'  => $hasMore,
            'q'        => $q,
        ]);
    }

    /**
     * Lanzador — renderiza la página del juego con un iframe que apunta
     * a /retroarch/index.html?core=...&content=...&autoStart=1
     */
    public function show(string $system, string $slug): View|Response
    {
        if (preg_match(self::SYSTEM_REGEX, $system) !== 1) {
            abort(404);
        }
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            abort(404);
        }

        $catalog = $this->catalog();
        $entry = $catalog['systems'][$system] ?? null;
        $config = config('retroarch.systems', [])[$system] ?? null;

        if ($entry === null) {
            abort(404, 'Sistema no encontrado.');
        }

        // Sistema cerrado: rebotar al placeholder informativo en vez de
        // intentar lanzar un juego que no funcionará.
        if (! empty($config['unavailable'])) {
            return redirect()->route('retroarch.system', ['system' => $system]);
        }

        $rom = null;
        foreach ($entry['roms'] ?? [] as $candidate) {
            if (($candidate['slug'] ?? null) === $slug) {
                $rom = $candidate;
                break;
            }
        }

        if ($rom === null) {
            abort(404, 'Juego no encontrado.');
        }

        // Resolver core: arcade puede tener override por-rom.
        $core = $entry['core'];
        if ($system === 'arcade') {
            $base = pathinfo((string) $rom['filename'], PATHINFO_FILENAME);
            $override = config('retroarch.arcade_overrides.'.$base);
            if (is_string($override) && preg_match('/^[a-z0-9_]+$/', $override) === 1) {
                $core = $override;
            }
        }

        $mount = (string) config('retroarch.rom_mount', '/home/web_user/retroarch/userdata/content/downloads/roms');
        $contentPath = $mount.'/'.$system.'/'.$rom['filename'];

        return view('retroarch.show', [
            'system'      => $system,
            'meta'        => $entry,
            'rom'         => $rom,
            'core'        => $core,
            'contentPath' => $contentPath,
        ]);
    }
}
