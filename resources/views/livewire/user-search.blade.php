<div style="display: flex; flex-direction: column; row-gap: 1rem">
    <section
        class="panelV2"
        x-data="{
            q: '',
            res: null,
            loading: false,
            lookup() {
                if (!this.q.trim()) return;
                this.loading = true;
                this.res = null;
                fetch('{{ route('staff.users.telegram_lookup') }}?q=' + encodeURIComponent(this.q.trim()))
                    .then(r => r.json())
                    .then(d => { this.res = d; this.loading = false; })
                    .catch(() => { this.res = { found: false, error: 'Error de red' }; this.loading = false; });
            }
        }"
    >
        <header class="panel__header">
            <h2 class="panel__heading">Búsqueda Telegram</h2>
        </header>
        <div class="panel__body" style="padding: 5px">
            <form class="form" @submit.prevent="lookup()">
                <div class="form__group--short-horizontal">
                    <p class="form__group">
                        <input
                            id="tgLookup"
                            class="form__text"
                            type="text"
                            x-model="q"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="tgLookup">
                            Usuario o chat_id
                        </label>
                    </p>
                    <p class="form__group">
                        <button
                            class="form__button form__button--filled"
                            type="submit"
                            :disabled="loading"
                        >
                            <span x-show="!loading">Buscar TG</span>
                            <span x-show="loading" style="opacity: .6">…</span>
                        </button>
                    </p>
                </div>
                <p class="form__text" style="opacity: .6; margin: .3rem 0 0">
                    Acepta nombre de usuario del tracker o chat_id numérico de Telegram.
                </p>
            </form>
            <template x-if="res && res.found">
                <p style="margin: .5rem 0 0">
                    <a :href="res.user.profile_url" x-text="res.user.username"></a>
                    <span x-show="res.user.group" x-text="' (' + res.user.group + ')'" style="opacity: .6"></span>
                    <template x-if="res.tg">
                        <span x-text="' — ' + [res.tg.first_name, res.tg.last_name].filter(Boolean).join(' ') + (res.tg.username ? ' @' + res.tg.username : '') + ' [' + res.tg.status + ']'"></span>
                    </template>
                    <template x-if="res.user.telegram_chat_id && !res.tg">
                        <span style="opacity: .6"> — sin respuesta de Telegram</span>
                    </template>
                    <template x-if="!res.user.telegram_chat_id">
                        <span style="opacity: .6"> — no vinculado a Telegram</span>
                    </template>
                </p>
            </template>
            <template x-if="res && !res.found">
                <p style="margin: .5rem 0 0; opacity: .6" x-text="res.error || 'Sin coincidencias'"></p>
            </template>
        </div>
    </section>
    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">{{ __('common.search') }}</h2>
        </header>
        <div class="panel__body" style="padding: 5px">
            <form class="form">
                <div class="form__group--short-horizontal">
                    <p class="form__group">
                        <input
                            id="username"
                            class="form__text"
                            type="text"
                            wire:model.live="username"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="username">
                            {{ __('common.username') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="soundexUsername"
                            class="form__text"
                            type="text"
                            wire:model.live="soundexUsername"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="soundexUsername">
                            Soundex {{ __('common.username') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="email"
                            class="form__text"
                            type="text"
                            wire:model.live="email"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="email">
                            {{ __('common.email') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="soundexEmail"
                            class="form__text"
                            type="text"
                            wire:model.live="soundexEmail"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="soundexEmail">
                            Soundex {{ __('common.email') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="apikey"
                            class="form__text"
                            type="text"
                            wire:model.live="apikey"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="apikey">
                            {{ __('user.apikey') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="rsskey"
                            class="form__text"
                            type="text"
                            wire:model.live="rsskey"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="rsskey">
                            {{ __('user.rsskey') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="passkey"
                            class="form__text"
                            type="text"
                            wire:model.live="passkey"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="passkey">
                            {{ __('user.passkey') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <select
                            id="groupId"
                            class="form__select"
                            wire:model.live="groupId"
                            x-data="{ groupId: '' }"
                            x-model="groupId"
                            x-bind:class="groupId === '' ? 'form__select--default' : ''"
                        >
                            <option hidden disabled selected value=""></option>
                            @foreach ($groups as $group)
                                <option class="form__option" value="{{ $group->id }}">
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                        <label for="groupId" class="form__label form__label--floating">
                            {{ __('common.group') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="show"
                            class="form__checkbox"
                            type="checkbox"
                            wire:model.live="show"
                        />
                        <label class="form__label" for="show">Include soft deletes</label>
                    </p>
                    <p class="form__group">
                        <select
                            id="quantity"
                            class="form__select"
                            wire:model.live="perPage"
                            required
                        >
                            <option>25</option>
                            <option>50</option>
                            <option>100</option>
                        </select>
                        <label class="form__label form__label--floating" for="quantity">
                            {{ __('common.quantity') }}
                        </label>
                    </p>
                </div>
            </form>
        </div>
    </section>
    <div>
        <section class="panelV2">
            <h2 class="panel__heading">{{ __('common.users') }}</h2>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <th>Avatar</th>
                            <th wire:click="sortBy('username')" role="columnheader button">
                                {{ __('common.username') }}
                                @include('livewire.includes._sort-icon', ['field' => 'username'])
                            </th>
                            <th wire:click="sortBy('group_id')" role="columnheader button">
                                {{ __('common.group') }}
                                @include('livewire.includes._sort-icon', ['field' => 'group_id'])
                            </th>
                            <th wire:click="sortBy('email')" role="columnheader button">
                                {{ __('common.email') }}
                                @include('livewire.includes._sort-icon', ['field' => 'email'])
                            </th>
                            <th wire:click="sortBy('created_at')" role="columnheader button">
                                {{ __('user.registration-date') }}
                                @include('livewire.includes._sort-icon', ['field' => 'created_at'])
                            </th>
                            <th wire:click="sortBy('last_login')" role="columnheader button">
                                {{ __('user.last-login') }}
                                @include('livewire.includes._sort-icon', ['field' => 'last_login'])
                            </th>
                            <th wire:click="sortBy('last_action')" role="columnheader button">
                                {{ __('user.last-action') }}
                                @include('livewire.includes._sort-icon', ['field' => 'last_action'])
                            </th>
                            <th>{{ __('common.action') }}</th>
                        </tr>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <img
                                        src="{{ $user->image === null ? url('img/profile.png') : route('authenticated_images.user_avatar', ['user' => $user]) }}"
                                        alt=""
                                        class="user-search__avatar"
                                    />
                                </td>
                                <td colspan="2">
                                    <x-user-tag :anon="false" :user="$user" />
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <time
                                        datetime="{{ $user->created_at }}"
                                        title="{{ $user->created_at }}"
                                    >
                                        {{ $user->created_at }}
                                    </time>
                                </td>
                                <td>
                                    <time
                                        datetime="{{ $user->last_login }}"
                                        title="{{ $user->last_login }}"
                                    >
                                        {{ $user->last_login ?? 'Never' }}
                                    </time>
                                </td>
                                <td>
                                    <time
                                        datetime="{{ $user->last_action }}"
                                        title="{{ $user->last_action }}"
                                    >
                                        {{ $user->last_action ?? 'Never' }}
                                    </time>
                                </td>
                                <td>
                                    <menu class="data-table__actions">
                                        <li class="data-table__action">
                                            <a
                                                class="form__button form__button--text"
                                                href="{{ route('staff.users.edit', ['user' => $user]) }}"
                                            >
                                                {{ __('common.edit') }}
                                            </a>
                                        </li>
                                        @if($user->telegram_chat_id)
                                        <li
                                            class="data-table__action"
                                            x-data="{ tg: null, loading: false }"
                                            style="display: flex; align-items: center; gap: .4rem; flex-wrap: wrap"
                                        >
                                            <button
                                                class="form__button form__button--text"
                                                title="Consultar Telegram en vivo"
                                                x-show="!tg"
                                                :disabled="loading"
                                                @click="
                                                    loading = true;
                                                    fetch('{{ route('staff.users.telegram_info', ['user' => $user]) }}')
                                                        .then(r => r.json())
                                                        .then(d => { tg = d; loading = false; })
                                                        .catch(() => { tg = { error: 'Error de red' }; loading = false; })
                                                "
                                            >
                                                <span x-show="!loading">TG</span>
                                                <span x-show="loading" style="opacity:.6">…</span>
                                            </button>
                                            <span
                                                x-show="tg && !tg.error"
                                                style="font-size:2rem; white-space: nowrap"
                                                x-text="tg ? [tg.first_name, tg.last_name].filter(Boolean).join(' ') + (tg.username ? ' @' + tg.username : '') + ' [' + tg.status + ']' : ''"
                                            ></span>
                                            <span
                                                x-show="tg && tg.error"
                                                style="font-size:2rem; opacity:.6"
                                                x-text="tg ? tg.error : ''"
                                            ></span>
                                        </li>
                                        @endif
                                    </menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Sin usuarios</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $users->links('partials.pagination') }}
        </section>
    </div>
</div>
