"use strict";
const $ = (id) => document.getElementById(id);

function fmtSize(n) {
  if (n >= 1 << 30) return (n / (1 << 30)).toFixed(2) + " GiB";
  if (n >= 1 << 20) return (n / (1 << 20)).toFixed(1) + " MiB";
  if (n >= 1 << 10) return (n / (1 << 10)).toFixed(1) + " KiB";
  return n + " B";
}
function fmtAge(secs) {
  if (secs == null) return "—";
  const h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60);
  if (h >= 24) return Math.floor(h / 24) + "d " + (h % 24) + "h";
  if (h) return h + "h " + m + "m";
  if (m) return m + "m " + (secs % 60) + "s";
  return secs + "s";
}
function fmtUptime(secs) {
  if (secs == null) return "—";
  const d = Math.floor(secs / 86400), h = Math.floor((secs % 86400) / 3600), m = Math.floor((secs % 3600) / 60);
  if (d) return d + "d " + h + "h";
  if (h) return h + "h " + m + "m";
  return m + "m";
}
function healthClass(c) {
  if (!c.running) return "off";
  if (c.health === "healthy" || c.health === "n/a") return "ok";
  if (c.health === "starting") return "warn";
  return "bad";
}

let streamingJob = null;
let lastStats = {};
let labUp = false;

const ACTION_BTNS = ["btn-restore", "btn-diff", "btn-export", "btn-tv", "btn-reset",
  "btn-apply-dry", "btn-apply", "btn-kit", "btn-dr", "btn-scan", "btn-exact-all", "btn-maint",
  "btn-snapshot", "btn-verify"];

async function refreshStatus() {
  try {
    const s = await (await fetch("/api/status")).json();
    labUp = s.lab_up;
    const dot = $("dot");
    dot.className = "dot " + (s.lab_up ? (s.health === "healthy" ? "ok" : "warn") : "off");
    $("state-text").textContent = s.lab_up ? "lab up" : "lab down";
    $("health").textContent = s.lab_up ? "(" + s.health + ")" : "";
    const busy = !!s.current_job;
    $("btn-wake").disabled = busy || s.lab_up;
    $("btn-sleep").disabled = busy || !s.lab_up;
    ACTION_BTNS.forEach((id) => { $(id).disabled = busy; });
    renderCards(s.containers || []);
    if (s.current_job && s.current_job !== streamingJob) streamJob(s.current_job);
  } catch (e) {
    $("state-text").textContent = "dashboard unreachable";
  }
}

function renderCards(containers) {
  const wrap = $("cards");
  wrap.innerHTML = "";
  for (const c of containers) {
    const st = lastStats[c.name] || {};
    const cls = healthClass(c);
    const cpu = st.cpu ? parseFloat(st.cpu) : null;
    const memPct = st.mem_pct ? parseFloat(st.mem_pct) : null;
    const card = document.createElement("div");
    card.className = "card " + cls;
    card.innerHTML = `
      <div class="card-head"><span class="dot ${cls}"></span><span class="mono">${c.name}</span></div>
      <div class="card-row"><span class="muted">state</span><span>${c.running ? "running" : "stopped"}</span></div>
      <div class="card-row"><span class="muted">health</span><span>${c.health}</span></div>
      <div class="card-row"><span class="muted">uptime</span><span>${fmtUptime(c.uptime)}</span></div>
      ${c.running ? `
      <div class="metric"><div class="metric-label"><span>CPU</span><span>${st.cpu || "—"}</span></div>
        <div class="bar"><div class="bar-fill" style="width:${cpu != null ? Math.min(cpu, 100) : 0}%"></div></div></div>
      <div class="metric"><div class="metric-label"><span>MEM</span><span>${st.mem || "—"}</span></div>
        <div class="bar"><div class="bar-fill mem" style="width:${memPct != null ? Math.min(memPct, 100) : 0}%"></div></div></div>
      ` : ""}`;
    wrap.appendChild(card);
  }
}

