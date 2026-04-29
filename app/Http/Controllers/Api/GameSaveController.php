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

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class GameSaveController extends Controller
{
    private const VALID_GAME_IDS = ['mi1-vga', 'mi2-talkie', 'maniac-mansion', 'loom', 'zak-mckracken', 'indy-atlantis', 'samnmax'];

    /**
     * Devuelve el manifiesto de partidas guardadas del usuario para un juego.
     * El frontend lo usa para restaurar el VFS de Emscripten al arrancar.
     */
    public function manifest(string $gameId): JsonResponse
    {
        if (! in_array($gameId, self::VALID_GAME_IDS, true)) {
            return response()->json(['error' => 'Juego no válido.'], 404);
        }

        $saves = GameSave::query()
            ->where('user_id', Auth::id())
            ->where('game_id', $gameId)
            ->latest('updated_at')
            ->get(['filename', 'file_size', 'updated_at'])
            ->unique('filename')
            ->values()
            ->map(fn ($save) => [
                'filename'     => $save->filename,
                'file_size'    => $save->file_size,
                'download_url' => route('gaming.saves.download', [
                    'gameId'   => $gameId,
                    'filename' => $save->filename,
                ]),
            ]);

        return response()->json(['files' => $saves]);
    }

    /**
     * Recibe un blob binario de partida desde el VFS de Emscripten y lo persiste.
     * Llamado automáticamente cuando ScummVM cierra un descriptor de archivo de guardado.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'save_blob' => ['required', 'file', 'max:512'],  // máx. 512 KB por partida
            'game_id'   => ['required', 'string', 'in:'.implode(',', self::VALID_GAME_IDS)],
        ]);

        $user    = Auth::user();
        $file    = $request->file('save_blob');
        $gameId  = $request->input('game_id');
        $filename = $file->getClientOriginalName();

        // Sanitizar el nombre de archivo para evitar path traversal
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);

        if (empty($filename) || $filename === '.') {
            return response()->json(['error' => 'Nombre de archivo inválido.'], 422);
        }

        $storagePath = sprintf('saves/%d/%s', $user->id, $gameId);
        $fullPath    = $storagePath.'/'.$filename;

        Storage::disk('local')->putFileAs($storagePath, $file, $filename);

        // Crear o actualizar el registro en base de datos
        GameSave::updateOrCreate(
            [
                'user_id'  => $user->id,
                'game_id'  => $gameId,
                'filename' => $filename,
            ],
            [
                'path'      => $fullPath,
                'file_size' => Storage::disk('local')->size($fullPath),
            ]
        );

        return response()->json(['status' => 'ok'], 200, [
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    /**
     * Sirve un archivo de partida al frontend para restaurarlo en el VFS.
     * Solo el propietario puede descargar sus propias partidas.
     */
    public function download(string $gameId, string $filename): Response
    {
        if (! in_array($gameId, self::VALID_GAME_IDS, true)) {
            abort(404);
        }

        // Sanitizar para prevenir path traversal
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);

        $save = GameSave::query()
            ->where('user_id', Auth::id())
            ->where('game_id', $gameId)
            ->where('filename', $filename)
            ->firstOrFail();

        if (! Storage::disk('local')->exists($save->path)) {
            abort(404);
        }

        $contents = Storage::disk('local')->get($save->path);

        return response($contents, 200, [
            'Content-Type'                 => 'application/octet-stream',
            'Content-Disposition'          => 'attachment; filename="'.$filename.'"',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
