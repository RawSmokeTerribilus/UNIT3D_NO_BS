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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GamingController extends Controller
{
    /**
     * Catálogo de juegos disponibles.
     * Datos fijos en código — no se necesita tabla de base de datos para 2 juegos.
     */
    private array $catalog = [
        'mi1-vga' => [
            'id'          => 'mi1-vga',
            'titulo'      => 'The Secret of Monkey Island',
            'descripcion' => 'La legendaria aventura gráfica de LucasArts que comenzó todo. Guía a Guybrush Threepwood en su camino para convertirse en el pirata definitivo.',
            'año'         => 1990,
            'desarrollador' => 'LucasArts',
            'idioma'      => 'Español',
            'version'     => 'VGA Floppy',
            'scummvm_id'  => 'monkey',
            'cover'       => '/img/games/mi1-vga.jpg',
            'data_path'   => '/games/mi1-vga/',
            'files'       => ['000.LFL', '901.LFL', '902.LFL', '903.LFL', '904.LFL', 'DISK01.LEC', 'DISK02.LEC', 'DISK03.LEC', 'DISK04.LEC'],
        ],
        'mi2-talkie' => [
            'id'          => 'mi2-talkie',
            'titulo'      => "Monkey Island 2: LeChuck's Revenge",
            'descripcion' => 'Guybrush busca el legendario tesoro de Big Whoop mientras LeChuck regresa más peligroso que nunca. Versión española con subtítulos completos.',
            'año'         => 1992,
            'desarrollador' => 'LucasArts',
            'idioma'      => 'Español',
            'version'     => 'CD Talkie',
            'scummvm_id'  => 'monkey2',
            'cover'       => '/img/games/mi2-talkie.jpg',
            'data_path'   => '/games/mi2-talkie/',
            'files'       => ['MONKEY2.000', 'MONKEY2.001', 'monkey2.sog'],
        ],
        'maniac-mansion' => [
            'id'            => 'maniac-mansion',
            'titulo'        => 'Maniac Mansion',
            'descripcion'   => 'La primera gran aventura gráfica de LucasArts. Rescata a Sandy de las garras del Dr. Fred y su meteorito mutante en esta mansión llena de secretos.',
            'año'           => 1987,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Inglés',
            'version'       => 'Enhanced',
            'scummvm_id'    => 'maniac',
            'cover'         => '/img/games/maniac-mansion.png',
            'data_path'     => '/games/maniac-mansion/',
            'files'         => [
                '00.lfl', '01.lfl', '02.lfl', '03.lfl', '04.lfl', '05.lfl', '06.lfl', '07.lfl',
                '08.lfl', '09.lfl', '10.lfl', '11.lfl', '12.lfl', '13.lfl', '14.lfl', '15.lfl',
                '16.lfl', '17.lfl', '18.lfl', '19.lfl', '20.lfl', '21.lfl', '22.lfl', '23.lfl',
                '24.lfl', '25.lfl', '26.lfl', '27.lfl', '28.lfl', '29.lfl', '30.lfl', '31.lfl',
                '32.lfl', '33.lfl', '34.lfl', '35.lfl', '36.lfl', '37.lfl', '38.lfl', '39.lfl',
                '40.lfl', '41.lfl', '42.lfl', '43.lfl', '44.lfl', '45.lfl', '46.lfl', '47.lfl',
                '48.lfl', '49.lfl', '50.lfl', '51.lfl', '52.lfl', '53.lfl',
            ],
        ],
        'loom' => [
            'id'            => 'loom',
            'titulo'        => 'Loom',
            'descripcion'   => 'Una aventura de fantasía única donde la magia se ejerce tejiendo melodías. Bobbin Threadbare debe salvar a su gremio de tejedores de una oscuridad creciente.',
            'año'           => 1990,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Inglés',
            'version'       => 'CD Talkie',
            'scummvm_id'    => 'loom',
            'cover'         => '/img/games/loom.png',
            'data_path'     => '/games/loom/',
            'files'         => ['000.lfl', '901.lfl', '902.lfl', '903.lfl', '904.lfl', 'disk01.lec', 'track1.ogg'],
        ],
        'zak-mckracken' => [
            'id'            => 'zak-mckracken',
            'titulo'        => 'Zak McKracken and the Alien Mindbenders',
            'descripcion'   => 'Un periodista de tabloides descubre una conspiración alienígena para reducir la inteligencia humana. Viaja por todo el mundo para salvar la humanidad.',
            'año'           => 1988,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Inglés',
            'version'       => 'FM Towns',
            'scummvm_id'    => 'zak',
            'cover'         => '/img/games/zak-mckracken.png',
            'data_path'     => '/games/zak-mckracken/',
            'files'         => [
                '00.lfl', '01.lfl', '02.lfl', '03.lfl', '04.lfl', '05.lfl', '06.lfl', '07.lfl',
                '08.lfl', '09.lfl', '10.lfl', '11.lfl', '12.lfl', '13.lfl', '14.lfl', '15.lfl',
                '16.lfl', '17.lfl', '18.lfl', '19.lfl', '20.lfl', '21.lfl', '22.lfl', '23.lfl',
                '24.lfl', '25.lfl', '26.lfl', '27.lfl', '28.lfl', '29.lfl', '30.lfl', '31.lfl',
                '32.lfl', '33.lfl', '34.lfl', '35.lfl', '36.lfl', '37.lfl', '38.lfl', '39.lfl',
                '40.lfl', '41.lfl', '42.lfl', '43.lfl', '44.lfl', '45.lfl', '46.lfl', '47.lfl',
                '48.lfl', '49.lfl', '50.lfl', '51.lfl', '52.lfl', '53.lfl', '54.lfl', '55.lfl',
                '56.lfl', '57.lfl', '58.lfl', '59.lfl', '98.lfl', '99.lfl',
                'track1.ogg', 'track2.ogg', 'track3.ogg', 'track4.ogg', 'track5.ogg',
                'track6.ogg', 'track7.ogg', 'track8.ogg', 'track9.ogg', 'track10.ogg',
                'track11.ogg', 'track12.ogg', 'track13.ogg', 'track14.ogg', 'track15.ogg',
                'track16.ogg', 'track17.ogg', 'track18.ogg', 'track19.ogg', 'track20.ogg', 'track21.ogg',
            ],
        ],
        'indy-atlantis' => [
            'id'            => 'indy-atlantis',
            'titulo'        => 'Indiana Jones and the Fate of Atlantis',
            'descripcion'   => 'Indiana Jones busca la legendaria Atlántida antes de que los nazis la usen para fabricar un arma suprema. Múltiples caminos y decisiones en esta épica aventura.',
            'año'           => 1992,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Inglés',
            'version'       => 'CD Talkie',
            'scummvm_id'    => 'atlantis',
            'cover'         => '/img/games/indy-atlantis.png',
            'data_path'     => '/games/indy-atlantis/',
            'files'         => ['atlantis.000', 'atlantis.001', 'monster.sog'],
        ],
        'samnmax' => [
            'id'            => 'samnmax',
            'titulo'        => 'Sam & Max Hit the Road',
            'descripcion'   => 'El dúo detective más disparatado del mundo investiga la desaparición de un Bigfoot de feria. Una comedia absurda y brillante por la América profunda.',
            'año'           => 1993,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Inglés',
            'version'       => 'CD Talkie',
            'scummvm_id'    => 'samnmax',
            'cover'         => '/img/games/samnmax.png',
            'data_path'     => '/games/samnmax/',
            'files'         => ['samnmax.000', 'samnmax.001', 'monster.sog'],
        ],
    ];

    /**
     * Página principal del arcade — lista de juegos disponibles.
     */
    public function index(): View
    {
        return view('gaming.index', [
            'juegos' => $this->catalog,
        ]);
    }

    /**
     * Reproductor de juego — inyecta metadatos y partidas del usuario en la vista.
     */
    public function show(string $gameId): View|Response
    {
        if (! isset($this->catalog[$gameId])) {
            abort(404, 'Juego no encontrado.');
        }

        $juego = $this->catalog[$gameId];

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
        ]);
    }
}
