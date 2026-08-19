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

return [
    /*
    |--------------------------------------------------------------------------
    | Avatar Settings
    |--------------------------------------------------------------------------
    |
    | Maximum accepted upload size, in bytes, for user avatars and icons.
    |
    | The image driver itself is no longer configured here: Intervention Image 4
    | is wired by intervention/image-laravel, which publishes its own
    | `config/intervention-image.php` and reads the IMAGE_DRIVER env var.
    |
    */

    'max_upload_size' => '2000000',
];
