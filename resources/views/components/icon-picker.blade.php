@props([
    'name' => 'icon',
    'value' => '',
    'label' => null,
    'required' => false,
])

@php($pickerId = 'icon-picker-'.trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-'))

<p class="form__group icon-picker" x-data="iconPicker" x-on:click.outside="close()" x-on:keydown.escape="close()">
    <input
        id="{{ $pickerId }}"
        class="form__text"
        type="text"
        name="{{ $name }}"
        placeholder=" "
        value="{{ $value }}"
        x-ref="field"
        @required($required)
    />
    <label class="form__label form__label--floating" for="{{ $pickerId }}">
        {{ $label ?? __('common.icon') }} (E.g. "fas fa-rocket")
    </label>
    <button
        type="button"
        class="icon-picker__toggle"
        title="Pick an icon"
        x-on:click="toggle()"
    >
        <i x-bind:class="$refs.field.value.trim() === '' ? 'fas fa-icons' : $refs.field.value"></i>
    </button>
    <span class="icon-picker__panel" x-cloak x-show="isOpen">
        <span class="icon-picker__search">
            <input
                type="text"
                class="icon-picker__search-input"
                placeholder="Search icons"
                autocomplete="off"
                x-ref="search"
                x-model="query"
            />
        </span>
        <span class="icon-picker__styles">
            <template x-for="s in styles" :key="s">
                <button
                    type="button"
                    class="icon-picker__style"
                    x-bind:class="s === style && 'icon-picker__style--active'"
                    x-on:click="style = s"
                    x-text="s"
                ></button>
            </template>
        </span>
        <span class="icon-picker__status" x-show="status !== ''" x-text="status"></span>
        <span
            class="icon-picker__status"
            x-show="status === '' && totalMatches > visibleIcons.length"
        >
            <span x-text="'Showing ' + visibleIcons.length + ' of ' + totalMatches + '.'"></span>
            <button type="button" class="icon-picker__show-all" x-on:click="showAll = true">
                Show all
            </button>
        </span>
        <span class="icon-picker__grid" x-show="status === ''">
            <template x-for="icon in visibleIcons" :key="icon[0]">
                <button
                    type="button"
                    class="icon-picker__button"
                    x-bind:title="style + ' fa-' + icon[0]"
                    x-on:click="pick(icon[0])"
                >
                    <i x-bind:class="style + ' fa-' + icon[0]"></i>
                </button>
            </template>
        </span>
        <span class="icon-picker__hint">
            Tip: only icons the current style really has are listed — brands live under
            <code>fab</code>.
        </span>
    </span>
</p>

@once
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('iconPicker', () => ({
                isOpen: false,
                catalogue: [],
                query: '',
                status: '',
                style: 'fas',
                styles: ['fas', 'far', 'fal', 'fat', 'fad', 'fab'],
                indexUrl: @js(url('vendor/fontawesome/icon-index.json').'?v='.(@filemtime(public_path('vendor/fontawesome/icon-index.json')) ?: '0')),
                totalMatches: 0,
                showAll: false,
                get visibleIcons() {
                    // Each entry is [name, codepoint, mask]; bit i of the mask
                    // says the font behind styles[i] really contains the glyph.
                    // Resolved server-side from the TTFs' cmap tables — the
                    // browser font APIs are face-level and answer differently
                    // per browser.
                    const query = this.query.trim().toLowerCase();
                    const bit = 1 << this.styles.indexOf(this.style);
                    const matches = [];
                    let total = 0;

                    for (const icon of this.catalogue) {
                        if ((icon[2] & bit) === 0) {
                            continue;
                        }

                        if (query === '' || icon[0].includes(query)) {
                            total++;

                            // The grid opens with at most 120 so the panel
                            // is instant; "show all" renders the rest on
                            // demand (content-visibility keeps offscreen rows
                            // cheap). Without a visible counter/button, the
                            // alphabetical wall hid everything past the As.
                            if (this.showAll || matches.length < 120) {
                                matches.push(icon);
                            }
                        }
                    }

                    this.totalMatches = total;

                    return matches;
                },
                toggle() {
                    this.isOpen = !this.isOpen;

                    if (!this.isOpen) {
                        return;
                    }

                    this.showAll = false;

                    // Pre-select the style already written in the field, so
                    // picking a replacement keeps e.g. an intentional `fal`.
                    const prefix = this.$refs.field.value.trim().split(/\s+/)[0];

                    if (this.styles.includes(prefix)) {
                        this.style = prefix;
                    }

                    this.loadCatalogue();
                    this.$nextTick(() => this.$refs.search?.focus());
                },
                close() {
                    this.isOpen = false;
                },
                async loadCatalogue() {
                    if (this.catalogue.length > 0 || this.status === 'Loading icons...') {
                        return;
                    }

                    this.status = 'Loading icons...';

                    try {
                        const response = await fetch(this.indexUrl, {
                            headers: { Accept: 'application/json' },
                        });

                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }

                        const index = await response.json();

                        this.catalogue = index.icons ?? [];
                        this.styles = index.styles ?? this.styles;
                        this.status = '';
                    } catch (error) {
                        // Never a dead panel: say what to do instead.
                        this.status =
                            'Icon list unavailable. You can still type a class like "fas fa-rocket" by hand.';
                        console.error('icon index: ' + error.message);
                    }
                },
                pick(icon) {
                    const field = this.$refs.field;

                    field.value = this.style + ' fa-' + icon;
                    field.dispatchEvent(new Event('input'));
                    this.close();
                },
            }));
        });
    </script>
@endonce
