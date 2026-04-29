<?php

declare(strict_types=1);

namespace App\Http\Livewire\Staff;

use App\Models\Setting;
use Livewire\Component;

class ConfigManager extends Component
{
    public $settingsData = [];

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $this->settingsData = Setting::all()->pluck('value', 'id')->toArray();
    }

    public function save()
    {
        try {
            foreach ($this->settingsData as $id => $value) {
                Setting::where('id', $id)->update(['value' => (string) $value]);
            }
            session()->flash('message', 'Configuración guardada correctamente.');
            $this->loadSettings();
        } catch (\Throwable $e) {
            session()->flash('error', 'Error al guardar.');
        }
    }

    public function render()
    {
        return view('livewire.staff.config-manager', [
            'dbSettings' => Setting::all()
        ]);
    }
}
