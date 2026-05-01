<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Http\Controllers;

use App\Models\GameSave;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GamingController extends Controller
{
    /**
     * Whitelist para identificadores ScummVM (scummvm_id, gameid).
     * ScummVM admite guiones en los target names ("indy3-towns") y a veces
     * en gameids ("indy3-towns" también), así que el regex los permite.
     * Defensa en profundidad: estos valores acaban en texto inyectado en JS
     * y como nombres de sección INI, así que se rechazan caracteres exóticos.
     */
    private const ID_REGEX = '/^[a-z][a-z0-9-]*$/';

    /**
     * Whitelist más estricta para engine_id. Sin guiones porque acaba como
     * nombre de archivo en una URL (lib<engine_id>.so) y todos los plugins
     * de ScummVM siguen el convenio sin guion (scumm, sci, sword1, sword25).
     */
    private const ENGINE_REGEX = '/^[a-z][a-z0-9]*$/';

    /**
     * Devuelve el catálogo enriquecido (cada entrada lleva su 'id' y 'data_path').
     *
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        $raw = config('gaming.catalog', []);
        $out = [];

        foreach ($raw as $id => $game) {
            // ID del juego: define el subdir en /games/<id>/. Bloquear cualquier
            // cosa que pueda escapar de esa jaula al construirse la URL.
            if (! is_string($id) || preg_match('/^[a-z][a-z0-9-]*$/', $id) !== 1) {
                continue;
            }

            $out[$id] = $game + [
                'id'        => $id,
                'data_path' => '/games/'.$id.'/',
            ];
        }

        return $out;
    }

    /**
     * Genera el contenido de scummvm.ini a partir del catálogo.
     * El launcher intercepta fetch('scummvm.ini') y devuelve este texto al
     * motor — fuente única de verdad para evitar la deriva entre PHP y JS.
     */
    public function buildScummIni(): string
    {
        $ini = "[scummvm]\nversioninfo=2.6.0\npluginspath=/plugins\n\n";

        foreach ($this->catalog() as $id => $game) {
            // Section name (a.k.a. ScummVM "target") debe ser único en el catálogo;
            // gameid es el identificador canónico del juego en ScummVM (puede
            // repetirse: mi1-vga, mi1-talkie e yonkey son los tres gameid=monkey).
            // Si la entrada no declara 'gameid', se asume que sección == gameid
            // (caso simple: un solo entry por juego canónico).
            $section = $game['scummvm_id'] ?? '';
            $gameid  = $game['gameid']     ?? $section;
            $engine  = $game['engine_id']  ?? '';

            // Saltar entradas con identificadores inválidos: el .ini se sirve
            // al motor, donde una sección malformada puede colgar el parser.
            if (preg_match(self::ID_REGEX, $section)    !== 1
                || preg_match(self::ID_REGEX, $gameid)  !== 1
                || preg_match(self::ENGINE_REGEX, $engine) !== 1
            ) {
                continue;
            }

            $ini .= "[{$section}]\n";
            $ini .= "gameid={$gameid}\n";
            $ini .= "engineid={$engine}\n";
            $ini .= "path=/games/{$id}\n";

            $opts = $game['ini'] ?? [];
            if (! empty($opts['language']) && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $opts['language']) === 1) {
                $ini .= "language={$opts['language']}\n";
            }
            if (! empty($opts['subtitles'])) {
                $ini .= "subtitles=true\n";
            }
            if (isset($opts['aspect_ratio']) && $opts['aspect_ratio'] === false) {
                $ini .= "aspect_ratio=false\n";
            }

            $ini .= "savepath=/saves\n\n";
        }

        return $ini;
    }

    /**
     * Página principal del arcade — lista de juegos disponibles.
     */
    public function index(): View
    {
        return view('gaming.index', [
            'juegos' => $this->catalog(),
        ]);
    }

    /**
     * Reproductor de juego — inyecta metadatos y partidas del usuario en la vista.
     */
    public function show(string $gameId): View|Response
    {
        $catalog = $this->catalog();

        if (! isset($catalog[$gameId])) {
            abort(404, 'Juego no encontrado.');
        }

        $juego = $catalog[$gameId];

        // Inyectar el manifiesto de partidas en Blade para cero round-trips al arrancar
        $saveManifest = GameSave::query()
            ->where('user_id', Auth::id())
            ->where('game_id', $gameId)
            ->get(['filename', 'file_size', 'updated_at'])
            ->map(fn ($save) => [
                'filename'     => $save->filename,
                'file_size'    => $save->file_size,
                'download_url' => route('gaming.saves.download', [
                    'gameId'   => $gameId,
                    'filename' => $save->filename,
                ]),
            ])
            ->values()
            ->all();

        return view('gaming.show', [
            'juego'        => $juego,
            'saveManifest' => $saveManifest,
            'scummIni'     => $this->buildScummIni(),
        ]);
    }
}
