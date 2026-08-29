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

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Donation System
    |--------------------------------------------------------------------------
    |
    | Configure site to use Donation System
    |
    */
    // NO se edita a mano: manda la fila `donation.is_enabled` de la tabla
    // `settings`, que `SettingServiceProvider` aplica con Config::set() en cada
    // petición. Aquí sólo vive el valor por defecto de una instalación nueva, y
    // se deja APAGADO para que portar este fichero no encienda las donaciones
    // en otro entorno por sorpresa. El interruptor está en /dashboard/config.
    'is_enabled'   => false,
    'monthly_goal' => 200,
    'currency'     => 'EUR',

    /*
     * Cómo se llama la pasarela de cara al donante. Sale por config y no
     * escrito en la vista porque el proveedor se cambia: se empezó con PayPal,
     * que en cuenta personal enseña el nombre legal del titular en el checkout,
     * y se pasó a Ko-fi, que enseña el nombre de la página. Ambas cosas se
     * gestionan desde /dashboard/config, sin tocar código.
     */
    'gateway_label' => 'Ko-fi',

    /*
     * ¿El enlace del tramo lleva el importe ya fijado?
     *
     * Con botones alojados de PayPal, sí. Con una página de propina genérica,
     * NO: el donante teclea la cantidad. De esto depende el texto del diálogo,
     * y decirle «no tienes que escribir nada» cuando sí tiene que hacerlo es
     * exactamente el tipo de mentira que hace que una donación acabe con el
     * importe equivocado.
     */
    'amount_prefilled' => false,
    'description'  => '¿Un billete pa un filete?',
];
