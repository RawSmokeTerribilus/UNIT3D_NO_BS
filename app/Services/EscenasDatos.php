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

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Models\Peer;
use App\Models\Report;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Datos reales para las escenas pixel art de cada zona (public/js/escenas).
 *
 * Cada metodo devuelve el array que la escena espera en `montar(canvas, id,
 * { datos })`. Los criterios son los de la pagina de verdad de esa zona: solo
 * torrents aprobados, foros que quien mira puede leer, «anonimo» si el perfil
 * es privado y sin subidas anonimas en el ranking, igual que /stats. El usuario
 * System (subidas automaticas) no compite en las vitrinas.
 *
 * Todo pasa por cache: las escenas son decorado y no deben costar consultas en
 * cada visita. Redis devuelve los numeros como texto, asi que se castea fuera
 * de `Cache::remember`.
 */
final class EscenasDatos
{
    private const int TTL = 600;

    /**
     * Nombre de la categoria en la base de datos => clave del bufé.
     *
     * Por nombre y no por id: los ids de prod y staging no coinciden.
     */
    private const array CATEGORIAS = [
        'Movies'         => 'peliculas',
        'TV'             => 'series',
        'Anime TV Shows' => 'anime',
        'Anime Movies'   => 'cine_anime',
        'Audiobooks'     => 'audiolibros',
        'E-Books'        => 'libros',
    ];

    /**
     * Recepción (inicio): últimos torrents, última noticia y socios en línea.
     *
     * @return array{novedades: list<string>, noticia: string, online: int}
     */
    public static function recepcion(?int $online = null, ?string $noticia = null): array
    {
        return [
            'novedades' => \array_slice(self::ultimosTorrents(), 0, 4),
            'noticia'   => (string) $noticia,
            'online'    => (int) $online,
        ];
    }

    /**
     * Bufé (torrents): torrents aprobados por categoría.
     *
     * @return array{categorias: array<string, int>}
     */
    public static function bufe(): array
    {
        $porNombre = Cache::remember('escenas:categorias', self::TTL, fn () => Torrent::query()
            ->join('categories', 'categories.id', '=', 'torrents.category_id')
            ->where('torrents.status', '=', ModerationStatus::APPROVED)
            ->groupBy('categories.name')
            ->selectRaw('categories.name as nombre, count(*) as n')
            ->pluck('n', 'nombre')
            ->all());

        $categorias = [];

        foreach (self::CATEGORIAS as $nombre => $clave) {
            $categorias[$clave] = (int) ($porNombre[$nombre] ?? 0);
        }

        return ['categorias' => $categorias];
    }

    /**
     * Bar (foro): los seis foros con más hilos de los que quien mira puede leer.
     *
     * Recibe las categorías que ya cargó el índice del foro, filtradas con
     * `authorized(canReadTopic: true)`: así el tablón nunca enseña un foro oculto.
     *
     * @param  iterable<\App\Models\ForumCategory> $categorias
     * @return array{foros: list<array{nombre: string, hilos: int}>}
     */
    public static function bar(iterable $categorias): array
    {
        $foros = [];

        foreach ($categorias as $categoria) {
            foreach ($categoria->forums as $foro) {
                $foros[] = ['nombre' => (string) $foro->name, 'hilos' => (int) $foro->num_topic];
            }
        }

        usort($foros, fn (array $a, array $b) => $b['hilos'] <=> $a['hilos']);

        return ['foros' => \array_slice($foros, 0, 6)];
    }

