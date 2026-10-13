{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
@php
    $group = $user->group;

    // Donante: los perks los aplica el announce Rust con sus overrides
    // (DONOR_*/LIFETIME_DONOR_* en docker-compose.yml): descarga 0, subida
    // x2 o x2,55 para siempre, inmunidad H&R y slots ilimitados. El panel solo
    // miraba sitio y grupo, y a todo donante le pintaba la cruz.
    $isDonor    = (bool) $user->is_donor;
    $donorLabel = $isDonor ? ($user->is_lifetime ? 'donante, para siempre' : 'donante') : null;

    // Freeleech — site-wide OR group perk OR donante
    $siteFreeleech  = (bool) config('other.freeleech');
    $groupFreeleech = (bool) $group->is_freeleech;
    $freeleechOn    = $siteFreeleech || $groupFreeleech || $isDonor;
    $freeleechSrc   = implode(' + ', array_filter([
        $siteFreeleech ? 'sitio' : null,
        $groupFreeleech ? 'grupo' : null,
        $donorLabel,
    ])) ?: null;
    // La fecha se guarda en UTC. Se imprime formateada y CON la zona dicha en
    // voz alta: antes salia la cadena cruda '06/15/2026 3:00 PM EST' en mitad
    // de una frase en espanol, y nadie sabia a que hora era eso.
    $freeleechUntil = $siteFreeleech ? \App\Services\PromoState::until('freeleech')?->format('d/m/Y H:i') : null;

    // Double upload
    $siteDouble  = (bool) config('other.doubleup');
    $groupDouble = (bool) $group->is_double_upload;
    $doubleOn    = $siteDouble || $groupDouble || $isDonor;
    $doubleSrc   = implode(' + ', array_filter([
        $siteDouble ? 'sitio' : null,
        $groupDouble ? 'grupo' : null,
        $isDonor ? ($user->is_lifetime ? 'donante, x2,55' : 'donante') : null,
    ])) ?: null;

    // Minimum ratio — group overrides global when set
    $groupRatio     = $group->min_ratio !== null ? (float) $group->min_ratio : null;
    $globalRatio    = (float) config('other.ratio');
    $effectiveRatio = $groupRatio ?? $globalRatio;
    $ratioSrc       = $groupRatio !== null ? 'grupo' : null;

    // Hit & Run
    $hrEnabled  = (bool) config('hitrun.enabled');
    $hrImmune   = (bool) $group->is_immune || $isDonor;
    $hrImmuneSrc = $group->is_immune ? 'grupo inmune' : $donorLabel;
    $hrSeedtime = (int) round(config('hitrun.seedtime') / 3600); // hours
    $hrGrace    = (int) config('hitrun.grace');
    $hrMaxWarn  = (int) config('hitrun.max_warnings');
    $hrExpire   = (int) config('hitrun.expire');

    // Download slots
    $slots = $group->download_slots;

    // Amnistia de descarga: el grupo Sanguijuela recupera la descarga
    // mientras dura el freeleech, salvo bloqueo por H&R.
    $amnestyOn      = \App\Services\LeechAmnesty::isActive();
    $amnestyApplies = $amnestyOn && $group->slug === \App\Services\LeechAmnesty::GROUP_SLUG;
    $amnestyGranted = $amnestyApplies && (bool) $user->can_download;
@endphp

<section class="panelV2">
    <h2 class="panel__heading">
        <i class="{{ config('other.font-awesome') }} fa-user-shield"></i>
        Condiciones que te aplican
    </h2>
    <dl class="key-value">

        {{-- Freeleech --}}
        <div class="key-value__group">
            <dt>Freeleech</dt>
            <dd>
                @if ($freeleechOn)
                    <i class="{{ config('other.font-awesome') }} fa-check text-green"></i>
                    <span class="text-green" style="font-size:.8em">{{ $freeleechSrc }}</span>
                    @if ($freeleechUntil)
                        <span style="font-size:.75em; opacity:.7"> hasta {{ $freeleechUntil }} UTC</span>
                    @endif
                @else
                    <i class="{{ config('other.font-awesome') }} fa-times text-red"></i>
                @endif
            </dd>
        </div>

        {{-- Double upload --}}
        <div class="key-value__group">
            <dt>Double upload</dt>
            <dd>
                @if ($doubleOn)
                    <i class="{{ config('other.font-awesome') }} fa-check text-green"></i>
                    <span class="text-green" style="font-size:.8em">{{ $doubleSrc }}</span>
                @else
                    <i class="{{ config('other.font-awesome') }} fa-times text-red"></i>
                @endif
            </dd>
        </div>

        {{-- Amnistía de descarga del freeleech --}}
        @if ($amnestyApplies)
            <div class="key-value__group">
                <dt>Amnistía de freeleech</dt>
                <dd>
                    @if ($amnestyGranted)
                        <i class="{{ config('other.font-awesome') }} fa-unlock text-green"></i>
                        <span class="text-green">Descarga devuelta</span>
                        <span style="font-size:.75em; opacity:.7">
                            mientras dure el freeleech — siembra lo que bajes, congela pero no repara
                        </span>
                    @else
                        <i class="{{ config('other.font-awesome') }} fa-lock text-red"></i>
                        <span class="text-red">No aplica</span>
                        <span style="font-size:.75em; opacity:.7">
                            tienes la descarga revocada por Hit &amp; Run; la amnistía perdona el ratio, no los avisos
                        </span>
                    @endif
                </dd>
            </div>
        @endif

        {{-- Minimum ratio --}}
        <div class="key-value__group">
            <dt>Ratio mínimo</dt>
            <dd>
                @if ($amnestyGranted)
                    <span class="text-green">Sin mínimo</span>
                    <span style="font-size:.8em; opacity:.7">(amnistía de freeleech)</span>
                @elseif ($effectiveRatio == 0)
                    <span class="text-green">Sin mínimo</span>
                @else
                    {{ number_format($effectiveRatio, 2) }}
                    @if ($ratioSrc)
                        <span style="font-size:.8em; opacity:.7">({{ $ratioSrc }})</span>
                    @endif
                @endif
            </dd>
        </div>

        {{-- Hit & Run --}}
        @if (!$hrEnabled)
            <div class="key-value__group">
                <dt>Hit &amp; Run</dt>
                <dd><span style="opacity:.6">Sistema desactivado</span></dd>
            </div>
        @elseif ($hrImmune)
            <div class="key-value__group">
                <dt>Hit &amp; Run</dt>
                <dd>
                    <i class="{{ config('other.font-awesome') }} fa-shield-alt text-green"></i>
                    <span class="text-green">Exento</span>
                    <span style="font-size:.8em; opacity:.7">({{ $hrImmuneSrc }})</span>
                </dd>
            </div>
        @else
            <div class="key-value__group">
                <dt>Seed mínimo</dt>
                <dd>{{ $hrSeedtime }}h</dd>
            </div>
            <div class="key-value__group">
                <dt>Período de gracia</dt>
                <dd>{{ $hrGrace }} {{ $hrGrace === 1 ? 'día' : 'días' }}</dd>
            </div>
            <div class="key-value__group">
                <dt>Avisos máximos</dt>
                <dd>{{ $hrMaxWarn }}</dd>
            </div>
            <div class="key-value__group">
                <dt>Aviso expira en</dt>
                <dd>{{ $hrExpire }} {{ $hrExpire === 1 ? 'día' : 'días' }}</dd>
            </div>
        @endif

        {{-- Download slots --}}
        <div class="key-value__group">
            <dt>Slots de descarga</dt>
            <dd>
                @if ($slots === null)
                    <span style="opacity:.7">Ilimitados</span>
                @elseif ($isDonor)
                    <span class="text-green">Ilimitados</span>
                    <span style="font-size:.8em; opacity:.7">({{ $donorLabel }})</span>
                @else
                    {{ $slots }}
                @endif
            </dd>
        </div>

    </dl>
</section>