async function refreshStats() {
  try { lastStats = (await (await fetch("/api/stats")).json()).stats || {}; } catch (e) {}
}

async function refreshBackups() {
  const body = document.querySelector("#backups tbody");
  try {
    const d = await (await fetch("/api/backups")).json();
    const sum = d.summary || {};
    const badge = $("backup-health");
    const age = sum.newest_age_secs;
    if (age == null) { badge.className = "badge bad"; badge.textContent = "no snapshots"; }
    else if (age <= sum.stale_after_secs) { badge.className = "badge ok"; badge.textContent = "fresh · " + fmtAge(age) + " ago"; }
    else if (age <= 86400) { badge.className = "badge warn"; badge.textContent = "late · " + fmtAge(age) + " ago"; }
    else { badge.className = "badge bad"; badge.textContent = "stale · " + fmtAge(age) + " ago"; }
    $("backup-summary").textContent =
      `${sum.count || 0} snapshots · ${fmtSize(sum.total_size || 0)} total · cron every ${fmtAge(sum.cron_interval_secs)}`;

    // keep the restore + DR dump <select>s in sync
    const opts = '<option value="latest">latest</option>' +
      d.backups.map((b) => `<option value="${b.name}">${b.name}</option>`).join("");
    for (const id of ["r-dump", "dr-dump"]) {
      const sel = $(id); if (!sel) continue;
      const prev = sel.value;
      sel.innerHTML = opts;
      sel.value = [...sel.options].some((o) => o.value === prev) ? prev : "latest";
    }

    body.innerHTML = "";
    if (!d.backups.length) { body.innerHTML = '<tr><td colspan="6" class="muted">no dumps</td></tr>'; return; }
    const max = Math.max(...d.backups.map((b) => b.size));
    d.backups.forEach((b, i) => {
      const pct = max ? (b.size / max * 100).toFixed(1) : 0;
      const tr = document.createElement("tr");
      tr.innerHTML =
        `<td class="mono">${b.name}</td><td class="nowrap">${fmtSize(b.size)}</td>` +
        `<td class="chart-col"><div class="hbar${i === 0 ? " newest" : ""}" style="width:${pct}%"></div></td>` +
        `<td class="nowrap">${b.mtime.replace("T", " ")}</td>` +
        `<td class="${b.binlogpos ? "yes" : "no"}">${b.binlogpos ? "✓" : "✗"}</td>` +
        `<td class="${b.sha256 ? "yes" : "no"}">${b.sha256 ? "✓" : "✗"}</td>`;
      body.appendChild(tr);
    });
  } catch (e) {
    body.innerHTML = '<tr><td colspan="6" class="muted">error loading backups</td></tr>';
  }
}

async function refreshExports() {
  const body = document.querySelector("#exports tbody");
  try {
    const d = await (await fetch("/api/exports")).json();
    body.innerHTML = "";
    if (!d.exports.length) { body.innerHTML = '<tr><td colspan="4" class="muted">no exports yet</td></tr>'; return; }
    for (const e of d.exports) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        `<td class="mono">${e.name}</td><td class="nowrap">${fmtSize(e.size)}</td>` +
        `<td class="nowrap">${e.mtime.replace("T", " ")}</td>` +
        `<td><button class="link" data-view="${e.name}">view</button></td>`;
      body.appendChild(tr);
    }
    body.querySelectorAll("[data-view]").forEach((btn) => {
      btn.onclick = () => viewExport(btn.getAttribute("data-view"));
    });
    // feed the apply / kit export <select>s (skip generated kits for apply)
    const applyOpts = d.exports.filter((e) => !e.name.startsWith("apply-kit-"))
      .map((e) => `<option value="${e.name}">${e.name}</option>`).join("");
    for (const id of ["ap-file", "kit-file"]) {
      const sel = $(id); if (!sel) continue;
      const prev = sel.value;
      sel.innerHTML = applyOpts || '<option value="">(no exports yet)</option>';
      if ([...sel.options].some((o) => o.value === prev)) sel.value = prev;
    }
  } catch (e) {
    body.innerHTML = '<tr><td colspan="4" class="muted">error</td></tr>';
  }
}

