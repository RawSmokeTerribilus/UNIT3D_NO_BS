<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mal' => [
        'client_id' => env('MAL_CLIENT_ID'),
    ],

    'telegram' => [
        'token'             => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'           => env('TELEGRAM_GROUP_ID'),
        'topic_id'          => env('TELEGRAM_TOPIC_NOVEDADES'),
        'bot_username'      => env('TELEGRAM_BOT_USERNAME'),
        'group_invite_link' => env('TELEGRAM_GROUP_INVITE_LINK'),
        'instance_label'    => env('TELEGRAM_INSTANCE_LABEL', env('APP_ENV', 'tracker')),
        'reply_cooldown_seconds' => env('TELEGRAM_REPLY_COOLDOWN_SECONDS', 120),
        'news_bot_id'           => env('TELEGRAM_NEWS_BOT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreTranslate
    |--------------------------------------------------------------------------
    |
    | Traductor local para las sinopsis que los proveedores sólo sirven en
    | inglés. Escucha únicamente en el loopback y no tiene autenticación, así
    | que la URL nunca debe apuntar fuera de esta máquina.
    |
    */

    'libretranslate' => [
        'url' => env('LIBRETRANSLATE_URL', ''),
    ],

];