    /**
     * Sala de trofeos (estadísticas): ranking de las vitrinas y totales del marcador.
     *
     * @return array{ranking: list<array{titulo: string, nombre: string, n: int}>, totales: array{subido_tb: float, bajado_tb: float}}
     */
    public static function trofeos(): array
    {
        $datos = Cache::remember('escenas:trofeos', self::TTL * 3, function (): array {
            $subidas = Torrent::query()
                ->where('anon', '=', false)
                ->where('user_id', '!=', User::SYSTEM_USER_ID)
                ->selectRaw('user_id, count(*) as n')
                ->groupBy('user_id')
                ->orderByDesc('n')
                ->first();

            $semilla = Peer::query()
                ->where('user_id', '!=', User::SYSTEM_USER_ID)
                ->where('seeder', '=', 1)
                ->where('active', '=', 1)
                ->selectRaw('user_id, count(distinct torrent_id) as n')
                ->groupBy('user_id')
                ->orderByDesc('n')
                ->first();

            $gracias = Thank::query()
                ->join('torrents', 'torrents.id', '=', 'thanks.torrent_id')
                ->where('torrents.anon', '=', false)
                ->where('torrents.user_id', '!=', User::SYSTEM_USER_ID)
                ->selectRaw('torrents.user_id as user_id, count(*) as n')
                ->groupBy('torrents.user_id')
                ->orderByDesc('n')
                ->first();

            return [
                'ranking' => [
                    ['titulo' => 'Más subidas', 'user_id' => $subidas?->user_id, 'n' => $subidas?->n],
                    ['titulo' => 'Más semilla', 'user_id' => $semilla?->user_id, 'n' => $semilla?->n],
                    ['titulo' => 'Más gracias', 'user_id' => $gracias?->user_id, 'n' => $gracias?->n],
                ],
                'subido' => User::query()->sum('uploaded'),
                'bajado' => User::query()->sum('downloaded'),
            ];
        });

        $usuarios = User::query()
            ->with('privacy')
            ->whereIntegerInRaw('id', array_filter(array_column($datos['ranking'], 'user_id')))
            ->get()
            ->keyBy('id');

        $ranking = [];

        foreach ($datos['ranking'] as $fila) {
            $usuario = $usuarios->get((int) $fila['user_id']);
            $nombre = match (true) {
                $usuario === null                               => '-',
                (bool) $usuario->privacy?->private_profile => 'Anónimo',
                default                                         => (string) $usuario->username,
            };
            $ranking[] = ['titulo' => $fila['titulo'], 'nombre' => $nombre, 'n' => (int) $fila['n']];
        }

        $tb = 1024 ** 4;

        return [
            'ranking' => $ranking,
            'totales' => [
                'subido_tb' => round((float) $datos['subido'] / $tb, 1),
                'bajado_tb' => round((float) $datos['bajado'] / $tb, 1),
            ],
        ];
    }

    /**
     * Pista (RetroArch): rótulos de las recreativas y total de juegos.
     *
     * @param  array<string, array<string, mixed>> $sistemas los que ya filtra el índice de RetroArch
     * @return array{sistemas: list<array{nombre: string}>, juegos: int}
     */
    public static function pista(array $sistemas): array
    {
        $juegos = 0;
        $rotulos = [];

        foreach ($sistemas as $slug => $sistema) {
            $juegos += (int) ($sistema['rom_count'] ?? 0);
            $rotulos[] = ['nombre' => (string) ($sistema['slug'] ?? $slug)];
        }

        return ['sistemas' => \array_slice($rotulos, 0, 4), 'juegos' => $juegos];
    }

    /**
     * SCUMM Bar (ScummVM): número de aventuras del catálogo.
     *
     * @param  array<mixed> $catalogo
     * @return array{juegos: int}
     */
    public static function scumm(array $catalogo): array
    {
        return ['juegos' => \count($catalogo)];
    }

    /**
     * Oficina de la rata (panel de staff): enjambre, reportes, bot y palancas.
     *
     * Freeleech y doble subida miran también el .env del announce Rust, que es
     * donde de verdad se aplican (ver TrackerPromos).
     *
     * @return array<string, mixed>
     */
    public static function oficina(): array
    {
        $enjambre = Cache::remember('escenas:enjambre', 300, fn (): array => [
            'pares'     => Peer::query()->where('active', '=', 1)->count(),
            'semillas'  => Peer::query()->where('active', '=', 1)->where('seeder', '=', 1)->count(),
            'descargas' => Peer::query()->where('active', '=', 1)->where('seeder', '=', 0)->count(),
            'socios'    => Peer::query()->where('active', '=', 1)->distinct()->count('user_id'),
        ]);

        $reportes = Report::query()
            ->whereNull('solved_at')
            ->where(fn ($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<', now()))
            ->count();

        $descarga = TrackerPromos::current('DOWNLOAD_FACTOR');
        $subida = TrackerPromos::current('UPLOAD_FACTOR');

        return [
            'enjambre' => array_map('intval', $enjambre),
            'reportes' => (int) $reportes,
            'telegram' => \array_slice(self::ultimosTorrents(), 0, 5),
            'ajustes'  => [
                'freeleech'    => (bool) config('other.freeleech') || $descarga === 0,
                'doble'        => (bool) config('other.doubleup') || ($subida !== null && $subida >= 200),
                'registro'     => ! (bool) config('other.invite-only'),
                'invitaciones' => ! (bool) config('other.invites_restriced'),
                'hitrun'       => (bool) config('hitrun.enabled'),
            ],
        ];
    }

    /**
     * Títulos de los últimos torrents aprobados.
     *
     * @return list<string>
     */
    private static function ultimosTorrents(): array
    {
        $nombres = Cache::remember('escenas:ultimos-torrents', 300, fn (): array => Torrent::query()
            ->where('status', '=', ModerationStatus::APPROVED)
            ->orderByDesc('id')
            ->limit(5)
            ->pluck('name')
            ->all());

        return array_values(array_map('strval', (array) $nombres));
    }
}
