{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0

    Orden y filtro por columna para <table data-tools>. Ver public/js/staff-table-tools.js.
    El ?v= es por Cloudflare, que cachea los estáticos aunque se purgue: subirlo al cambiar el JS.
--}}
<style>
    .tt-bar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin: 0.75rem 1rem; }
    .tt-bar .tt-col { max-width: 22rem; }
    .tt-bar .tt-q { flex: 1 1 18rem; max-width: 32rem; }
    .tt-count { opacity: 0.75; font-size: 1.2rem; }
    th.tt-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
    th.tt-sortable::after { content: ' ⇅'; opacity: 0.35; }
    th.tt-sortable[data-dir="asc"]::after { content: ' ▲'; opacity: 1; }
    th.tt-sortable[data-dir="desc"]::after { content: ' ▼'; opacity: 1; }
</style>
<script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/staff-table-tools.js') }}?v=20261013"></script>
