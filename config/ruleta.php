<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra derivada de UNIT3D Community Edition (HDInnovations), de la que hereda
 * la licencia GNU AGPL v3.0.
 */

/*
|--------------------------------------------------------------------------
| La ruleta del mirón — destinos de la casilla 🎲
|--------------------------------------------------------------------------
|
| Vive en «Sólo quiero mirar» de la página de donar. Cuando sale 🎲 se manda
| al mirón a una página de aquí, al azar y SIN REPETIR hasta haberlas visto
| todas (la bolsa se guarda en su navegador). Con seis destinos siempre
| acababa en peticiones; por eso son muchos.
|
| Cada entrada: nombre de ruta, sus parámetros y el chiste. Una ruta que no
| exista en este entorno se salta sola (Route::has en la vista), así que se
| puede portar el fichero tal cual.
|
| Sólo páginas de dentro y que un miembro pueda abrir: nada del panel de
| staff, nada que cree o borre cosas al visitarlo.
|
*/

return [
    'destinos' => [
        ['ruta' => 'torrents.index', 'texto' => 'A los torrents. Ya que miras, descarga algo.'],
        ['ruta' => 'trending.index', 'texto' => 'A lo que está de moda. Tú no: lo de la lista.'],
        ['ruta' => 'missing.index', 'texto' => 'A lo que falta. Ya que miras, mira esto.'],
        ['ruta' => 'requests.index', 'texto' => 'A peticiones. Como la de la ruleta, mira.'],
        ['ruta' => 'polls.index', 'texto' => 'A las encuestas, por si quieres pedir otra ruleta.'],
        ['ruta' => 'forums.index', 'texto' => 'Al foro. Allí también se mira mucho y se escribe poco.'],
        ['ruta' => 'topics.index', 'texto' => 'A los temas del foro. Alguno habrá que no hayas leído.'],
        ['ruta' => 'posts.index', 'texto' => 'A los últimos mensajes del foro. Cotilla.'],
        ['ruta' => 'wikis.index', 'texto' => 'A la wiki. Leer también es mirar.'],
        ['ruta' => 'pages.show', 'parametros' => ['page' => 1], 'texto' => 'A las normas. Nadie las lee, así que eres especial.'],
        ['ruta' => 'pages.show', 'parametros' => ['page' => 2], 'texto' => 'A las preguntas frecuentes. Seguro que la tuya está.'],
        ['ruta' => 'pages.index', 'texto' => 'A todas las páginas del sitio. Todas. Disfruta.'],
        ['ruta' => 'about', 'texto' => 'A «Acerca de». Por si no sabías dónde estabas.'],
        ['ruta' => 'client_blacklist', 'texto' => 'A la lista negra de clientes. Comprueba que el tuyo no esté.'],
        ['ruta' => 'stats', 'texto' => 'A las estadísticas. Números para mirar sin parar.'],
        ['ruta' => 'seeders', 'texto' => 'A los que más siembran. Inspírate.'],
        ['ruta' => 'leechers', 'texto' => 'A los que más se llevan. No te inspires.'],
        ['ruta' => 'bankers', 'texto' => 'A los más ricos en BON. Aquí mirar no da puntos.'],
        ['ruta' => 'seedtime', 'texto' => 'A los que más tiempo siembran. Paciencia de monje.'],
        ['ruta' => 'dead', 'texto' => 'A los torrents muertos. Resucita alguno, valiente.'],
        ['ruta' => 'dying', 'texto' => 'A los moribundos. Una semilla les vendría de lujo.'],
        ['ruta' => 'torrent-reseed.index', 'texto' => 'A los que piden resiembra. Tu momento de ser héroe.'],
        ['ruta' => 'themes', 'texto' => 'A los temas más usados. El tuyo es el mejor, claro.'],
        ['ruta' => 'yearly_overviews.index', 'texto' => 'Al resumen del año. Spoiler: hubo torrents.'],
        ['ruta' => 'subtitles.index', 'texto' => 'A los subtítulos. Para mirar entendiendo.'],
        ['ruta' => 'playlists.index', 'texto' => 'A las listas. Alguien hizo una para ti. O no.'],
        ['ruta' => 'mediahub.index', 'texto' => 'Al mediahub. Mirar en modo enciclopedia.'],
        ['ruta' => 'events.index', 'texto' => 'A los eventos. A lo mejor hay alguno.'],
        ['ruta' => 'gaming.index', 'texto' => 'A la sala de juegos. Esta ruleta no cuenta.'],
        ['ruta' => 'retroarch.index', 'texto' => 'A lo retro. Antes se miraba con tubo de rayos catódicos.'],
        ['ruta' => 'swarm.graph', 'texto' => 'Al enjambre. Mira cómo zumba.'],
        ['ruta' => 'tickets.create', 'texto' => 'A abrir un ticket. Para quejarte de la ruleta, por ejemplo.'],
        ['ruta' => 'home.index', 'texto' => 'A la portada. Volver al principio también es un viaje.'],
        ['ruta' => 'donations.index', 'texto' => 'Destino aleatorio: aquí mismo. Qué cosas.'],
    ],
];
