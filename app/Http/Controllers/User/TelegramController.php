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

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    private function telegramInstanceLabel(): string
    {
        return (string) config('services.telegram.instance_label', config('app.name', 'tracker'));
    }

    public function resetToken(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->is($user), 403);

        $oldChatId = $user->telegram_chat_id;

        // Clear link and generate new token
        $user->update([
            'telegram_chat_id'         => null,
            'telegram_group_joined_at' => null,
            'telegram_token'           => 'TRK-' . Str::random(32),
        ]);

        // If the user was linked, notify them and kick from group
        if ($oldChatId) {
            try {
                $telegram = app(TelegramService::class);

                $telegram->sendMessage(
                    "🔴 <b>Cuenta desvinculada</b>\n\nTu enlace con {$this->telegramInstanceLabel()} ha sido eliminado.\nSi quieres volver a vincular, usa el nuevo token desde tu panel de notificaciones.",
                    (string) $oldChatId,
                );

                $telegram->kickUser((string) $oldChatId);
            } catch (\Throwable $e) {
                Log::warning('Telegram: failed to notify/kick on unlink', [
                    'user'    => $user->username,
                    'chat_id' => $oldChatId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->back()
            ->with('success', 'Telegram desvinculado y token regenerado correctamente.');
    }

    public function checkLink(Request $request, User $user): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->is($user), 403);

        return response()->json([
            'linked'       => $user->telegram_chat_id !== null,
            'group_joined' => $user->telegram_chat_id !== null
                ? app(TelegramService::class)->syncLinkedUserMembership($user->fresh())
                : false,
        ]);
    }
}
