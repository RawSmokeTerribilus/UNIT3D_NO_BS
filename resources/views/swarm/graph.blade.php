@extends('layout.with-main')

@section('title')
    <title>Swarm Intel — {{ config('other.title') }}</title>
@endsection

@section('page', 'page__swarm-graph')

@section('main')
<div id="swarm-wrapper" style="position: relative; width: 100%; height: calc(100vh - 120px); overflow: hidden; background: #04060a; z-index: 0; isolation: isolate;">

    <div id="graph-container" style="position: absolute; inset: 0;"></div>
    <svg id="chord-container" style="position: absolute; inset: 0; display: none; pointer-events: auto;" preserveAspectRatio="xMidYMid meet"></svg>

    <div id="js-indicator" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 5; color: #ff0066; font-family: monospace; font-size: 1.2rem; text-align: center;">
        ⚠ JS NO SE EJECUTA<br>
        <span style="font-size: .8rem; color: #888;">(revisar consola)</span>
    </div>

    <div style="position: absolute; inset: 0; pointer-events: none; z-index: 2;
        background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,255,255,.012) 2px, rgba(0,255,255,.012) 4px);
    "></div>

    <div id="swarm-controls" style="
        position: absolute; top: 1rem; left: 50%; transform: translateX(-50%);
        z-index: 10; display: flex; flex-direction: column; gap: .4rem; align-items: center;
        background: rgba(4,6,18,.82); padding: .5rem .9rem; border-radius: 4px;
        border: 1px solid rgba(0,255,255,.25); backdrop-filter: blur(8px);
        box-shadow: 0 0 16px rgba(0,255,255,.15), inset 0 0 8px rgba(0,0,0,.4);
        font-family: 'Courier New', monospace;
    ">
        <div style="display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center;">
            <button data-mode="network"     class="swarm-btn active">🕸 RED</button>
            <button data-mode="social"      class="swarm-btn">👥 SOCIAL</button>
            <button data-mode="content"     class="swarm-btn">🎬 CONTENIDO</button>
            <button data-mode="propagation" class="swarm-btn">📡 PROPAGACIÓN</button>
        </div>
        <div style="display: flex; gap: .4rem; flex-wrap: wrap; justify-content: center; align-items: center;">
            <span style="font-size:.85rem; color:#077; letter-spacing:.1em;">DISEÑO:</span>
            <button data-layout="free"         class="swarm-btn swarm-btn--sm">LIBRE</button>
            <button data-layout="radial"       class="swarm-btn swarm-btn--sm">RADIAL</button>
            <button data-layout="hierarchical" class="swarm-btn swarm-btn--sm" id="btn-layout-hierarchy">JERARQUÍA</button>
            <button data-layout="3d"           class="swarm-btn swarm-btn--sm active">3D</button>
            <button data-layout="chord"        class="swarm-btn swarm-btn--sm" id="btn-layout-chord">CUERDA</button>
        </div>
        <div id="network-color-row" style="display: flex; gap: .4rem; flex-wrap: wrap; justify-content: center; align-items: center;">
            <span style="font-size:.85rem; color:#077; letter-spacing:.1em;">COLOR POR:</span>
            <button data-color="seeders"    class="swarm-btn swarm-btn--sm active">SEMILLAS</button>
            <button data-color="category"   class="swarm-btn swarm-btn--sm">CATEGORÍA</button>
            <button data-color="resolution" class="swarm-btn swarm-btn--sm">RESOLUCIÓN</button>
            <button data-color="type"       class="swarm-btn swarm-btn--sm">TIPO</button>
            <button data-color="health"     class="swarm-btn swarm-btn--sm">SALUD</button>
        </div>
        <div id="social-color-row" style="display: none; gap: .4rem; flex-wrap: wrap; justify-content: center; align-items: center;">
            <span style="font-size:.85rem; color:#077; letter-spacing:.1em;">COLOR POR:</span>
            <button data-scolor="seedtime" class="swarm-btn swarm-btn--sm active">T.SIEMBRA</button>
            <button data-scolor="hitruns"  class="swarm-btn swarm-btn--sm">HIT&amp;RUN</button>
            <button data-scolor="upload"   class="swarm-btn swarm-btn--sm">SUBIDA</button>
            <button data-scolor="activity" class="swarm-btn swarm-btn--sm">ACTIVIDAD</button>
            <button data-scolor="age"      class="swarm-btn swarm-btn--sm">EDAD</button>
        </div>
        <div id="content-color-row" style="display: none; gap: .4rem; flex-wrap: wrap; justify-content: center; align-items: center;">
            <span style="font-size:.85rem; color:#077; letter-spacing:.1em;">COLOR POR:</span>
            <button data-ccolor="type"       class="swarm-btn swarm-btn--sm active">TIPO</button>
            <button data-ccolor="popularity" class="swarm-btn swarm-btn--sm">POPULARIDAD</button>
            <button data-ccolor="size"       class="swarm-btn swarm-btn--sm">TAMAÑO</button>
            <button data-ccolor="age"        class="swarm-btn swarm-btn--sm">EDAD</button>
        </div>
        <div id="propagation-color-row" style="display: none; gap: .4rem; flex-wrap: wrap; justify-content: center; align-items: center;">
            <span style="font-size:.85rem; color:#077; letter-spacing:.1em;">COLOR POR:</span>
            <button data-pcolor="status" class="swarm-btn swarm-btn--sm active">ESTADO</button>
            <button data-pcolor="wave"   class="swarm-btn swarm-btn--sm">OLA</button>
            <button data-pcolor="client" class="swarm-btn swarm-btn--sm">CLIENTE</button>
            <button data-pcolor="role"   class="swarm-btn swarm-btn--sm">ROL</button>
        </div>
        <div id="bipartite-color-row" style="display: none; gap: .4rem; flex-wrap: wrap; justify-content: center; align-items: center;">
            <span style="font-size:.85rem; color:#077; letter-spacing:.1em;">COLOR POR:</span>
            <button data-bcolor="grupo"     class="swarm-btn swarm-btn--sm active">GRUPO</button>
            <button data-bcolor="descargas" class="swarm-btn swarm-btn--sm">DESCARGAS</button>
            <button data-bcolor="simple"    class="swarm-btn swarm-btn--sm">SIMPLE</button>
        </div>
        <div style="display: flex; gap: .3rem; align-items: center; flex-wrap: wrap; justify-content: center;">
            <span id="prop-search" style="display:none; gap:.25rem; align-items:center;">
                <input id="prop-torrent-id" type="number" placeholder="ID Torrent"
                    style="background:#060a14; color:#0ff; border:1px solid rgba(0,255,255,.3); border-radius:3px;
                           padding:.25rem .5rem; width:120px; font-family:inherit; font-size:.975rem;">
                <button id="btn-prop-go" class="swarm-btn swarm-btn--sm">IR</button>
                <button id="btn-prop-global" class="swarm-btn swarm-btn--sm">🗺 MAPA DE ALCANCE</button>
            </span>
            <span id="bipartite-search" style="display:none; gap:.25rem; align-items:center;">
                <input id="bip-user-name" type="text" placeholder="Buscar usuario"
                    style="background:#060a14; color:#0ff; border:1px solid rgba(0,255,255,.3); border-radius:3px;
                           padding:.25rem .5rem; width:160px; font-family:inherit; font-size:.975rem;">
                <button id="btn-bip-search" class="swarm-btn swarm-btn--sm">🔍 BUSCAR</button>
                <button id="btn-bip-clear" class="swarm-btn swarm-btn--sm">✕</button>
            </span>
            <span id="graph-status" style="font-size:.9rem; color:#0aa; font-family:'Courier New',monospace;"></span>
        </div>
    </div>

    <div id="graph-tooltip" style="
        position: absolute; display: none; pointer-events: none; z-index: 20;
        background: rgba(4,6,18,.95); color: #0ff; padding: .6rem 1rem;
        border-radius: 4px; border: 1px solid rgba(0,255,255,.45);
        box-shadow: 0 0 12px rgba(0,255,255,.25);
        font-family: 'Courier New',monospace; font-size: 1rem; font-weight: 700;
        max-width: 360px; line-height: 1.6;
    "></div>

    <div id="swarm-legend" style="
        position: absolute; bottom: 1.2rem; left: 1rem; z-index: 10; display: none;
        background: rgba(4,6,18,.9); color: #cde; padding: .875rem 1.25rem;
        border-radius: 5px; border: 1px solid rgba(0,255,255,.3);
        box-shadow: 0 0 12px rgba(0,255,255,.12);
        font-family: 'Courier New',monospace; font-size: 1.06rem; line-height: 1.5;
        letter-spacing: .03em; max-width: 460px;
    "></div>

    <div id="viz-panel" style="
        position: absolute; bottom: 1.2rem; right: 1rem; z-index: 10;
        background: rgba(4,6,18,.92); color: #cde;
        border-radius: 5px; border: 1px solid rgba(0,255,255,.3);
        box-shadow: 0 0 12px rgba(0,255,255,.12);
        font-family: 'Courier New',monospace;
        min-width: 230px;
    ">
        <div id="viz-panel-header" style="
            display: flex; justify-content: space-between; align-items: center;
            padding: .45rem .8rem; cursor: pointer;
            border-bottom: 1px solid rgba(0,255,255,.15);
        ">
            <span style="font-size:.85rem; color:#0ff; letter-spacing:.1em;">⚙ PARÁMETROS</span>
            <span id="viz-toggle-icon" style="color:#0aa; font-size:.75rem;">▲</span>
        </div>
        <div id="viz-sliders-body" style="padding: .6rem .8rem; display: flex; flex-direction: column; gap: .45rem;">
        </div>
    </div>