async function viewExport(name) {
  $("job-label").textContent = name + " (read-only)";
  $("job-result").className = "badge"; $("job-result").textContent = "";
  try {
    const txt = await (await fetch("/api/exports/view?name=" + encodeURIComponent(name))).text();
    $("log").textContent = txt || "(empty)";
  } catch (e) { $("log").textContent = "error loading " + name; }
}

const VERDICT = { 0: ["ok", "match"], 2: ["bad", "file missing"], 3: ["bad", "infohash mismatch"] };

async function streamJob(jobId, action) {
  streamingJob = jobId;
  $("job-label").textContent = jobId;
  $("job-result").className = "badge"; $("job-result").textContent = "";
  $("log").textContent = "";
  try {
    const resp = await fetch("/api/jobs/" + jobId + "/stream");
    const reader = resp.body.getReader();
    const dec = new TextDecoder();
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      const el = $("log");
      el.textContent += dec.decode(value, { stream: true });
      el.scrollTop = el.scrollHeight;
    }
  } catch (e) {
    $("log").textContent += "\n[stream interrupted]";
  } finally {
    streamingJob = null;
    try {
      const job = await (await fetch("/api/jobs/" + jobId)).json();
      const rc = job.exit_code;
      const b = $("job-result");
      if (job.action === "torrent-validate" && VERDICT[rc]) {
        b.className = "badge " + VERDICT[rc][0]; b.textContent = VERDICT[rc][1];
      } else if (rc === 0) { b.className = "badge ok"; b.textContent = "done (0)"; }
      else { b.className = "badge bad"; b.textContent = "exit " + rc; }
    } catch (e) {}
    refreshStatus(); refreshBackups(); refreshExports();
    if (action === "timeline") { $("btn-scan").textContent = "scan binlogs"; loadTimeline(); }
    if (action === "exact-count") { $("btn-exact-all").textContent = "exact all"; loadExactCounts(); }
    if (action === "snapshot") { $("btn-snapshot").textContent = "snapshot now"; refreshBackups(); refreshStorageHealth(); }
    if (action === "verify-backups") { $("btn-verify").textContent = "verify checksums"; }
  }
}

async function post(action, body) {
  ACTION_BTNS.concat(["btn-wake", "btn-sleep"]).forEach((id) => { $(id).disabled = true; });
  const resp = await fetch("/api/" + action, {
    method: "POST", headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {}),
  });
  const d = await resp.json();
  if (resp.status === 409) { $("log").textContent = "lab busy — job " + d.running_job + " in flight"; refreshStatus(); return; }
  if (resp.status === 400) { $("log").textContent = "rejected: " + d.error; refreshStatus(); return; }
  if (d.job_id) streamJob(d.job_id, d.action);
}

$("btn-wake").onclick = () => post("wake");
$("btn-sleep").onclick = () => post("sleep");
$("btn-restore").onclick = () => post("restore", {
  dump: $("r-dump").value, until: $("r-until").value.trim(), no_replay: $("r-noreplay").checked,
});
$("btn-diff").onclick = () => post("diff", { tables: $("d-tables").value.trim().split(/\s+/).filter(Boolean) });
$("btn-export").onclick = () => post("export", { table: $("e-table").value.trim() });
$("btn-tv").onclick = () => post("torrent-validate", { target: $("t-target").value.trim() });
$("btn-reset").onclick = () => {
  if ($("x-confirm").value !== "RESET") { $("log").textContent = "type RESET to confirm"; return; }
  post("reset", { confirm: $("x-confirm").value });
  $("x-confirm").value = "";
};
// ---- data topology panel ----------------------------------------------------
let topoData = { prod: [], ids: [], lab: null, lab_up: false };
let topoIds = new Set();
let exactCounts = {};          // table -> {prod, lab} exact override
const diffSet = new Set();     // tables chosen for diff

