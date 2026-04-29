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

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// cspell:ignore orwill

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::upsert([
            [
                'id'      => 1,
                'name'    => 'Normas',
                'content' => '[b]Bienvenido a N.O.B.S (Nuclear Order Bit Syndicate)[/b]

[b]Qué somos[/b]
Un tracker privado de coleccionistas: cine, series, documentales, anime y videojuegos retro. Hecho por la scene, para la scene. Un santuario para preservar colecciones digitales, no una plataforma de distribución.

[b]Normas Fundamentales[/b]

[b]1. Ratio y Seeding[/b]
[list]
[*]Ratio mínimo: [b]0.4[/b] (simbólico; valoramos la preservación, no el beneficio)
[*]Seedea con responsabilidad: mantén el contenido vivo para los demás
[*]Sin tiempos de seed forzados, pero deja películas y series seedeando indefinidamente si tienes capacidad
[*]Las penalizaciones por hit & run existen pero se aplican con criterio
[/list]

[b]2. Estándares de Contenido[/b]
[list]
[*]El contenido debe estar indexado en [b]TMDB[/b], [b]IGDB[/b] u otros proveedores de metadatos oficiales
[*][b]Sin gatekeeping de calidad[/b]: 4K UHD, 1080p, 720p, SD (si es el original) son bienvenidos
[*]Razonamiento: Babylon 5 es SD, el anime de los 80 es 360p. Mejor preservar que rechazar por estándares obsoletos
[*]Videojuegos retro archivados tal cual (ROMs, ISOs, dumps)
[/list]

[b]3. Conducta en la Comunidad[/b]
[list]
[*]Respeta a tus compañeros coleccionistas; esto es solo para staff (por invitación)
[*]Sin redistribución comercial ni monetización
[*]Mantén los datos de tu cuenta en privado
[*]Reporta torrents rotos al staff para su mantenimiento
[/list]

[b]4. Técnico[/b]
[list]
[*]Usa clientes modernos: se recomienda [b]qBittorrent[/b] (Transmission está roto)
[*]Integra con el [b]stack Arr[/b] (Sonarr, Radarr, Lidarr) para indexación automatizada
[*]Usa [b]Singularity/RaW_Suite[/b] para subidas profesionales (opcional pero recomendado)
[*]Las subidas deben pasar el health-check antes de seedear
[/list]

[b]5. Privacidad y Seguridad[/b]
[list]
[*]Activa PEX (Peer Exchange) con cuidado; usa VPN si compartes en redes no confiables
[*]No distribuyas tus archivos .torrent fuera de este tracker
[*]Oculta tu IP en los ajustes de tu cuenta si te preocupa la privacidad
[/list]

[i]¿Dudas? Consulta las [url=https://nobs.rawsmoke.net/pages/2]FAQ[/url] o contacta al staff.[/i]',
            ],
            [
                'id'      => 2,
                'name'    => 'FAQ',
                'content' => '[b]Preguntas Frecuentes[/b]

[b]P: ¿Cómo consigo una invitación?[/b]
R: Los miembros del staff pueden enviar invitaciones. Debes ser conocido en la scene o estar avalado por miembros existentes.

[b]P: ¿Cuál es el requisito de ratio?[/b]
R: Mínimo [b]0.4[/b], pero los ratios son principalmente simbólicos. Priorizamos la preservación. Sube buen contenido, seedea, y listo.

[b]P: ¿Puedo subir torrents?[/b]
R: ¡Sí! Consulta la [url=https://nobs.rawsmoke.net/pages/4]Guía de Subida[/url]. Idealmente procesa los archivos con [url=https://github.com/RawSmokeTerribilus/Singularity]Singularity/RaW_Suite[/url] para mayor consistencia.

[b]P: ¿Qué cliente debo usar?[/b]
R: [b]qBittorrent[/b] (moderno y fiable). Transmission está roto. Intégralo con [b]Sonarr/Radarr/Jackett[/b] para automatización.

[b]P: ¿Cómo automatizo las descargas?[/b]
R:
[list]
[*]Configura [b]Sonarr[/b] para series (indexación vía Jackett)
[*]Configura [b]Radarr[/b] para películas (indexación vía Jackett)
[*]Configura qBittorrent como cliente torrent
[*]Sonarr/Radarr capturan automáticamente los torrents y los envían a qBit
[*]Singularity/RaW_Suite puede post-procesar y optimizar archivos
[/list]

[b]P: ¿Puedo subir con RaW_Suite/Singularity?[/b]
R: ¡Totalmente recomendado! Automatiza: validación de archivos, transcodificación, capturas, generación de MediaInfo y creación de torrent. Ver [url=https://github.com/RawSmokeTerribilus/Singularity]repo Singularity[/url].

[b]P: ¿Es esto legal?[/b]
R: Este tracker es para [b]preservación y colección personal[/b]. No es para distribución ni lucro. Respeta los derechos de autor y úsalo con responsabilidad.

[b]P: ¿Puedo descargar a alta velocidad?[/b]
R: La velocidad depende de los seeders activos. Anima a otros a seedear subiendo contenido tú mismo.

[b]P: ¿Qué formatos están permitidos?[/b]
R: [b]Preferiblemente .mkv[/b] con codec h265. Los remuxes (extraídos de ISO) con todas las pistas de audio y subtítulos son bienvenidos. Ver [url=https://nobs.rawsmoke.net/pages/4]Guía de Subida[/url].

[b]P: Mi torrent aparece como roto. ¿Qué hago?[/b]
R: Repórtalo al staff. Investigaremos y lo reviviremos o eliminaremos.

[i]¿Sigues atascado? Contacta al staff por [b]Mensajes[/b] o [b]Foros[/b].[/i]',
            ],
            [
                'id'      => 3,
                'name'    => 'Clientes Recomendados',
                'content' => '[b]Configuración BitTorrent Recomendada[/b]

[b]Cliente Torrent Principal[/b]

[b]qBittorrent[/b]
[list]
[*]Moderno, ligero, multiplataforma (Linux, Windows, macOS)
[*]Búsqueda integrada, RSS feeds, descarga automática
[*]Se integra perfectamente con Sonarr/Radarr
[*][url=https://www.qbittorrent.org/]Descargar[/url]
[/list]

[b]¿Por qué NO usar Transmission?[/b]
Transmission está desactualizado y tiene bugs graves con los trackers modernos. [b]No lo uses.[/b]

---

[b]Indexación y Automatización (Stack Arr)[/b]

[b]Jackett[/b] - Indexador universal de torrents
[list]
[*]Conecta Sonarr/Radarr con trackers privados
[*]Proporciona integración de búsqueda de torrents
[*][url=https://github.com/Jackett/Jackett]GitHub[/url]
[/list]

[b]Sonarr[/b] - Gestor automatizado de series
[list]
[*]Monitoriza series, captura episodios automáticamente, envía a qBit
[*]Organiza archivos, renombra, integra metadatos
[*][url=https://sonarr.tv/]Web[/url]
[/list]

[b]Radarr[/b] - Gestor automatizado de películas
[list]
[*]Como Sonarr pero para películas
[*]Descarga automática, organización y optimización
[*][url=https://radarr.video/]Web[/url]
[/list]

[b]Lidarr[/b] - Gestor automatizado de música (extra)
[list]
[*]Para colecciones musicales
[*][url=https://lidarr.audio/]Web[/url]
[/list]

---

[b]Herramientas de Subida (Opcionales pero Recomendadas)[/b]

[b]Singularity / RaW_Suite[/b]
[list]
[*]Kit completo de automatización de subidas
[*]Validación de archivos, transcodificación, capturas, MediaInfo, creación de torrent
[*]Instalación en dos comandos
[*][url=https://github.com/RawSmokeTerribilus/Singularity]GitHub[/url]
[/list]

---

[b]Mejoras Opcionales[/b]

[b]Prowlarr[/b] - Gestor centralizado de indexadores (alternativa moderna a Jackett)
[url=https://prowlarr.com/]Web[/url]

[b]TDarr[/b] - Transcodificación distribuida (para optimización masiva de archivos)
[url=https://www.tdarr.io/]Web[/url]

[b]Plex / Jellyfin[/b] - Servidores multimedia (organiza y reproduce tu colección)
[url=https://www.plex.tv/]Plex[/url] | [url=https://jellyfin.org/]Jellyfin[/url]

---

[i]¿Necesitas ayuda con la configuración? ¡Contacta al staff![/i]',
            ],
            [
                'id'      => 4,
                'name'    => 'Guía de Subida',
                'content' => '[b]Cómo Subir Torrents a N.O.B.S[/b]

[b]Requisitos de Archivo[/b]

[b]Formato[/b]
[list]
[*][b]Principal: contenedor Matroska (.mkv)[/b]
[*]Remux: extracción completa de ISO con todas las pistas de audio y subtítulos
[*]Codec: [b]H.265 (HEVC) preferido[/b], H.264 aceptable
[/list]

[b]Estándares de Calidad[/b]
[list]
[*]Sin mínimo de calidad estricto (ver [url=https://nobs.rawsmoke.net/pages/1]Normas[/url])
[*]Para series: mínimo 720p (excepciones para contenido vintage/archival)
[*]Para películas: variable (4K UHD → 1080p → 720p → SD aceptado)
[*]Debe ser [b]fuente original o transcodificado legítimamente[/b]
[/list]

[b]Health Check[/b]
[list]
[*]Antes de subir, valida los archivos:
  [list]
    [*]MediaInfo reporta streams de vídeo/audio válidos
    [*]Sin corrupción en la reproducción (primeros 5 min, mitad, final)
    [*]Sincronización de subtítulos verificada (si se incluyen)
  [/list]
[/list]

---

[b]Metadatos y Descripción[/b]

[b]Capturas de Pantalla (Mínimo 4)[/b]
[list]
[*]Cubre: escena inicial, momento clave, diálogo, créditos
[*]Formato: .jpg o .png, mínimo 1280x720
[*]Incluir en la descripción del torrent
[/list]

[b]MediaInfo[/b]
[list]
[*]Incluye la salida completa de la herramienta [b]MediaInfo[/b]
[*]Muestra: codec de vídeo, bitrate, resolución, pistas de audio, subtítulos
[*]Copiar y pegar en la descripción del torrent
[/list]

[b]Info de Encode (si está transcodificado)[/b]
[list]
[*]Configuración usada: codec, preset, CRF/bitrate, fuente
[*]Ejemplo: [i]"x265 10-bit, preset slow, CRF 20, desde 1080p H.264"[/i]
[*]Incluir en la descripción
[/list]

[b]Trailer (Solo Películas)[/b]
[list]
[*]Enlace al trailer oficial o vídeo embebido
[*]Preferiblemente de YouTube o fuente del estudio
[/list]

[b]IDs de Metadatos[/b]
[list]
[*][b]ID de TMDB:[/b] (para películas/series)
[*][b]ID de IGDB:[/b] (para juegos)
[*]Permite al tracker auto-rellenar pósters, descripciones y valoraciones
[/list]

---

[b]Proceso de Subida[/b]

[b]Opción A: Subida Manual (Simple)[/b]
[list]
[*]1. Haz clic en [b]Subir Torrent[/b] en el menú superior
[*]2. Rellena título y descripción (con MediaInfo + capturas)
[*]3. Selecciona la categoría (Película, Serie, Juego, etc.)
[*]4. Elige las etiquetas de resolución/calidad
[*]5. Envía el archivo .torrent
[*]6. Empieza a seedear desde el cliente
[/list]

[b]Opción B: RaW_Suite/Singularity (Automatizado)[/b]
[list]
[*]Instala: [url=https://github.com/RawSmokeTerribilus/Singularity]Singularity[/url]
[*]Dos comandos lo configuran todo
[*]Automáticamente:
  [list]
    [*]Valida archivos
    [*]Genera capturas de pantalla
    [*]Crea MediaInfo
    [*]Construye metadatos del torrent
    [*]Publica en el tracker
    [*]Inicia el seeding
  [/list]
[*][b]Muy recomendado[/b] para subidas consistentes y profesionales
[/list]

---

[b]Buenas Prácticas[/b]

[list]
[*][b]Seedea indefinidamente:[/b] Mantén vivo el contenido popular
[*][b]Usa VPN si es necesario:[/b] Seguridad personal (fuera del tracker N.O.B.S)
[*][b]Preferir contenido original:[/b] Usa fuentes oficiales o compras legítimas
[*][b]Organiza bien:[/b] Usa las convenciones de nombres de Sonarr/Radarr
[*][b]Prueba antes de subir:[/b] Verifica la reproducción en tu hardware
[/list]

[i]¿Dudas? Consulta las [url=https://nobs.rawsmoke.net/pages/2]FAQ[/url] o contacta al staff.[/i]',
            ],
            [
                'id'      => 5,
                'name'    => 'Códigos del Tracker',
                'content' => '[b]Respuestas del Announce y Códigos del Tracker[/b]

[b]Códigos de Estado HTTP[/b]

[b]200 OK[/b]
Announce exitoso. El servidor devolvió la lista de peers.

[b]400 Bad Request[/b]
Consulta de announce inválida. Revisa: info_hash, peer_id, parámetros del evento.

[b]401 Unauthorized[/b]
Passkey inválida o usuario no autenticado. Vuelve a comprobar la URL del tracker en el cliente.

[b]403 Forbidden[/b]
Cuenta suspendida o IP bloqueada. Contacta al staff.

[b]404 Not Found[/b]
Torrent no encontrado en la base de datos del tracker. Puede haber sido eliminado.

[b]500 Internal Server Error[/b]
Problema temporal del tracker. Reintenta en unos segundos.

[b]503 Service Unavailable[/b]
Tracker en mantenimiento. Comprueba la página de estado.

---

[b]Eventos de Torrent[/b]

[b]started[/b]
Enviado cuando el cliente se conecta al tracker por primera vez.

[b]stopped[/b]
Enviado cuando el cliente cierra el torrent o se apaga.

[b]completed[/b]
Enviado cuando la descarga finaliza (100%).

[b]empty[/b]
Polling continuo; sin cambio de estado.

---

[b]¿Problemas de Conexión?[/b]

[list]
[*][b]Revisa tu passkey:[/b] Cuenta → Ajustes → Copiar URL del tracker
[*][b]Verifica el firewall:[/b] Permite conexiones HTTP salientes
[*][b]Prueba manualmente:[/b] Pega la URL de announce en el navegador (ver respuesta)
[*][b]Revisa la config del cliente:[/b] Asegúrate de que la URL incluye la passkey
[*][b]Contacta al staff[/b] si persiste
[/list]

[i]Detalles técnicos: Este tracker usa el Protocolo de Announce de UNIT3D (compatible con la especificación estándar de BitTorrent).[/i]',
            ],
            [
                'id'      => 6,
                'name'    => 'Terms Of Use',
                'content' => '*All references to "we", "us" or "our" refer to the site owner(s).

Welcome to our website located at '.config('app.url').' (this "Site")! This Site allows you to:
(a) participate in interactive features that we may make available from time to time through the Site; or
(b) simply view this Site (collectively, the "Services"). We prepared this Terms of Use Agreement (this "Agreement") to help explain the terms that apply to your use of this Site and Services. Provisions in these terms that apply to the Site shall also apply to the Services, and provisions in these terms that apply to the Services shall also apply to this Site.In order to use the interactive features on this Site, you must first register with us through our on-line registration process on this Site. Regardless of how you decide to use this Site, your conduct on this Site and use of the Services is governed by this Agreement.YOU ACCEPT THIS AGREEMENT BY USING THIS SITE AND/OR THE SERVICES IN ANY MANNER. IF YOU DO NOT AGREE TO ALL THESE TERMS THEN DO NOT USE THIS WEBSITE.
[b]
1. Membership
[/b]When you use this Site, you represent that:
(a) the information you submit is truthful and accurate;
(b) you will update your contact information if it changes so that we can contact you;
(c) your use of this Site and your use of services available on this Site do not violate any applicable law or regulation;
(d) you are 13 years of age or older; and
(e) you will comply with the rules for on-line conduct and making Contributions (as defined in Section 2 below) to this Site, as discussed in Section 2 below. You further represent and warrant that you will comply with all local rules regarding on-line conduct and acceptable Contributions

[b]2. User conduct
[/b]This Site may provide fora and other features for communication. Please read our Privacy Policy, available at '.config('app.url').'/p/privacy.8 to understand your privacy protections. You are entirely responsible for the content of, and any harm resulting from, any of your postings or submissions to this Site (collectively, "Contributions"). You understand that we may also make the Contributions you submit available to other websites and businesses (such other websites and businesses, the “Network”) where they may be used. Any licenses or other rights grants, and promises, representations and warranties you make about the Contributions with respect to this Site or the Services, you are also hereby making with respect to the use of such Contributions through and by the Network (i.e., wherever you are granting a license or other rights grant, or making a promise, representation or warranty, with respect to this Site or the Services, that grant, promise, representation or warranty shall be deemed and construed to also apply to the Network). When you create or make available a Contribution, you represent and warrant that you:own or have sufficient rights to post your Contributions on or through this Site;will not post Contributions that violate our or any other person’s privacy rights, publicity rights, intellectual property rights (including without limitation copyrights), confidentiality or contract rights;have fully complied with any third-party licenses relating to Contributions, agree to pay all royalties, fees and any other monies owning any person by reason of Contributions that you posted to or through this Site;will not post or submit Contributions that:
(i) are defamatory, damaging, disruptive, unlawful, inappropriate, offensive, inaccurate, pornographic, vulgar, indecent, profane, hateful, racially or ethnically offensive, obscene, lewd, lascivious, filthy, threatening, excessively violent, harassing, or otherwise objectionable;
(ii) incite, encourage or threaten immediate physical harm against another, including but not limited to, Contributions that promote racism, bigotry, sexism, religious intolerance or harm against any group or individual; or
(iii) contain material that solicits personal information from anyone under 13 or exploits anyone in a sexual or violent manner;will not post or submit Contributions that contain advertisements or solicit any person to buy or sell products or services (other than our products and services,will not use this Site for any unauthorized purpose including collecting usernames and/or email addresses of other users by electronic or other means for the purpose of sending unsolicited email or other electronic communications, or engaging in unauthorized framing of, or linking to, this Site without our express written consent;will not post or submit Contributions that constitute, contain, install or attempt to install or promote spyware, malware or other computer code, whether on our or others’ computers or equipment, designated to enable you or others to gather information about or monitor the on-line or other activities of another party;will not transmit chain letters, bulk or junk email or interfere with, disrupt, or create an undue burden on this Site or the networks or services connected to this Site, including without limitation, hacking into this Site, or using the system to send unsolicited or commercial emails, bulletins, comments or other communications; orwill not impersonate any other person or entity, sell or let others use your profile or password, provide false or misleading identification or address information, or invade the privacy, or violate the personal or proprietary right, of any person or entity.
[b]
3. Grant of License to Us for Contributions
[/b]We do not claim any ownership right in the Contributions that you post on or through this Site. After posting your Contributions on this Site, you continue to retain any rights you may have in your Contributions, including any intellectual property rights or other proprietary rights associated with your Contributions, subject to the license you grant to us below.By making a Contribution to this Site, you grant us a perpetual, non-exclusive (meaning you are free to license your Contribution to anyone else in addition to us), fully-paid, royalty-free (meaning that neither we nor anyone who directly or indirectly receives the Contribution from us are required to pay you to use your Contribution), sublicensable (so that we can distribute the Contributions to third parties, regardless of whether through this Site, through our other products, or through other sites or products offered by our affiliates)) and worldwide (because the Internet and this Site are global in reach) license to use, modify, create derivative works of, publicly perform, publicly display, reproduce and distribute the Contribution in connection with this Site and other websites and businesses, or the promotion thereof in any media formats and through any media channels now known or hereafter devised.If you provides us with any feedback (e.g. suggested improvements, corrections etc.) about the Site or Services (“Feedback”), you assign all right, title and interest in and to such Feedback to us and acknowledge that we will be entitled to use, including without limitation, implement and exploit, any such Feedback in any manner without any restriction or obligation. You further acknowledge and agree that we are not obligated to act on such Feedback.

[b]4. Grant of License to You to use Contributions for Personal, Non-Commercial Purposes

[/b][b]4.1 License.[/b]We grant you a non-exclusive license to use and copy other users’ Contributions solely for personal, non-commercial purposes subject to the restrictions set forth herein.

[b]4.2 License Restrictions.[/b]   
4.2.1 Retention of IP Notices. If you download, copy or print a copy of the Materials (as defined in Section 6 below) for your own personal use, you must retain all trademark, copyright and other proprietary notices contained in and on the materials.   
4.2.2 No Circumvention of IP Protection Mechanisms. You shall not either directly or through the use of any device, software, internet site, web-based service or other means remove, alter, bypass, avoid, interfere with, or circumvent any copyright, trademark, or other proprietary notices marked on Contributions or any digital rights management mechanism, device, or other content protection or access control measure associated with Contributions or the Site.   
4.2.3 No Unauthorized Copying, Broadcasting or Screen Scraping. You shall not either directly or through the use of any device, software, internet site, web-based service or other means copy, download, reproduce, duplicate, archive, distribute, upload, publish, modify, translate, broadcast, perform, display, sell, transfer, rent, sub-license, transmit or retransmit Contributions except as permitted in Section 4.1.   
4.2.4 No Indexing. Furthermore, you may not create, recreate, distribute or advertise an index of any Contributions unless authorized by us in writing.   
4.2.5 No Commercial Use of Contributions. You may not build a business utilizing the Contributions, whether or not for profit. Contributions covered by these restrictions include without limitation any text, graphics, layout, interface, logos, photographs, audio and video materials, and stills.   
4.2.6 No Derivative Works. In addition, you are strictly prohibited from creating derivative works or materials that otherwise are derived from or based on Contributions in any way, including montages, wallpaper, desktop themes, greeting cards, and merchandise, unless it is expressly permitted by us in writing. This prohibition applies even if you intend to give away the derivative materials free of charge.
[b]
5. Use and Protection of Account Number and Password
[/b]We may ask you to create a username and password. You are responsible for maintaining the confidentiality of your account number and password, if applicable. You are responsible for all uses of your account, whether or not actually or expressly authorized by you. When you use the Site or Services we ask you to use the Site and/or Services in a reasonable way that does not negatively affect other users ability to use the Site or Services.

[b]6. Our Intellectual Property Rights[/b]Content on this Site ("Materials"), the trademarks, service marks, and logos contained on this Site ("Marks"), is owned by or licensed to us and is subject to copyright and other intellectual property rights under United States and foreign laws and international conventions. We reserve all rights not expressly granted in and to this Site and the Materials. You agree that you will not circumvent, disable or otherwise interfere with security related features of this Site or features that:
(a) prevent or restrict use or copying of any Materials or
(b) enforce limitations on use of this Site or the Materials on this Site. You further agree not to access this Site by any means other than through the interface that we provide, unless otherwise specifically authorized by us in a separate written agreement.
[b]
7. Our Management of this Site/User Misconduct[/b]   
7.1 Our Site Management. We may, but are not required to:
(a) monitor or review this Site for violations of this Agreement and for compliance with our policies;
(b) report to law enforcement authorities and/or take legal action against anyone who violates this Agreement;
(c) refuse, restrict access to or the availability of, or remove or disable any Contribution or any portion thereof without prior notice to you; and/or
(d) manage this Site in a manner designed to protect our and third parties’ rights and property or to facilitate the proper functioning of this Site.   
7.2 Our Right to Terminate Users. Without limiting any other provision of this Agreement, we reserve the right to, in our sole discretion and without notice or liability deny access to and use of this Site to any person for any reason or for no reason at all, including without limitation for breach of any representation, warranty or covenant contained in this Agreement, or of any applicable law or regulation.   
7.3 Risk of Harm. Please note that there are risks, including but not limited to the risk of physical harm, of dealing with strangers, including persons who may be acting under false pretenses. Please choose carefully the information you post on this Site and that you give to other Site users. You are discouraged from publicly posting your full name, telephone numbers, street addresses or other information that identifies you or allows strangers to find you or steal your identity. Despite this prohibition, other people’s information may be offensive, harmful or inaccurate, and in some cases will be mislabeled or deceptively labeled. You assume all risks associated with dealing with other users with whom you come in contact through this Site. We expect that you will use caution and common sense when using this Site.
[b]
8. Copyright Policy
[/b]You are solely responsible for the content, including but not limited to photos, profiles information, messages, search results edits, and other content that you upload, publish or display (hereinafter, "submit") on or through the Service, or transmit to or share with other users. You may not submit content to the Service that you did not create or that you not have permission to submit. For submissions to search results pages, you may not submit content that is not compatible with the license used by the particular project of the Service. You understand and agree that the others may, but are not obligated to, edit, delete or remove (without notice) any content from the Service, for any reason or no reason. You are solely responsible at your sole cost and expense for creating backup copies and replacing any content you post or store on the Service or provide to the Company.
[b]Claims of Copyright Infringement[/b]
[i] For claims of copyright infringement, please contact us at example@example.com This site is in compliance with DCMA takedown notices.[/i]
[b]
9. Modifications
[/b]The Internet and technology are rapidly changing. Accordingly, we may modify this Agreement from time to time without notice and it is your responsibility to read it carefully and review any changes that may have been made. Since changes will be posted on this page, we encourage you to check this page regularly. Your continued use of this Site or the Services constitutes your agreement with such modifications.
[b]
10. Non-commercial Use by Users
[/b]The Site is made available to you only for your personal use, and you may not use the Site or any Contributions or Materials in connection with any commercial endeavors except those that are specifically approved by us in writing.
[b]
11. Third Party Sites
[/b]This Site may contain links to other websites ("Third Party Sites"). We do not own or operate the Third Party Sites, and we have not reviewed, and cannot review, all of the material, including goods or services, made available through Third-Party Sites. The availability of these links on this Site does not represent, warrant or imply that we endorse any Third Party Sites or any materials, opinions, goods or services available on them. Third party materials accessed through or used by means of the Third Party Sites may also be protected by copyright and other intellectual property laws. THIS AGREEMENT DOES NOT APPLY TO THIRD PARTY SITES. BEFORE VISITING A THIRD PARTY SITE BY MEANS OF THIS SITE OR A LINK LOCATED ON THIS SITE, USERS SHOULD REVIEW THE THIRD PARTY SITE’S TERMS AND CONDITIONS, PRIVACY POLICY AND ALL OTHER SITE DOCUMENTS, AND INFORM THEMSELVES OF THE REGULATIONS, POLICIES AND PRACTICES OF THESE THIRD PARTY SITES.
[b]
12. Disputes Between Users
[/b]You are solely responsible for your conduct. You agree that we cannot be liable for any dispute that arises between you and any other user.We may run advertisements and promotions from third parties on the Site. Your correspondence or business dealings with, or participation in promotions of, advertisers other than us found on or through the Site, including payment and delivery of related goods or services, and any other terms, conditions, warranties or representations associated with such dealings, are solely between You and such advertiser. We are not responsible or liable for any loss or damage of any sort incurred as the result of any such dealings or as the result of the presence of such advertisers on the Site.
[b]
13. Disclaimers
[/b]ALL CONTRIBUTIONS OR ANY OTHER MATERIALS OR ITEMS PROVIDED THROUGH THIS SITE BY US ARE PROVIDED "AS IS" AND "AS AVAILABLE," WITHOUT WARRANTY OR CONDITIONS OF ANY KIND. By operating this Site, WE DO not represent or imply that WE ENDORSE any Contributions or any other Materials or items available on or linked to by this Site, including without limitation, content hosted on third party Sites, or that WE BELIEVE Contributions or any other Materials or items to be accurate, useful or non-harmful. WE cannot guarantee and do not promise any specific results from use of this Site. No advice or information, whether oral or written, obtained by you from US or this Site shall create any warranty not expressly stated In THIS AGREEMENT.

YOU AGREE THAT YOUR USE OF THIS SITE AND SERVICES WILL BE AT YOUR SOLE RISK. WE DO NOT WARRANT THAT THE SITE OR SERVICES WILL BE AVAILABLE FOR USE, AND WE DO NOT MAKE ANY WARRANTIES AS TO THE QUALITY OF THE SITE, SERVICES OR ITS CONTENT. TO THE FULLEST EXTENT PERMITTED BY LAW, WE AND EACH OF OUR ADVERTISERS, LICENSORS, SUPPLIERS, OFFICERS, DIRECTORS, INVESTORS, EMPLOYEES, AGENTS, SERVICE PROVIDERS AND OTHER CONTRACTORS DISCLAIM ALL WARRANTIES, EXPRESS OR IMPLIED IN CONNECTION WITH THIS SITE AND YOUR USE THEREOF.WE MAKE NO WARRANTIES OR REPRESENTATIONS ABOUT THE ACCURACY, RELIABILITY, TIMELINESS OR COMPLETENESS OF THIS SITE\'S CONTENT, THE CONTENT OF ANY SITE LINKED TO THIS SITE, CONTRIBUTIONS, INFORMATION OR ANY OTHER ITEMS OR MATERIALS ON THIS SITE OR LINKED TO BY THIS SITE. WE ASSUME NO LIABILITY OR RESPONSIBILITY FOR ANY:

(A) ERRORS, MISTAKES OR INACCURACIES OF CONTENT, CONTRIBUTIONS AND MATERIALS,
(B) PERSONAL INJURY OR PROPERTY DAMAGE, OF ANY NATURE WHATSOEVER, RESULTING FROM YOUR ACCESS TO AND USE OF OUR SITE OR SERVICES,
(C) ANY ILLEGAL OR UNAUTHORIZED ACCESS TO OR USE OF OUR SECURE SERVERS AND ALL PERSONAL INFORMATION STORED THEREIN,
(D) ANY INTERRUPTION OR CESSATION OF TRANSMISSION TO OR FROM THIS SITE,
(E) ANY BUGS, VIRUSES, TROJAN HORSES, OR THE LIKE, WHICH MAY BE TRANSMITTED TO OR THROUGH THIS SITE BY ANY THIRD PARTY, AND/OR
(F) ANY ERRORS OR OMISSIONS IN ANY CONTRIBUTIONS, CONTENT AND MATERIALS OR FOR ANY LOSS OR DAMAGE OF ANY KIND INCURRED AS A RESULT OF THE USE OF ANY CONTENT, CONTRIBUTIONS, OR MATERIALS POSTED, TRANSMITTED, OR OTHERWISE MADE AVAILABLE VIA THIS SITE.WE WILL NOT BE LIABLE TO YOU FOR ANY LOSS OF ANY DATA (INCLUDING CONTENT) OR FOR LOSS OF USE OF THIS SITE.SOME STATES OR JURISDICTIONS DO NOT ALLOW THE LIMITATION OR EXCLUSION OF CERTAIN WARRANTIES, OR THE EXCLUSION OR LIMITATION OF CERTAIN DAMAGES. IF YOU RESIDE IN ONE OF THESE STATES OR JURISDICTIONS, THE ABOVE LIMITATIONS OR EXCLUSIONS MAY NOT APPLY TO YOU.',
            ],
        ], ['id'], ['name', 'content']);
    }
}
