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

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Hash;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Set the bcrypt hashing rounds to just 4 for testing
        Hash::setRounds(4);

        // Cortafuegos: la suite usa LazilyRefreshDatabase en el TestCase base y
        // esta atada a Feature y Unit, asi que cualquier test puede tirar y
        // re-migrar la base a la que apunte la conexion. phpunit.xml fija
        // DB_DATABASE, pero el contenedor tambien inyecta DB_DATABASE por
        // docker-compose y sin force="true" PHPUnit no la sobreescribe.
        // Si esa proteccion se pierde otra vez, aqui se para en seco.
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (!str_ends_with($database, '_testing')) {
            fwrite(STDERR, PHP_EOL.'ABORTADO: la suite apunta a la base "'.$database.'", que no termina en _testing.'.PHP_EOL
                .'Los tests la borrarian. Revisa DB_DATABASE en phpunit.xml (necesita force="true")'.PHP_EOL
                .'y la variable DB_DATABASE inyectada por docker-compose.'.PHP_EOL.PHP_EOL);
            exit(1);
        }

        return $app;
    }
}
