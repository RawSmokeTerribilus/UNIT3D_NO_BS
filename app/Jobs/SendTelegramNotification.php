<?php

namespace App\Jobs;

use App\Helpers\StringHelper;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 60, 300];
    public $timeout = 30;

    public function __construct(public Torrent $torrent, public User $user)
    {
    }

    public function handle(): void
    {
        try {
            $token       = config('services.telegram.token');
            $chatId      = config('services.telegram.chat_id');
            $topicId     = config('services.telegram.topic_id');
            $botUsername  = config('services.telegram.bot_username');

            if (empty($token) || empty($chatId)) {
                Log::error('Telegram: Missing config', [
                    'token_empty'   => empty($token),
                    'chat_id_empty' => empty($chatId),
                ]);
                return;
            }

            $torrent = $this->torrent;
            $user    = $this->user;

            // --- Metadata ---
            $category = $torrent->category->name ?? 'Varios';
            $type     = $torrent->type->name ?? 'N/A';
            $size     = StringHelper::formatBytes((int) $torrent->size);
            $uploader = $user->username;

            $codec = 'N/A';
            $audioFormat = 'N/A';
            $resolution = '';
            $duration = '';
            $bitrate = '';
            $framerate = '';
            $aspectRatio = '';
            $audioFlags = '';
            $subFlags = '';

            if (!empty($torrent->mediainfo)) {
                $mi = $torrent->mediainfo;

                // Video codec
                if (preg_match('/^Video\s*$.*?^Format\s+:\s+([^\n]+)/msi', $mi, $v)) {
                    $codec = trim($v[1]);
                }
                // Audio format (first track)
                if (preg_match('/^Audio(?:\s*#\d+)?\s*$.*?^Format\s+:\s+([^\n]+)/msi', $mi, $a)) {
                    $audioFormat = trim($a[1]);
                }
                // Resolution
                if (preg_match('/^Width\s+:\s+([^\n]+)/mi', $mi, $w)
                    && preg_match('/^Height\s+:\s+([^\n]+)/mi', $mi, $h)) {
                    $resolution = trim(str_replace(' ', '', $w[1])) . 'x' . trim(str_replace(' ', '', $h[1]));
                }
                // Duration (from General section)
                if (preg_match('/^Duration\s+:\s+([^\n]+)/mi', $mi, $d)) {
                    $duration = trim($d[1]);
                }
                // Video bitrate
                if (preg_match('/^Video\s*$.*?^Bit rate\s+:\s+([^\n]+)/msi', $mi, $br)) {
                    $bitrate = trim($br[1]);
                }
                // Frame rate
                if (preg_match('/^Frame rate\s+:\s+([^\n]+)/mi', $mi, $fr)) {
                    $framerate = trim(preg_replace('/\s*\(.*\)/', '', $fr[1]));
                }
                // Aspect ratio
                if (preg_match('/^Display aspect ratio\s+:\s+([^\n]+)/mi', $mi, $ar)) {
                    $aspectRatio = trim($ar[1]);
                }
                // Audio track languages → flag emojis
                if (preg_match_all('/^Audio(?:\s*#\d+)?\s*$.*?^Language\s+:\s+([^\n]+)/msi', $mi, $al)) {
                    $audioFlags = implode(' ', array_unique(array_map(fn ($l) => $this->languageToFlag($l), $al[1])));
                }
                // Subtitle track languages → flag emojis
                if (preg_match_all('/^Text(?:\s*#\d+)?\s*$.*?^Language\s+:\s+([^\n]+)/msi', $mi, $sl)) {
                    $subFlags = implode(' ', array_unique(array_map(fn ($l) => $this->languageToFlag($l), $sl[1])));
                }
            }

            // --- URLs ---
            $torrentUrl = route('torrents.show', ['id' => $torrent->id]);
            $poster     = $this->resolvePosterUrl($torrent);

            // --- Caption (max 1024 for sendPhoto) ---
            $caption = "🎬 <b>{$this->esc($torrent->name)}</b>\n\n"
                     . "📂 <b>Categoría:</b> {$this->esc($category)}\n"
                     . "💾 <b>Tamaño:</b> {$size}\n"
                     . "⭐ <b>Calidad:</b> {$this->esc($type)}\n"
                     . "🎞 <b>Codec:</b> {$codec}\n";

            if ($resolution) {
                $caption .= "📐 <b>Resolución:</b> {$resolution}\n";
            }
            if ($aspectRatio) {
                $caption .= "🖼 <b>Aspecto:</b> {$aspectRatio}\n";
            }
            if ($duration) {
                $caption .= "⏱ <b>Duración:</b> {$duration}\n";
            }
            if ($bitrate) {
                $caption .= "📊 <b>Bitrate:</b> {$bitrate}\n";
            }
            if ($framerate) {
                $caption .= "🎯 <b>Framerate:</b> {$framerate}\n";
            }

            $caption .= "🔊 <b>Audio:</b> {$audioFormat}";
            if ($audioFlags) {
                $caption .= "  {$audioFlags}";
            }
            $caption .= "\n";

            if ($subFlags) {
                $caption .= "💬 <b>Subs:</b> {$subFlags}\n";
            }

            $caption .= "👤 <b>Subido por:</b> {$this->esc($uploader)}\n";

            if (mb_strlen($caption) > 1024) {
                $caption = mb_substr($caption, 0, 1020) . '...';
            }

            // --- Inline Keyboard ---
            $row1 = [['text' => '📥 Descargar / Ver Torrent', 'url' => $torrentUrl]];

            $row2 = [];
            if ($torrent->imdb > 0) {
                $imdbId = str_pad((string) $torrent->imdb, 7, '0', STR_PAD_LEFT);
                $row2[] = ['text' => '🎬 IMDb', 'url' => "https://www.imdb.com/title/tt{$imdbId}"];
            }
            if (!empty($torrent->tmdb_movie_id)) {
                $row2[] = ['text' => '🎞 TMDb', 'url' => "https://www.themoviedb.org/movie/{$torrent->tmdb_movie_id}"];
            } elseif (!empty($torrent->tmdb_tv_id)) {
                $row2[] = ['text' => '📺 TMDb', 'url' => "https://www.themoviedb.org/tv/{$torrent->tmdb_tv_id}"];
            }

            $trailerUrl = $this->resolveTrailerUrl($torrent);
            if ($trailerUrl) {
                $row2[] = ['text' => '▶️ Trailer', 'url' => $trailerUrl];
            }

            $rows = [$row1];
            if (!empty($row2)) {
                $rows[] = $row2;
            }

            // --- Send ---
            $payload = [
                'chat_id'           => $chatId,
                'photo'             => $poster,
                'caption'           => $caption,
                'parse_mode'        => 'HTML',
                'reply_markup'      => ['inline_keyboard' => $rows],
            ];

            if ($topicId) {
                $payload['message_thread_id'] = (int) $topicId;
            }

            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendPhoto", $payload);

            if (!$response->successful()) {
                Log::error('Telegram: API error', [
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                    'torrent_id' => $torrent->id,
                ]);
                return;
            }

            Log::info('Telegram: Sent', [
                'torrent_id' => $torrent->id,
                'user_id'    => $user->id,
                'message_id' => $response->json('result.message_id'),
            ]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Telegram: HTTP failed', [
                'status'     => $e->response?->status(),
                'body'       => $e->response?->body(),
                'torrent_id' => $this->torrent->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram: Unexpected error', [
                'error'      => $e->getMessage(),
                'torrent_id' => $this->torrent->id,
            ]);
        }
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function languageToFlag(string $language): string
    {
        $language = strtolower(trim(preg_replace('/\s*\(.*\)/', '', $language)));

        $flags = [
            'spanish'    => "\u{1F1EA}\u{1F1F8}", // 🇪🇸
            'english'    => "\u{1F1EC}\u{1F1E7}", // 🇬🇧
            'french'     => "\u{1F1EB}\u{1F1F7}", // 🇫🇷
            'german'     => "\u{1F1E9}\u{1F1EA}", // 🇩🇪
            'italian'    => "\u{1F1EE}\u{1F1F9}", // 🇮🇹
            'portuguese' => "\u{1F1F5}\u{1F1F9}", // 🇵🇹
            'russian'    => "\u{1F1F7}\u{1F1FA}", // 🇷🇺
            'japanese'   => "\u{1F1EF}\u{1F1F5}", // 🇯🇵
            'korean'     => "\u{1F1F0}\u{1F1F7}", // 🇰🇷
            'chinese'    => "\u{1F1E8}\u{1F1F3}", // 🇨🇳
            'arabic'     => "\u{1F1F8}\u{1F1E6}", // 🇸🇦
            'hindi'      => "\u{1F1EE}\u{1F1F3}", // 🇮🇳
            'turkish'    => "\u{1F1F9}\u{1F1F7}", // 🇹🇷
            'dutch'      => "\u{1F1F3}\u{1F1F1}", // 🇳🇱
            'polish'     => "\u{1F1F5}\u{1F1F1}", // 🇵🇱
            'swedish'    => "\u{1F1F8}\u{1F1EA}", // 🇸🇪
            'norwegian'  => "\u{1F1F3}\u{1F1F4}", // 🇳🇴
            'danish'     => "\u{1F1E9}\u{1F1F0}", // 🇩🇰
            'finnish'    => "\u{1F1EB}\u{1F1EE}", // 🇫🇮
            'greek'      => "\u{1F1EC}\u{1F1F7}", // 🇬🇷
            'czech'      => "\u{1F1E8}\u{1F1FF}", // 🇨🇿
            'romanian'   => "\u{1F1F7}\u{1F1F4}", // 🇷🇴
            'hungarian'  => "\u{1F1ED}\u{1F1FA}", // 🇭🇺
            'thai'       => "\u{1F1F9}\u{1F1ED}", // 🇹🇭
            'vietnamese' => "\u{1F1FB}\u{1F1F3}", // 🇻🇳
            'indonesian' => "\u{1F1EE}\u{1F1E9}", // 🇮🇩
            'malay'      => "\u{1F1F2}\u{1F1FE}", // 🇲🇾
            'catalan'    => "\u{1F1EA}\u{1F1F8}", // 🇪🇸 (catalán → España)
            'basque'     => "\u{1F1EA}\u{1F1F8}", // 🇪🇸 (euskera → España)
            'galician'   => "\u{1F1EA}\u{1F1F8}", // 🇪🇸 (gallego → España)
            'hebrew'     => "\u{1F1EE}\u{1F1F1}", // 🇮🇱
            'persian'    => "\u{1F1EE}\u{1F1F7}", // 🇮🇷
            'ukrainian'  => "\u{1F1FA}\u{1F1E6}", // 🇺🇦
            'croatian'   => "\u{1F1ED}\u{1F1F7}", // 🇭🇷
            'serbian'    => "\u{1F1F7}\u{1F1F8}", // 🇷🇸
            'bulgarian'  => "\u{1F1E7}\u{1F1EC}", // 🇧🇬
            'slovak'     => "\u{1F1F8}\u{1F1F0}", // 🇸🇰
            'slovenian'  => "\u{1F1F8}\u{1F1EE}", // 🇸🇮
            'estonian'   => "\u{1F1EA}\u{1F1EA}", // 🇪🇪
            'latvian'    => "\u{1F1F1}\u{1F1FB}", // 🇱🇻
            'lithuanian' => "\u{1F1F1}\u{1F1F9}", // 🇱🇹
            'icelandic'  => "\u{1F1EE}\u{1F1F8}", // 🇮🇸
            'brazilian'  => "\u{1F1E7}\u{1F1F7}", // 🇧🇷
        ];

        return $flags[$language] ?? "\u{1F3F3}\u{FE0F}"; // 🏳️
    }

    private function resolvePosterUrl(Torrent $torrent): string
    {
        $poster = $torrent->movie?->poster ?? $torrent->tv?->poster;

        if ($poster && (str_starts_with($poster, 'http://') || str_starts_with($poster, 'https://'))) {
            return $poster;
        }

        return 'https://via.placeholder.com/600x900?text=No+Poster';
    }

    private function resolveTrailerUrl(Torrent $torrent): ?string
    {
        // 1. From TMDB movie trailer field
        $trailerId = $torrent->movie?->trailer;

        if ($trailerId) {
            if (str_starts_with($trailerId, 'http://') || str_starts_with($trailerId, 'https://')) {
                return $trailerId;
            }

            return "https://www.youtube.com/watch?v={$trailerId}";
        }

        // 2. Fallback: extract [youtube]ID[/youtube] from description
        if ($torrent->description && preg_match('/\[youtube\]([a-zA-Z0-9_-]{11})\[\/youtube\]/i', $torrent->description, $m)) {
            return "https://www.youtube.com/watch?v={$m[1]}";
        }

        return null;
    }
}