</div>

<style>
.swarm-btn {
    background: rgba(0,255,255,.06);
    color: #0dd;
    border: 1px solid rgba(0,255,255,.3);
    border-radius: 3px;
    padding: .375rem 1.06rem;
    cursor: pointer;
    font-size: .975rem;
    font-family: 'Courier New',monospace;
    letter-spacing: .08em;
    transition: all .12s;
    text-shadow: 0 0 6px #0ff9;
}
.swarm-btn--sm { padding: .25rem .69rem; font-size: .875rem; letter-spacing: .05em; }
.swarm-btn:hover {
    background: rgba(0,255,255,.16);
    color: #fff;
    box-shadow: 0 0 8px rgba(0,255,255,.5);
}
.swarm-btn.active {
    background: rgba(0,255,180,.12);
    color: #00ffcc;
    border-color: #00ffcc;
    box-shadow: 0 0 12px rgba(0,255,180,.4);
    text-shadow: 0 0 10px #00ffcc;
}
.swarm-btn[disabled] { opacity: .35; cursor: not-allowed; }
.viz-slider {
    width: 100%; accent-color: #00ffcc;
    background: transparent; cursor: pointer;
    height: 4px;
}
.viz-row { margin-bottom: .1rem; }
</style>
@endsection

@section('scripts')
@php $nonce = HDVinnie\SecureHeaders\SecureHeaders::nonce('script'); @endphp
<script nonce="{{ $nonce }}" src="{{ asset('vendor/d3/d3.min.js') }}"></script>
<script nonce="{{ $nonce }}" src="{{ asset('vendor/force-graph/force-graph.min.js') }}"></script>
<script nonce="{{ $nonce }}" src="{{ asset('vendor/three/three.min.js') }}"></script>
<script nonce="{{ $nonce }}" src="{{ asset('vendor/3d-force-graph/3d-force-graph.min.js') }}"></script>
<script nonce="{{ $nonce }}">
'use strict';

// 12-color cyberpunk palette for categorical maps
const CAT_PALETTE = ['#00f5ff','#ff2079','#39ff14','#ffdd00','#bf5fff','#ff6d00','#00b4ff','#ff1493','#7fffd4','#ff5555','#a0ff00','#ff00aa'];
const catColor = (id) => CAT_PALETTE[(id ?? 0) % CAT_PALETTE.length];

// Log-scale color: maps 0-N to hot magenta → electric cyan (low → high)
function logScaleColor(val, maxLog) {
    const v = Math.log1p(Math.max(0, val ?? 0)) / Math.max(1, maxLog);
    const h = Math.round(v * 180);  // 0=magenta → 180=cyan
    return `hsl(${h}, 95%, 58%)`;
}

// Network color computed dynamically — gets max value from data
let networkColorMode = 'seeders';
let networkMaxSeeders = 0;
let networkMaxLeechers = 0;

// Social color computed dynamically
let socialColorMode = 'seedtime';
let socialMaxSeedtime = 0;
let socialMaxUpload = 0;
let socialMaxHitruns = 0;
let socialMaxAge = 0;

// Content color computed dynamically
let contentColorMode = 'type';
let contentMaxCompleted = 0;
let contentMaxSize = 0;
let contentMaxAge = 0;

// Propagation color
let propColorMode = 'status';
let propAgentColors = {};       // agent string → palette color
let propMinDate = 0, propMaxDate = 0;  // epoch ms for wave gradient


// gradient: t in 0..1 → red(0) → yellow(60) → green(120)
const gradRYG = (t) => `hsl(${Math.round(Math.max(0, Math.min(1, t)) * 120)}, 90%, 55%)`;
// cool gradient: t 0..1 → deep blue → cyan → white (for time waves)
const gradWave = (t) => {
    const c = Math.max(0, Math.min(1, t));
    return `hsl(${Math.round(220 - c * 40)}, 95%, ${Math.round(35 + c * 50)}%)`;
};

const COLORS = {
    network: {
        node: (n) => {
            switch (networkColorMode) {
                case 'category':   return catColor(n.category_id);
                case 'resolution': return catColor((n.resolution_id ?? 0) + 1);  // shift to avoid same color as cat 0
                case 'type':       return catColor((n.type_id ?? 0) + 3);
                case 'health':     return `hsl(${Math.round(((n.health_pct ?? 0) / 100) * 180)}, 95%, 58%)`;
                case 'seeders':
                default:
                    return logScaleColor(n.tracker_seeders ?? n.seeders ?? 0, Math.log1p(networkMaxSeeders));
            }
        },
        link: () => 'rgba(0,255,220,0.35)',
    },
    social: {
        node: (n) => {
            switch (socialColorMode) {
                case 'hitruns': {
                    // 0 H&R = green, more = red (inverted)
                    const hr = n.hitandruns ?? 0;
                    if (hr === 0) return gradRYG(1);
                    return gradRYG(1 - Math.min(1, hr / Math.max(1, socialMaxHitruns)));
                }
                case 'upload': {
                    const t = Math.log1p(n.uploaded ?? 0) / Math.max(1, Math.log1p(socialMaxUpload));
                    return gradRYG(t);
                }
                case 'activity': {
                    const tot = (n.seeding_now ?? 0) + (n.leeching_now ?? 0);
                    return gradRYG(tot > 0 ? (n.seeding_now ?? 0) / tot : 0);
                }
                case 'age': {
                    const t = Math.log1p(n.age_days ?? 0) / Math.max(1, Math.log1p(socialMaxAge));
                    return gradRYG(t);
                }
                case 'seedtime':
                default: {
                    const t = Math.log1p(n.seedtime ?? 0) / Math.max(1, Math.log1p(socialMaxSeedtime));
                    return gradRYG(t);
                }
            }
        },
        link: () => 'rgba(160,80,255,0.18)',
    },
    content: {
        node: (n) => {
            switch (contentColorMode) {
                case 'popularity': {
                    const t = Math.log1p(n.times_completed ?? 0) / Math.max(1, Math.log1p(contentMaxCompleted));
                    return gradRYG(t);
                }
                case 'size': {
                    const t = Math.log1p(+(n.size ?? 0)) / Math.max(1, Math.log1p(contentMaxSize));
                    return gradWave(t);
                }
                case 'age': {
                    const t = Math.log1p(n.age_days ?? 0) / Math.max(1, Math.log1p(contentMaxAge));
                    // fresh = green, old = red (inverted: new content = hot)
                    return gradRYG(1 - t);
                }
                case 'type':
                default:
                    return catColor((n.type_id ?? 0) + 3);
            }
        },
        link: (l) => ({
            alternate:  'rgba(0,245,255,0.55)',
            uploader:   'rgba(255,221,0,0.45)',
            codownload: 'rgba(191,95,255,0.4)',
        })[l.link_type] ?? 'rgba(255,255,255,0.12)',
    },
    propagation: {
        node: (n) => {
            if (n.isCenter) return '#ffffff';
            // Global bipartite
            if (propIsGlobal) {
                if (n.kind === 'torrent') {
                    return bipartiteColorMode === 'simple' ? '#ffaa00' : catColor(n.category_id ?? 0);
                }
                // user node
                if (bipartiteColorMode === 'simple') return '#00d4ff';
                if (bipartiteColorMode === 'descargas') {
                    return gradRYG(Math.log1p(n.downloads ?? 0) / Math.max(1, Math.log1p(bipMaxUserDownloads)));
                }
                return gradRYG(Math.log1p(n.degree ?? 0) / Math.max(1, Math.log1p(bipMaxUserDegree)));
            }
            switch (propColorMode) {
                case 'wave': {
                    const ts = n.dateTs ?? 0;
                    if (!ts || propMaxDate === propMinDate) return '#3366aa';
                    return gradWave((ts - propMinDate) / (propMaxDate - propMinDate));
                }
                case 'client':
                    return propAgentColors[n.agent ?? '—'] ?? '#888888';
                case 'role':
                    return n.seeder ? '#00ffcc' : '#ff5544';
                case 'status':
                default:
                    return n.is_live ? '#00ffcc' : (n.seeder ? '#00b4ff' : '#ff4444');
            }
        },
        link: () => 'rgba(0,255,200,0.08)',
    },
};

