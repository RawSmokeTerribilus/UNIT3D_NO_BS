<?php

declare(strict_types=1);

/**
 * Catálogo del Arcade ScummVM.
 *
 * Fuente única de verdad para los juegos disponibles. Tanto el controlador
 * (GamingController) como el INI generado para ScummVM se construyen a partir
 * de aquí. Para añadir un juego: copiar los datos a public/games/<id>/, la
 * portada a public/img/games/<id>.{png,jpg}, y registrar la entrada abajo.
 *
 * El orden de las entradas determina el orden de visualización en el índice.
 * Política actual: agrupar por saga (Monkey Island → Indiana Jones → Maniac
 * Mansion → standalones cronológicos).
 *
 * Campos por entrada:
 *   titulo, descripcion, año, desarrollador, idioma, version  → metadatos UI
 *   engine_id      → motor ScummVM (debe corresponder a un libxxx.so en
 *                    public/engine/data/plugins/). Whitelist: ^[a-z][a-z0-9]*$
 *   scummvm_id     → "target" interno de ScummVM (sección INI). Único en el
 *                    catálogo.  Whitelist: ^[a-z][a-z0-9-]*$
 *   cover          → ruta al PNG/JPG de portada
 *   files          → lista relativa a public/games/<id>/ (admite subdirs como
 *                    'DATA/foo.SAN' para juegos con layout anidado tipo FT)
 *   ini            → opcional. Sobreescribe valores en scummvm.ini. Soporta:
 *                      language     (string)  — código de idioma p.ej. 'es'
 *                      subtitles    (bool)
 *                      aspect_ratio (bool)    — false para desactivar
 *   gameid         → opcional. ID canónico de ScummVM (lo que va en gameid=).
 *                    Por defecto se asume igual a scummvm_id. Sólo necesario
 *                    cuando varias entradas comparten el mismo juego canónico
 *                    (p.ej. mi1-vga e yonkey son ambos gameid=monkey pero
 *                    deben tener scummvm_id distintos como sección INI).
 */