function logScale(v, max) {
  if (!v || v <= 0 || !max) return 0;
  return Math.max(3, (Math.log10(v + 1) / Math.log10(max + 1)) * 100);
}

function fragClass(pct) { return pct >= 25 ? "bad" : pct >= 10 ? "warn" : "ok"; }

function syncBadge(prodRows, labRows) {
  if (labRows == null) return '<span class="sync off" title="absent in lab">○</span>';
  if (labRows > prodRows * 1.001) return '<span class="sync up" title="lab has more rows than prod (recovery candidates)">▲</span>';
  if (labRows >= prodRows * 0.99) return '<span class="sync ok" title="in sync with prod">●</span>';
  return '<span class="sync warn" title="lab behind prod">▼</span>';
}

function renderTopology() {
  const list = $("topo-list");
  const q = $("topo-search").value.trim().toLowerCase();
  const sort = $("topo-sort").value;
  const labUp = topoData.lab_up;
  $("topo-lab").className = "badge " + (labUp ? "ok" : "muted");
  $("topo-lab").textContent = labUp ? "lab overlay on" : "lab off";

  let rows = topoData.prod.slice();
  const totalSize = rows.reduce((a, t) => a + t.data + t.idx, 0) || 1;
  $("topo-summary").textContent =
    `${rows.length} tables · ${fmtSize(totalSize)} DB · ${topoIds.size} diffable`;
  renderDisk(topoData.disk);
  renderAdvisories();

  if (q) rows = rows.filter((t) => t.name.toLowerCase().includes(q));
  const maxRows = Math.max(1, ...rows.map((t) => t.rows));
  const fragPct = (t) => { const tot = t.data + t.idx + t.free; return tot ? t.free / tot * 100 : 0; };
  rows.sort((a, b) => {
    if (sort === "name") return a.name.localeCompare(b.name);
    if (sort === "rows") return b.rows - a.rows;
    if (sort === "frag") return fragPct(b) - fragPct(a);
    return (b.data + b.idx) - (a.data + a.idx);
  });

  list.innerHTML = "";
  for (const t of rows) {
    const size = t.data + t.idx;
    const fp = fragPct(t);
    const hasId = topoIds.has(t.name);
    const ex = exactCounts[t.name];
    const prodRows = ex ? ex.prod : t.rows;
    const labRows = labUp ? (ex && ex.lab != null ? ex.lab : (topoData.lab ? topoData.lab[t.name] : null)) : null;
    const rowsTxt = (ex ? "=" : "~") + (prodRows != null ? Number(prodRows).toLocaleString() : "?");

    const el = document.createElement("div");
    el.className = "trow" + (diffSet.has(t.name) ? " sel" : "") + (hasId ? "" : " noid");
    el.dataset.table = t.name;
    el.innerHTML = `
      <div class="trow-head">
        <i class="iddot ${hasId ? "ok" : "off"}" title="${hasId ? "has id PK — diffable" : "no id column"}"></i>
        <span class="tname mono">${t.name}</span>
        <span class="tengine ${t.engine === "InnoDB" ? "" : "alt"}">${t.engine}</span>
        <button class="exact link" title="exact COUNT(*)">=</button>
      </div>
      <div class="meters">
        <div class="meter"><span class="mlabel">rows</span>
          <div class="vu"><div class="vu-fill rows" style="width:${logScale(prodRows, maxRows)}%"></div></div>
          <span class="mval">${rowsTxt}</span></div>
        <div class="meter"><span class="mlabel">size</span>
          <div class="vu"><div class="vu-fill size" style="width:${Math.max(size / totalSize * 100, size ? 1.5 : 0)}%"></div></div>
          <span class="mval" title="${(size / totalSize * 100).toFixed(1)}% of DB">${fmtSize(size)}</span></div>
        <div class="meter"><span class="mlabel">frag</span>
          <div class="vu"><div class="vu-fill frag ${fragClass(fp)}" style="width:${Math.min(fp, 100)}%"></div></div>
          <span class="mval">${fp.toFixed(0)}%</span></div>
        ${labUp ? `<div class="meter"><span class="mlabel">lab</span>
          <div class="vu"><div class="vu-fill lab" style="width:${logScale(labRows, maxRows)}%"></div></div>
          <span class="mval">${syncBadge(prodRows, labRows)}</span></div>` : ""}
      </div>`;
    el.querySelector(".exact").onclick = (e) => { e.stopPropagation(); exactCount(t.name); };
    el.onclick = () => toggleTable(t.name);
    list.appendChild(el);
  }
  if (!rows.length) list.innerHTML = '<div class="muted small" style="padding:.5rem">no match</div>';
}

