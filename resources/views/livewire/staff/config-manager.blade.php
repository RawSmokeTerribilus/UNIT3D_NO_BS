<div>
    <div class="row">
        <div class="col-md-12">
            @if (session()->has('message'))
                <div class="alert alert-success" style="padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; color: #3c763d; background-color: #dff0d8; border-color: #d6e9c6;">
                    {{ session('message') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger" style="padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; color: #a94442; background-color: #f2dede; border-color: #ebccd1;">
                    {{ session('error') }}
                </div>
            @endif
        </div>
    </div>

    <div class="card panelV2">
        <div class="card-header panel__heading">
            <h2 class="panel__title">Gestor de Configuración Global</h2>
        </div>
        <div class="card-body panel__body">
            <form wire:submit.prevent="save">
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Parámetro</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dbSettings as $setting)
                                <tr wire:key="setting-{{ $setting->id }}">
                                    <td><strong>{{ $setting->key }}</strong></td>
                                    <td>
                                        @if($setting->key === 'other.default_style')
                                            <select class="form__input" wire:model.defer="settingsData.{{ $setting->id }}">
                                                <option value="0">Classic Light Theme</option>
                                                <option value="1">Galactic Theme</option>
                                                <option value="2">Dark Blue Theme</option>
                                                <option value="3">Dark Green Theme</option>
                                                <option value="4">Dark Pink Theme</option>
                                                <option value="5">Dark Purple Theme</option>
                                                <option value="6">Dark Red Theme</option>
                                                <option value="7">Dark Teal Theme</option>
                                                <option value="8">Dark Yellow Theme</option>
                                                <option value="9">Cosmic Void</option>
                                                <option value="10">Nord</option>
                                                <option value="11">Revel</option>
                                                <option value="12">Material Design v3 Light</option>
                                                <option value="13">Material Design v3 Dark</option>
                                                <option value="14">Material Design v3 Amoled</option>
                                                <option value="15">Material Design v3 Navy</option>
                                                <option value="16">NOBS (Nuclear Order Bit Syndicate)</option>
                                                <option value="17">Refined NOBS</option>
                                                <option value="18">Refined NOBS V2 (Retro)</option>
                                            </select>
                                        @elseif(is_numeric($setting->value))
                                            <input type="number" step="0.01" class="form__input" 
                                                wire:model.defer="settingsData.{{ $setting->id }}">
                                        @elseif($setting->value === 'true' || $setting->value === 'false')
                                            <select class="form__input" wire:model.defer="settingsData.{{ $setting->id }}">
                                                <option value="true">Activado (True)</option>
                                                <option value="false">Desactivado (False)</option>
                                            </select>
                                        @else
                                            <input type="text" class="form__input" 
                                                wire:model.defer="settingsData.{{ $setting->id }}">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="text-right mt-4" style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="form__button form__button--primary" style="background-color: #4caf50; color: white; padding: 10px 20px;">
                        <i class="{{ config('other.font-awesome') }} fa-save"></i> GUARDAR CAMBIOS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
