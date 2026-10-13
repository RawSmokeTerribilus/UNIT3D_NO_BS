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

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/**
 * Copia por correo del mensaje de hit and run del staff (App\Services\HitRunContacts).
 * Texto plano: es una carta, no un boletin.
 */
class HitRunReminder extends Mailable
{
    use Queueable;

    public function __construct(public string $asunto, public string $cuerpo)
    {
    }

    public function build(): static
    {
        return $this->text('emails.hitrun-reminder')
            ->subject($this->asunto.' - '.config('other.title'));
    }
}