function toggleTable(name) {
  $("e-table").value = name;                     // export = last clicked
  if (diffSet.has(name)) diffSet.delete(name); else diffSet.add(name);
  $("d-tables").value = [...diffSet].join(" ");   // diff = the selected set
  renderTopology();
}

async function exactCount(name) {
  try {
    const d = await (await fetch("/api/table-count?table=" + encodeURIComponent(name))).json();
    if (!d.error) { exactCounts[name] = { prod: d.prod, lab: d.lab }; renderTopology(); }
  } catch (e) {}
}

function renderDisk(disk) {
  const el = $("topo-disk");
  if (!disk) { el.innerHTML = ""; return; }
  const pct = disk.pct || 0;
  const cls = pct >= 90 ? "bad" : pct >= 75 ? "warn" : "ok";
  el.innerHTML =
    `<span class="mlabel">disk</span>` +
    `<div class="vu"><div class="vu-fill frag ${cls}" style="width:${pct}%"></div></div>` +
    `<span class="mval" title="datadir filesystem">${fmtSize(disk.used)} / ${fmtSize(disk.total)} (${pct}%)</span>`;
}

async function refreshTopology() {
  try {
    topoData = await (await fetch("/api/topology")).json();
    topoIds = new Set(topoData.ids || []);
    renderTopology();
  } catch (e) {
    $("topo-summary").textContent = "topology unreachable";
  }
}

// ---- storage health gauges ---------------------------------------------------
function gauge(label, fillPct, cls, valText, title) {
  return `<div class="meter" title="${title || ""}"><span class="mlabel">${label}</span>` +
    `<div class="vu"><div class="vu-fill ${cls}" style="width:${Math.max(0, Math.min(fillPct, 100))}%"></div></div>` +
    `<span class="mval">${valText}</span></div>`;
}

async function refreshStorageHealth() {
  const el = $("storage-gauges");
  try {
    const s = await (await fetch("/api/storage-health")).json();
    const disk = s.disk || {};
    const diskTotal = disk.total || 1;
    const bl = s.binlogs || {};
    const span = (bl.oldest && bl.newest) ? fmtAge(bl.newest - bl.oldest) : "—";
    const diskCls = (disk.pct || 0) >= 90 ? "bad" : (disk.pct || 0) >= 75 ? "warn" : "ok";
    el.innerHTML =
      gauge("disk", disk.pct || 0, "frag " + diskCls,
            `${fmtSize(disk.used || 0)} / ${fmtSize(disk.total || 0)} (${disk.pct || 0}%)`, "datadir filesystem") +
      gauge("binlog", (bl.bytes || 0) / diskTotal * 100, "size",
            `${fmtSize(bl.bytes || 0)} · ${bl.count || 0} files · PITR ${span}`, "binlog footprint + recovery window") +
      gauge("redis", (s.redis_bytes || 0) / diskTotal * 100, "rows",
            fmtSize(s.redis_bytes || 0), "redis datadir (read-only)") +
      gauge("meili", (s.meili_bytes || 0) / diskTotal * 100, "lab",
            fmtSize(s.meili_bytes || 0), "meilisearch datadir (read-only)");
  } catch (e) { el.textContent = "storage health unreachable"; }
}

