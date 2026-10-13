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

use App\Jobs\Middleware\CuotaDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use DateTimeInterface;

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

    /**
     * Correo de campaña: respeta la cuota diaria (sale mañana si hoy no cabe).
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new CuotaDeCorreo()];
    }

    /**
     * Cada espera por la cuota cuenta un intento y el worker va con --tries=1:
     * sin esto, el primer aplazamiento lo daria por fallido.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(7);
    }

    public function build(): static
    {
        return $this->text('emails.hitrun-reminder')
            ->subject($this->asunto.' - '.config('other.title'));
    }
}