// Mode explainer — what nodes/links/size mean + per-color-variant meaning
const MODE_LABELS = {
    network: 'RED',
    social: 'SOCIAL',
    content: 'CONTENIDO',
    propagation: 'PROPAGACIÓN',
};

const MODE_INFO = {
    network: {
        what: 'NODOS = torrents con pares activos. ENLACES = dos torrents comparten un usuario activo. TAMAÑO = nº de pares activos.',
        colors: {
            seeders:    'COLOR: magenta = pocas semillas → cian = muchas (escala log)',
            category:   'COLOR: un tono por categoría',
            resolution: 'COLOR: un tono por resolución (4K / 1080p / 720p / SD)',
            type:       'COLOR: un tono por tipo de release (Remux / BluRay / WEB-DL …)',
            health:     'COLOR: magenta = 0% sembrado → cian = 100% sembrado',
        },
        colorVar: () => networkColorMode,
    },
    social: {
        what: 'NODOS = usuarios con pares activos. ENLACES = dos usuarios comparten un enjambre. TAMAÑO = nº de torrents activos.',
        colors: {
            seedtime: 'COLOR: rojo = poco tiempo de siembra → verde = mucho (escala log)',
            hitruns:  'COLOR: verde = 0 hit&run → rojo = muchos',
            upload:   'COLOR: rojo = poco volumen de subida → verde = mucho (escala log)',
            activity: 'COLOR: rojo = descargando ahora → verde = sembrando ahora',
            age:      'COLOR: rojo = cuenta nueva → verde = veterano (escala log)',
        },
        colorVar: () => socialColorMode,
    },
    content: {
        what: 'NODOS = todos los torrents. ENLACES = release alternativo (cian) / mismo subidor (oro) / co-descargado (violeta). TAMAÑO = veces completado.',
        colors: {
            type:       'COLOR: un tono por tipo de release',
            popularity: 'COLOR: rojo = poco descargado → verde = mucho (escala log)',
            size:       'COLOR: azul = archivo pequeño → blanco = enorme (escala log)',
            age:        'COLOR: verde = subida reciente → rojo = archivo antiguo',
        },
        colorVar: () => contentColorMode,
    },
    propagation: {
        what: 'Busca un ID de torrent: centro = el torrent, alrededor = los usuarios que lo tienen/tuvieron. Botón MAPA DE ALCANCE = compara cuántos usuarios alcanzó cada torrent.',
        colors: {
            status: 'COLOR: cian = activo · azul = sembrador pasado · rojo = sanguijuela pasada',
            wave:   'COLOR: azul oscuro = se unió pronto → blanco = reciente (la ola de propagación)',
            client: 'COLOR: un tono por cliente BitTorrent',
            role:   'COLOR: cian = sembrador · rojo = sanguijuela',
        },
        colorVar: () => propColorMode,
    },
};

let currentMode = 'network';
let currentLayout = 'free';
let Graph = null;
let containerEl = null;
let svgEl = null;
let lastData = { nodes: [], links: [] };

// Bipartite (global propagation) color
let bipartiteColorMode = 'grupo';
let bipMaxUserDegree = 0, bipMaxUserDownloads = 0;

// Hover-highlight state
let hlNodes = new Set();
let hlLinks = new Set();
let adjNodes = {};   // node id → Set of neighbour ids
let adjLinks = {};   // node id → array of link objects
let searchedNodeId = null;   // locked highlight from user search
let currentGlobalCap = -1;   // torrent cap of the loaded Mapa de Alcance (0 = full)
let hlEnabled = true;

let _reheatTimer = null;
function gentleReheat() {
    if (!Graph) return;
    clearTimeout(_reheatTimer);
    try {
        if (typeof Graph.d3AlphaTarget === 'function') {
            Graph.d3AlphaTarget(0.12);
            _reheatTimer = setTimeout(() => { try { if (Graph) Graph.d3AlphaTarget(0); } catch(e){} }, 800);
        } else if (Graph.d3ReheatSimulation) {
            Graph.d3ReheatSimulation();
        }
    } catch(e) {}
}

function setStatus(msg) {
    const el = document.getElementById('graph-status');
    if (el) el.textContent = msg;
}

let propIsGlobal = false;

// ── Viz parameter panel ──────────────────────────────────────────────────────
const VIZ_DEFAULTS = { charge: -150, linkDist: 30, linkWidth: 1.4, nodeSize: 6, cooldown: 300 };
const VIZ_RANGES = {
    charge:    { min: -800, max: -5,   step: 5,   label: 'REPULSIÓN' },
    linkDist:  { min: 10,   max: 400,  step: 5,   label: 'DIST. ENLACES' },
    linkWidth: { min: 0.2,  max: 5.0,  step: 0.1, label: 'GROSOR LÍNEAS' },
    nodeSize:  { min: 1,    max: 20,   step: 0.5, label: 'TAMAÑO NODOS' },
    cooldown:  { min: 0,    max: 1000, step: 10,  label: 'ENFRIAMIENTO' },
};
let vizCurrentSettings = { ...VIZ_DEFAULTS };

function vizKey(mode) { return `swarm_viz_${mode}`; }

function loadVizSettings(mode) {
    try {
        const raw = localStorage.getItem(vizKey(mode));
        if (raw) return { ...VIZ_DEFAULTS, ...JSON.parse(raw) };
    } catch (e) {}
    return { ...VIZ_DEFAULTS };
}

function saveVizSettings(mode, s) {
    try { localStorage.setItem(vizKey(mode), JSON.stringify(s)); } catch (e) {}
}

function buildVizPanel() {
    const body = document.getElementById('viz-sliders-body');
    if (!body) return;
    body.innerHTML = '';
    const s = vizCurrentSettings;
    Object.keys(VIZ_RANGES).forEach(key => {
        const r = VIZ_RANGES[key];
        const row = document.createElement('div');
        row.className = 'viz-row';
        row.innerHTML =
            `<div style="display:flex;justify-content:space-between;font-size:.78rem;color:#0aa;margin-bottom:.1rem;letter-spacing:.05em;">` +
            `<span>${r.label}</span><span id="viz-val-${key}">${s[key]}</span></div>` +
            `<input type="range" id="viz-${key}" class="viz-slider" ` +
            `min="${r.min}" max="${r.max}" step="${r.step}" value="${s[key]}">`;
        body.appendChild(row);
    });
    const resetRow = document.createElement('div');
    resetRow.style.cssText = 'display:flex; justify-content:flex-end; gap:.35rem; margin-top:.35rem;';
    resetRow.innerHTML =
        `<button id="btn-hl-toggle" class="swarm-btn swarm-btn--sm${hlEnabled ? ' active' : ''}">👁 RESALTAR</button>` +
        `<button id="btn-viz-reset" class="swarm-btn swarm-btn--sm">↺ RESET</button>`;
    body.appendChild(resetRow);
    wireVizPanel();
}

