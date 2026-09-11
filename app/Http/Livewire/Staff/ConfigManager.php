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

use App\Models\Setting;
use App\Services\PromoState;
use Livewire\Component;

class ConfigManager extends Component
{
    public array $settingsData = [];
    public array $idByKey = [];

    public static array $groups = [
        [
            'title' => 'Sitio',
            'icon'  => 'fa-globe',
            'settings' => [
                'other.invite-only'                => ['label' => 'Solo por invitación',         'hint' => 'Los nuevos usuarios solo pueden registrarse con invitación',          'type' => 'boolean'],
                'other.openreg_until'              => ['label' => 'Registro abierto hasta (UTC)', 'hint' => 'Cuándo se vuelve a cerrar el registro. Vacío = sin fecha de fin y sin reloj en el banner. La hora es UTC, no peninsular', 'type' => 'datetime'],
                'other.default_style'              => ['label' => 'Tema por defecto',             'hint' => 'Tema que verán los nuevos usuarios al registrarse',                  'type' => 'theme'],
                'services.telegram.instance_label' => ['label' => 'Etiqueta Telegram',            'hint' => 'Nombre identificador del sitio en las notificaciones de Telegram',   'type' => 'text'],
            ],
        ],
        [
            'title' => 'Freeleech & Double Upload',
            'icon'  => 'fa-gift',
            'settings' => [
                'other.freeleech'       => ['label' => 'Freeleech global',        'hint' => 'Todos los torrents son freeleech para todos los usuarios',             'type' => 'boolean'],
                'other.freeleech_until' => ['label' => 'Freeleech hasta (UTC)',   'hint' => 'Cuándo se apaga el freeleech global. Vacío = sin fecha de fin y sin reloj en el banner. La hora es UTC, no peninsular', 'type' => 'datetime'],
                'other.freeleech_leech_amnesty' => ['label' => 'Amnistía de descarga en freeleech', 'hint' => 'Mientras haya freeleech global, los Sanguijuela recuperan la descarga para poder reponer ratio. NO afecta a quien tiene la descarga revocada por Hit & Run. Al apagar el freeleech se revierte solo. Ojo: si revocas la descarga a mano a un Sanguijuela con pocos avisos, esto se la devuelve — para castigarlo de verdad, muévelo a Castigados', 'type' => 'boolean'],
                'other.freeleech_leech_slots'   => ['label' => 'Slots durante la amnistía',       'hint' => 'Descargas simultáneas que se le conceden a Sanguijuela mientras dura la amnistía. Fuera de ella el grupo vuelve a 0, que no es un tope sino un bloqueo', 'type' => 'integer'],
                'other.doubleup'        => ['label' => 'Double upload global',    'hint' => 'Todas las descargas cuentan el doble para el upload',                  'type' => 'boolean'],
                'other.doubleup_until'  => ['label' => 'Double upload hasta (UTC)', 'hint' => 'Cuándo se apaga la doble subida global. Vacío = sin fecha de fin y sin reloj en el banner. La hora es UTC, no peninsular', 'type' => 'datetime'],
                'other.refundable'      => ['label' => 'Ratio reembolsable',      'hint' => 'El ratio puede ser reembolsado al eliminar torrents propios',          'type' => 'boolean'],
            ],
        ],
        [
            'title' => 'Ratio & Descargas',
            'icon'  => 'fa-balance-scale',
            'settings' => [
                'other.ratio'                  => ['label' => 'Ratio mínimo',                       'hint' => 'Ratio mínimo requerido para descargar',                          'type' => 'decimal'],
                'other.default_upload'         => ['label' => 'Upload inicial',                     'hint' => 'Crédito de upload que reciben los nuevos usuarios (en bytes)',    'type' => 'bytes'],
                'other.default_download'       => ['label' => 'Download inicial',                   'hint' => 'Crédito de download que reciben los nuevos usuarios (en bytes)',  'type' => 'bytes'],
                'torrent.download_check_page'  => ['label' => 'Página de verificación de descarga', 'hint' => 'Muestra una página de confirmación antes de descargar',           'type' => 'bool01'],
                'torrent.magnet'               => ['label' => 'Magnet links',                       'hint' => 'Permite usar magnet links además del archivo .torrent',           'type' => 'bool01'],
            ],
        ],
        [
            'title' => 'Invitaciones',
            'icon'  => 'fa-envelope',
            'settings' => [
                'other.invite_expire'           => ['label' => 'Expiración de invitación (días)',  'hint' => 'Días antes de que una invitación enviada expire',              'type' => 'integer'],
                'other.max_unused_user_invites' => ['label' => 'Tope al comprar invitaciones con BON', 'hint' => 'Si el usuario ya tiene este número de invitaciones sin gastar, la tienda de BON le deja de vender más. NO es un tope global: los packs de donación y las que regala el staff se lo saltan a propósito', 'type' => 'integer'],
            ],
        ],
        [
            'title' => 'Hit & Run',
            'icon'  => 'fa-exclamation-triangle',
            'settings' => [
                'hitrun.enabled'      => ['label' => 'Sistema H&R activo',             'hint' => 'Activa el sistema de penalizaciones por hit & run',                              'type' => 'boolean'],
                'hitrun.seedtime'     => ['label' => 'Tiempo mínimo de seed (horas)',    'hint' => 'Horas que debe seedearse un torrent para no recibir advertencia (ej: 96 = 4 días)', 'type' => 'integer'],
                'hitrun.max_warnings' => ['label' => 'Máx. advertencias',              'hint' => 'Número de advertencias antes de perder los privilegios de descarga',            'type' => 'integer'],
                'hitrun.grace'        => ['label' => 'Período de gracia (días)',        'hint' => 'Días que tiene el usuario para cumplir el seedtime antes de ser advertido',     'type' => 'integer'],
                'hitrun.buffer'       => ['label' => 'Buffer (%)',                     'hint' => 'Porcentaje del torrent verificado contra el downloaded real (margen de error)', 'type' => 'integer'],
                'hitrun.expire'       => ['label' => 'Expiración de advertencia (días)','hint' => 'Días antes de que una advertencia activa expire automáticamente',              'type' => 'integer'],
            ],
        ],
        [
            'title' => 'Donaciones',
            'icon'  => 'fa-hand-holding-heart',
            'settings' => [
                'donation.is_enabled' => ['label' => 'Sistema de donaciones activo', 'hint' => 'Muestra «Donar» en el menú y la página de tramos. Apagado, la página no es accesible y el panel de donaciones del staff se oculta', 'type' => 'boolean'],
                'donation.gateway_label' => ['label' => 'Nombre de la pasarela', 'hint' => 'Cómo se llama de cara al donante: Ko-fi, PayPal, Stripe… Aparece en el botón de pago y en el diálogo', 'type' => 'text'],
                'donation.amount_prefilled' => ['label' => 'El enlace lleva el importe fijado', 'hint' => 'Actívalo sólo si el enlace de cada tramo abre la pasarela con la cantidad ya puesta. Si el donante tiene que teclearla, déjalo apagado y el diálogo se lo dirá', 'type' => 'boolean'],
                'donation.monthly_goal' => ['label' => 'Objetivo mensual (€)', 'hint' => 'Lo que rellena la barra del botón «Sostén» en la barra superior. El contador suma las donaciones aprobadas desde el día 1 y se reinicia cada mes, así que la meta es mensual, no acumulada', 'type' => 'integer'],
            ],
        ],
        [
            'title' => 'Sistema de Thanks',
            'icon'  => 'fa-heart',
            'settings' => [
                'thanks-system.is-enabled' => ['label' => 'Sistema de Thanks activo', 'hint' => 'Permite a los usuarios agradecer torrents y posts', 'type' => 'boolean'],
                'other.thanks-ratio-enabled' => ['label' => 'Ratio de Thanks activo', 'hint' => 'Activa el ratio basado en descargas completadas, thanks y bonus por comentario', 'type' => 'boolean'],
                'other.thanks-ratio-minimum-overall' => ['label' => 'Ratio de Thanks mínimo global', 'hint' => 'Ratio mínimo requerido para comprar cualquier elemento de la tienda BON', 'type' => 'decimal'],
                'other.thanks-ratio-minimum-invite' => ['label' => 'Ratio de Thanks mínimo para invitaciones', 'hint' => 'Ratio mínimo requerido para comprar o enviar invitaciones', 'type' => 'decimal'],
                'other.thanks-ratio-minimum-personal-freeleech' => ['label' => 'Ratio de Thanks mínimo para freeleech personal', 'hint' => 'Ratio mínimo requerido para activar el freeleech personal de 24h', 'type' => 'decimal'],
            ],
        ],
    ];

