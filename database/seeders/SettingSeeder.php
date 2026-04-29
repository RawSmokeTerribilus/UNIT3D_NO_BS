<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'other.ratio' => (string) config('other.ratio'),
            'other.freeleech' => config('other.freeleech') ? 'true' : 'false',
            'other.freeleech_until' => (string) config('other.freeleech_until'),
            'other.doubleup' => config('other.doubleup') ? 'true' : 'false',
            'other.refundable' => config('other.refundable') ? 'true' : 'false',
            'other.default_upload' => (string) config('other.default_upload'),
            'other.default_download' => (string) config('other.default_download'),
            'hitrun.enabled' => config('hitrun.enabled') ? 'true' : 'false',
            'hitrun.seedtime' => (string) config('hitrun.seedtime'),
            'hitrun.max_warnings' => (string) config('hitrun.max_warnings'),
            'hitrun.grace' => (string) config('hitrun.grace'),
            'hitrun.buffer' => (string) config('hitrun.buffer'),
            'hitrun.expire' => (string) config('hitrun.expire'),
            'other.invite_expire' => (string) config('other.invite_expire'),
            'other.max_unused_user_invites' => (string) config('other.max_unused_user_invites'),
            'other.default_style' => (string) config('other.default_style'),
            'torrent.download_check_page' => (string) config('torrent.download_check_page'),
            'torrent.magnet' => (string) config('torrent.magnet'),
            'other.invite-only' => config('other.invite-only') ? 'true' : 'false',
            'thanks-system.is-enabled' => config('thanks-system.is-enabled') ? 'true' : 'false',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