function wireVizPanel() {
    Object.keys(VIZ_RANGES).forEach(key => {
        const input = document.getElementById(`viz-${key}`);
        const valEl = document.getElementById(`viz-val-${key}`);
        if (!input) return;
        input.addEventListener('input', () => {
            const val = parseFloat(input.value);
            if (valEl) valEl.textContent = val;
            vizCurrentSettings[key] = val;
            saveVizSettings(currentMode, vizCurrentSettings);
            hotApplyViz(key, val);
        });
    });
    const hlBtn = document.getElementById('btn-hl-toggle');
    if (hlBtn) hlBtn.addEventListener('click', () => {
        hlEnabled = !hlEnabled;
        hlBtn.classList.toggle('active', hlEnabled);
        if (!hlEnabled) { hlNodes.clear(); hlLinks.clear(); }
    });
    const resetBtn = document.getElementById('btn-viz-reset');
    if (resetBtn) resetBtn.addEventListener('click', () => {
        vizCurrentSettings = { ...VIZ_DEFAULTS };
        saveVizSettings(currentMode, vizCurrentSettings);
        buildVizPanel();
        applyVizSettings(true);
    });
    const header = document.getElementById('viz-panel-header');
    if (header) header.addEventListener('click', () => {
        const body = document.getElementById('viz-sliders-body');
        const icon = document.getElementById('viz-toggle-icon');
        if (!body) return;
        const collapsed = body.style.display === 'none';
        body.style.display = collapsed ? '' : 'none';
        if (icon) icon.textContent = collapsed ? '▲' : '▼';
    });
}

function hotApplyViz(key, val) {
    if (!Graph || currentLayout === 'chord') return;
    try {
        switch (key) {
            case 'charge': {
                const f = Graph.d3Force('charge');
                if (f) f.strength(val);
                gentleReheat();
                break;
            }
            case 'linkDist': {
                const f = Graph.d3Force('link');
                if (f) f.distance(val);
                gentleReheat();
                break;
            }
            case 'linkWidth': {
                if (currentLayout === '3d') {
                    Graph.linkWidth(l => Math.max(0.4, Math.sqrt(l.weight ?? 1) * val * 0.7));
                } else {
                    Graph.linkWidth(l => {
                        const lw = vizCurrentSettings.linkWidth ?? 1.4;
                        const base = (currentMode === 'propagation' && propIsGlobal)
                            ? Math.max(0.3, 0.7 * lw)
                            : Math.max(0.4, Math.sqrt(l.weight ?? 1) * lw);
                        return (hlLinks.size > 0 && hlLinks.has(l)) ? base + 2 : base;
                    });
                }
                break;
            }
            case 'nodeSize':   Graph.nodeRelSize(val); break;
            case 'cooldown':   Graph.cooldownTicks(val); break;
        }
    } catch (e) { console.warn('[viz] hotApply failed', e); }
}

function applyVizSettings(reheat = false) {
    if (!Graph || currentLayout === 'chord') return;
    const s = vizCurrentSettings;
    try {
        const charge = Graph.d3Force('charge');
        if (charge) charge.strength(s.charge);
        const link = Graph.d3Force('link');
        if (link) link.distance(s.linkDist);
        Graph.nodeRelSize(s.nodeSize);
        Graph.cooldownTicks(s.cooldown);
        if (reheat && Graph.d3ReheatSimulation) Graph.d3ReheatSimulation();
    } catch (e) { console.warn('[viz] applyVizSettings failed', e); }
}
// ────────────────────────────────────────────────────────────────────────────

