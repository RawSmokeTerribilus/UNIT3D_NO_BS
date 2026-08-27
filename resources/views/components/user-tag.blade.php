@props([
    'style',
    'anon',
    'appendedIcons',
    'user',
])

@php
    // Icono de rango a la IZQUIERDA del nick. En UNIT3D es una CLASE de
    // FontAwesome sobre el <a>, que la pinta con ::before y le aplica el color
    // del grupo. Un fichero .svg no puede ser una clase, asi que cuando el
    // donante tiene uno se emite un <img> dentro del enlace y se deja el <a>
    // sin clase de icono.
    //
    // Se usa <img> y no `mask-image` porque de las 21 insignias 11 son
    // multicolor: enmascarar las aplanaria a un unico color. El precio es que
    // el SVG no hereda el color del rango, cosa que de todas formas nunca fue
    // posible para esas 11.
    // Se mira si el campo esta puesto, no `is_donor`. No es por dar el perk a
    // nadie mas: es que la condicion estaba repetida en nueve sitios de este
    // fichero y tres del chat, y eso se desincroniza. Quien puede ELEGIRLO se
    // decide en un solo punto (el formulario del perfil), y al caducar lo
    // limpia AutoRemoveExpiredDonors.
    $iconoRango = $user->donor_rank_icon ?? $user->group->icon;

    $iconoRangoEsSvg = str_ends_with((string) $iconoRango, '.svg');

    $claseIconoRango = $iconoRangoEsSvg ? '' : $iconoRango;

    $tituloRango = $user->group->name;
@endphp

@if ($anon)
    @if (auth()->user()->is($user) || auth()->user()->group->is_modo)
        <span
            {{ $attributes->class('user-tag fas fa-eye-slash') }}
            {{ $attributes->merge(['style' => 'background: ' . ($user->donor_effect ?? ($user->is_donor == 1 ? 'url(/img/sparkels.gif) center/auto 100% repeat-x' : $user->group->effect)) . ';' . ($style ?? '')]) }}
        >
            (
            <a
                class="user-tag__link user-tag__link--anonymous {{ $claseIconoRango }}"
                href="{{ route('users.show', ['user' => $user]) }}"
                style="color: {{ $user->group->color }}"
                title="{{ $tituloRango }}"
            >
                @if ($iconoRangoEsSvg)
                    <img
                        class="user-tag__rank-svg"
                        src="{{ asset('img/insignias/'.basename($iconoRango)).'?v='.config('perks-donante.version') }}"
                        alt="{{ $tituloRango }}"
                        loading="lazy"
                    />
                @endif

                {{ $user->username }}
            </a>
            @if ($user->icon !== null)
                <i>
                    <img
                        @style([
                            'max-height: 22px;' =>
                                request()
                                    ->route()
                                    ->getName() === 'users.show',
                            'max-height: 17px;' =>
                                request()
                                    ->route()
                                    ->getName() !== 'users.show',
                            'vertical-align: text-bottom',
                        ])
                        title="Icono propio"
                        src="{{ route('authenticated_images.user_icon', ['user' => $user]) }}"
                    />
                </i>
            @endif

            @if ($user->donor_badge_icon !== null)
                @if (str_ends_with($user->donor_badge_icon, '.svg'))
                    <img
                        class="user-tag__badge-svg"
                        src="{{ asset('img/insignias/'.basename($user->donor_badge_icon)).'?v='.config('perks-donante.version') }}"
                        alt="{{ $user->donor_badge_title }}"
                        title="{{ $user->donor_badge_title }}"
                        loading="lazy"
                    />
                @else
                    <i
                        class="{{ $user->donor_badge_icon }}"
                        style="color: {{ $user->donor_badge_color ?? 'inherit' }}"
                        title="{{ $user->donor_badge_title }}"
                    ></i>
                @endif
            @elseif ($user->is_lifetime == 1)
                <i class="fal fa-star" id="lifeline" title="Donante de por vida"></i>
            @elseif ($user->is_donor == 1)
                <i class="fal fa-star text-gold" title="Donante"></i>
            @endif

            {{ $appendedIcons ?? '' }}
            )
        </span>
    @else
        <span {{ $attributes->class('user-tag fas fa-eye-slash') }}>
            ({{ __('common.anonymous') }})
        </span>
    @endif
@else
    <span
        {{ $attributes->class('user-tag') }}
        {{ $attributes->merge(['style' => 'background: ' . ($user->donor_effect ?? ($user->is_donor == 1 ? 'url(/img/sparkels.gif) center/auto 100% repeat-x' : $user->group->effect)) . ';' . ($style ?? '')]) }}
    >
        <a
            class="user-tag__link {{ $claseIconoRango }}"
            href="{{ route('users.show', ['user' => $user]) }}"
            style="color: {{ $user->group->color }}"
            title="{{ $tituloRango }}"
        >
            @if ($iconoRangoEsSvg)
                <img
                    class="user-tag__rank-svg"
                    src="{{ asset('img/insignias/'.basename($iconoRango)).'?v='.config('perks-donante.version') }}"
                    alt="{{ $tituloRango }}"
                    loading="lazy"
                />
            @endif

            {{ $user->username }}
        </a>
        @if ($user->icon !== null)
            <i>
                <img
                    @style([
                        'max-height: 22px;' =>
                            request()
                                ->route()
                                ->getName() === 'users.show',
                        'max-height: 17px;' =>
                            request()
                                ->route()
                                ->getName() !== 'users.show',
                        'vertical-align: text-bottom',
                    ])
                    title="Icono propio"
                    src="{{ route('authenticated_images.user_icon', ['user' => $user]) }}"
                />
            </i>
        @endif

        @if ($user->donor_badge_icon !== null)
            @if (str_ends_with($user->donor_badge_icon, '.svg'))
                <img
                    class="user-tag__badge-svg"
                    src="{{ asset('img/insignias/'.basename($user->donor_badge_icon)).'?v='.config('perks-donante.version') }}"
                    alt="{{ $user->donor_badge_title }}"
                    title="{{ $user->donor_badge_title }}"
                    loading="lazy"
                />
            @else
                <i
                    class="{{ $user->donor_badge_icon }}"
                    style="color: {{ $user->donor_badge_color ?? 'inherit' }}"
                    title="{{ $user->donor_badge_title }}"
                ></i>
            @endif
        @elseif ($user->is_lifetime == 1)
            <i class="fal fa-star" id="lifeline" title="Donante de por vida"></i>
        @elseif ($user->is_donor == 1)
            <i class="fal fa-star text-gold" title="Donante"></i>
        @endif

        {{ $appendedIcons ?? '' }}
    </span>
@endif
