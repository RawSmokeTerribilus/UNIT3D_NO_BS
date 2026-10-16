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

namespace App\Http\Livewire\Staff;

use App\Services\QueueMonitor as Monitor;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Una cola, a lo consola del navegador: una línea desplegable por tipo de job
 * y dentro los sueltos; los fallos en rojo, con filtro y saltos de uno en uno.
 */
class QueueMonitor extends Component
{
    /** Entradas que se pintan de cada grupo antes de «mostrar más». */
    private const GROUP_PAGE = 25;

    #[Locked]
    public string $queue = 'default';

    /** all | active | failed | ok */
    public string $filter = 'all';

    public string $search = '';

    public bool $live = true;

    /** @var array<string, int> grupo → entradas que se enseñan */
    public array $limits = [];

    /**
     * Grupos abiertos. Va en el servidor y no en el navegador: solo se pintan
     * los abiertos, y el sondeo de cada 5 s no arrastra miles de líneas.
     *
     * @var array<string, bool>
     */
    public array $open = [];

    /** @var array<string, string> uuid → traza completa ya pedida */
    public array $traces = [];

    public string $notice = '';

    public function boot(): void
    {
        // La ruta ya pasa por el middleware admin; las acciones de Livewire
        // entran por /livewire/update y hay que comprobarlo otra vez.
        abort_unless((bool) auth()->user()?->group?->is_admin, 403);
    }

    /** El sondeo cada 5 s; con «en vivo» apagado no repinta. */
    public function tick(): void
    {
        if (!$this->live) {
            $this->skipRender();
        }
    }

    public function toggle(string $name): void
    {
        if (isset($this->open[$name])) {
            unset($this->open[$name]);
        } else {
            $this->open[$name] = true;
        }
    }

    /** Para el navegador de fallos: abre todos los grupos que tienen alguno. */
    public function openFails(): void
    {
        foreach (Monitor::entries($this->queue)['entries'] as $e) {
            if ($e['status'] === 'failed') {
                $this->open[$e['name']] = true;
            }
        }
    }

    public function more(string $name): void
    {
        $this->limits[$name] = ($this->limits[$name] ?? self::GROUP_PAGE) + 100;
    }

    public function trace(string $uuid): void
    {
        if (isset($this->traces[$uuid])) {
            unset($this->traces[$uuid]);

            return;
        }

        $this->traces[$uuid] = Monitor::exception($uuid) ?? '(ya no está en failed_jobs)';
    }

    public function retry(string $uuid): void
    {
        $this->retryUuids(Monitor::storedFailed($this->queue, [$uuid]));
    }

    public function forget(string $uuid): void
    {
        $this->forgetUuids(Monitor::storedFailed($this->queue, [$uuid]));
    }

    public function retryGroup(string $name): void
    {
        $this->retryUuids(Monitor::storedFailedByName($this->queue, $name));
    }

    public function forgetGroup(string $name): void
    {
        $this->forgetUuids(Monitor::storedFailedByName($this->queue, $name));
    }

    /**
     * @param list<string> $uuids
     */
    private function retryUuids(array $uuids): void
    {
        if ($uuids === []) {
            $this->notice = 'Nada que reintentar: ya no está en failed_jobs.';

            return;
        }

        try {
            Artisan::call('queue:retry', ['id' => $uuids]);
            $this->notice = \count($uuids).' job(s) devueltos a la cola «'.$this->queue.'».';
        } catch (\Throwable $e) {
            // queue:retry deserializa el job; si su clase ya no existe o cambió, revienta.
            $this->notice = '❌ No se pudo reintentar: '.$e->getMessage();
        }
    }

    /**
     * @param list<string> $uuids
     */
    private function forgetUuids(array $uuids): void
    {
        $failer = app('queue.failer');

        foreach ($uuids as $uuid) {
            $failer->forget($uuid);
            unset($this->traces[$uuid]);
        }

        $this->notice = $uuids === []
            ? 'Nada que descartar: ya no está en failed_jobs.'
            : \count($uuids).' fallido(s) descartados de failed_jobs.';
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        ['entries' => $entries, 'counts' => $counts] = Monitor::entries($this->queue);

        $needle = mb_strtolower(trim($this->search));

        $entries = array_filter($entries, function (array $e) use ($needle): bool {
            $keep = match ($this->filter) {
                'active' => \in_array($e['status'], ['running', 'pending', 'delayed'], true),
                'failed' => \in_array($e['status'], ['failed', 'retry'], true),
                'ok'     => $e['status'] === 'ok',
                default  => true,
            };

            return $keep && ($needle === '' || str_contains(mb_strtolower($e['name'].' '.$e['desc']), $needle));
        });

        $groups = [];

        foreach ($entries as $e) {
            $g = &$groups[$e['name']];
            $g ??= ['name' => $e['name'], 'entries' => [], 'counts' => [], 'last' => 0.0, 'ms' => [], 'stored' => 0];
            $g['entries'][] = $e;
            $g['counts'][$e['status']] = ($g['counts'][$e['status']] ?? 0) + 1;
            $g['last'] = max($g['last'], $e['at']);

            if ($e['ms'] !== null && $e['status'] === 'ok') {
                $g['ms'][] = $e['ms'];
            }

            if ($e['stored']) {
                $g['stored']++;
            }

            unset($g);
        }

        foreach ($groups as &$g) {
            // Lo que está en marcha arriba; después, lo más reciente.
            usort($g['entries'], fn (array $a, array $b) => [self::rank($a['status']), $b['at']] <=> [self::rank($b['status']), $a['at']]);
            $g['avg'] = $g['ms'] === [] ? null : (int) round(array_sum($g['ms']) / \count($g['ms']));
            $g['active'] = ($g['counts']['running'] ?? 0) + ($g['counts']['pending'] ?? 0) + ($g['counts']['delayed'] ?? 0);
            $g['failed'] = $g['counts']['failed'] ?? 0;
            $g['limit'] = $this->limits[$g['name']] ?? self::GROUP_PAGE;
            [$g['label'], $g['tag']] = self::label($g['name']);
        }
        unset($g);

        uasort($groups, fn (array $a, array $b) => [$b['active'] > 0, $b['last']] <=> [$a['active'] > 0, $a['last']]);

        return view('livewire.staff.queue-monitor', [
            'groups' => $groups,
            'counts' => $counts,
            'fails'  => array_sum(array_column($groups, 'failed')),
        ]);
    }

    /**
     * «App\Jobs\SyncTrackerTorrentsBatch» → [SyncTrackerTorrentsBatch, job].
     *
     * @return array{string, string}
     */
    private static function label(string $name): array
    {
        if (!str_contains($name, '\\')) {
            return [$name, str_contains($name, ':') ? 'comando' : ''];
        }

        $tag = match (true) {
            str_starts_with($name, 'App\\Notifications\\') => 'notificación',
            str_starts_with($name, 'App\\Jobs\\')          => 'job',
            str_contains($name, 'Mail')                    => 'correo',
            default                                        => 'laravel',
        };

        return [class_basename($name), $tag];
    }

    private static function rank(string $status): int
    {
        return match ($status) {
            'running' => 0,
            'pending', 'delayed' => 1,
            default => 2,
        };
    }
}