function updateLegend() {
    const el = document.getElementById('swarm-legend');
    if (!el) return;
    const info = MODE_INFO[currentMode];
    if (!info) { el.style.display = 'none'; return; }

    // Global bipartite has its own explainer
    if (currentMode === 'propagation' && propIsGlobal) {
        const bdesc = {
            grupo:     'COLOR: torrent = categoría · usuario = nº de conexiones (rojo pocas → verde muchas)',
            descargas: 'COLOR: torrent = categoría · usuario = torrents descargados (rojo pocos → verde muchos)',
            simple:    'COLOR: oro = torrent · azul = usuario',
        };
        el.innerHTML =
            `<div style="color:#0ff; margin-bottom:.3rem;"><strong>MAPA BIPARTITO</strong></div>` +
            `<div style="margin-bottom:.25rem;">IZQUIERDA = torrents (3D = mapa completo · 2D = top 400 por pares). DERECHA = usuarios puente (activos en ≥2 torrents). LÍNEAS = quién tiene qué. Ratón sobre un nodo aísla su red.</div>` +
            `<div style="color:#7fffd4;">${bdesc[bipartiteColorMode] ?? ''} · TAMAÑO = nº de conexiones.</div>`;
        el.style.display = 'block';
        return;
    }

    const cv = info.colorVar();
    const colorLine = info.colors[cv] ?? '';

    el.innerHTML =
        `<div style="color:#0ff; margin-bottom:.3rem;"><strong>${MODE_LABELS[currentMode] ?? currentMode.toUpperCase()}</strong></div>` +
        `<div style="margin-bottom:.25rem;">${info.what}</div>` +
        `<div style="color:#7fffd4;">${colorLine}</div>`;
    el.style.display = 'block';
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function buildTooltip(n) {
    if (currentMode === 'network') {
        const col = COLORS.network.node(n);
        return `<strong>${esc(n.name)}</strong><br>
            Pares: ${n.peer_count} &nbsp; Semillas: ${n.seeders} &nbsp; Sangui.: ${n.leechers} &nbsp; Inactivos: ${n.stale ?? 0}<br>
            Sembrado: <span style="color:${col}">${n.health_pct}%</span>`;
    }
    if (currentMode === 'social') {
        const ratio = (n.ratio ?? 0) >= 999 ? '∞' : (n.ratio ?? 0);
        const days = n.age_days ?? 0;
        const seedH = Math.round((n.seedtime ?? 0) / 3600);
        return `<strong>${esc(n.name)}</strong><br>
            Ratio: ${ratio} &nbsp; H&amp;R: ${n.hitandruns ?? 0}<br>
            T.Siembra: ${seedH}h &nbsp; Edad: ${days}d<br>
            Torrents activos: ${n.torrent_count}<br>
            Ahora: ${n.seeding_now ?? 0} sembrando · ${n.leeching_now ?? 0} descargando`;
    }
    if (currentMode === 'content') {
        const gb = ((+(n.size ?? 0)) / 1073741824).toFixed(2);
        return `<strong>${esc(n.name)}</strong><br>
            Completado: ${n.times_completed}× &nbsp; Tamaño: ${gb} GB<br>
            Edad: ${n.age_days ?? 0}d`;
    }
    if (currentMode === 'propagation') {
        if (propIsGlobal) {
            if (n.kind === 'torrent') {
                return `🎬 <strong>${esc(n.name)}</strong><br>En manos de ${n.degree ?? 0} pares`;
            }
            return `👤 <strong>${esc(n.name)}</strong><br>
                En ${n.degree ?? 0} de estos torrents<br>
                ${n.downloads ?? 0} torrents descargados (total)`;
        }
        if (n.isCenter) return `🎬 <strong>${esc(n.name)}</strong>`;
        return `👤 <strong>${esc(n.name ?? 'Usuario')}</strong><br>
            Cliente: ${esc(n.agent ?? '—')}<br>
            ${n.is_live ? '🟢 activo' : '📅 ' + (n.date ?? '')} · ${n.seeder ? 'sembrador' : 'sanguijuela'}`;
    }
    return esc(n.name ?? '');
}

function showCanvas() {
    if (containerEl) containerEl.style.display = '';
    if (svgEl)       svgEl.style.display = 'none';
}

function showSvg() {
    if (containerEl) containerEl.style.display = 'none';
    if (svgEl)       svgEl.style.display = '';
}

function destroyGraph() {
    if (!Graph) return;
    try {
        if (typeof Graph._destructor === 'function') Graph._destructor();
        else if (typeof Graph.pauseAnimation === 'function') Graph.pauseAnimation();
    } catch (e) { /* noop */ }
    if (containerEl) containerEl.innerHTML = '';
    Graph = null;
}

function initGraph2D() {
    destroyGraph();
    showCanvas();
    console.log('[swarm] init 2D, ForceGraph:', typeof ForceGraph);
    Graph = ForceGraph()(containerEl)
        .width(containerEl.offsetWidth || window.innerWidth)
        .height(containerEl.offsetHeight || (window.innerHeight - 120))
        .backgroundColor('#04060a')
        .autoPauseRedraw(false)
        .nodeRelSize(vizCurrentSettings.nodeSize ?? 6)
        .nodeVal(nodeValAccessor)
        .nodeColor(n => COLORS[currentMode]?.node(n) ?? '#00ffcc')
        .nodeCanvasObjectMode(() => 'replace')
        .nodeCanvasObject((node, ctx) => {
            // node.val is already sqrt-compressed by nodeValAccessor
            const r = Math.max(1.5, Math.min(48, (node.val ?? 1) * (vizCurrentSettings.nodeSize ?? 6) * 0.467));
            const dimmed = hlNodes.size > 0 && !hlNodes.has(node.id);
            const color = COLORS[currentMode]?.node(node) ?? '#00ffcc';
            ctx.globalAlpha = dimmed ? 0.12 : 1;
            ctx.shadowColor = color;
            ctx.shadowBlur = dimmed ? 0 : 14;
            ctx.beginPath();
            ctx.arc(node.x, node.y, r, 0, 2 * Math.PI);
            ctx.fillStyle = dimmed ? '#3a4452' : color;
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.globalAlpha = 1;
        })
        .nodeLabel(() => null)
        .linkColor(l => {
            if (hlLinks.size > 0) {
                return hlLinks.has(l) ? 'rgba(0,255,255,0.85)' : 'rgba(120,140,160,0.04)';
            }
            return COLORS[currentMode]?.link(l) ?? 'rgba(0,255,200,0.35)';
        })
        .linkWidth(l => {
            const lw = vizCurrentSettings.linkWidth ?? 1.4;
            const base = (currentMode === 'propagation' && propIsGlobal)
                ? Math.max(0.3, 0.7 * lw)
                : Math.max(0.4, Math.sqrt(l.weight ?? 1) * lw);
            return (hlLinks.size > 0 && hlLinks.has(l)) ? base + 2 : base;
        })
        .cooldownTicks(vizCurrentSettings.cooldown ?? 300)
        .warmupTicks(currentMode === 'content' ? 80 : 0)
        .onNodeHover(onHover)
        .onNodeClick(n => { if (n.url) window.open(n.url, '_blank'); });
}

function initGraph3D() {
    destroyGraph();
    showCanvas();
    console.log('[swarm] init 3D, ForceGraph3D:', typeof ForceGraph3D);
    if (typeof ForceGraph3D === 'undefined') { setStatus('falta lib 3D'); return; }
    Graph = ForceGraph3D()(containerEl)
        .width(containerEl.offsetWidth || window.innerWidth)
        .height(containerEl.offsetHeight || (window.innerHeight - 120))
        .backgroundColor('#04060a')
        .nodeRelSize(vizCurrentSettings.nodeSize ?? 6)
        .nodeVal(nodeValAccessor)
        .nodeColor(n => COLORS[currentMode]?.node(n) ?? '#00ffcc')
        .nodeOpacity(0.9)
        .nodeLabel(() => '')
        .linkColor(l => COLORS[currentMode]?.link(l) ?? 'rgba(0,255,200,0.35)')
        .linkWidth(l => Math.max(0.4, Math.sqrt(l.weight ?? 1) * (vizCurrentSettings.linkWidth ?? 1.4) * 0.7))
        .linkOpacity(0.55)
        .cooldownTicks(vizCurrentSettings.cooldown ?? 300)
        .warmupTicks(currentMode === 'content' ? 80 : 0)
        .onNodeHover(onHover)
        .onNodeClick(n => { if (n.url) window.open(n.url, '_blank'); });
}

// raw metric — used for sorting / top-N selection
function rawMetric(n) {
    return Math.max(1, n.peer_count ?? n.torrent_count ?? n.times_completed ?? n.reach ?? n.degree ?? 1);
}

// sqrt-compressed value fed to force-graph — tames the "one giant meatball" spread
function nodeValAccessor(n) {
    return Math.sqrt(rawMetric(n));
}

// Set hlNodes/hlLinks to a node id + its direct net.
function highlightNet(nodeId) {
    hlNodes.clear();
    hlLinks.clear();
    if (!nodeId) return;
    hlNodes.add(nodeId);
    (adjNodes[nodeId] || []).forEach(id => hlNodes.add(id));
    (adjLinks[nodeId] || []).forEach(l => hlLinks.add(l));
}

function onHover(n) {
    const tip = document.getElementById('graph-tooltip');
    if (!n) {
        tip.style.display = 'none';
        if (hlEnabled) highlightNet(searchedNodeId);
        return;
    }
    tip.style.display = 'block';
    tip.innerHTML = buildTooltip(n);
    if (hlEnabled) highlightNet(n.id);
}

function searchUser() {
    const input = document.getElementById('bip-user-name');
    const q = (input ? input.value : '').trim().toLowerCase();
    if (!q) return;

    const node = lastData.nodes.find(n =>
        n.kind === 'user' && String(n.name ?? '').toLowerCase() === q
    );

    if (!node) {
        setStatus(`usuario "${q}" no está en el mapa (solo usuarios puente)`);
        return;
    }

    searchedNodeId = node.id;
    highlightNet(searchedNodeId);

    // center + zoom on the node
    if (Graph && node.x !== undefined && node.y !== undefined) {
        if (Graph.centerAt) Graph.centerAt(node.x, node.y, 800);
        if (Graph.zoom)     Graph.zoom(3.5, 800);
    }
    setStatus(`usuario: ${node.name} · en ${node.degree ?? 0} torrents`);
}

function clearSearch() {
    searchedNodeId = null;
    hlNodes.clear();
    hlLinks.clear();
    const input = document.getElementById('bip-user-name');
    if (input) input.value = '';
    if (Graph && Graph.zoomToFit) try { Graph.zoomToFit(600, 80); } catch (e) {}
}

// Build node→neighbour and node→links maps for hover-highlight.
function buildAdjacency() {
    adjNodes = {};
    adjLinks = {};
    (lastData.links || []).forEach(l => {
        const s = (typeof l.source === 'object') ? l.source.id : l.source;
        const t = (typeof l.target === 'object') ? l.target.id : l.target;
        (adjNodes[s] ??= new Set()).add(t);
        (adjNodes[t] ??= new Set()).add(s);
        (adjLinks[s] ??= []).push(l);
        (adjLinks[t] ??= []).push(l);
    });
}

function applyLayout(layout) {
    if (!Graph) return;
    if (layout === 'hierarchical') {
        // dagMode only works on true DAGs. Propagation single-torrent = star = safe.
        // All other modes have undirected/cyclic edges — use forceY tier instead.
        const canDag = currentMode === 'propagation' && !propIsGlobal;
        if (canDag) {
            try { Graph.dagMode('td').dagLevelDistance(60); } catch (e) { console.warn('[swarm] dagMode failed', e); }
        } else {
            try {
                Graph.dagMode(null);
                Graph.d3Force('radial', null);
                if (typeof d3 !== 'undefined') {
                    const maxVal = Math.max(1, ...lastData.nodes.map(nodeValAccessor));
                    Graph.d3Force('y', d3.forceY(n => {
                        const v = nodeValAccessor(n);
                        return (1 - Math.sqrt(v) / Math.sqrt(maxVal)) * 400;
                    }).strength(0.25));
                }
                if (Graph.d3ReheatSimulation) Graph.d3ReheatSimulation();
            } catch (e) { console.warn('[swarm] hierarchy fallback failed', e); }
        }
    } else if (layout === 'radial') {
        if (typeof d3 === 'undefined') { console.warn('[swarm] d3 missing for radial'); return; }
        // Custom radial force: pin nodes to concentric rings based on val
        try {
            Graph.dagMode(null);
            const maxVal = Math.max(1, ...lastData.nodes.map(nodeValAccessor));
            Graph.d3Force('radial', d3.forceRadial(n => {
                const v = nodeValAccessor(n);
                // small val → outer ring, large val → inner ring
                return (1 - Math.sqrt(v) / Math.sqrt(maxVal)) * 300 + 50;
            }, 0, 0).strength(0.15));
            Graph.d3ReheatSimulation && Graph.d3ReheatSimulation();
        } catch (e) { console.warn('[swarm] radial failed', e); }
    } else {
        try {
            Graph.dagMode(null);
            Graph.d3Force('radial', null);
            Graph.d3Force('y', null);
            Graph.d3ReheatSimulation && Graph.d3ReheatSimulation();
        } catch (e) { /* noop */ }
    }
}

function renderChord() {
    showSvg();
    destroyGraph();

    if (typeof d3 === 'undefined') { setStatus('falta lib d3'); return; }
    const nodes = lastData.nodes;
    const links = lastData.links;
    if (!nodes.length) { setStatus('sin datos'); return; }

    // Step 1: pick top 60 by val (overshoot so we can filter unconnected nodes)
    let candidates = [...nodes].sort((a, b) => nodeValAccessor(b) - nodeValAccessor(a)).slice(0, 60);
    let candIdx = new Map(candidates.map((n, i) => [String(n.id), i]));

    // Step 2: build matrix on candidates, then drop nodes with zero ribbons
    const tempMatrix = Array.from({ length: candidates.length }, () => new Array(candidates.length).fill(0));
    links.forEach(l => {
        const s = candIdx.get(String(l.source?.id ?? l.source));
        const t = candIdx.get(String(l.target?.id ?? l.target));
        if (s == null || t == null) return;
        const w = l.weight ?? 1;
        tempMatrix[s][t] += w;
        tempMatrix[t][s] += w;
    });

    // Find nodes that have at least one in-matrix ribbon
    const connectedIdx = candidates
        .map((n, i) => ({ n, i, total: tempMatrix[i].reduce((a, b) => a + b, 0) }))
        .filter(x => x.total > 0)
        .slice(0, 40);  // hard cap at 40 readable arcs

    if (connectedIdx.length < 2) {
        svgEl.innerHTML = '';
        setStatus('cuerda · sin conexiones suficientes en top 40');
        return;
    }

    const sorted = connectedIdx.map(x => x.n);
    const idIndex = new Map(sorted.map((n, i) => [String(n.id), i]));
    const N = sorted.length;
    const matrix = Array.from({ length: N }, () => new Array(N).fill(0));

    links.forEach(l => {
        const s = idIndex.get(String(l.source?.id ?? l.source));
        const t = idIndex.get(String(l.target?.id ?? l.target));
        if (s == null || t == null) return;
        const w = l.weight ?? 1;
        matrix[s][t] += w;
        matrix[t][s] += w;
    });

    // Use container dims — SVG clientWidth reports intrinsic 300x150 default, not CSS-stretched size
    const w = containerEl.offsetWidth  || window.innerWidth;
    const h = containerEl.offsetHeight || (window.innerHeight - 120);
    const size = Math.min(w, h);
    const outerRadius = size * 0.34;
    const innerRadius = outerRadius - 12;
    const labelRadius = outerRadius + 6;

    svgEl.setAttribute('viewBox', `${-w/2} ${-h/2} ${w} ${h}`);
    svgEl.innerHTML = '';

    const chord = d3.chord().padAngle(0.04).sortSubgroups(d3.descending)(matrix);
    const arc = d3.arc().innerRadius(innerRadius).outerRadius(outerRadius);
    const ribbon = d3.ribbon().radius(innerRadius);

    const svg = d3.select(svgEl);
    const g = svg.append('g');

    // Inner ribbons (links) — drawn first so arcs sit on top
    const ribbonG = g.append('g').attr('class', 'ribbons').attr('fill-opacity', 0.55);
    const ribbonPaths = ribbonG.selectAll('path')
        .data(chord)
        .enter().append('path')
        .attr('d', ribbon)
        .attr('fill', d => COLORS[currentMode]?.node(sorted[d.source.index]) ?? '#00ffcc')
        .attr('stroke', 'rgba(0,255,200,0.15)');

    // Outer arcs (nodes)
    const arcG = g.append('g').attr('class', 'arcs');
    const arcPaths = arcG.selectAll('path')
        .data(chord.groups)
        .enter().append('path')
        .attr('d', arc)
        .attr('fill', d => COLORS[currentMode]?.node(sorted[d.index]) ?? '#00ffcc')
        .attr('stroke', '#04060a')
        .style('filter', 'drop-shadow(0 0 4px currentColor)')
        .style('cursor', 'pointer')
        .on('mouseover', (e, d) => {
            onHover(sorted[d.index]);
            const i = d.index;
            // dim everything not connected to this arc
            arcPaths.attr('opacity', a => a.index === i ? 1 : 0.15);
            ribbonPaths.attr('opacity', r => (r.source.index === i || r.target.index === i) ? 1 : 0.08);
        })
        .on('mouseout', () => {
            onHover(null);
            arcPaths.attr('opacity', 1);
            ribbonPaths.attr('opacity', 1);
        })
        .on('click', (e, d) => { const url = sorted[d.index]?.url; if (url) window.open(url, '_blank'); });

    // Labels around the ring — radial, truncated names
    const labelG = g.append('g').attr('class', 'arc-labels');
    labelG.selectAll('text')
        .data(chord.groups)
        .enter().append('text')
        .each(function (d) {
            d.angle = (d.startAngle + d.endAngle) / 2;
        })
        .attr('dy', '0.35em')
        .attr('transform', d => `
            rotate(${(d.angle * 180 / Math.PI - 90)})
            translate(${labelRadius})
            ${d.angle > Math.PI ? 'rotate(180)' : ''}
        `)
        .attr('text-anchor', d => d.angle > Math.PI ? 'end' : null)
        .attr('fill', d => COLORS[currentMode]?.node(sorted[d.index]) ?? '#00ffcc')
        .style('font-family', 'Courier New, monospace')
        .style('font-size', '10px')
        .style('letter-spacing', '0.05em')
        .style('text-shadow', '0 0 4px currentColor')
        .style('pointer-events', 'none')
        .text(d => {
            const name = sorted[d.index]?.name ?? '';
            return name.length > 28 ? name.slice(0, 26) + '…' : name;
        });

    // Track cursor for tooltip
    svgEl.onmousemove = e => {
        const tip = document.getElementById('graph-tooltip');
        const rect = svgEl.getBoundingClientRect();
        if (tip.style.display === 'block') {
            tip.style.left = (e.clientX - rect.left + 14) + 'px';
            tip.style.top  = (e.clientY - rect.top  - 10) + 'px';
        }
    };

    setStatus(`cuerda · ${N} nodos conectados (de ${nodes.length} total)`);
}

// Two-column layout for the global bipartite: torrents pinned left, users right.
function applyBipartiteLayout() {
    if (!Graph || typeof d3 === 'undefined') return;
    try {
        Graph.dagMode(null);
        Graph.d3Force('radial', null);
        // huge gap between columns, very tall spread, strong repulsion + collision
        Graph.d3Force('x', d3.forceX(n => n.kind === 'torrent' ? -2200 : 2200).strength(0.5));
        Graph.d3Force('y', d3.forceY(0).strength(0.012));
        const charge = Graph.d3Force('charge');
        if (charge) charge.strength(-220);
        // collision force — guarantees node bubbles never overlap
        Graph.d3Force('collide', d3.forceCollide(n => Math.max(3, (n.val ?? 1) * 2.8) + 8));
        // Bipartite needs ~70 ticks for forceX to split the two columns — fixed, ignores slider.
        // 0 = ball (no split), high = freeze on big maps. 70 splits fast without jank.
        Graph.cooldownTicks(70);
        if (Graph.d3ReheatSimulation) Graph.d3ReheatSimulation();
    } catch (e) {
        console.warn('[swarm] bipartite layout failed', e);
    }
}

function applyData() {
    if (!Graph) return;
    buildAdjacency();
    highlightNet(searchedNodeId);   // re-apply locked search (or clear if none)
    // accessors are already set by initGraph2D/3D (mode-aware + hover-aware) —
    // do NOT re-set them here or the hover-highlight linkColor gets clobbered.
    // Content: set layout forces BEFORE graphData so warmupTicks settles them headlessly.
    if (currentMode === 'content') applyLayout(currentLayout);
    Graph.graphData({ nodes: lastData.nodes, links: lastData.links });
    applyVizSettings();
    setTimeout(() => {
        if (currentMode === 'propagation' && propIsGlobal) {
            applyBipartiteLayout();
        } else if (currentMode !== 'content') {
            applyLayout(currentLayout);
        }
        if (Graph.zoomToFit) try { Graph.zoomToFit(600, 80); } catch (e) {}
    }, 300);
}

function rebuildGraphForLayout() {
    if (currentLayout === 'chord') {
        renderChord();
    } else if (currentLayout === '3d') {
        initGraph3D();
        applyData();
    } else {
        initGraph2D();
        applyData();
    }
}

function loadMode(mode) {
    console.log('[swarm] loadMode', mode);
    currentMode = mode;
    searchedNodeId = null;
    vizCurrentSettings = loadVizSettings(mode);
    buildVizPanel();
    document.querySelectorAll('.swarm-btn[data-mode]').forEach(b => {
        b.classList.toggle('active', b.dataset.mode === mode);
    });

    const ps = document.getElementById('prop-search');
    if (ps) ps.style.display = mode === 'propagation' ? 'inline-flex' : 'none';

    updateColorRows();
    updateLayoutButtons();

    if (mode === 'propagation') {
        propIsGlobal = false;
    }
    updateLegend();

    if (mode === 'propagation') {
        setStatus('introduce ID torrent, o pulsa GLOBAL →');
        lastData = { nodes: [], links: [] };
        rebuildGraphForLayout();
        return;
    }

    setStatus('cargando…');
    fetch(`/swarm/data?mode=${mode}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => {
            lastData = { nodes: data.nodes ?? [], links: data.links ?? [] };
            // compute per-mode color scale maxes from data
            if (mode === 'network') {
                networkMaxSeeders  = Math.max(0, ...lastData.nodes.map(n => +(n.tracker_seeders  ?? n.seeders  ?? 0)));
                networkMaxLeechers = Math.max(0, ...lastData.nodes.map(n => +(n.tracker_leechers ?? n.leechers ?? 0)));
            }
            if (mode === 'social') {
                socialMaxSeedtime = Math.max(0, ...lastData.nodes.map(n => +(n.seedtime ?? 0)));
                socialMaxUpload   = Math.max(0, ...lastData.nodes.map(n => +(n.uploaded ?? 0)));
                socialMaxHitruns  = Math.max(0, ...lastData.nodes.map(n => +(n.hitandruns ?? 0)));
                socialMaxAge      = Math.max(0, ...lastData.nodes.map(n => +(n.age_days ?? 0)));
            }
            if (mode === 'content') {
                contentMaxCompleted = Math.max(0, ...lastData.nodes.map(n => +(n.times_completed ?? 0)));
                contentMaxSize      = Math.max(0, ...lastData.nodes.map(n => +(n.size ?? 0)));
                contentMaxAge       = Math.max(0, ...lastData.nodes.map(n => +(n.age_days ?? 0)));
            }
            setStatus(`${lastData.nodes.length} nodos · ${lastData.links.length} enlaces`);
            rebuildGraphForLayout();
        })
        .catch(e => { console.error('[swarm] err', e); setStatus('error: ' + e.message); });
}

// Mapa de Alcance torrent cap: 3D handles the full map, 2D layouts get a lighter slice.
function layoutCap(layout) {
    return layout === '3d' ? 0 : 400;
}

function setLayout(layout) {
    currentLayout = layout;
    document.querySelectorAll('.swarm-btn[data-layout]').forEach(b => {
        b.classList.toggle('active', b.dataset.layout === layout);
    });
    // global propagation: re-fetch if the layout's cap differs from what's loaded
    if (currentMode === 'propagation' && propIsGlobal && layoutCap(layout) !== currentGlobalCap) {
        loadGlobalPropagation();
        return;
    }
    rebuildGraphForLayout();
}

// Show the color-picker row matching the current mode/state.
function updateColorRows() {
    const show = (id, on) => { const el = document.getElementById(id); if (el) el.style.display = on ? 'flex' : 'none'; };
    show('network-color-row',     currentMode === 'network');
    show('social-color-row',      currentMode === 'social');
    show('content-color-row',     currentMode === 'content');
    show('propagation-color-row', currentMode === 'propagation' && !propIsGlobal);
    show('bipartite-color-row',   currentMode === 'propagation' && propIsGlobal);
    const bipSearch = document.getElementById('bipartite-search');
    if (bipSearch) bipSearch.style.display = (currentMode === 'propagation' && propIsGlobal) ? 'inline-flex' : 'none';
}

// All layouts re-enabled — Reach Map is edgeless so HIERARCHY can't fibrillate,
// and cooldownTicks caps the simulation. CHORD on edgeless data degrades to "sin datos".
function updateLayoutButtons() {
    const chordBtn = document.getElementById('btn-layout-chord');
    if (chordBtn) chordBtn.disabled = false;
    const hierBtn = document.getElementById('btn-layout-hierarchy');
    if (hierBtn) hierBtn.disabled = false;
}

function loadPropagation() {
    const idInput = document.getElementById('prop-torrent-id');
    const id = idInput ? idInput.value : '';
    if (!id) return;
    setStatus('cargando…');

    fetch(`/swarm/data?mode=propagation&torrent_id=${id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (!data.torrent) { setStatus('torrent no encontrado'); return; }

            const center = { id: `t${data.torrent.id}`, name: data.torrent.name, isCenter: true };
            const nodes = [center];
            const links = [];
            const seen = new Set();

            const toTs = (d) => {
                if (!d) return 0;
                const t = Date.parse(d);
                return isNaN(t) ? 0 : t;
            };

            (data.timeline ?? []).forEach(p => {
                const nid = `u${p.user_id}`;
                if (!seen.has(nid)) {
                    seen.add(nid);
                    nodes.push({
                        id: nid,
                        name: p.username ?? 'Usuario',
                        agent: p.agent ?? '—',
                        seeder: p.seeder,
                        date: p.date,
                        dateTs: toTs(p.date),
                        is_live: false,
                        url: p.username ? `/users/${encodeURIComponent(p.username)}` : null,
                    });
                    links.push({ source: center.id, target: nid, weight: 1 });
                }
            });

            (data.live ?? []).forEach(p => {
                const nid = `u${p.user_id}`;
                const ex = nodes.find(n => n.id === nid);
                if (ex) { ex.is_live = true; ex.agent = p.agent ?? ex.agent; }
                else {
                    nodes.push({
                        id: nid,
                        name: p.username ?? 'Usuario',
                        agent: p.agent ?? '—',
                        seeder: p.seeder,
                        connectable: p.connectable,
                        date: p.date,
                        dateTs: toTs(p.date),
                        is_live: true,
                        url: p.username ? `/users/${encodeURIComponent(p.username)}` : null,
                    });
                    links.push({ source: center.id, target: nid, weight: 1 });
                }
            });

            // wave gradient bounds
            const tsList = nodes.filter(n => !n.isCenter && n.dateTs).map(n => n.dateTs);
            propMinDate = tsList.length ? Math.min(...tsList) : 0;
            propMaxDate = tsList.length ? Math.max(...tsList) : 0;

            // agent (BitTorrent client) → palette color
            propAgentColors = {};
            let ai = 0;
            nodes.forEach(n => {
                if (n.isCenter) return;
                const a = n.agent ?? '—';
                if (!(a in propAgentColors)) {
                    propAgentColors[a] = CAT_PALETTE[ai % CAT_PALETTE.length];
                    ai++;
                }
            });

            lastData = { nodes, links };
            propIsGlobal = false;
            updateColorRows();
            updateLayoutButtons();
            updateLegend();
            setStatus(`${data.torrent.name} · ${nodes.length - 1} pares`);
            rebuildGraphForLayout();
        })
        .catch(e => { console.error('[swarm] err', e); setStatus('error: ' + e.message); });
}

function loadGlobalPropagation() {
    const cap = layoutCap(currentLayout);
    currentGlobalCap = cap;
    setStatus('cargando mapa bipartito…');
    fetch(`/swarm/data?mode=propagation&torrent_id=0&cap=${cap}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (!data.bipartite) { setStatus('datos no disponibles'); return; }
            lastData = { nodes: data.nodes ?? [], links: data.links ?? [] };
            propIsGlobal = true;
            searchedNodeId = null;

            const userNodes = lastData.nodes.filter(n => n.kind === 'user');
            bipMaxUserDegree    = Math.max(0, ...userNodes.map(n => +(n.degree ?? 0)));
            bipMaxUserDownloads = Math.max(0, ...userNodes.map(n => +(n.downloads ?? 0)));

            updateColorRows();
            updateLayoutButtons();
            updateLegend();
            const torrents = lastData.nodes.filter(n => n.kind === 'torrent').length;
            const users    = lastData.nodes.filter(n => n.kind === 'user').length;
            const scope = cap > 0 ? `top ${cap} · usa 3D para mapa completo` : 'mapa completo';
            setStatus(`bipartito · ${torrents} torrents · ${users} usuarios · ${lastData.links.length} enlaces · ${scope}`);
            rebuildGraphForLayout();
        })
        .catch(e => { console.error('[swarm] err', e); setStatus('error: ' + e.message); });
}

function wireControls() {
    document.querySelectorAll('.swarm-btn[data-mode]').forEach(btn => {
        btn.addEventListener('click', () => loadMode(btn.dataset.mode));
    });
    document.querySelectorAll('.swarm-btn[data-layout]').forEach(btn => {
        btn.addEventListener('click', () => { if (!btn.disabled) setLayout(btn.dataset.layout); });
    });
    document.querySelectorAll('.swarm-btn[data-color]').forEach(btn => {
        btn.addEventListener('click', () => {
            networkColorMode = btn.dataset.color;
            document.querySelectorAll('.swarm-btn[data-color]').forEach(b => {
                b.classList.toggle('active', b.dataset.color === networkColorMode);
            });
            updateLegend();
            if (currentMode === 'network') rebuildGraphForLayout();
        });
    });
    document.querySelectorAll('.swarm-btn[data-scolor]').forEach(btn => {
        btn.addEventListener('click', () => {
            socialColorMode = btn.dataset.scolor;
            document.querySelectorAll('.swarm-btn[data-scolor]').forEach(b => {
                b.classList.toggle('active', b.dataset.scolor === socialColorMode);
            });
            updateLegend();
            if (currentMode === 'social') rebuildGraphForLayout();
        });
    });
    document.querySelectorAll('.swarm-btn[data-ccolor]').forEach(btn => {
        btn.addEventListener('click', () => {
            contentColorMode = btn.dataset.ccolor;
            document.querySelectorAll('.swarm-btn[data-ccolor]').forEach(b => {
                b.classList.toggle('active', b.dataset.ccolor === contentColorMode);
            });
            updateLegend();
            if (currentMode === 'content') rebuildGraphForLayout();
        });
    });
    document.querySelectorAll('.swarm-btn[data-pcolor]').forEach(btn => {
        btn.addEventListener('click', () => {
            propColorMode = btn.dataset.pcolor;
            document.querySelectorAll('.swarm-btn[data-pcolor]').forEach(b => {
                b.classList.toggle('active', b.dataset.pcolor === propColorMode);
            });
            updateLegend();
            if (currentMode === 'propagation' && !propIsGlobal) rebuildGraphForLayout();
        });
    });
    document.querySelectorAll('.swarm-btn[data-bcolor]').forEach(btn => {
        btn.addEventListener('click', () => {
            bipartiteColorMode = btn.dataset.bcolor;
            document.querySelectorAll('.swarm-btn[data-bcolor]').forEach(b => {
                b.classList.toggle('active', b.dataset.bcolor === bipartiteColorMode);
            });
            updateLegend();
            if (currentMode === 'propagation' && propIsGlobal) rebuildGraphForLayout();
        });
    });
    const goBtn = document.getElementById('btn-prop-go');
    if (goBtn) goBtn.addEventListener('click', loadPropagation);
    const globalBtn = document.getElementById('btn-prop-global');
    if (globalBtn) globalBtn.addEventListener('click', loadGlobalPropagation);
    const idInput = document.getElementById('prop-torrent-id');
    if (idInput) idInput.addEventListener('keydown', e => { if (e.key === 'Enter') loadPropagation(); });

    const bipSearchBtn = document.getElementById('btn-bip-search');
    if (bipSearchBtn) bipSearchBtn.addEventListener('click', searchUser);
    const bipClearBtn = document.getElementById('btn-bip-clear');
    if (bipClearBtn) bipClearBtn.addEventListener('click', clearSearch);
    const bipInput = document.getElementById('bip-user-name');
    if (bipInput) bipInput.addEventListener('keydown', e => { if (e.key === 'Enter') searchUser(); });
}

function attachMouseTracker() {
    if (!containerEl) return;
    containerEl.addEventListener('mousemove', e => {
        const tip = document.getElementById('graph-tooltip');
        const rect = containerEl.getBoundingClientRect();
        if (tip.style.display === 'block') {
            tip.style.left = (e.clientX - rect.left + 14) + 'px';
            tip.style.top  = (e.clientY - rect.top  - 10) + 'px';
        }
    });
    window.addEventListener('resize', () => {
        if (Graph && containerEl && currentLayout !== 'chord') {
            try { Graph.width(containerEl.offsetWidth).height(containerEl.offsetHeight); } catch (e) {}
        }
        if (currentLayout === 'chord') renderChord();
    });
    // Fire when container gets its real layout dimensions (handles blank-on-load)
    if (window.ResizeObserver) {
        new ResizeObserver(() => {
            if (!Graph || currentLayout === 'chord') return;
            try { Graph.width(containerEl.offsetWidth).height(containerEl.offsetHeight); } catch(e) {}
        }).observe(containerEl);
    }
    // Fire when tab becomes visible again
    document.addEventListener('visibilitychange', () => {
        if (document.hidden || !containerEl) return;
        if (Graph && currentLayout !== 'chord') {
            try { Graph.width(containerEl.offsetWidth).height(containerEl.offsetHeight); } catch(e) {}
        }
        if (currentLayout === 'chord') renderChord();
    });
}

// Opera (incl. Opera GX) has flaky WebGL/rAF behaviour that breaks the 3D
// swarm map — warn the user, leave everything else untouched.
function showBrowserNote() {
    if (!/OPR\//i.test(navigator.userAgent)) return;
    const b = document.createElement('div');
    b.textContent = 'Tu navegador es Opera — el Mapa del Enjambre puede no renderizar correctamente. Recomendamos Chrome o Firefox.';
    b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;'
        + 'background:#3a0d0d;color:#ffc9c9;border-bottom:1px solid #6a1a1a;'
        + 'font:13px/1.5 sans-serif;text-align:center;padding:7px 14px;';
    document.body.appendChild(b);
}

function boot() {
    console.log('[swarm] boot — globals check:',
        'd3:', typeof d3,
        '| ForceGraph:', typeof ForceGraph,
        '| THREE:', typeof THREE,
        '| ForceGraph3D:', typeof ForceGraph3D
    );
    containerEl = document.getElementById('graph-container');
    svgEl = document.getElementById('chord-container');
    const ind = document.getElementById('js-indicator');
    if (ind) ind.remove();
    showBrowserNote();

    if (typeof ForceGraph === 'undefined') {
        console.error('[swarm] ForceGraph missing');
        setStatus('falta lib force-graph');
        return;
    }

    attachMouseTracker();
    wireControls();
    currentLayout = '3d';
    loadMode('network');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
</script>
@endsection
