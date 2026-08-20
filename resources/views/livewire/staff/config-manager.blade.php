<div>
    @if (session()->has('message'))
        <div class="panelV2" style="border-left: 4px solid #4caf50; margin-bottom: 16px;">
            <div class="panel__body" style="padding: 12px 16px; color: #4caf50;">
                <i class="{{ config('other.font-awesome') }} fa-check-circle"></i>
                {{ session('message') }}
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="panelV2" style="border-left: 4px solid #e53935; margin-bottom: 16px;">
            <div class="panel__body" style="padding: 12px 16px; color: #e53935;">
                <i class="{{ config('other.font-awesome') }} fa-times-circle"></i>
                {{ session('error') }}
            </div>
        </div>
    @endif

    <form wire:submit.prevent="save">

        @foreach(App\Http\Livewire\Staff\ConfigManager::$groups as $group)
            <section class="panelV2" style="margin-bottom: 20px;">
                <h2 class="panel__heading">
                    <i class="{{ config('other.font-awesome') }} {{ $group['icon'] }}" style="margin-right: 8px;"></i>
                    {{ $group['title'] }}
                </h2>
                <div class="panel__body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Parámetro</th>
                                <th style="width: 65%;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group['settings'] as $key => $meta)
                                @php $id = $idByKey[$key] ?? null; @endphp
                                @if($id === null) @continue @endif

                                <tr>
                                    <td>
                                        <strong>{{ $meta['label'] }}</strong>
                                        @if($meta['hint'])
                                            <br><small style="color: var(--color-text-muted, #888); font-weight: normal;">{{ $meta['hint'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @switch($meta['type'])

                                            @case('boolean')
                                                <select class="form__input" wire:model="settingsData.{{ $id }}" style="max-width: 220px;">
                                                    <option value="true">✅ Activado</option>
                                                    <option value="false">❌ Desactivado</option>
                                                </select>
                                                @break

                                            @case('bool01')
                                                <select class="form__input" wire:model="settingsData.{{ $id }}" style="max-width: 220px;">
                                                    <option value="1">✅ Activado</option>
                                                    <option value="0">❌ Desactivado</option>
                                                </select>
                                                @break

                                            @case('theme')
                                                <select class="form__input" wire:model="settingsData.{{ $id }}" style="max-width: 320px;">
                                                    <option value="0">Classic Light</option>
                                                    <option value="1">Galactic</option>
                                                    <option value="2">Dark Blue</option>
                                                    <option value="3">Dark Green</option>
                                                    <option value="4">Dark Pink</option>
                                                    <option value="5">Dark Purple</option>
                                                    <option value="6">Dark Red</option>
                                                    <option value="7">Dark Teal</option>
                                                    <option value="8">Dark Yellow</option>
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
                                                @break

                                            @case('decimal')
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="form__input"
                                                    wire:model="settingsData.{{ $id }}"
                                                    style="max-width: 120px;"
                                                />
                                                @break

                                            @case('integer')
                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="0"
                                                    class="form__input"
                                                    wire:model="settingsData.{{ $id }}"
                                                    style="max-width: 120px;"
                                                />
                                                @break

                                            @case('bytes')
                                                @php
                                                    $bytes = (int) ($settingsData[$id] ?? 0);
                                                    $gb    = $bytes > 0 ? round($bytes / 1073741824, 2) : 0;
                                                @endphp
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <input
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        class="form__input"
                                                        wire:model="settingsData.{{ $id }}"
                                                        style="max-width: 200px;"
                                                    />
                                                    <small style="color: var(--color-text-muted, #888);">≈ {{ $gb }} GB</small>
                                                </div>
                                                @break

                                            @case('seedtime')
                                                @php
                                                    $secs  = (int) ($settingsData[$id] ?? 0);
                                                    $hours = round($secs / 3600, 1);
                                                    $days  = round($secs / 86400, 2);
                                                @endphp
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <input
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        class="form__input"
                                                        wire:model="settingsData.{{ $id }}"
                                                        style="max-width: 160px;"
                                                    />
                                                    <small style="color: var(--color-text-muted, #888);">≈ {{ $hours }}h / {{ $days }} días</small>
                                                </div>
                                                @break

                                            @default
                                                <input
                                                    type="text"
                                                    class="form__input"
                                                    wire:model="settingsData.{{ $id }}"
                                                    style="max-width: 380px;"
                                                />
                                                @break

                                        @endswitch
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <div style="text-align: right; margin-bottom: 32px;">
            <button type="submit" class="form__button form__button--filled">
                <i class="{{ config('other.font-awesome') }} fa-save"></i>
                Guardar todos los cambios
            </button>
        </div>

    </form>
</div>
