@php
    $group = $user->group;

    // Freeleech — site-wide OR group perk
    $siteFreeleech  = (bool) config('other.freeleech');
    $groupFreeleech = (bool) $group->is_freeleech;
    $freeleechOn    = $siteFreeleech || $groupFreeleech;
    $freeleechSrc   = match(true) {
        $siteFreeleech && $groupFreeleech => 'sitio + grupo',
        $siteFreeleech                    => 'sitio',
        $groupFreeleech                   => 'grupo',
        default                           => null,
    };
    $freeleechUntil = $siteFreeleech ? config('other.freeleech_until') : null;

    // Double upload
    $siteDouble  = (bool) config('other.doubleup');
    $groupDouble = (bool) $group->is_double_upload;
    $doubleOn    = $siteDouble || $groupDouble;
    $doubleSrc   = match(true) {
        $siteDouble && $groupDouble => 'sitio + grupo',
        $siteDouble                 => 'sitio',
        $groupDouble                => 'grupo',
        default                     => null,
    };

    // Minimum ratio — group overrides global when set
    $groupRatio     = $group->min_ratio !== null ? (float) $group->min_ratio : null;
    $globalRatio    = (float) config('other.ratio');
    $effectiveRatio = $groupRatio ?? $globalRatio;
    $ratioSrc       = $groupRatio !== null ? 'grupo' : null;

    // Hit & Run
    $hrEnabled  = (bool) config('hitrun.enabled');
    $hrImmune   = (bool) $group->is_immune;
    $hrSeedtime = (int) round(config('hitrun.seedtime') / 3600); // hours
    $hrGrace    = (int) config('hitrun.grace');
    $hrMaxWarn  = (int) config('hitrun.max_warnings');
    $hrExpire   = (int) config('hitrun.expire');

    // Download slots
    $slots = $group->download_slots;
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
                        <span style="font-size:.75em; opacity:.7"> hasta {{ $freeleechUntil }}</span>
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

        {{-- Minimum ratio --}}
        <div class="key-value__group">
            <dt>Ratio mínimo</dt>
            <dd>
                @if ($effectiveRatio == 0)
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
                    <span style="font-size:.8em; opacity:.7">(grupo inmune)</span>
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
                @else
                    {{ $slots }}
                @endif
            </dd>
        </div>

    </dl>
</section>
