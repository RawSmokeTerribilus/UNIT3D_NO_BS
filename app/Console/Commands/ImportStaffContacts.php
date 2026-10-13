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

namespace App\Console\Commands;

use App\Models\StaffContact;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Carga en staff_contacts los contactos que solo estaban en el vault.
 *
 * El CSV NO va en el repo: lleva nombres de socios. Cabecera obligatoria:
 *   usuario,fecha,campana,canal,plantilla,conversation_id,plazo_hasta,
 *   avisos_activos,horas_pendientes,nota
 * Fechas en UTC, 'Y-m-d' o 'Y-m-d H:i'. Vacio = null.
 *
 * Es idempotente: una fila con el mismo socio, fecha y campana no se repite.
 * Las cuentas aniquiladas se importan igual (siguen en users con baja logica):
 * el expediente no desaparece porque la cuenta lo haga.
 */
class ImportStaffContacts extends Command
{
    protected $signature = 'staff-contacts:import
                            {fichero : Ruta al CSV dentro del contenedor}
                            {--dry-run : Solo dice qué importaría}';

    protected $description = 'Importa al registro de contactos del staff los contactos anotados en el vault';

    private const array COLUMNAS = [
        'usuario', 'fecha', 'campana', 'canal', 'plantilla', 'conversation_id',
        'plazo_hasta', 'avisos_activos', 'horas_pendientes', 'nota',
    ];

    final public function handle(): int
    {
        $ruta = (string) $this->argument('fichero');
        $seco = (bool) $this->option('dry-run');

        if (!is_readable($ruta)) {
            $this->error("No puedo leer {$ruta}.");

            return self::FAILURE;
        }

        $fh = fopen($ruta, 'r');
        $cabecera = fgetcsv($fh, escape: '\\');

        if ($cabecera !== self::COLUMNAS) {
            $this->error('Cabecera inesperada. Tiene que ser: '.implode(',', self::COLUMNAS));

            return self::FAILURE;
        }

        $nuevas = 0;
        $repetidas = 0;
        $sinCuenta = [];

        while (($linea = fgetcsv($fh, escape: '\\')) !== false) {
            if ($linea === [null] || \count($linea) !== \count(self::COLUMNAS)) {
                continue;
            }

            $f = array_map(fn ($v) => $v === '' ? null : $v, array_combine(self::COLUMNAS, $linea));

            $user = User::withTrashed()->where('username', '=', $f['usuario'])->first();

            if ($user === null) {
                $sinCuenta[] = $f['usuario'];

                continue;
            }

            $fecha = Carbon::parse($f['fecha'], 'UTC');

            $existe = StaffContact::query()
                ->where('user_id', '=', $user->id)
                ->where('contacted_at', '=', $fecha)
                ->where('campana', '=', $f['campana'])
                ->exists();

            if ($existe) {
                $repetidas++;

                continue;
            }

            $nuevas++;

            if ($seco) {
                continue;
            }

            StaffContact::create([
                'user_id'          => $user->id,
                'contacted_at'     => $fecha,
                'campana'          => $f['campana'],
                'canal'            => $f['canal'] ?? 'ninguno',
                'plantilla'        => $f['plantilla'],
                'conversation_id'  => $f['conversation_id'],
                'plazo_hasta'      => $f['plazo_hasta'] === null ? null : Carbon::parse($f['plazo_hasta'], 'UTC'),
                'avisos_activos'   => $f['avisos_activos'],
                'horas_pendientes' => $f['horas_pendientes'],
                'origen'           => 'importado',
                'nota'             => $f['nota'],
            ]);
        }

        fclose($fh);

        $this->info(\sprintf('%s %d · ya estaban %d · sin cuenta %d', $seco ? 'Importaría' : 'Importadas', $nuevas, $repetidas, \count($sinCuenta)));

        if ($sinCuenta !== []) {
            $this->warn('Sin cuenta: '.implode(', ', array_unique($sinCuenta)));
        }

        return self::SUCCESS;
    }
}