// ---- topology health advisories (link to the gated maintenance actions) ------
const ID_CEIL = { "int": 2147483647, "int unsigned": 4294967295, "bigint": 9223372036854775807,
                  "bigint unsigned": 18446744073709551615, "smallint": 32767, "smallint unsigned": 65535,
                  "mediumint": 8388607, "mediumint unsigned": 16777215 };

function renderAdvisories() {
  const el = $("topo-advisories");
  if (!el) return;
  const prod = topoData.prod || [];
  const items = [];

  // fragmentation / OPTIMIZE candidates: only flag meaningful reclaim — a floor of
  // 16 MiB free, and either >50 MiB absolute or >30% of the table is free space.
  const MiB = 1024 * 1024;
  const frag = prod.filter((t) => t.free >= 16 * MiB &&
      (t.free >= 50 * MiB || t.free / (t.data + t.idx + t.free) >= 0.30))
    .sort((a, b) => b.free - a.free).slice(0, 8);
  for (const t of frag)
    items.push(`<div class="adv warn" data-op="optimize" data-table="${t.name}">` +
      `<span class="adv-tag">defrag</span><span class="mono">${t.name}</span>` +
      `<span class="muted">${fmtSize(t.free)} free</span><span class="adv-fix">optimize →</span></div>`);

  // stale estimates: exact vs estimate diverge a lot
  for (const t of prod) {
    const ex = exactCounts[t.name];
    if (!ex || ex.prod == null || ex.prod < 1000) continue;
    const est = t.rows || 0;
    const diff = Math.abs(ex.prod - est) / ex.prod;
    if (diff > 0.2)
      items.push(`<div class="adv info" data-op="analyze" data-table="${t.name}">` +
        `<span class="adv-tag">stale</span><span class="mono">${t.name}</span>` +
        `<span class="muted">est ${est.toLocaleString()} vs ${ex.prod.toLocaleString()}</span><span class="adv-fix">analyze →</span></div>`);
  }

  // autoincrement headroom
  const idtypes = topoData.idtypes || {};
  for (const t of prod) {
    const ty = (idtypes[t.name] || "").toLowerCase();
    const ceil = ID_CEIL[ty];
    if (!ceil || !t.autoinc) continue;
    const pct = t.autoinc / ceil * 100;
    if (pct >= 60)
      items.push(`<div class="adv ${pct >= 85 ? "bad" : "warn"}">` +
        `<span class="adv-tag">id ${pct.toFixed(0)}%</span><span class="mono">${t.name}</span>` +
        `<span class="muted">${t.autoinc.toLocaleString()} / ${ty}</span></div>`);
  }

  if (!items.length) { el.innerHTML = '<div class="adv ok"><span class="adv-tag">ok</span><span class="muted">no fragmentation / stale-estimate / id-headroom issues</span></div>'; return; }
  el.innerHTML = items.join("");
  el.querySelectorAll(".adv[data-op]").forEach((row) => {
    row.onclick = () => {
      $("mt-op").value = row.dataset.op;
      $("mt-op").onchange();   // toggles the password field visibility
      $("mt-tables").value = row.dataset.table;
      $("mt-op").scrollIntoView({ behavior: "smooth", block: "center" });
    };
  });
}

async function loadExactCounts() {
  try {
    const d = await (await fetch("/api/exact-counts")).json();
    for (const [t, v] of Object.entries(d.counts || {})) exactCounts[t] = v;
    renderTopology();
  } catch (e) {}
}

$("topo-search").oninput = renderTopology;
$("topo-sort").onchange = renderTopology;
$("btn-exact-all").onclick = () => { $("btn-exact-all").textContent = "counting…"; post("exact-count", {}); };

