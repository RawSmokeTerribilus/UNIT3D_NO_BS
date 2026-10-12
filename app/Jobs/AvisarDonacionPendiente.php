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

namespace App\Jobs;

use App\Enums\ModerationStatus;
use App\Models\Donation;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Privado por Telegram a los Owners cuando entra una donación.
 *
 * Los Owners son los únicos que pueden aprobarla, así que el aviso va a ellos y
 * no al grupo de staff. Sólo a quien tenga la cuenta vinculada
 * (`telegram_chat_id`); el bot no puede escribir a quien no le ha hablado.
 *
 * El justificante NO va en el mensaje: un código de cheque regalo es dinero, y
 * un chat de Telegram no es sitio para él. Se mira en el panel.
 *
 * En cola para que un Telegram lento o caído no frene el envío del donante.
 */
class AvisarDonacionPendiente implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public Donation $donation)
    {
    }

    public function handle(TelegramService $telegram): void
    {
        $donation = $this->donation->loadMissing(['user', 'package', 'gateway']);

        $owners = User::query()
            ->whereNotNull('telegram_chat_id')
            ->whereHas('group', fn ($query) => $query->where('is_owner', '=', true))
            ->get();

        if ($owners->isEmpty()) {
            Log::info('AvisarDonacionPendiente: ningún Owner con Telegram vinculado, el aviso no sale.');

            return;
        }

        $e = fn (?string $texto) => htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_HTML5);
        $importe = number_format((float) $donation->package->cost, 2, ',', '.').' €';

        // Con quién hablar si algo sale mal, que pasa mucho: Ko-fi sin cuenta no
        // da código de transacción, gente que se equivoca de importe… El enlace
        // `tg://user?id=` abre el privado aunque no tenga @usuario público; el
        // chat_id de un privado ES el id de usuario de Telegram.
        $donante = $donation->user;

        if ($donante->telegram_chat_id !== null) {
            $tg = $donante->telegram_username
                ? '<a href="tg://user?id='.$e((string) $donante->telegram_chat_id).'">@'.$e(ltrim($donante->telegram_username, '@')).'</a>'
                : '<a href="tg://user?id='.$e((string) $donante->telegram_chat_id).'">abrir privado</a> (sin @usuario)';
        } else {
            $tg = 'sin Telegram vinculado';
        }

        $pendientes = Donation::query()->where('status', '=', ModerationStatus::PENDING)->count();

        $texto = '💸 <b>['.$e(config('services.telegram.instance_label')).'] Donación pendiente</b>'
            ."\n".$e($donante->username).' · '.$e($donation->package->name).' · '.$importe
            ."\nMétodo: ".$e($donation->gateway?->name ?? '—')
            ."\nTelegram: ".$tg
            ."\n\n".'<a href="'.$e(route('staff.donations.index')).'">Revisar en el panel</a>'
            .($pendientes > 1 ? ' · '.$pendientes.' pendientes en total' : '');

        foreach ($owners as $owner) {
            if (!$telegram->sendMessage($texto, (string) $owner->telegram_chat_id)) {
                Log::warning('AvisarDonacionPendiente: Telegram no aceptó el aviso.', [
                    'donation_id' => $donation->id,
                    'owner_id'    => $owner->id,
                ]);
            }
        }
    }
}