    public function mount(): void
    {
        $this->loadSettings();
    }

    // Keys stored internally in seconds but displayed/edited in hours
    private const HOUR_FIELDS = ['hitrun.seedtime'];

    /**
     * Fechas de fin de promo. Se guardan como 'Y-m-d H:i:s' en UTC y se editan
     * con <input type="datetime-local">, que habla 'Y-m-d\TH:i'.
     *
     * Antes eran texto libre y nadie las validaba ni las parseaba en servidor:
     * el reloj del banner era el unico que las leia, y en el navegador. Una
     * cadena como '09/07/2026 3:00 PM EST' cabia igual que 'cuando yo diga'.
     */
    private const DATETIME_FIELDS = [
        'other.freeleech_until',
        'other.doubleup_until',
        'other.openreg_until',
    ];

    private const DATETIME_INPUT_FORMAT = 'Y-m-d\TH:i';

    private const DATETIME_STORAGE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Interpreta lo que venga del formulario. Devuelve la cadena lista para la
     * tabla, '' si el campo esta vacio, o null si no hay forma de entenderlo —
     * y en ese caso no se guarda nada, que es mejor que guardar basura.
     */
    public static function parseDatetime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        foreach ([self::DATETIME_INPUT_FORMAT, 'Y-m-d\TH:i:s', self::DATETIME_STORAGE_FORMAT, 'Y-m-d H:i'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone('UTC'));

            if ($parsed !== false) {
                return $parsed->format(self::DATETIME_STORAGE_FORMAT);
            }
        }