return [
    'catalog' => [
        // ─── Monkey Island series ─────────────────────────────────────────
        'mi1-vga' => [
            'titulo'        => 'The Secret of Monkey Island',
            'descripcion'   => 'La legendaria aventura gráfica de LucasArts que comenzó todo, en su edición CD Talkie con doblaje y banda sonora orquestada. Guía a Guybrush Threepwood en su camino para convertirse en el pirata definitivo.',
            'año'           => 1990,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'monkey',
            'cover'         => '/img/games/mi1-vga.jpg',
            'files'         => [
                'monkey.000', 'monkey.001', 'monkey.sog',
                'track1.ogg', 'track2.ogg', 'track3.ogg', 'track4.ogg', 'track5.ogg',
                'track6.ogg', 'track7.ogg', 'track8.ogg', 'track8_no_sfx.ogg', 'track9.ogg',
                'track10.ogg', 'track11.ogg', 'track12.ogg', 'track13.ogg', 'track14.ogg',
                'track15.ogg', 'track16.ogg', 'track17.ogg', 'track18.ogg', 'track19.ogg',
                'track20.ogg', 'track21.ogg', 'track22.ogg', 'track23.ogg', 'track24.ogg',
                'track25.ogg', 'track26.ogg', 'track27.ogg', 'track28.ogg', 'track29.ogg',
            ],
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],

        'mi2-talkie' => [
            'titulo'        => "Monkey Island 2: LeChuck's Revenge",
            'descripcion'   => 'Guybrush busca el legendario tesoro de Big Whoop mientras LeChuck regresa más peligroso que nunca. Versión española con subtítulos completos.',
            'año'           => 1992,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'monkey2',
            'cover'         => '/img/games/mi2-talkie.jpg',
            'files'         => ['MONKEY2.000', 'MONKEY2.001', 'monkey2.sog'],
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],

        'comi' => [
            'titulo'        => 'The Curse of Monkey Island',
            'descripcion'   => 'La tercera aventura de Guybrush Threepwood, ahora dibujada a mano al estilo Disney. LeChuck transforma a Elaine en estatua de oro y sólo una maldición pirata desatada por un anillo puede salvarla. SCUMM v8 — la cima técnica del motor.',
            'año'           => 1997,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'comi',
            'cover'         => '/img/games/comi.jpg',
            'files'         => [
                'comi.la0', 'comi.la1', 'comi.la2',
                'resource/BBSAN.SAN', 'resource/CURSERNG.SAN', 'resource/FG010GP.SAN',
                'resource/FINALE.SAN', 'resource/FONT0.NUT', 'resource/FONT1.NUT',
                'resource/FONT2.NUT', 'resource/FONT3.NUT', 'resource/FONT4.NUT',
                'resource/KIS030.SAN', 'resource/LANGUAGE.TAB', 'resource/LAVARIDE.SAN',
                'resource/LIFTCRSE.SAN', 'resource/MORESLAW.SAN', 'resource/MUSDISK1.BUN',
                'resource/MUSDISK2.BUN', 'resource/NEWBOOTS.SAN', 'resource/OPENING.SAN',
                'resource/SB010.SAN', 'resource/SB020.SAN', 'resource/SINKSHP.SAN',
                'resource/VOXDISK1.BUN', 'resource/VOXDISK2.BUN', 'resource/WRECKSAN.SAN',
                'resource/ZAP010.SAN',
            ],
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],

        'yonkey' => [
            'titulo'        => 'Yonkey Island 1.0',
            'descripcion'   => 'Traducción charnega y desternillante de Monkey Island por Charnego Translations. Mismo juego, mismo Guybrush, pero con un toque andaluz que hay que vivir para creer.',
            'año'           => 2023,
            'desarrollador' => 'LucasArts / Charnego Translations',
            'idioma'        => 'Charnego (ES)',
            'version'       => 'CD Talkie · Fan-trans',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'yonkey',
            'gameid'        => 'monkey',
            'cover'         => '/img/games/yonkey.jpg',
            'files'         => [
                'monkey.000', 'monkey.001',
                'track1.ogg', 'track2.ogg', 'track3.ogg', 'track4.ogg', 'track5.ogg',
                'track6.ogg', 'track7.ogg', 'track8.ogg', 'track9.ogg', 'track10.ogg',
                'track11.ogg', 'track12.ogg', 'track13.ogg', 'track14.ogg', 'track15.ogg',
                'track16.ogg', 'track17.ogg', 'track18.ogg', 'track19.ogg', 'track20.ogg',
                'track21.ogg', 'track22.ogg', 'track23.ogg', 'track24.ogg',
            ],
            'ini'           => ['subtitles' => true],
        ],

        // ─── Indiana Jones series ─────────────────────────────────────────
        'indy3' => [
            'titulo'        => 'Indiana Jones y la Última Cruzada',
            'descripcion'   => 'La aventura gráfica que precede a Atlántida: Indy y su padre rastrean el Santo Grial mientras los nazis pisan sus talones. Sistema IQ pionero y múltiples soluciones por puzzle, en español.',
            'año'           => 1989,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'VGA Floppy',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'indy3',
            'cover'         => '/img/games/indy3.png',
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
            'ini'           => ['language' => 'es'],
        ],

        'indy-atlantis' => [
            'titulo'        => 'Indiana Jones y el Destino de la Atlántida',
            'descripcion'   => 'Indiana Jones busca la legendaria Atlántida antes de que los nazis la usen para fabricar un arma suprema. Múltiples caminos y decisiones en esta épica aventura, con voces y textos en español.',
            'año'           => 1992,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'atlantis',
            'cover'         => '/img/games/indy-atlantis.png',
            'files'         => ['atlantis.000', 'atlantis.001', 'monster.so3'],
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],

        // ─── Maniac Mansion series ────────────────────────────────────────
        'maniac-mansion' => [
            'titulo'        => 'Maniac Mansion',
            'descripcion'   => 'La primera gran aventura gráfica de LucasArts, ahora en español. Rescata a Sandy de las garras del Dr. Fred y su meteorito mutante en esta mansión llena de secretos.',
            'año'           => 1987,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'Enhanced',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'maniac',
            'cover'         => '/img/games/maniac-mansion.png',
            'files'         => [
                '00.lfl', '01.lfl', '02.lfl', '03.lfl', '04.lfl', '05.lfl', '06.lfl', '07.lfl',
                '08.lfl', '09.lfl', '10.lfl', '11.lfl', '12.lfl', '13.lfl', '14.lfl', '15.lfl',
                '16.lfl', '17.lfl', '18.lfl', '19.lfl', '20.lfl', '21.lfl', '22.lfl', '23.lfl',
                '24.lfl', '25.lfl', '26.lfl', '27.lfl', '28.lfl', '29.lfl', '30.lfl', '31.lfl',
                '32.lfl', '33.lfl', '34.lfl', '35.lfl', '36.lfl', '37.lfl', '38.lfl', '39.lfl',
                '40.lfl', '41.lfl', '42.lfl', '43.lfl', '44.lfl', '45.lfl', '46.lfl', '47.lfl',
                '48.lfl', '49.lfl', '50.lfl', '51.lfl', '52.lfl', '53.lfl',
            ],
            'ini'           => ['language' => 'es'],
        ],

        'tentacle' => [
            'titulo'        => 'Maniac Mansion 2: El Día del Tentáculo',
            'descripcion'   => 'Bernard, Hoagie y Laverne viajan en el tiempo para impedir que el Tentáculo Púrpura conquiste el mundo. La secuela de Maniac Mansion, con humor desatado y puzzles enrevesados.',
            'año'           => 1993,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'Floppy',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'tentacle',
            'cover'         => '/img/games/tentacle.jpg',
            'files'         => ['tentacle.000', 'tentacle.001', 'monster.sou'],
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],

        // ─── Other LucasArts classics (chronological) ────────────────────
        'zak-mckracken' => [
            'titulo'        => 'Zak McKracken and the Alien Mindbenders',
            'descripcion'   => 'Un periodista de tabloides descubre una conspiración alienígena para reducir la inteligencia humana, ahora con textos en español. Viaja por todo el mundo para salvar la humanidad.',
            'año'           => 1988,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'FM Towns',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'zak',
            'cover'         => '/img/games/zak-mckracken.png',
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
            'ini'           => ['language' => 'es', 'aspect_ratio' => false],
        ],

        'loom' => [
            'titulo'        => 'Loom',
            'descripcion'   => 'Una aventura de fantasía única donde la magia se ejerce tejiendo melodías, ahora con textos en español. Bobbin Threadbare debe salvar a su gremio de tejedores de una oscuridad creciente.',
            'año'           => 1990,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'loom',
            'cover'         => '/img/games/loom.png',
            'files'         => ['000.lfl', '901.lfl', '902.lfl', '903.lfl', '904.lfl', 'disk01.lec', 'track1.mp3'],
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],

        'samnmax' => [
            'titulo'        => 'Sam & Max Hit the Road',
            'descripcion'   => 'El dúo detective más disparatado del mundo investiga la desaparición de un Bigfoot de feria. Una comedia absurda y brillante por la América profunda.',
            'año'           => 1993,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Inglés',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'samnmax',
            'cover'         => '/img/games/samnmax.png',
            'files'         => ['samnmax.000', 'samnmax.001', 'monster.sog'],
        ],

        'dig' => [
            'titulo'        => 'The Dig',
            'descripcion'   => 'Una historia de ciencia ficción co-creada por Steven Spielberg: un equipo de astronautas atrapado en un asteroide alienígena descubre los secretos de una civilización perdida. Aventura atmosférica de LucasArts, doblada al español.',
            'año'           => 1995,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'dig',
            'cover'         => '/img/games/dig.jpg',
            'files'         => [
                'dig.la0', 'dig.la1', 'digmusic.bun', 'digvoice.bun', 'language.bnd',
                'video/ALCOVE.SAN', 'video/ASTTUN.SAN', 'video/DARKCAVE.SAN', 'video/DIGTXT.TRS',
                'video/FONT0.NUT', 'video/FONT1.NUT', 'video/FONT2.NUT', 'video/FONT3.NUT',
                'video/M1.SAN', 'video/M2.SAN', 'video/NEXUSPAN.SAN', 'video/PIGOUT.SAN',
                'video/RTRAM1.SAN', 'video/RTRAM2.SAN', 'video/RTRAM3.SAN', 'video/RTRAM4.SAN',
                'video/RTRAM5.SAN', 'video/SKY.SAN', 'video/SQ1.SAN', 'video/SQ2.SAN',
                'video/SQ3.SAN', 'video/SQ4.SAN', 'video/SQ5.SAN', 'video/SQ6.SAN',
                'video/SQ6SC3.SAN', 'video/SQ7.SAN', 'video/SQ8A.SAN', 'video/SQ8A1.SAN',
                'video/SQ8B.SAN', 'video/SQ8C.SAN', 'video/SQ9.SAN', 'video/SQ10.SAN',
                'video/SQ11.SAN', 'video/SQ12A.SAN', 'video/SQ12B.SAN', 'video/SQ13.SAN',
                'video/SQ14B.SAN', 'video/SQ14SC04.SAN', 'video/SQ14SC07.SAN', 'video/SQ14SC11.SAN',
                'video/SQ14SC14.SAN', 'video/SQ14SC16.SAN', 'video/SQ14SC19.SAN', 'video/SQ14SC22.SAN',
                'video/SQ15A.SAN', 'video/SQ15B.SAN', 'video/SQ17.SAN', 'video/SQ18A.SAN',
                'video/SQ18B.SAN', 'video/SQ18SC15.SAN', 'video/SQ19A.SAN', 'video/SQ19B.SAN',
                'video/SQ19C.SAN', 'video/TOMBDWN1.SAN', 'video/TOMBDWN2.SAN',
                'video/TRAM1.SAN', 'video/TRAM2.SAN', 'video/TRAM3.SAN', 'video/TRAM4.SAN',
                'video/TRAM5.SAN',
            ],
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],

        'ft' => [
            'titulo'        => 'Full Throttle',
            'descripcion'   => 'Ben, líder de la banda motera Polecats, se ve atrapado en una trama de asesinato corporativo. Una aventura grasienta de moteros, carreteras y rock, doblada al español.',
            'año'           => 1995,
            'desarrollador' => 'LucasArts',
            'idioma'        => 'Español',
            'version'       => 'CD Talkie',
            'engine_id'     => 'scumm',
            'scummvm_id'    => 'ft',
            'cover'         => '/img/games/ft.jpg',
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
            'ini'           => ['language' => 'es', 'subtitles' => true],
        ],
    ],
];
