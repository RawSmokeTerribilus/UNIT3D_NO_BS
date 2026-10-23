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

use Illuminate\Support\Facades\Route;

// Puerta de nginx (auth_request) del origen propio de la Arena. Sin grupo de middleware: sin
// sesión (no crea una por cada fichero del juego) ni throttle. Solo la llama nginx (internal).
Route::get('/__arena/pase', [App\Http\Controllers\ArenaController::class, 'pase']);