        return null;
    }

    public function loadSettings(): void
    {
        $all = Setting::all();

        $this->idByKey      = $all->pluck('id', 'key')->toArray();
        $data               = $all->pluck('value', 'id')->toArray();

        foreach (self::HOUR_FIELDS as $key) {
            if (isset($this->idByKey[$key])) {
                $id = $this->idByKey[$key];
                if (isset($data[$id])) {
                    $data[$id] = (string) round((int) $data[$id] / 3600);
                }
            }
        }

        foreach (self::DATETIME_FIELDS as $key) {
            if (isset($this->idByKey[$key])) {
                $id = $this->idByKey[$key];

                if (isset($data[$id])) {
                    $stored    = self::parseDatetime((string) $data[$id]);
                    $data[$id] = $stored === null || $stored === ''
                        ? ''
                        : \DateTimeImmutable::createFromFormat(
                            '!'.self::DATETIME_STORAGE_FORMAT,
                            $stored,
                            new \DateTimeZone('UTC')
                        )->format(self::DATETIME_INPUT_FORMAT);
                }
            }
        }

        $this->settingsData = $data;
    }

    public function save(): void
    {
        try {
            // Las fechas se validan antes de tocar la tabla: si una no se
            // entiende se aborta el guardado entero y se dice cual. Guardar
            // media configuracion es peor que no guardar ninguna.
            $invalid = [];

            foreach (self::DATETIME_FIELDS as $key) {
                if (!isset($this->idByKey[$key])) {
                    continue;
                }

                $id = $this->idByKey[$key];

                if (!isset($this->settingsData[$id])) {
                    continue;
                }

                $parsed = self::parseDatetime((string) $this->settingsData[$id]);

                if ($parsed === null) {
                    $invalid[] = $key;

                    continue;
                }

                $this->settingsData[$id] = $parsed;
            }

            if ($invalid !== []) {
                session()->flash('error', 'Fecha no válida en: '.implode(', ', $invalid).'. No se ha guardado nada.');

                return;
            }

            // Convert hours → seconds before persisting
            foreach (self::HOUR_FIELDS as $key) {
                if (isset($this->idByKey[$key])) {
                    $id = $this->idByKey[$key];
                    if (isset($this->settingsData[$id])) {
                        $this->settingsData[$id] = (string) ((int) $this->settingsData[$id] * 3600);
                    }
                }
            }

            // Estado de las promos ANTES de escribir, para anunciar despues solo
            // lo que ha cambiado de verdad.
            $promosAntes = PromoState::states();

            foreach ($this->settingsData as $id => $value) {
                Setting::where('id', $id)->update(['value' => (string) $value]);
            }

            // Volcado a settings.json (lo que restaura el seeder tras un rebuild)
            // + proyeccion sobre el tracker Rust + reconciliacion de la amnistia.
            // Encender o apagar una promo tiene que surtir efecto ya, no en la
            // siguiente pasada del comando periodico.
            PromoState::persist();

            // Encender o apagar una promo es un evento del sitio: se anuncia a
            // los miembros en el topic de noticias.
            PromoState::announceTransitions($promosAntes, PromoState::states());

            session()->flash('message', 'Configuración guardada correctamente.');
            $this->loadSettings();
        } catch (\Throwable) {
            session()->flash('error', 'Error al guardar. Revisa los logs.');
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.staff.config-manager');
    }
}
