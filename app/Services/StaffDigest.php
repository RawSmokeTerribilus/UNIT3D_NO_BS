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

use App\Enums\ModerationStatus;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use Illuminate\Support\Facades\DB;

/**
 * Las cifras operativas del sitio, en un sitio.
 *
 * Cada metrica lleva su umbral: por encima de el, el digest la marca y el
 * comando de vigilancia puede avisar fuera de hora. Un numero sin umbral es
 * decoracion — nadie sabe si 1.282 avisos activos son muchos o pocos si no hay
 * nada contra lo que compararlo.
 */
final class StaffDigest
{
    /**
     * Umbrales por encima de los cuales una metrica se considera «caliente».
     * null = informativa, nunca dispara aviso.
     *
     * @var array<string, null|int>
     */
    public const array THRESHOLDS = [
        'moderacion_pendiente' => 10,
        'moderacion_aplazada'  => 10,
        'reportes_abiertos'    => 15,
        'reportes_sin_asignar' => 10,
        'tickets_abiertos'     => 5,
        'tickets_sin_staff'    => 3,
        'logins_fallidos_24h'  => 50,
        'usuarios_atacados'    => 3,
        'hitrun_emitidos_24h'  => 100,
        'hitrun_activos'       => null,
    ];

    /**
     * @return array<string, array{valor: int, etiqueta: string, caliente: bool}>
     */
    public static function metrics(): array
    {
        $desde = now()->subDay();

        $crudas = [
            // withoutGlobalScope OBLIGATORIO: ApprovedScope filtra el catalogo a
            // los aprobados, asi que contar pendientes con el modelo pelado da
            // SIEMPRE cero. La primera version del digest reportaba 0 con 6 en
            // la cola.
            'moderacion_pendiente' => [
                'etiqueta' => 'Torrents pendientes de moderar',
                'valor'    => Torrent::withoutGlobalScope(ApprovedScope::class)
                    ->where('status', '=', ModerationStatus::PENDING)
                    ->count(),
            ],
            'moderacion_aplazada' => [
                'etiqueta' => 'Torrents aplazados',
                'valor'    => Torrent::withoutGlobalScope(ApprovedScope::class)
                    ->where('status', '=', ModerationStatus::POSTPONED)
                    ->count(),
            ],
            // Un reporte aplazado no esta esperando a nadie: alguien ya lo miro
            // y decidio volver a el mas tarde. Contarlo como pendiente hacia que
            // el digest gritara todos los dias por trabajo que no existia -- 20
            // «sin resolver» de los que 19 estaban aplazados.
            //
            // El aplazamiento CADUCA, asi que la condicion no es «no tiene
            // fecha», es «no tiene fecha o ya paso»: cuando vence, el reporte
            // vuelve a la cuenta solo. Es la misma definicion de «abierto» que
            // usa la busqueda de reportes del staff (ReportSearch:82), para que
            // el numero del digest y el que ve el staff en pantalla coincidan.
            'reportes_abiertos' => [
                'etiqueta' => 'Reportes sin resolver',
                'valor'    => DB::table('reports')
                    ->whereNull('solved_at')
                    ->where(fn ($query) => $query->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()))
                    ->count(),
            ],
            'reportes_sin_asignar' => [
                'etiqueta' => '  ...de ellos sin asignar',
                'valor'    => DB::table('reports')
                    ->whereNull('solved_at')
                    ->whereNull('assigned_to')
                    ->where(fn ($query) => $query->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()))
                    ->count(),
            ],
            'tickets_abiertos' => [
                'etiqueta' => 'Tickets abiertos',
                'valor'    => DB::table('tickets')->whereNull('closed_at')->whereNull('deleted_at')->count(),
            ],
            'tickets_sin_staff' => [
                'etiqueta' => '  ...de ellos sin staff asignado',
                'valor'    => DB::table('tickets')->whereNull('closed_at')->whereNull('deleted_at')->whereNull('staff_id')->count(),
            ],
            'logins_fallidos_24h' => [
                'etiqueta' => 'Logins fallidos (24 h)',
                'valor'    => DB::table('failed_login_attempts')->where('created_at', '>=', $desde)->count(),
            ],
            'usuarios_atacados' => [
                'etiqueta' => '  ...cuentas con más de 5 intentos',
                'valor'    => DB::table('failed_login_attempts')
                    ->where('created_at', '>=', $desde)
                    ->select('username')
                    ->groupBy('username')
                    ->havingRaw('COUNT(*) > 5')
                    ->get()
                    ->count(),
            ],
            'hitrun_emitidos_24h' => [
                'etiqueta' => 'Avisos de H&R emitidos (24 h)',
                'valor'    => DB::table('warnings')->where('created_at', '>=', $desde)->count(),
            ],
            'hitrun_activos' => [
                'etiqueta' => 'Avisos de H&R activos',
                'valor'    => DB::table('warnings')->where('active', '=', 1)->count(),
            ],
        ];

        $metricas = [];

        foreach ($crudas as $clave => $m) {
            $umbral = self::THRESHOLDS[$clave] ?? null;

            $metricas[$clave] = [
                'valor'    => (int) $m['valor'],
                'etiqueta' => $m['etiqueta'],
                'caliente' => $umbral !== null && $m['valor'] > $umbral,
            ];
        }

        return $metricas;
    }

    /**
     * Estado de las promos GLOBALES. No entra el freeleech por torrent: eso es
     * otra cosa y se caduca sola cada hora.
     *
     * @return list<string>
     */
    public static function promos(): array
    {
        $lineas = [];

        foreach (PromoState::PROMOS as $clave => $spec) {
            if (!PromoState::isOn($clave)) {
                continue;
            }

            $until = PromoState::until($clave);

            $lineas[] = $spec['label'].($until === null
                ? ' — sin fecha de fin'
                : ' — hasta '.$until->format('d/m/Y H:i').' UTC');
        }

        return $lineas;
    }

    /**
     * @return list<string> claves de las métricas por encima de su umbral
     */
    public static function hot(): array
    {
        return array_keys(array_filter(self::metrics(), static fn (array $m): bool => $m['caliente']));
    }

    /**
     * Texto plano para Telegram.
     */
    public static function render(): string
    {
        $metricas = self::metrics();
        $promos   = self::promos();
        $calientes = array_filter($metricas, static fn (array $m): bool => $m['caliente']);

        $texto = "📋 Resumen operativo — ".now()->format('d/m/Y H:i')." UTC\n\n";

        foreach ($metricas as $m) {
            $texto .= sprintf("%s %s: %d\n", $m['caliente'] ? '⚠️' : '·', $m['etiqueta'], $m['valor']);
        }

        $texto .= "\nPromos globales: ".($promos === [] ? 'ninguna activa' : "\n· ".implode("\n· ", $promos))."\n";

        $texto .= "\n".($calientes === []
            ? '✅ Nada por encima de umbral.'
            : '⚠️ '.\count($calientes).' métrica(s) por encima de umbral.');

        return $texto;
    }
}
