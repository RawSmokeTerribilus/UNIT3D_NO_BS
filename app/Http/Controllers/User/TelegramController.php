<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    public function resetToken(Request $request)
    {
        $user = $request->user();
        $oldChatId = $user->telegram_chat_id;

        // Clear link and generate new token
        $user->update([
            'telegram_chat_id' => null,
            'telegram_token'   => 'TRK-' . Str::random(32),
        ]);

        // If the user was linked, notify them and kick from group
        if ($oldChatId) {
            try {
                $telegram = app(TelegramService::class);

                $telegram->sendMessage(
                    "🔴 <b>Cuenta desvinculada</b>\n\nTu enlace con NOBS ha sido eliminado.\nSi quieres volver a vincular, usa el nuevo token desde tu panel de notificaciones.",
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

    public function checkLink(Request $request)
    {
        return response()->json([
            'linked' => $request->user()->telegram_chat_id !== null,
        ]);
    }
}
