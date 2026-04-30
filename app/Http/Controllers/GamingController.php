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
            'descripcion'   => 'La primera gran aventura gráfica de LucasArts, ahora en español. Rescata a Sandy de las garras del Dr. Fred y su meteorito mutante en esta mansión llena de secretos.',
            'año'           => 1987,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
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
            'descripcion'   => 'Una aventura de fantasía única donde la magia se ejerce tejiendo melodías, ahora con textos en español. Bobbin Threadbare debe salvar a su gremio de tejedores de una oscuridad creciente.',
            'año'           => 1990,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'scummvm_id'    => 'loom',
            'cover'         => '/img/games/loom.png',
            'data_path'     => '/games/loom/',
            'files'         => ['000.lfl', '901.lfl', '902.lfl', '903.lfl', '904.lfl', 'disk01.lec', 'track1.mp3'],
        ],
        'zak-mckracken' => [
            'id'            => 'zak-mckracken',
            'titulo'        => 'Zak McKracken and the Alien Mindbenders',
            'descripcion'   => 'Un periodista de tabloides descubre una conspiración alienígena para reducir la inteligencia humana, ahora con textos en español. Viaja por todo el mundo para salvar la humanidad.',
            'año'           => 1988,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
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
            'titulo'        => 'Indiana Jones y el Destino de la Atlántida',
            'descripcion'   => 'Indiana Jones busca la legendaria Atlántida antes de que los nazis la usen para fabricar un arma suprema. Múltiples caminos y decisiones en esta épica aventura, con voces y textos en español.',
            'año'           => 1992,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'scummvm_id'    => 'atlantis',
            'cover'         => '/img/games/indy-atlantis.png',
            'data_path'     => '/games/indy-atlantis/',
            'files'         => ['atlantis.000', 'atlantis.001', 'monster.so3'],
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
        'indy3' => [
            'id'            => 'indy3',
            'titulo'        => 'Indiana Jones y la Última Cruzada',
            'descripcion'   => 'La aventura gráfica que precede a Atlántida: Indy y su padre rastrean el Santo Grial mientras los nazis pisan sus talones. Sistema IQ pionero y múltiples soluciones por puzzle, en español.',
            'año'           => 1989,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'VGA Floppy',
            'scummvm_id'    => 'indy3',
            'cover'         => '/img/games/indy3.png',
            'data_path'     => '/games/indy3/',
            'files'         => [
                '00.lfl', '01.lfl', '02.lfl', '03.lfl', '04.lfl', '06.lfl', '07.lfl', '08.lfl', '09.lfl',
                '12.lfl', '13.lfl', '14.lfl', '15.lfl', '16.lfl', '17.lfl', '18.lfl', '19.lfl', '20.lfl',
                '21.lfl', '22.lfl', '23.lfl', '24.lfl', '25.lfl', '26.lfl', '27.lfl', '28.lfl', '29.lfl',
                '30.lfl', '31.lfl', '32.lfl', '33.lfl', '34.lfl', '35.lfl', '36.lfl', '37.lfl', '38.lfl',
                '39.lfl', '40.lfl', '41.lfl', '42.lfl', '43.lfl', '44.lfl', '45.lfl', '46.lfl', '47.lfl',
                '48.lfl', '49.lfl', '50.lfl', '51.lfl', '52.lfl', '53.lfl', '54.lfl', '55.lfl', '56.lfl',
                '57.lfl', '58.lfl', '59.lfl', '60.lfl', '61.lfl', '62.lfl', '63.lfl', '64.lfl', '66.lfl',
                '67.lfl', '68.lfl', '69.lfl', '70.lfl', '71.lfl', '72.lfl', '73.lfl', '74.lfl', '75.lfl',
                '76.lfl', '77.lfl', '78.lfl', '79.lfl', '80.lfl', '81.lfl', '82.lfl', '83.lfl', '84.lfl',
                '85.lfl', '86.lfl', '87.lfl', '90.lfl', '91.lfl', '93.lfl', '94.lfl', '98.lfl', '99.lfl',
            ],
        ],
        'tentacle' => [
            'id'            => 'tentacle',
            'titulo'        => 'Maniac Mansion 2: El Día del Tentáculo',
            'descripcion'   => 'Bernard, Hoagie y Laverne viajan en el tiempo para impedir que el Tentáculo Púrpura conquiste el mundo. La secuela de Maniac Mansion, con humor desatado y puzzles enrevesados.',
            'año'           => 1993,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'Floppy',
            'scummvm_id'    => 'tentacle',
            'cover'         => '/img/games/tentacle.jpg',
            'data_path'     => '/games/tentacle/',
            'files'         => ['tentacle.000', 'tentacle.001', 'monster.sou'],
        ],
        'ft' => [
            'id'            => 'ft',
            'titulo'        => 'Full Throttle',
            'descripcion'   => 'Ben, líder de la banda motera Polecats, se ve atrapado en una trama de asesinato corporativo. Una aventura grasienta de moteros, carreteras y rock, doblada al español.',
            'año'           => 1995,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'scummvm_id'    => 'ft',
            'cover'         => '/img/games/ft.jpg',
            'data_path'     => '/games/ft/',
            'files'         => [
                'ft.la0', 'ft.la1', 'monster.sou',
                'DATA/BENCUT.NUT', 'DATA/BENFLIP.SAN', 'DATA/BENSGOGG.NUT', 'DATA/CHASOUT.SAN',
                'DATA/CHASTHRU.SAN', 'DATA/FISHFEAR.SAN', 'DATA/FISHGOG2.SAN', 'DATA/FISHGOGG.SAN',
                'DATA/GETNITRO.SAN', 'DATA/GOGLPALT.RIP', 'DATA/HITDUST1.SAN', 'DATA/HITDUST2.SAN',
                'DATA/HITDUST3.SAN', 'DATA/HITDUST4.SAN', 'DATA/ICONS.NUT', 'DATA/ICONS2.NUT',
                'DATA/LIFTBORD.SAN', 'DATA/LIFTCHAY.SAN', 'DATA/LIFTGOG.SAN', 'DATA/LIFTMACE.SAN',
                'DATA/LIFTSAW.SAN', 'DATA/LIMOCRSH.SAN', 'DATA/MINEDRIV.FLU', 'DATA/MINEDRIV.SAN',
                'DATA/MINEEXIT.SAN', 'DATA/MINEFITE.FLU', 'DATA/MINEFITE.SAN', 'DATA/MINEROAD.TRS',
                'DATA/ROADRASH.RIP', 'DATA/ROADRSH2.RIP', 'DATA/ROADRSH3.RIP', 'DATA/ROTTFITE.SAN',
                'DATA/ROTTFLIP.SAN', 'DATA/ROTTOPEN.SAN', 'DATA/SCUMMFNT.NUT', 'DATA/SPECFNT.NUT',
                'DATA/TECHFNT.NUT', 'DATA/TITLFNT.NUT', 'DATA/TOMINE.SAN', 'DATA/TORANCH.FLU',
                'DATA/TORANCH.SAN', 'DATA/TOVISTA1.FLU', 'DATA/TOVISTA1.SAN', 'DATA/TOVISTA2.FLU',
                'DATA/TOVISTA2.SAN', 'DATA/VISTTHRU.SAN', 'DATA/WR2_BEN.SAN', 'DATA/WR2_BENC.SAN',
                'DATA/WR2_BENR.SAN', 'DATA/WR2_BENV.SAN', 'DATA/WR2_CAVE.SAN', 'DATA/WR2_CVKO.SAN',
                'DATA/WR2_ROTT.SAN', 'DATA/WR2_VLTC.SAN', 'DATA/WR2_VLTP.SAN', 'DATA/WR2_VLTS.SAN',
                'VIDEO/2009_10.SAN', 'VIDEO/2009_10.TRS', 'VIDEO/ACCIDENT.SAN', 'VIDEO/ACCIDENT.TRS',
                'VIDEO/BARKDOG.SAN', 'VIDEO/BC.SAN', 'VIDEO/BENBURN.SAN', 'VIDEO/BENESCPE.SAN',
                'VIDEO/BENHANG.SAN', 'VIDEO/BENHANG.TRS', 'VIDEO/BIGBLOW.SAN', 'VIDEO/BOLUSKIL.SAN',
                'VIDEO/BOLUSKIL.TRS', 'VIDEO/BURST.SAN', 'VIDEO/BURSTDOG.SAN', 'VIDEO/CABSTRUG.SAN',
                'VIDEO/CES_NITE.SAN', 'VIDEO/CREDITS.SAN', 'VIDEO/CREDITS.TRS', 'VIDEO/DAZED.SAN',
                'VIDEO/DAZED.TRS', 'VIDEO/ESCPPLNE.SAN', 'VIDEO/ESCPPLNE.TRS', 'VIDEO/FANSTOP.SAN',
                'VIDEO/FI_14_15.SAN', 'VIDEO/FI_14_15.TRS', 'VIDEO/FIRE.SAN', 'VIDEO/FLINCH.SAN',
                'VIDEO/FULPLANE.SAN', 'VIDEO/GOTCHA.SAN', 'VIDEO/GOTCHA.TRS', 'VIDEO/GR_LOS.SAN',
                'VIDEO/GR_WIN.SAN', 'VIDEO/INTO_FAN.SAN', 'VIDEO/INTRO4.SAN', 'VIDEO/INTROD_8.SAN',
                'VIDEO/INTROD_8.TRS', 'VIDEO/JG.SAN', 'VIDEO/JUMPGORG.SAN', 'VIDEO/KICK_OFF.SAN',
                'VIDEO/KILLGULL.SAN', 'VIDEO/KR.SAN', 'VIDEO/KS_1.SAN', 'VIDEO/KS_1.TRS',
                'VIDEO/KS_11.SAN', 'VIDEO/KS_11.TRS', 'VIDEO/KS_111.SAN', 'VIDEO/KS_111.TRS',
                'VIDEO/KS_IV.SAN', 'VIDEO/KS_IV.TRS', 'VIDEO/KS_V.SAN', 'VIDEO/KS_V.TRS',
                'VIDEO/KS_X.SAN', 'VIDEO/MO_FUME.SAN', 'VIDEO/MOEJECT.SAN', 'VIDEO/MOEJECT.TRS',
                'VIDEO/MOREACH.SAN', 'VIDEO/MWC.SAN', 'VIDEO/MWC.TRS', 'VIDEO/NAMES_MO.SAN',
                'VIDEO/NAMES_MO.TRS', 'VIDEO/NB_BLOW.SAN', 'VIDEO/NITEFADE.SAN', 'VIDEO/NITERIDE.SAN',
                'VIDEO/NTREACT.SAN', 'VIDEO/OFFGOGG.SAN', 'VIDEO/OM.SAN', 'VIDEO/OM.TRS',
                'VIDEO/REALRIDE.SAN', 'VIDEO/REALRIDE.TRS', 'VIDEO/REV.SAN', 'VIDEO/RIDEAWAY.SAN',
                'VIDEO/RIDEOUT.SAN', 'VIDEO/RIP_KILL.SAN', 'VIDEO/RIP_KILL.TRS', 'VIDEO/RIPBROW.SAN',
                'VIDEO/RIPBROW.TRS', 'VIDEO/RIPSHIFT.SAN', 'VIDEO/SCRAPBOT.SAN', 'VIDEO/SCRAPBOT.TRS',
                'VIDEO/SEEN_RIP.SAN', 'VIDEO/SEEN_RIP.TRS', 'VIDEO/SHEFALLS.SAN', 'VIDEO/SHEFALLS.TRS',
                'VIDEO/SHORTOPN.SAN', 'VIDEO/SHOWSMRK.SAN', 'VIDEO/SHOWUP1.SAN', 'VIDEO/SHOWUP1.TRS',
                'VIDEO/SNAPFOTO.SAN', 'VIDEO/SNAPFOTO.TRS', 'VIDEO/SNSETRDE.SAN', 'VIDEO/SNSETRDE.TRS',
                'VIDEO/SQUINT.SAN', 'VIDEO/TC.SAN', 'VIDEO/TEETBURN.SAN', 'VIDEO/TINYPLNE.SAN',
                'VIDEO/TINYTRUK.SAN', 'VIDEO/TOKCRGO.SAN', 'VIDEO/TOKCRGO.TRS', 'VIDEO/TRKCRSH.SAN',
                'VIDEO/VISION.SAN', 'VIDEO/WEREINIT.SAN', 'VIDEO/WEREINIT.TRS', 'VIDEO/WHIP_PAN.SAN',
                'VIDEO/WHIP_PAN.TRS',
            ],
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
