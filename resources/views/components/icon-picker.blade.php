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
                indexUrl: @js(url('vendor/fontawesome/icon-index.json')),
                coverage: {},
                // Which loaded @font-face answers for each style prefix. The
                // fonts are the vendored FA Pro build; document.fonts.check()
                // against them is the ground truth of which glyphs a style
                // really covers (brands only exist in fab, and vice versa).
                fontSpecs: {
                    fas: '900 16px "Font Awesome 6 Pro"',
                    far: '400 16px "Font Awesome 6 Pro"',
                    fal: '300 16px "Font Awesome 6 Pro"',
                    fat: '100 16px "Font Awesome 6 Pro"',
                    fad: '900 16px "Font Awesome 6 Duotone"',
                    fab: '400 16px "Font Awesome 6 Brands"',
                },
                get visibleIcons() {
                    const query = this.query.trim().toLowerCase();
                    const known = this.coverage[this.style];
                    const matches = [];

                    for (const icon of this.catalogue) {
                        if (known && !known.has(icon[1])) {
                            continue;
                        }

                        if (query === '' || icon[0].includes(query)) {
                            matches.push(icon);

                            if (matches.length >= 120) {
                                break;
                            }
                        }
                    }

                    return matches;
                },
                async buildCoverage(style) {
                    if (this.coverage[style] || !document.fonts?.check) {
                        return;
                    }

                    const spec = this.fontSpecs[style];

                    try {
                        // check() answers false for faces not yet loaded, so
                        // force the load first; the argument text is anything
                        // non-empty.
                        await document.fonts.load(spec, '\uf005');
                    } catch (error) {
                        return; // leave the style unfiltered rather than empty
                    }

                    const known = new Set();

                    for (const icon of this.catalogue) {
                        if (document.fonts.check(spec, String.fromCodePoint(parseInt(icon[1], 16)))) {
                            known.add(icon[1]);
                        }
                    }

                    // A face that failed to load answers false for everything;
                    // an empty filter would blank the whole grid for no reason.
                    if (known.size > 0) {
                        this.coverage[style] = known;
                    }
                },
                toggle() {
                    this.isOpen = !this.isOpen;

                    if (!this.isOpen) {
                        return;
                    }

                    // Pre-select the style already written in the field, so
                    // picking a replacement keeps e.g. an intentional `fal`.
                    const prefix = this.$refs.field.value.trim().split(/\s+/)[0];

                    if (this.styles.includes(prefix)) {
                        this.style = prefix;
                    }

                    this.loadCatalogue();
                    this.$nextTick(() => this.$refs.search?.focus());
                },
                init() {
                    this.$watch('style', (style) => this.buildCoverage(style));
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
                        this.status = '';
                        this.buildCoverage(this.style);
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
