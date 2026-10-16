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

namespace App\Services;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Lo que pasa por las colas, para la página de colas del panel (/dashboard/queues).
 *
 * Laravel solo guarda los jobs mientras esperan (Redis) y los que fallan
 * (failed_jobs). Los que terminan bien desaparecen sin rastro: aquí el worker
 * deja una línea por job terminado en una lista de Redis por cola, con tope de
 * HISTORY_MAX líneas y HISTORY_DAYS días. Va en la conexión de las colas (db2),
 * no en la de caché: «Limpiar caché» hace FLUSHDB y se lo llevaría.
 *
 * Si Redis se reinicia se pierde el historial; los fallidos siguen en MySQL.
 */
class QueueMonitor
{
    public const HISTORY_MAX = 5000;

    public const HISTORY_DAYS = 7;

    /** Colas que se enseñan siempre, aunque estén vacías. */
    public const KNOWN_QUEUES = ['default', 'meta-refresh'];

    /** Lo que se pinta de una cola sin parsear: el total sale de LLEN. */
    private const PENDING_PARSE_MAX = 2000;

    /** @var array<string, float> uuid → inicio (el worker es un único proceso) */
    private static array $started = [];

    /**
     * Engancha el registro a los eventos del worker. Se llama en el arranque
     * de la aplicación; en la web no hace nada porque allí no se procesan jobs.
     */
    public static function listen(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            self::$started[$event->job->uuid() ?? ''] = microtime(true);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            if ($event->job->hasFailed()) {
                return; // lo apunta JobFailed
            }

            self::record($event->connectionName, $event->job, $event->job->isReleased() ? 'released' : 'ok');
        });

        Event::listen(JobReleasedAfterException::class, function (JobReleasedAfterException $event): void {
            self::record($event->connectionName, $event->job, 'retry');
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            self::record($event->connectionName, $event->job, 'failed', $event->exception);
        });
    }

    /**
     * @param \Illuminate\Contracts\Queue\Job $job
     */
    private static function record(string $connection, $job, string $status, ?Throwable $e = null): void
    {
        if ($connection === 'sync') {
            return;
        }

        try {
            $uuid = $job->uuid() ?? '';
            $start = self::$started[$uuid] ?? null;
            unset(self::$started[$uuid]);

            $payload = $job->payload();
            $now = microtime(true);

            $line = [
                'id'       => $uuid,
                'name'     => $payload['displayName'] ?? $job->resolveName(),
                'desc'     => self::describe($payload),
                'status'   => $status,
                'queued'   => $payload['createdAt'] ?? null,
                'start'    => $start !== null ? round($start, 3) : null,
                'end'      => round($now, 3),
                'ms'       => $start !== null ? (int) round(($now - $start) * 1000) : null,
                'attempts' => $job->attempts(),
                'error'    => $e !== null ? self::firstLine($e::class.': '.$e->getMessage()) : null,
            ];

            $key = self::historyKey($job->getQueue());
            $redis = self::redis();
            $redis->lpush($key, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
            $redis->ltrim($key, 0, self::HISTORY_MAX - 1);
        } catch (Throwable $t) {
            // El historial nunca puede tumbar un job.
            Log::warning('QueueMonitor: no se pudo apuntar el job: '.$t->getMessage());
        }
    }

    /**
     * Colas a enseñar: las conocidas, las que tienen algo en Redis y las que
     * tienen fallidos.
     *
     * @return array<string>
     */
    public static function queues(): array
    {
        $queues = self::KNOWN_QUEUES;
        $redis = self::redis();

        foreach (['queues:*', 'nobs:queue-history:*'] as $pattern) {
            foreach ((array) $redis->keys($pattern) as $key) {
                $key = (string) $key;

                if (preg_match('/(?:^|:)queues:([^:]+)$/', $key, $m) || preg_match('/nobs:queue-history:(.+)$/', $key, $m)) {
                    $queues[] = $m[1];
                }
            }
        }

        foreach (DB::table('failed_jobs')->distinct()->pluck('queue') as $queue) {
            $queues[] = (string) $queue;
        }

        return array_values(array_unique($queues));
    }

    /**
     * Totales por cola, para las pestañas. «new» son los fallidos posteriores a
     * la última vez que ese usuario miró la cola.
     *
     * @return array<string, array{pending: int, running: int, failed: int, new: int}>
     */
    public static function totals(int $userId): array
    {
        $redis = self::redis();
        $failed = DB::table('failed_jobs')->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue');
        $out = [];

        foreach (self::queues() as $queue) {
            $seen = self::seen($userId, $queue);

            $out[$queue] = [
                'pending' => (int) $redis->llen("queues:{$queue}") + (int) $redis->zcard("queues:{$queue}:delayed"),
                'running' => (int) $redis->zcard("queues:{$queue}:reserved"),
                'failed'  => (int) ($failed[$queue] ?? 0),
                'new'     => $seen === null ? 0 : DB::table('failed_jobs')
                    ->where('queue', '=', $queue)
                    ->where('failed_at', '>', gmdate('Y-m-d H:i:s', (int) $seen))
                    ->count(),
            ];
        }

        return $out;
    }

    /**
     * Cuándo miró este usuario esta cola por última vez (null: nunca).
     */
    public static function seen(int $userId, string $queue): ?float
    {
        $value = self::redis()->get("nobs:queue-seen:{$userId}:{$queue}");

        return $value === null || $value === false ? null : (float) $value;
    }

    /**
     * Apunta que la mira ahora y devuelve la vez anterior. La primera vez
     * devuelve ahora: no tiene sentido marcar 1.400 fallidos viejos como nuevos.
     */
    public static function markSeen(int $userId, string $queue): float
    {
        $now = microtime(true);
        $before = self::seen($userId, $queue) ?? $now;
        self::redis()->setex("nobs:queue-seen:{$userId}:{$queue}", 90 * 86400, (string) $now);

        return $before;
    }

    /**
     * Todo lo de una cola como entradas sueltas, la más reciente primero:
     * en curso, pendientes, diferidos, historial y fallidos (estos se cruzan
     * por uuid con el historial).
     *
     * @return array{entries: list<array<string, mixed>>, counts: array<string, int>}
     */
    public static function entries(string $queue): array
    {
        $redis = self::redis();
        $entries = [];
        $counts = ['running' => 0, 'pending' => 0, 'delayed' => 0, 'ok' => 0, 'failed' => 0, 'retry' => 0];

        foreach ((array) $redis->zrange("queues:{$queue}:reserved", 0, -1, ['withscores' => true]) as $raw => $expires) {
            $entries[] = self::fromPayload((string) $raw, 'running', (float) $expires);
            $counts['running']++;
        }

        $pending = (int) $redis->llen("queues:{$queue}");
        $counts['pending'] = $pending;

        foreach ((array) $redis->lrange("queues:{$queue}", 0, self::PENDING_PARSE_MAX - 1) as $raw) {
            $entries[] = self::fromPayload((string) $raw, 'pending');
        }

        foreach ((array) $redis->zrange("queues:{$queue}:delayed", 0, -1, ['withscores' => true]) as $raw => $at) {
            $entries[] = self::fromPayload((string) $raw, 'delayed', (float) $at);
            $counts['delayed']++;
        }

        // Fallidos de MySQL: la fuente de verdad (el historial solo cubre 7 días).
        $failed = [];

        foreach (
            DB::table('failed_jobs')
                ->where('queue', '=', $queue)
                ->selectRaw("uuid, failed_at, JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) as name, LEFT(exception, 400) as head")
                ->orderByDesc('failed_at')
                ->get() as $row
        ) {
            $failed[(string) $row->uuid] = $row;
        }

        $cutoff = microtime(true) - self::HISTORY_DAYS * 86400;
        $seen = [];

        foreach ((array) $redis->lrange(self::historyKey($queue), 0, -1) as $raw) {
            $line = json_decode((string) $raw, true);

            if (!\is_array($line) || ($line['end'] ?? 0) < $cutoff) {
                continue;
            }

            $id = (string) ($line['id'] ?? '');
            $isFailed = $line['status'] === 'failed';

            $entries[] = [
                'id'       => $id,
                'name'     => (string) $line['name'],
                'desc'     => (string) ($line['desc'] ?? ''),
                'status'   => $line['status'],
                'at'       => (float) $line['end'],
                'ms'       => $line['ms'],
                'attempts' => $line['attempts'] ?? null,
                'error'    => $line['error'] ?? null,
                // Sigue en failed_jobs: se puede reintentar o descartar.
                'stored'   => $isFailed && isset($failed[$id]),
            ];

            if ($isFailed) {
                $seen[$id] = true;
            }

            $counts[$line['status']] = ($counts[$line['status']] ?? 0) + 1;
        }

        foreach ($failed as $id => $row) {
            if (isset($seen[$id])) {
                continue;
            }

            $entries[] = [
                'id'       => $id,
                'name'     => (string) $row->name,
                'desc'     => '',
                'status'   => 'failed',
                'at'       => (float) strtotime((string) $row->failed_at.' UTC'),
                'ms'       => null,
                'attempts' => null,
                'error'    => self::firstLine((string) $row->head),
                'stored'   => true,
            ];
            $counts['failed']++;
        }

        // Fallidos que solo quedan en el historial (descartados o reintentados).
        $counts['failed'] = \count(array_filter($entries, fn (array $e) => $e['status'] === 'failed'));

        return ['entries' => $entries, 'counts' => $counts];
    }

    /**
     * La excepción completa de un fallido (se pide al desplegarlo).
     */
    public static function exception(string $uuid): ?string
    {
        $value = DB::table('failed_jobs')->where('uuid', '=', $uuid)->value('exception');

        return $value === null ? null : (string) $value;
    }

    /**
     * @param  list<string>       $uuids
     * @return list<string>       los que siguen en failed_jobs de esa cola
     */
    public static function storedFailed(string $queue, array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }

        return DB::table('failed_jobs')
            ->where('queue', '=', $queue)
            ->whereIn('uuid', $uuids)
            ->pluck('uuid')
            ->map(fn ($u) => (string) $u)
            ->all();
    }

    /**
     * @return list<string> uuids de los fallidos de un tipo de job en una cola
     */
    public static function storedFailedByName(string $queue, string $name): array
    {
        return DB::table('failed_jobs')
            ->where('queue', '=', $queue)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) = ?", [$name])
            ->pluck('uuid')
            ->map(fn ($u) => (string) $u)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromPayload(string $raw, string $status, ?float $score = null): array
    {
        $payload = json_decode($raw, true) ?: [];

        return [
            'id'       => (string) ($payload['uuid'] ?? ''),
            'name'     => (string) ($payload['displayName'] ?? '?'),
            'desc'     => self::describe($payload),
            'status'   => $status,
            // pendiente: cuándo se encoló; diferido: cuándo sale; en curso: cuándo caduca la reserva
            'at'       => $score ?? (float) ($payload['createdAt'] ?? 0),
            'ms'       => null,
            'attempts' => $payload['attempts'] ?? null,
            'error'    => null,
            'stored'   => false,
        ];
    }

    /**
     * Una descripción corta sacada de las propiedades públicas escalares del
     * job, leyendo el texto serializado sin deserializarlo (deserializar
     * despertaría modelos y haría consultas). «lote 3/27» si el job lleva
     * batch y batches.
     *
     * @param array<string, mixed> $payload
     */
    private static function describe(array $payload): string
    {
        $command = $payload['data']['command'] ?? null;

        if (!\is_string($command) || ($payload['data']['commandName'] ?? '') === \Illuminate\Queue\CallQueuedClosure::class) {
            return '';
        }

        $skip = ['connection', 'queue', 'delay', 'afterCommit', 'chainConnection', 'chainQueue', 'chainCatchCallbacks', 'middleware', 'chained', 'tries', 'timeout', 'maxExceptions', 'failOnTimeout', 'backoff', 'deleteWhenMissingModels', 'job', 'batchId', 'locale', 'class', 'relations', 'collectionClass'];
        $props = [];

        preg_match_all('/s:\d+:"([A-Za-z_]\w*)";(?:i:(-?\d+)|b:([01])|d:([-\d.E+]+)|s:\d+:"([^"]{0,80})"|a:(\d+):)/', $command, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $name = $m[1];

            if (\in_array($name, $skip, true) || isset($props[$name]) || \count($props) >= 4) {
                continue;
            }

            $props[$name] = match (true) {
                ($m[2] ?? '') !== '' => $m[2],
                ($m[3] ?? '') !== '' => $m[3] === '1' ? 'sí' : null,
                ($m[4] ?? '') !== '' => $m[4],
                ($m[5] ?? '') !== '' => $m[5],
                ($m[6] ?? '') !== '' => $m[6].' elem.',
                default              => null,
            };
        }

        $props = array_filter($props, fn ($v) => $v !== null);

        if (isset($props['batch'], $props['batches'])) {
            $lote = "lote {$props['batch']}/{$props['batches']}";
            unset($props['batch'], $props['batches']);
            $props = ['' => $lote] + $props;
        }

        return implode(' · ', array_map(
            fn ($k, $v) => $k === '' ? (string) $v : "{$k}={$v}",
            array_keys($props),
            $props,
        ));
    }

    private static function firstLine(string $text): string
    {
        $line = strtok($text, "\n");
        $line = trim($line === false ? $text : $line);
        // «… in /var/www/html/vendor/…/Fichero.php:15»: está en la traza completa.
        $line = (string) preg_replace('/ in \/\S+:\d+$/', '', $line);

        return mb_strimwidth($line, 0, 300, '…');
    }

    private static function historyKey(string $queue): string
    {
        return "nobs:queue-history:{$queue}";
    }

    private static function redis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection((string) config('queue.connections.redis.connection', 'job'));
    }
}
