<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Torrent;
use Illuminate\View\View;

class AlertsComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $backdrops = cache()->flexible(
            'cached-alert-backdrops',
            [900, 1800],
            fn (): array => Torrent::query()
                ->select(['id', 'tmdb_movie_id', 'tmdb_tv_id', 'seeders', 'times_completed'])
                ->with([
                    'movie:id,backdrop',
                    'tv:id,backdrop',
                ])
                ->where(fn ($query) => $query
                    ->whereNotNull('tmdb_movie_id')
                    ->orWhereNotNull('tmdb_tv_id')
                )
                ->orderByDesc('seeders')
                ->orderByDesc('times_completed')
                ->limit(1800)
                ->get()
                ->map(fn (Torrent $torrent): ?string => $torrent->movie?->backdrop ?? $torrent->tv?->backdrop)
                ->filter()
                ->map(fn (string $backdrop): string => tmdb_image('back_small', $backdrop))
                ->unique()
                ->take(200)
                ->values()
                ->all(),
        );

        $view->with([
            'backdrops' => $backdrops,
        ]);
    }
}
