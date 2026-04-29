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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inyecta las cabeceras de aislamiento cross-origin necesarias para habilitar
 * SharedArrayBuffer en ScummVM WebAssembly. Solo se aplica en rutas /gaming/*.
 *
 * COOP + COEP desbloquean SharedArrayBuffer (necesario para hilos Wasm).
 * Sin estas cabeceras, ScummVM multihilo falla silenciosamente en Chrome/Firefox.
 */
class GamingIsolationHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Replace Laravel's strict CSP with a gaming-specific policy.
        // ScummVM Wasm (Emscripten asyncify) requires:
        //   - 'unsafe-eval' + 'wasm-unsafe-eval' → Wasm compile + asm.js fallback
        //   - blob: in script-src → dynamically injected <script> from Blob
        //   - worker-src blob: → Web Workers created via createObjectURL
        //   - data: in img-src + font-src → embedded sprites/fonts in the engine
        //   - media-src blob: → SDL3 audio playback nodes
        // The SecureHeaders middleware already ran (via $next) and set its own
        // Content-Security-Policy. We overwrite it here so ours wins.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            . "script-src 'self' 'unsafe-eval' 'wasm-unsafe-eval' blob:; "
            . "script-src-elem 'self' 'unsafe-eval' blob:; "
            . "script-src-attr 'none'; "
            . "worker-src 'self' blob:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' data:; "
            . "media-src 'self' blob:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self';"
        );

        return $response;
    }
}
