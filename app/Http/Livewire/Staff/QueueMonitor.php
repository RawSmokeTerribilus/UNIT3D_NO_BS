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
 * Una cola, a lo consola del navegador.
 *
 * Primer nivel: una línea por tipo de job. Segundo nivel: una línea por cosa
 * distinta que le ha pasado (en curso, en cola, terminados, y cada error
 * distinto con su contador y su rango de fechas, como el «×1316» de la
 * consola). Tercer nivel: los jobs sueltos, con traza y botones.
 */
class QueueMonitor extends Component
{
    /** Jobs sueltos que se pintan de cada línea antes de «mostrar más». */
    private const PAGE = 25;

    #[Locked]
    public string $queue = 'default';

    /**
     * Hasta cuándo había mirado esta cola quien la tiene abierta: lo posterior
     * sale marcado como nuevo. Se fija al entrar y no se mueve con el sondeo.
     */
    #[Locked]
    public float $seenBefore = 0.0;

    /** all | active | failed | ok */
    public string $filter = 'all';

    public string $search = '';

    public bool $live = true;

    /** @var array<string, int> línea → jobs sueltos que se enseñan */
    public array $limits = [];

    /**
     * Grupos y líneas abiertos («job» o «job|clave»). Va en el servidor y no en
     * el navegador: solo se pinta lo abierto, y el sondeo de cada 5 s no
     * arrastra miles de líneas.
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

    public function mount(): void
    {
        $this->seenBefore = Monitor::markSeen((int) auth()->id(), $this->queue);
    }

    /** El sondeo cada 5 s; con «en vivo» apagado no repinta. */
    public function tick(): void
    {
        if (!$this->live) {
            $this->skipRender();
        }
    }

    public function toggle(string $key): void
    {
        if (isset($this->open[$key])) {
            unset($this->open[$key]);
        } else {
            $this->open[$key] = true;
        }
    }

    /** Para el navegador de fallos: abre los grupos que tienen alguno. */
    public function openFails(): void
    {
        foreach (Monitor::entries($this->queue)['entries'] as $e) {
            if ($e['status'] === 'failed') {
                $this->open[$e['name']] = true;
            }
        }
    }

    public function more(string $key): void
    {
        $this->limits[$key] = ($this->limits[$key] ?? self::PAGE) + 100;
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

    /** Todos los fallidos de un job, o solo los de una clase de error si va $sub. */
    public function retryGroup(string $name, ?string $sub = null): void
    {
        $this->retryUuids($this->storedIn($name, $sub));
    }

    public function forgetGroup(string $name, ?string $sub = null): void
    {
        $this->forgetUuids($this->storedIn($name, $sub));
    }

    /**
     * @return list<string>
     */
    private function storedIn(string $name, ?string $sub): array
    {
        if ($sub === null) {
            return Monitor::storedFailedByName($this->queue, $name);
        }

        $uuids = [];

        foreach (Monitor::entries($this->queue)['entries'] as $e) {
            if ($e['name'] === $name && $e['stored'] && self::subKey($e) === $sub) {
                $uuids[] = $e['id'];
            }
        }

        return Monitor::storedFailed($this->queue, $uuids);
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

            return $keep && ($needle === '' || str_contains(mb_strtolower($e['name'].' '.$e['desc'].' '.$e['error']), $needle));
        });

        $groups = [];

        foreach ($entries as $e) {
            $e['new'] = $e['at'] > $this->seenBefore && !\in_array($e['status'], ['running', 'pending', 'delayed'], true);

            $g = &$groups[$e['name']];
            $g ??= ['name' => $e['name'], 'subs' => [], 'counts' => [], 'last' => 0.0, 'ms' => [], 'stored' => 0, 'new' => 0];
            $g['counts'][$e['status']] = ($g['counts'][$e['status']] ?? 0) + 1;
            $g['last'] = max($g['last'], $e['at']);
            $g['stored'] += (int) $e['stored'];
            $g['new'] += (int) ($e['new'] && $e['status'] === 'failed');

            if ($e['ms'] !== null && $e['status'] === 'ok') {
                $g['ms'][] = $e['ms'];
            }

            $key = self::subKey($e);
            $s = &$g['subs'][$key];
            $s ??= ['key' => $key, 'status' => $e['status'], 'text' => '', 'entries' => [], 'first' => $e['at'], 'last' => $e['at'], 'stored' => 0, 'new' => 0];
            $s['entries'][] = $e;
            $s['first'] = min($s['first'], $e['at']);
            $s['stored'] += (int) $e['stored'];
            $s['new'] += (int) $e['new'];

            if ($e['at'] >= $s['last']) {
                $s['last'] = $e['at'];
                // El texto de la línea es el del caso más reciente, con sus números de verdad.
                $s['text'] = (string) ($e['error'] ?? '');
            }

            unset($g, $s);
        }

        foreach ($groups as &$g) {
            foreach ($g['subs'] as &$s) {
                usort($s['entries'], fn (array $a, array $b) => $b['at'] <=> $a['at']);
                $s['open'] = isset($this->open[$g['name'].'|'.$s['key']]);
                $s['limit'] = $this->limits[$g['name'].'|'.$s['key']] ?? self::PAGE;
            }
            unset($s);

            // Lo que está en marcha arriba; después, lo más reciente.
            uasort($g['subs'], fn (array $a, array $b) => [self::rank($a['status']), $b['last']] <=> [self::rank($b['status']), $a['last']]);

            $g['avg'] = $g['ms'] === [] ? null : (int) round(array_sum($g['ms']) / \count($g['ms']));
            $g['active'] = ($g['counts']['running'] ?? 0) + ($g['counts']['pending'] ?? 0) + ($g['counts']['delayed'] ?? 0);
            $g['failed'] = $g['counts']['failed'] ?? 0;
            $g['total'] = array_sum($g['counts']);
            $g['open'] = isset($this->open[$g['name']]);
            [$g['label'], $g['tag']] = self::label($g['name']);
        }
        unset($g);

        uasort($groups, fn (array $a, array $b) => [$b['active'] > 0, $b['new'] > 0, $b['last']] <=> [$a['active'] > 0, $a['new'] > 0, $a['last']]);

        return view('livewire.staff.queue-monitor', [
            'groups'  => $groups,
            'counts'  => $counts,
            'newFail' => array_sum(array_column($groups, 'new')),
            'kinds'   => array_sum(array_map(fn (array $g) => \count(array_filter($g['subs'], fn (array $s) => $s['status'] === 'failed')), $groups)),
        ]);
    }

    /**
     * La línea de segundo nivel a la que va un job: por estado y, en los
     * errores, por el mensaje con los números quitados (el ID del torrent, la
     * línea, el uuid), para que 1.316 «too many times» sean una sola línea.
     *
     * @param array<string, mixed> $e
     */
    private static function subKey(array $e): string
    {
        if (!\in_array($e['status'], ['failed', 'retry'], true)) {
            return $e['status'] === 'delayed' ? 'pending' : $e['status'];
        }

        $text = (string) ($e['error'] ?? '');
        $text = (string) preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '…', $text);
        $text = (string) preg_replace('/\d+/', '#', $text);

        return $e['status'].':'.md5($text);
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
            'failed', 'retry' => 2,
            default => 3,
        };
    }
}
