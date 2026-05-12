<?php

declare(strict_types=1);

namespace App\Http\Livewire\Staff;

use App\Models\Setting;
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
                'other.default_style'              => ['label' => 'Tema por defecto',             'hint' => 'Tema que verán los nuevos usuarios al registrarse',                  'type' => 'theme'],
                'services.telegram.instance_label' => ['label' => 'Etiqueta Telegram',            'hint' => 'Nombre identificador del sitio en las notificaciones de Telegram',   'type' => 'text'],
            ],
        ],
        [
            'title' => 'Freeleech & Double Upload',
            'icon'  => 'fa-gift',
            'settings' => [
                'other.freeleech'       => ['label' => 'Freeleech global',        'hint' => 'Todos los torrents son freeleech para todos los usuarios',             'type' => 'boolean'],
                'other.freeleech_until' => ['label' => 'Freeleech hasta',         'hint' => 'Fecha y hora de fin del freeleech — formato: MM/DD/YYYY H:MM AM/PM TZ', 'type' => 'text'],
                'other.doubleup'        => ['label' => 'Double upload global',    'hint' => 'Todas las descargas cuentan el doble para el upload',                  'type' => 'boolean'],
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
                'other.max_unused_user_invites' => ['label' => 'Máx. invitaciones sin usar',       'hint' => 'Invitaciones pendientes máximas permitidas por usuario',      'type' => 'integer'],
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

        $this->settingsData = $data;
    }

    public function save(): void
    {
        try {
            // Convert hours → seconds before persisting
            foreach (self::HOUR_FIELDS as $key) {
                if (isset($this->idByKey[$key])) {
                    $id = $this->idByKey[$key];
                    if (isset($this->settingsData[$id])) {
                        $this->settingsData[$id] = (string) ((int) $this->settingsData[$id] * 3600);
                    }
                }
            }

            foreach ($this->settingsData as $id => $value) {
                Setting::where('id', $id)->update(['value' => (string) $value]);
            }

            // Write JSON snapshot so the seeder restores live values after container rebuilds
            $snapshot = Setting::all()->pluck('value', 'key')->toArray();
            file_put_contents(
                storage_path('app/settings.json'),
                json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

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
