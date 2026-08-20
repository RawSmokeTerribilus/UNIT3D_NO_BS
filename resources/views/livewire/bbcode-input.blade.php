<div id="bbcode-input" class="bbcode-input" x-data="{{ $name }}BbcodeInput">
    <p class="bbcode-input__tabs">
        <input
            class="bbcode-input__tab-input"
            type="radio"
            id="{{ $name }}-bbcode-preview-disabled"
            value="0"
            wire:model.live="isPreviewEnabled"
        />
        <label class="bbcode-input__tab-label" for="{{ $name }}-bbcode-preview-disabled">
            Write
        </label>
        <input
            class="bbcode-input__tab-input"
            type="radio"
            id="{{ $name }}-bbcode-preview-enabled"
            value="1"
            wire:model.live="isPreviewEnabled"
        />
        <label class="bbcode-input__tab-label" for="{{ $name }}-bbcode-preview-enabled">
            {{ __('common.preview') }}
        </label>
    </p>
    <p class="bbcode-input__icon-bar-toggle">
        <button
            type="button"
            class="form__button form__button--text"
            x-on:click="toggleButtonVisibility"
        >
            BBCode
        </button>
    </p>
    <menu class="bbcode-input__icon-bar" x-cloak x-show="showButtons">
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertBold">
                <abbr title="Bold">
                    <i class="{{ config('other.font-awesome') }} fa-bold"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertItalic">
                <abbr title="Italics">
                    <i class="{{ config('other.font-awesome') }} fa-italic"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button
                type="button"
                class="form__standard-icon-button"
                x-on:click="insertUnderline"
            >
                <abbr title="Underline">
                    <i class="{{ config('other.font-awesome') }} fa-underline"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button
                type="button"
                class="form__standard-icon-button"
                x-on:click="insertStrikethrough"
            >
                <abbr title="Strikethrough">
                    <i class="{{ config('other.font-awesome') }} fa-strikethrough"></i>
                </abbr>
            </button>
        </li>
        <hr class="bbcode-input__icon-separator" />
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertImage">
                <abbr title="Insert image">
                    <i class="{{ config('other.font-awesome') }} fa-image"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertYoutube">
                <abbr title="Insert YouTube">
                    <i class="fab fa-youtube"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertUrl">
                <abbr title="Link">
                    <i class="{{ config('other.font-awesome') }} fa-link"></i>
                </abbr>
            </button>
        </li>
        <hr class="bbcode-input__icon-separator" />
        <li>
            <button
                type="button"
                class="form__standard-icon-button"
                x-on:click="insertUnorderedList"
            >
                <abbr title="Unordered list">
                    <i class="{{ config('other.font-awesome') }} fa-list"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button
                type="button"
                class="form__standard-icon-button"
                x-on:click="insertOrderedList"
            >
                <abbr title="Ordered list">
                    <i class="{{ config('other.font-awesome') }} fa-list-ol"></i>
                </abbr>
            </button>
        </li>
        <hr class="bbcode-input__icon-separator" />
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertColor">
                <abbr title="Font color">
                    <i class="{{ config('other.font-awesome') }} fa-palette"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertSize">
                <abbr title="Font size">
                    <i class="{{ config('other.font-awesome') }} fa-text-size"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button
                type="button"
                class="form__button form__button--text"
                x-on:click="insertFont"
            >
                <abbr title="Font family">Font</abbr>
            </button>
        </li>
        <hr class="bbcode-input__icon-separator" />
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertLeft">
                <abbr title="Align left">
                    <i class="{{ config('other.font-awesome') }} fa-align-left"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertCenter">
                <abbr title="Align center">
                    <i class="{{ config('other.font-awesome') }} fa-align-center"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertRight">
                <abbr title="Align right">
                    <i class="{{ config('other.font-awesome') }} fa-align-right"></i>
                </abbr>
            </button>
        </li>
        <hr class="bbcode-input__icon-separator" />
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertQuote">
                <abbr title="Quote">
                    <i class="{{ config('other.font-awesome') }} fa-quote-right"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertCode">
                <abbr title="Code">
                    <i class="{{ config('other.font-awesome') }} fa-code"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertSpoiler">
                <abbr title="Spoiler">
                    <i class="{{ config('other.font-awesome') }} fa-eye-slash"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertNote">
                <abbr title="Note">
                    <i class="{{ config('other.font-awesome') }} fa-sticky-note"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertAlert">
                <abbr title="Alert">
                    <i class="{{ config('other.font-awesome') }} fa-file-exclamation"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button type="button" class="form__standard-icon-button" x-on:click="insertTable">
                <abbr title="Table">
                    <i class="{{ config('other.font-awesome') }} fa-table"></i>
                </abbr>
            </button>
        </li>
        <li>
            <button
                type="button"
                class="form__standard-icon-button"
                x-on:click="toggleEmojiPicker"
                x-bind:aria-expanded="isEmojiPickerOpen"
                aria-haspopup="dialog"
            >
                <abbr title="Emoji">
                    <i class="{{ config('other.font-awesome') }} fa-face-smile"></i>
                </abbr>
            </button>
        </li>
    </menu>
    <div
        class="bbcode-input__emoji-picker"
        x-cloak
        x-show="isEmojiPickerOpen"
        x-on:click.outside="closeEmojiPicker"
        x-on:keydown.escape.window="closeEmojiPicker"
        role="dialog"
        aria-label="Emoji"
    >
        <p class="bbcode-input__emoji-search">
            <input
                type="text"
                class="bbcode-input__emoji-search-input"
                placeholder="Search emoji"
                autocomplete="off"
                x-ref="emojiSearch"
                x-model="emojiQuery"
            />
        </p>
        <menu class="bbcode-input__emoji-categories" x-show="emojiQuery === ''">
            <template x-for="category in emojiCategories" :key="category.id">
                <li>
                    <button
                        type="button"
                        class="bbcode-input__emoji-category"
                        x-bind:class="
                            category.id === emojiCategory &&
                                'bbcode-input__emoji-category--active'
                        "
                        x-on:click="emojiCategory = category.id"
                        x-text="category.label"
                    ></button>
                </li>
            </template>
        </menu>
        <p class="bbcode-input__emoji-status" x-show="emojiStatus !== ''" x-text="emojiStatus"></p>
        <div class="bbcode-input__emoji-grid" x-show="emojiStatus === ''">
            <template x-for="emoji in visibleEmoji" :key="emoji[0]">
                <button
                    type="button"
                    class="bbcode-input__emoji-button"
                    x-on:click="insertEmoji(emoji[1])"
                    x-bind:title="':' + emoji[1] + ':'"
                >
                    <img
                        class="bbcode-input__emoji-image"
                        loading="lazy"
                        decoding="async"
                        width="28"
                        height="28"
                        x-bind:src="emojiImagePath + emoji[0] + '.png'"
                        x-bind:alt="emoji[1]"
                    />
                </button>
            </template>
        </div>
        <p class="bbcode-input__emoji-hint">
            Tip: you can also type the name directly, like <code>:smile:</code>
        </p>
    </div>
    <div class="bbcode-input__tab-pane">
        <div class="bbcode-input__preview bbcode-rendered" x-show="isPreviewEnabled">
            @bbcode($contentBbcode)
        </div>
        <p class="form__group" x-show="isPreviewDisabled">
            <textarea
                id="bbcode-{{ $name }}"
                name="{{ $name }}"
                class="form__textarea bbcode-input__input"
                placeholder=" "
                x-bind="textarea"
                wire:model="contentBbcode"
                @required($isRequired)
            ></textarea>
            <label class="form__label form__label--floating" for="bbcode-{{ $name }}">
                {{ $label }}
            </label>
        </p>
    </div>
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('{{ $name }}BbcodeInput', () => ({
                showButtons: false,
                bbcodePreviewHeight: null,
                isEmojiPickerOpen: false,
                emojiCatalogue: [],
                emojiCategories: [],
                emojiCategory: 'people',
                emojiQuery: '',
                emojiStatus: '',
                emojiImagePath: @js(rtrim(url((string) config('joypixels.imagePathPNG')), '/').'/'),
                emojiIndexUrl: @js(url('vendor/joypixels/emoji-index.json')),
                isPreviewEnabled: @entangle('isPreviewEnabled').live,
                isOverInput: false,
                previousActiveElement: document.activeElement,
                toggleButtonVisibility() {
                    this.showButtons = !this.showButtons;
                },
                isPreviewDisabled() {
                    return !this.isPreviewEnabled;
                },
                textarea: {
                    ['x-ref']: 'bbcode',
                    ['x-on:mouseup']() {
                        if (this.isOverInput) {
                            this.bbcodePreviewHeight = this.$el.style.height;
                        }
                    },
                    ['x-on:mousedown']() {
                        this.previousActiveElement = document.activeElement;
                    },
                    ['x-on:mouseover']() {
                        this.isOverInput = true;
                    },
                    ['x-on:mouseleave']() {
                        this.isOverInput = false;
                    },
                    ['x-bind:style']() {
                        return {
                            height: this.bbcodePreviewHeight !== null && this.bbcodePreviewHeight,
                            transition:
                                this.previousActiveElement === this.$el
                                    ? 'none'
                                    : 'border-color 600ms cubic-bezier(0.25, 0.8, 0.25, 1), height 600ms cubic-bezier(0.25, 0.8, 0.25, 1)',
                        };
                    },
                    ['x-on:keydown.self.ctrl.b.prevent']() {
                        this.insertBold();
                    },
                    ['x-on:keydown.self.ctrl.i.prevent']() {
                        this.insertItalic();
                    },
                    ['x-on:keydown.self.ctrl.u.prevent']() {
                        this.insertUnderline();
                    },
                },
                insertBold() {
                    this.insert('[b]', '[/b]');
                },
                insertItalic() {
                    this.insert('[i]', '[/i]');
                },
                insertUnderline() {
                    this.insert('[u]', '[/u]');
                },
                insertStrikethrough() {
                    this.insert('[s]', '[/s]');
                },
                insertImage() {
                    this.insert('[img=350]', '[/img]');
                },
                insertYoutube() {
                    this.insert('[center][youtube]', '[/youtube][/center]');
                },
                insertUrl() {
                    this.insert('[url]', '[/url]');
                },
                insertUnorderedList() {
                    this.insert('\n[list]\n[*]', '\n[/list]\n');
                },
                insertOrderedList() {
                    this.insert('\n[list=1]\n[*]', '\n[/list]\n');
                },
                insertColor() {
                    this.insert('[color=]', '[/color]');
                },
                insertSize() {
                    this.insert('[size=]', '[/size]');
                },
                insertFont() {
                    this.insert('[font=]', '[/font]');
                },
                insertLeft() {
                    this.insert('\n[left]\n', '\n[/left]\n');
                },
                insertCenter() {
                    this.insert('\n[center]\n', '\n[/center]\n');
                },
                insertRight() {
                    this.insert('\n[right]\n', '\n[/right]\n');
                },
                insertQuote() {
                    this.insert('\n[quote]\n', '\n[/quote]\n');
                },
                insertCode() {
                    this.insert('[code]', '[/code]');
                },
                insertSpoiler() {
                    this.insert('[spoiler]', '[/spoiler]');
                },
                insertNote() {
                    this.insert('[note]', '[/note]');
                },
                insertAlert() {
                    this.insert('[alert]', '[/alert]');
                },
                insertTable() {
                    this.insert('[table]\n[tr]\n[td]', '[/td]\n[/tr]\n[/table]');
                },
                get visibleEmoji() {
                    const query = this.emojiQuery.trim().toLowerCase();

                    if (query === '') {
                        return this.emojiCatalogue.filter(
                            (emoji) => emoji[2] === this.emojiCategory,
                        );
                    }

                    // Shortname first so an exact-ish name outranks a keyword
                    // match, then keywords. Capped: rendering hundreds of
                    // thumbnails on every keystroke is what makes other
                    // pickers feel sluggish.
                    const byName = [];
                    const byKeyword = [];

                    for (const emoji of this.emojiCatalogue) {
                        if (emoji[1].includes(query)) {
                            byName.push(emoji);
                        } else if (emoji[4].some((keyword) => keyword.includes(query))) {
                            byKeyword.push(emoji);
                        }

                        if (byName.length >= 120) {
                            break;
                        }
                    }

                    return byName.concat(byKeyword).slice(0, 120);
                },
                toggleEmojiPicker() {
                    this.isEmojiPickerOpen = !this.isEmojiPickerOpen;

                    if (!this.isEmojiPickerOpen) {
                        return;
                    }

                    this.loadEmojiCatalogue();
                    this.$nextTick(() => this.$refs.emojiSearch?.focus());
                },
                closeEmojiPicker() {
                    this.isEmojiPickerOpen = false;
                },
                async loadEmojiCatalogue() {
                    if (this.emojiCatalogue.length > 0 || this.emojiStatus === 'Loading emoji...') {
                        return;
                    }

                    this.emojiStatus = 'Loading emoji...';

                    try {
                        const response = await fetch(this.emojiIndexUrl, {
                            headers: { Accept: 'application/json' },
                        });

                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }

                        const index = await response.json();

                        this.emojiCategories = index.categories ?? [];
                        this.emojiCatalogue = index.emoji ?? [];
                        this.emojiCategory = this.emojiCategories[0]?.id ?? 'people';
                        this.emojiStatus = '';
                    } catch (error) {
                        // Never a dead panel: say what to do instead.
                        this.emojiStatus =
                            'Emoji list unavailable. You can still type names like :smile: by hand.';
                        console.error('emoji index: ' + error.message);
                    }
                },
                insertEmoji(shortname) {
                    this.insertText(':' + shortname + ':');
                    this.closeEmojiPicker();
                },
                insertText(text) {
                    // insert() wraps a selection in a tag pair and can toggle it
                    // back off. An emoji is neither: it replaces the selection
                    // and leaves the caret after it.
                    const input = this.$refs.bbcode;
                    const start = input.selectionStart;
                    const end = input.selectionEnd;

                    input.value =
                        input.value.substring(0, start) + text + input.value.substring(end);

                    input.dispatchEvent(new Event('input'));
                    input.focus();
                    input.setSelectionRange(start + text.length, start + text.length);
                },
                insert(openTag, closeTag) {
                    input = this.$refs.bbcode;
                    start = input.selectionStart;
                    end = input.selectionEnd;
                    alreadyNested =
                        input.value.substring(start, start + openTag.length) === openTag &&
                        input.value.substring(end - closeTag.length, end) === closeTag;
                    if (alreadyNested) {
                        input.value =
                            input.value.substring(0, start) +
                            input.value.substring(start + openTag.length, end - closeTag.length) +
                            input.value.substring(end);
                    } else {
                        input.value =
                            input.value.substring(0, start) +
                            openTag +
                            input.value.substring(start, end) +
                            closeTag +
                            input.value.substring(end);
                    }
                    input.dispatchEvent(new Event('input'));
                    input.focus();
                    if (openTag.charAt(openTag.length - 2) === '=') {
                        input.setSelectionRange(
                            start + openTag.length - 1,
                            start + openTag.length - 1,
                        );
                    } else if (start == end) {
                        input.setSelectionRange(start + openTag.length, end + openTag.length);
                    } else {
                        if (alreadyNested) {
                            input.setSelectionRange(start, end - openTag.length - closeTag.length);
                        } else {
                            input.setSelectionRange(start, end + openTag.length + closeTag.length);
                        }
                    }
                },
            }));
        });
    </script>
</div>
