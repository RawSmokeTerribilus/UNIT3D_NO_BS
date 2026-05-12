@foreach ($roms as $rom)
    <li class="ra-rom__item">
        <a class="ra-rom__card" href="{{ route('retroarch.show', ['system' => $system, 'slug' => $rom['slug']]) }}">
            <div class="ra-rom__cover">
                @if (! empty($rom['cover']))
                    <img src="{{ $rom['cover'] }}" alt="Portada de {{ $rom['title'] }}" loading="lazy" />
                @else
                    <i class="{{ config('other.font-awesome') }} fa-circle-question ra-rom__cover-fallback"></i>
                @endif
            </div>
            <div class="ra-rom__info">
                <h3 class="ra-rom__title">{{ $rom['title'] }}</h3>
                <p class="ra-rom__meta">
                    <span>{{ number_format($rom['size'] / 1024, 0) }} KB</span>
                </p>
                <span class="btn btn--filled ra-rom__play">
                    <i class="{{ config('other.font-awesome') }} fa-play"></i> Jugar
                </span>
            </div>
        </a>
    </li>
@endforeach