// ---- damage timeline ---------------------------------------------------------
let tlData = { events: [], by_severity: {}, top_tables: [] };
const SEV_RANK = { critical: 0, high: 1, medium: 2, low: 3, info: 4 };
let tlShowAll = false;   // default: only critical+high

function untilMinus1s(ts) {
  // ts = "YYYY-MM-DD HH:MM:SS" -> one second earlier, same format
  const d = new Date(ts.replace(" ", "T"));
  if (isNaN(d)) return ts;
  d.setSeconds(d.getSeconds() - 1);
  const p = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

function renderTimeline() {
  const list = $("tl-list");
  const sev = tlData.by_severity || {};
  const meta = $("tl-meta");
  const parts = ["critical", "high", "medium", "low"].filter((s) => sev[s])
    .map((s) => `<span class="sevtag ${s}">${sev[s]} ${s}</span>`);
  meta.innerHTML = (tlData.count != null ? `${tlData.count} events · ` : "") + parts.join(" ") +
    (tlData.mtime ? ` · scanned ${tlData.mtime.replace("T", " ")}` : "") +
    ` · <a href="#" id="tl-toggle">${tlShowAll ? "show destructive only" : "show all"}</a>`;
  const tg = $("tl-toggle"); if (tg) tg.onclick = (e) => { e.preventDefault(); tlShowAll = !tlShowAll; renderTimeline(); };

  let evs = (tlData.events || []).slice();
  if (!tlShowAll) evs = evs.filter((e) => e.severity === "critical" || e.severity === "high");
  evs.sort((a, b) => (SEV_RANK[a.severity] - SEV_RANK[b.severity]) || (b.rows || 0) - (a.rows || 0) || (b.ts || "").localeCompare(a.ts || ""));
  evs = evs.slice(0, 200);

  if (!evs.length) {
    list.innerHTML = '<div class="muted small">' +
      (tlData.count ? "no destructive events in window — only routine churn. (show all to see it)" : "no scan yet.") + '</div>';
    return;
  }
  list.innerHTML = "";
  for (const e of evs) {
    const row = document.createElement("div");
    row.className = "tl-row " + e.severity;
    const detail = e.sql ? e.sql : (e.rows != null ? e.rows + " rows" : "");
    row.innerHTML =
      `<span class="tl-sev ${e.severity}">${e.severity}</span>` +
      `<span class="tl-ts mono">${e.ts || "?"}</span>` +
      `<span class="tl-type">${e.type}</span>` +
      `<span class="tl-table mono">${e.table || ""}</span>` +
      `<span class="tl-detail muted">${detail}</span>` +
      `<button class="link tl-use" title="set Restore until to just before this">↤ until</button>`;
    row.querySelector(".tl-use").onclick = (ev) => {
      ev.stopPropagation();
      const u = untilMinus1s(e.ts);
      for (const id of ["r-until", "dr-until"]) {   // arm both lab-restore and DR
        const el = $(id); if (!el) continue;
        el.value = u;
        el.classList.add("flash");
        setTimeout(() => el.classList.remove("flash"), 1200);
      }
      $("r-until").scrollIntoView({ behavior: "smooth", block: "center" });
    };
    list.appendChild(row);
  }
}

async function loadTimeline() {
  try { tlData = await (await fetch("/api/timeline")).json(); renderTimeline(); } catch (e) {}
}

function scanTimeline() {
  $("btn-scan").textContent = "scanning…";
  post("timeline", {}).then(() => {});   // post() streams the job; we reload on its finish below
}

$("btn-scan").onclick = scanTimeline;

// apply & disaster-recovery controls (prod writes — password-gated)
$("btn-apply-dry").onclick = () => {
  const f = $("ap-file").value, pw = $("ap-pw").value;
  if (!f) { $("log").textContent = "no export selected"; return; }
  if (!pw) { $("log").textContent = "enter the prod DB password"; return; }
  post("prod-apply", { file: f, password: pw, commit: false });
};
$("btn-apply").onclick = () => {
  const f = $("ap-file").value, pw = $("ap-pw").value;
  if (!f || !pw) { $("log").textContent = "need export + prod DB password"; return; }
  if (!confirm("Apply " + f + " to PROD (committed)? Backup-first + dry-run run automatically.")) return;
  post("prod-apply", { file: f, password: pw, commit: true });
  $("ap-pw").value = "";
};
$("btn-kit").onclick = () => {
  const f = $("kit-file").value;
  if (!f) { $("log").textContent = "no export selected"; return; }
  post("build-apply-kit", { files: [f] });
};
$("btn-dr").onclick = () => {
  const dump = $("dr-dump").value, until = $("dr-until").value.trim();
  const pw = $("dr-pw").value, confirmTok = $("dr-confirm").value;
  if (!pw) { $("log").textContent = "enter the prod DB password"; return; }
  if (confirmTok !== "RESTORE PROD") { $("log").textContent = "type RESTORE PROD to confirm"; return; }
  if (!confirm("DESTRUCTIVE: DROP + recreate PROD schema from " + dump + " up to " + until + ". Backup-first runs. Continue?")) return;
  post("prod-restore", { dump, until, password: pw, confirm: confirmTok });
  $("dr-pw").value = ""; $("dr-confirm").value = "";
};

// maintenance: show password field only for analyze/optimize (writes prod)
$("mt-op").onchange = () => {
  $("mt-pw-wrap").style.display = $("mt-op").value === "check" ? "none" : "flex";
};
$("btn-maint").onclick = () => {
  const op = $("mt-op").value;
  const tables = $("mt-tables").value.trim().split(/\s+/).filter(Boolean);
  if (!tables.length) { $("log").textContent = "enter at least one table"; return; }
  const body = { op, tables };
  if (op !== "check") {
    const pw = $("mt-pw").value;
    if (!pw) { $("log").textContent = "enter the prod DB password for " + op; return; }
    body.password = pw;
    if (op === "optimize" && !confirm("OPTIMIZE rebuilds " + tables.join(", ") + " on PROD (can lock under live traffic). Backup-first runs. Continue?")) return;
  }
  post("maint", body);
  $("mt-pw").value = "";
};

// backup lifecycle
$("btn-snapshot").onclick = () => { $("btn-snapshot").textContent = "snapshotting…"; post("snapshot", {}); };
$("btn-verify").onclick = () => { $("btn-verify").textContent = "verifying…"; post("verify-backups", {}); };

$("btn-refresh").onclick = refreshBackups;
$("btn-refresh-exports").onclick = refreshExports;

// per-panel info flashcards: click the (i) to toggle, click elsewhere/Esc to close
document.addEventListener("click", (e) => {
  const btn = e.target.closest(".infobtn");
  const panel = btn ? btn.closest(".panel") : null;
  document.querySelectorAll(".panel.show-info").forEach((p) => { if (p !== panel) p.classList.remove("show-info"); });
  if (panel) { e.stopPropagation(); panel.classList.toggle("show-info"); }
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") document.querySelectorAll(".panel.show-info").forEach((p) => p.classList.remove("show-info"));
});

// prefill diff tables with the bench defaults
fetch("/api/tables").then((r) => r.json()).then((d) => {
  $("d-tables").value = (d.default_tables || []).join(" ");
}).catch(() => {});

refreshStats().then(refreshStatus);
refreshBackups(); refreshExports(); refreshTopology(); loadTimeline(); loadExactCounts(); refreshStorageHealth();
setInterval(refreshStatus, 3000);
setInterval(() => refreshStats().then(refreshStatus), 5000);
setInterval(refreshBackups, 30000);
setInterval(refreshTopology, 8000);   // near-realtime topology (cheap info_schema)
setInterval(refreshStorageHealth, 20000);
