/* ===========================================================
   NOBS · Lateral FX engine  (integrated build)
   Draws an animated effect ONLY in the empty laterals beside
   the centered <main>. One fixed full-viewport canvas, clipped
   to the left + right strips.

   Source: design_handoff_lateral_fx/fx-engine.js (effects verbatim).
   Integration changes vs handoff demo:
     1. canvas id  #fx   -> #nobs-fx
     2. cfg source localStorage/dock -> canvas data-* attrs (blade-fed)
     3. geom() target #site -> main  (UNIT3D centered content column)
   Removed (demo-only): chooser dock wiring, scroll persistence,
   #site-img re-measure, setActive.
   =========================================================== */
(function () {
  const cv = document.getElementById('nobs-fx');
  if (!cv) return;
  let ctx = cv.getContext('2d');       // paintLayer() lo cambia un momento por el de una capa fija
  const root = document.documentElement;

  // --- config from canvas data-attrs (blade feeds user preference) ---
  const ds = cv.dataset;
  const cfg = {
    fx: ds.fx || 'rain',                  // 'rain' | 'circuit' | 'racks' | 'rising' | 'off'
    speed: +(ds.speed || 1),
    density: +(ds.density || 1),
    hue: +(ds.hue || 322),
  };
  if (cfg.fx === 'off') return;

  // scope guard: only worth it when laterals are wide enough
  const contentEl = document.querySelector('main');
  if (!contentEl) return;

  let DPR = 1, vw = 0, vh = 0;
  let L = null, R = null;        // strip rects {x,w}
  let stripW = 0;
  let mono = 'monospace';        // --mono, read once per geom() instead of every frame

  function geom() {
    DPR = Math.min(window.devicePixelRatio || 1, 2);
    mono = getCss('--mono');
    sprites.clear();
    vw = window.innerWidth; vh = window.innerHeight;
    cv.width = vw * DPR; cv.height = vh * DPR;
    cv.style.width = vw + 'px'; cv.style.height = vh + 'px';
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);

    const r = contentEl.getBoundingClientRect();
    const left = Math.max(0, r.left);
    const right = Math.min(vw, r.right);
    L = { x: 0, w: Math.max(0, left) };
    R = { x: right, w: Math.max(0, vw - right) };
    stripW = Math.max(L.w, R.w);
    if (effects[cfg.fx]) effects[cfg.fx].init();
    start();
  }

  function clipStrips() {
    const p = new Path2D();
    p.rect(L.x, 0, L.w, vh);
    p.rect(R.x, 0, R.w, vh);
    ctx.clip(p);
  }
  // run a draw fn once per strip, with x-origin & width handed in
  function eachStrip(fn) {
    if (L.w > 4) fn(L.x, L.w, 'L');
    if (R.w > 4) fn(R.x, R.w, 'R');
  }

  // color helpers (track hue live)
  const H = () => cfg.hue;
  const neon  = (a = 1, l = 62) => `hsl(${H()} 95% ${l}% / ${a})`;
  const fade  = (a) => `hsla(${H() - 4}, 40%, 4%, ${a})`;

  /* Glow sprites. shadowBlur on every particle, every frame, is the most expensive thing a
     2D canvas does (one Gaussian blur per draw call). Each glowing shape is painted ONCE,
     with the very same shadowBlur, into a small canvas and then stamped with drawImage;
     globalAlpha carries the per-particle fade (it scales fill and shadow alike, as the
     old `neon(a)` colours did). shadowBlur ignores transforms, so the sprite keeps the
     same device-pixel halo as drawing straight on the main canvas. */
  const sprites = new Map();
  function sprite(key, w, h, ox, oy, paint) {
    let s = sprites.get(key);
    if (!s) {
      const c = document.createElement('canvas');
      c.width = Math.ceil(w * DPR); c.height = Math.ceil(h * DPR);
      const g = c.getContext('2d');
      g.setTransform(DPR, 0, 0, DPR, 0, 0);
      paint(g);
      s = { c, w, h, ox, oy };
      sprites.set(key, s);
    }
    return s;
  }
  const haloPad = (b) => Math.ceil((b * 1.5) / DPR) + 2;   // blur reach (3σ, σ = b/2) in CSS px
  function dotSprite(r, b, fill, shadow) {
    const half = Math.ceil(r + haloPad(b)), size = half * 2;
    return sprite(`d|${r}|${b}|${fill}|${shadow}`, size, size, half, half, (g) => {
      g.shadowColor = shadow; g.shadowBlur = b; g.fillStyle = fill;
      g.beginPath(); g.arc(half, half, r, 0, 7); g.fill();
    });
  }
  // baseline 'top' or 'middle', drawn from (x, y) like fillText with textAlign 'start'
  function textSprite(ch, px, fill, shadow, b, baseline) {
    const key = `t|${ch}|${px}|${fill}|${shadow}|${b}|${baseline}`;
    const hit = sprites.get(key);
    if (hit) return hit;                      // measuring/setting fonts per particle is not cheap
    const pad = haloPad(b), h = Math.ceil(px * 1.3) + pad * 2;
    ctx.font = `${px}px ${mono}`;
    const w = Math.ceil(ctx.measureText(ch).width) + pad * 2;
    const oy = baseline === 'top' ? pad : h / 2;
    return sprite(key, w, h, pad, oy, (g) => {
      g.font = `${px}px ${mono}`; g.textBaseline = baseline;
      g.shadowColor = shadow; g.shadowBlur = b; g.fillStyle = fill;
      g.fillText(ch, pad, oy);
    });
  }
  function stamp(s, x, y, a = 1) {
    ctx.globalAlpha = a;
    ctx.drawImage(s.c, x - s.ox, y - s.oy, s.w, s.h);
    ctx.globalAlpha = 1;
  }

  /* Capas fijas. Lo que no se mueve (fondo, chasis, pistas, componentes) se pinta UNA vez por
     geometría en un canvas aparte y cada fotograma se copia con drawImage; solo se redibuja lo que
     se mueve. Los helpers dibujan sobre `ctx`, así que se cambia por el de la capa mientras se pinta. */
  function paintLayer(paint) {
    const c = document.createElement('canvas');
    c.width = Math.ceil(vw * DPR); c.height = Math.ceil(vh * DPR);
    const g = c.getContext('2d');
    g.setTransform(DPR, 0, 0, DPR, 0, 0);
    const main = ctx; ctx = g;
    try { paint(); } finally { ctx = main; }
    return c;
  }

  /* ---------------------------------------------------------- */
  /* EFFECT 1 — BIT RAIN                                         */
  /* ---------------------------------------------------------- */
  const rain = (() => {
    let cols = [], fontSize = 16;
    function init() {
      fontSize = 15;
      cols = [];
      const colGap = fontSize / cfg.density;   // tighter columns as density rises
      eachStrip((x, w) => {
        const n = Math.floor(w / colGap);
        const arr = [];
        for (let i = 0; i < n; i++) {
          arr.push({ x: x + i * colGap + 3, y: Math.random() * -vh, sp: 0.5 + Math.random() * 0.9 });
        }
        cols.push(arr);
      });
    }
    function draw(dt) {
      ctx.save(); clipStrips();
      ctx.fillStyle = fade(0.16); ctx.fillRect(0, 0, vw, vh);
      const lead = [textSprite('0', fontSize, '#fff', neon(1), 8, 'top'),
                    textSprite('1', fontSize, '#fff', neon(1), 8, 'top')];
      ctx.font = `${fontSize}px ${mono}`;
      ctx.textBaseline = 'top';
      ctx.fillStyle = neon(0.5, 58);
      const step = fontSize * cfg.speed * dt * 0.06 * 60;
      for (const arr of cols) {
        for (const d of arr) {
          if (Math.random() < 0.012 / cfg.density) continue; // gaps
          stamp(lead[Math.random() < 0.5 ? 0 : 1], d.x, d.y);   // bright leader
          ctx.fillText(Math.random() < 0.5 ? '0' : '1', d.x, d.y - fontSize); // dim follower
          d.y += d.sp * step;
          if (d.y > vh + Math.random() * 240) { d.y = -fontSize * (2 + Math.random() * 8); d.sp = 0.5 + Math.random() * 0.9; }
        }
      }
      ctx.restore();
    }
    return { init, draw };
  })();

  /* ---------------------------------------------------------- */
  /* EFFECT 2 — CIRCUIT PULSE                                    */
  /* ---------------------------------------------------------- */
  const circuit = (() => {
    let traces = [], comps = [], branches = [];
    // Seed evenly per lane (no black gaps), but route across the WHOLE strip
    // so traces cross each other like real PCB tracks — mixing 90° jogs and
    // 45° diagonals instead of a parallel comb.
    function buildTrace(laneX, laneW, x0, w) {
      const pts = [];
      const lo = x0 + 6, hi = x0 + w - 6;           // clamp to full strip, not lane
      const clamp = (v) => Math.max(lo, Math.min(hi, v));
      let x = laneX + laneW * (0.3 + Math.random() * 0.4); // seed mid-lane
      let y = -20;
      pts.push({ x, y });
      while (y < vh + 20) {
        const r = Math.random();
        if (r < 0.4) {
          // 45° diagonal segment (equal dx/dy) — classic PCB routing angle
          const dir = Math.random() < 0.5 ? -1 : 1;
          const run = 18 + Math.random() * (w * 0.5);
          const nx = clamp(x + dir * run);
          const ny = y + Math.abs(nx - x);
          x = nx; y = ny;
          pts.push({ x, y });
        } else {
          // vertical run, then an optional right-angle jog that can cross lanes
          y += 30 + Math.random() * 80;
          pts.push({ x, y });
          if (Math.random() < 0.55) {
            const dir = Math.random() < 0.5 ? -1 : 1;
            x = clamp(x + dir * (20 + Math.random() * (w * 0.55)));
            pts.push({ x, y });
          }
        }
      }
      // cumulative length for pulse travel
      let len = 0; const seg = [];
      for (let i = 1; i < pts.length; i++) {
        const d = Math.hypot(pts[i].x - pts[i - 1].x, pts[i].y - pts[i - 1].y);
        seg.push(len); len += d;
      }
      seg.push(len);
      return { pts, seg, len, pulses: [] };
    }
    let base = null;   // capa fija: pistas, nodos, ramales y componentes
    function init() {
      base = null;
      traces = []; comps = []; branches = [];
      let uid = 1, rid = 1, cid = 1;
      eachStrip((x, w) => {
        const lo = x + 8, hi = x + w - 8;
        const clamp = (v) => Math.max(lo, Math.min(hi, v));
        const lanes = Math.max(2, Math.round((w / 46) * cfg.density));
        const laneW = w / lanes;
        const built = [];
        for (let i = 0; i < lanes; i++) {
          const t = buildTrace(x + i * laneW, laneW, x, w);
          const np = 1 + Math.floor(Math.random() * 2);
          for (let k = 0; k < np; k++) t.pulses.push({ d: Math.random() * t.len, sp: 40 + Math.random() * 70 });
          traces.push(t); built.push(t);
        }

        // Branches: short right-angle stubs off a trace vertex, ending in a via
        // (T-junctions — what a comb of parallel traces was missing).
        const nb = Math.round(lanes * 0.9);
        for (let i = 0; i < nb; i++) {
          const t = built[Math.floor(Math.random() * built.length)];
          if (t.pts.length < 4) continue;
          const p = t.pts[1 + Math.floor(Math.random() * (t.pts.length - 2))];
          const dir = Math.random() < 0.5 ? -1 : 1;
          const bx = clamp(p.x + dir * (14 + Math.random() * (w * 0.4)));
          branches.push({ x0: p.x, y0: p.y, x1: bx, y1: p.y });
        }

        // ICs: sparse SOIC/QFP-ish bodies with pin combs on two sides.
        const nic = Math.max(1, Math.round(0.9 * cfg.density));
        for (let i = 0; i < nic; i++) {
          const pins = 3 + Math.floor(Math.random() * 3);   // per side
          const bw = 16 + Math.random() * 10;
          const bh = pins * 7 + 6;
          const cx = clamp(x + 12 + Math.random() * (w - 24 - bw));
          const cy = 50 + Math.random() * Math.max(40, vh - 140);
          comps.push({ type: 'ic', x: cx, y: cy, w: bw, h: bh, pins, label: 'U' + (uid++) });
        }

        // Passives: two-pad 0805-ish bodies straddling a vertical trace segment.
        const npas = Math.max(2, Math.round(2.5 * cfg.density));
        for (let i = 0; i < npas; i++) {
          const t = built[Math.floor(Math.random() * built.length)];
          const idx = 1 + Math.floor(Math.random() * Math.max(1, t.pts.length - 2));
          const p = t.pts[idx];
          const isR = Math.random() < 0.6;
          comps.push({
            type: 'passive', x: p.x, y: p.y, isR,
            label: (isR ? 'R' + (rid++) : 'C' + (cid++)),
            val: isR ? (['1k', '4k7', '10k', '100R', '47k'][Math.floor(Math.random() * 5)])
                     : (['100n', '1u', '22p', '10u', '4n7'][Math.floor(Math.random() * 5)]),
          });
        }

        // Loose vias scattered on trace vertices.
        const nv = Math.round(lanes * 1.6);
        for (let i = 0; i < nv; i++) {
          const t = built[Math.floor(Math.random() * built.length)];
          const p = t.pts[1 + Math.floor(Math.random() * Math.max(1, t.pts.length - 2))];
          comps.push({ type: 'via', x: p.x, y: p.y });
        }
      });
    }
    function posAt(t, dist) {
      const { pts, seg } = t;
      let i = 1;
      while (i < seg.length && seg[i] < dist) i++;
      if (i >= pts.length) return pts[pts.length - 1];
      const a = pts[i - 1], b = pts[i];
      const f = (dist - seg[i - 1]) / Math.max(0.001, seg[i] - seg[i - 1]);
      return { x: a.x + (b.x - a.x) * f, y: a.y + (b.y - a.y) * f };
    }
    function via(x, y, r = 3) {
      ctx.beginPath(); ctx.arc(x, y, r, 0, 7);
      ctx.fillStyle = neon(0.35, 55); ctx.fill();         // annular ring
      ctx.beginPath(); ctx.arc(x, y, r * 0.45, 0, 7);
      ctx.fillStyle = fade(1); ctx.fill();                // drilled hole
    }
    function label(x, y, txt, a = 0.5) {
      ctx.font = `9px ${mono}`;
      ctx.textBaseline = 'middle';
      ctx.fillStyle = neon(a, 66);
      ctx.fillText(txt, x, y);
    }
    function drawIC(c) {
      const pinL = 5, pitch = (c.h - 6) / (c.pins - 1 || 1);
      // legs
      ctx.strokeStyle = neon(0.3, 58); ctx.lineWidth = 1.4;
      for (let i = 0; i < c.pins; i++) {
        const py = c.y + 3 + i * pitch;
        ctx.beginPath(); ctx.moveTo(c.x - pinL, py); ctx.lineTo(c.x, py); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(c.x + c.w, py); ctx.lineTo(c.x + c.w + pinL, py); ctx.stroke();
      }
      // body + pin-1 notch
      ctx.fillStyle = fade(1); ctx.fillRect(c.x, c.y, c.w, c.h);
      ctx.strokeStyle = neon(0.4, 60); ctx.lineWidth = 1.2;
      ctx.strokeRect(c.x, c.y, c.w, c.h);
      ctx.beginPath(); ctx.arc(c.x + 4, c.y + 4, 1.4, 0, 7);
      ctx.fillStyle = neon(0.45, 62); ctx.fill();
      label(c.x + c.w + pinL + 3, c.y + c.h / 2, c.label, 0.55);
    }
    function drawPassive(c) {
      const isR = c.isR, bw = 7, bh = 12, x = c.x - bw / 2, y = c.y - bh / 2;
      // pads top/bottom on the trace
      ctx.fillStyle = neon(0.34, 56);
      ctx.fillRect(x, y - 3, bw, 3); ctx.fillRect(x, y + bh, bw, 3);
      // body
      ctx.fillStyle = fade(1); ctx.strokeStyle = neon(0.42, 60); ctx.lineWidth = 1.2;
      ctx.fillRect(x, y, bw, bh); ctx.strokeRect(x, y, bw, bh);
      if (!isR) { // cap: second plate line
        ctx.beginPath(); ctx.moveTo(x, y + bh * 0.5); ctx.lineTo(x + bw, y + bh * 0.5); ctx.stroke();
      }
      label(c.x + 6, c.y, c.label + ' ' + c.val, 0.5);
    }

    function paintBase() {
      ctx.fillStyle = fade(1); ctx.fillRect(0, 0, vw, vh); // full clear, crisp
      // base traces
      ctx.lineWidth = 1.1; ctx.strokeStyle = neon(0.16, 50);
      for (const t of traces) {
        ctx.beginPath();
        ctx.moveTo(t.pts[0].x, t.pts[0].y);
        for (let i = 1; i < t.pts.length; i++) ctx.lineTo(t.pts[i].x, t.pts[i].y);
        ctx.stroke();
        // nodes
        ctx.fillStyle = neon(0.28, 55);
        for (const p of t.pts) { ctx.beginPath(); ctx.arc(p.x, p.y, 1.6, 0, 7); ctx.fill(); }
      }
      // branch stubs (T-junctions) ending in a via
      ctx.lineWidth = 1.1; ctx.strokeStyle = neon(0.16, 50);
      for (const b of branches) {
        ctx.beginPath(); ctx.moveTo(b.x0, b.y0); ctx.lineTo(b.x1, b.y1); ctx.stroke();
        via(b.x1, b.y1, 2.4);
      }
      // components (ICs / passives / vias) + silkscreen labels
      for (const c of comps) {
        if (c.type === 'via') via(c.x, c.y, 3);
        else if (c.type === 'ic') drawIC(c);
        else drawPassive(c);
      }
    }
    function draw(dt) {
      ctx.save(); clipStrips();
      if (!base) base = paintLayer(paintBase);
      ctx.drawImage(base, 0, 0, vw, vh);
      // pulses: white head + 4 fading neon dots, all with a 12 px halo
      const head = dotSprite(2.6, 12, '#fff', neon(1));
      const tail = dotSprite(2.0, 12, neon(0.8, 62), neon(1));
      for (const t of traces) {
        for (const pu of t.pulses) {
          pu.d += pu.sp * cfg.speed * dt;
          if (pu.d > t.len + 40) pu.d = -Math.random() * 60;
          for (let k = 0; k < 5; k++) {
            const p = posAt(t, pu.d - k * 7);
            stamp(k === 0 ? head : tail, p.x, p.y, 1 - k / 5);
          }
        }
      }
      ctx.restore();
    }
    return { init, draw };
  })();

  /* ---------------------------------------------------------- */
  /* EFFECT 3 — SERVER RACKS (blinking LEDs)                     */
  /* ---------------------------------------------------------- */
  const racks = (() => {
    let units = [];  // each: {x,y,w,h, leds:[{x,y,state,t,col}]}
    let base = null; // capa fija: chasis, rejillas y todos los LED apagados
    function init() {
      base = null;
      units = [];
      const uw = 64, gap = 14, uh = 30;
      eachStrip((x, w) => {
        const cols = Math.max(1, Math.floor((w - 8) / (uw + gap)));
        const pad = (w - cols * (uw + gap) + gap) / 2;
        for (let c = 0; c < cols; c++) {
          const ux = x + pad + c * (uw + gap);
          let uy = 8 + ((c % 3) * 10);
          while (uy < vh) {
            const leds = [];
            const nled = 5 + Math.floor(Math.random() * 4);
            for (let i = 0; i < nled; i++) {
              leds.push({ ox: 7 + i * 6.6, on: Math.random() < 0.5, t: Math.random() * 2, sp: 0.4 + Math.random() * 2.2,
                kind: Math.random() < 0.78 ? 'n' : (Math.random() < 0.6 ? 'c' : 'a') });
            }
            units.push({ x: ux, y: uy, w: uw, h: uh, leds, lit: Math.random() < 0.7 });
            uy += uh + 6;
          }
        }
      });
    }
    function ledColor(kind, a) {
      if (kind === 'c') return `hsl(${(H()+150)%360} 90% 62% / ${a})`;  // cyan-ish complement
      if (kind === 'a') return `hsl(${(H()+90)%360} 90% 60% / ${a})`;   // amber-ish
      return neon(a);
    }
    function paintBase() {
      ctx.fillStyle = fade(1); ctx.fillRect(0, 0, vw, vh);
      for (const u of units) {
        // chassis
        ctx.fillStyle = 'hsl(' + H() + ' 18% 7% / .9)';
        ctx.strokeStyle = neon(0.22, 48); ctx.lineWidth = 1;
        roundRect(u.x, u.y, u.w, u.h, 3); ctx.fill(); ctx.stroke();
        // vent lines
        ctx.strokeStyle = 'hsl(' + H() + ' 20% 16% / .8)';
        for (let v = 0; v < 3; v++) { ctx.beginPath(); ctx.moveTo(u.x + 44, u.y + 7 + v * 6); ctx.lineTo(u.x + u.w - 6, u.y + 7 + v * 6); ctx.stroke(); }
        // leds, all off: the lit ones are stamped on top every frame
        ctx.fillStyle = 'hsl(' + H() + ' 30% 18% / .9)';
        for (const led of u.leds) { ctx.beginPath(); ctx.arc(u.x + led.ox, u.y + u.h / 2, 2.1, 0, 7); ctx.fill(); }
      }
    }
    function draw(dt) {
      ctx.save(); clipStrips();
      if (!base) base = paintLayer(paintBase);
      ctx.drawImage(base, 0, 0, vw, vh);
      for (const u of units) {
        for (const led of u.leds) {
          led.t += dt * led.sp * cfg.speed;
          if (led.t > 1) { led.t = 0; if (Math.random() < 0.5) led.on = !led.on; }
          if (!led.on) continue;
          const col = ledColor(led.kind, 1);
          stamp(dotSprite(2.1, 7, col, col), u.x + led.ox, u.y + u.h / 2);
        }
      }
      ctx.restore();
    }
    return { init, draw };
  })();

  /* ---------------------------------------------------------- */
  /* EFFECT 4 — RISING BITS                                      */
  /* ---------------------------------------------------------- */
  const rising = (() => {
    let parts = [];
    function spawn(x0, w) {
      const glyph = Math.random() < 0.45;
      return {
        x: x0 + 6 + Math.random() * (w - 12),
        y: vh + Math.random() * 60,
        sp: 14 + Math.random() * 46,
        drift: (Math.random() - 0.5) * 14,
        ph: Math.random() * 6.28,
        size: glyph ? (11 + Math.random() * 6) : (1.4 + Math.random() * 2.6),
        glyph, ch: Math.random() < 0.5 ? '0' : '1',
        life: 0, max: 3 + Math.random() * 4,
      };
    }
    let pool = [];
    function init() {
      parts = []; pool = [];
      eachStrip((x, w) => pool.push({ x, w, target: Math.round((w / 9) * cfg.density) }));
      for (const s of pool) for (let i = 0; i < s.target; i++) { const p = spawn(s.x, s.w); p.y = Math.random() * vh; parts.push(p); }
    }
    function draw(dt) {
      ctx.save(); clipStrips();
      ctx.fillStyle = fade(0.20); ctx.fillRect(0, 0, vw, vh);
      for (const p of parts) {
        p.life += dt;
        p.y -= p.sp * cfg.speed * dt;
        p.x += Math.sin(p.ph + p.life * 1.4) * p.drift * dt;
        const a = Math.max(0, Math.min(1, Math.min(p.life / 0.6, (p.max - p.life) / 1.0)));
        if (a <= 0.01) continue;
        if (p.glyph) {
          stamp(textSprite(p.ch, 13, neon(0.85, 66), neon(1), 6, 'middle'), p.x, p.y, a);
        } else {
          // dot sizes rounded to 0.25 px so the sprite cache stays small (~22 sprites)
          const r = Math.round(p.size * 4) / 4;
          stamp(dotSprite(r, 9, a > 0.7 ? '#fff' : neon(1, 70), neon(1)), p.x, p.y, a);
        }
      }
      // recycle
      for (let i = 0; i < parts.length; i++) {
        if (parts[i].y < -20 || parts[i].life > parts[i].max) {
          const strip = stripOf(parts[i].x);
          parts[i] = spawn(strip.x, strip.w);
        }
      }
      ctx.restore();
    }
    function stripOf(x) {
      if (x < L.x + L.w + 1 && L.w > 4) return L;
      return R.w > 4 ? R : L;
    }
    return { init, draw };
  })();

  const effects = { rain, circuit, racks, rising };

  /* ---------------- helpers ---------------- */
  function getCss(v) { return getComputedStyle(root).getPropertyValue(v).trim() || 'monospace'; }
  function roundRect(x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }

  /* ---------------- loop ---------------- */
  // No strips (phone, narrow window): clear once and stop the loop instead of
  // clearing a full-viewport canvas 60 times a second for nothing. geom()
  // restarts it when a resize makes room for the strips again.
  // 30 fps cap: half the work of a 60 Hz screen (a quarter on 120/144 Hz) and the
  // particles don't show it. The -4 ms slack keeps 60 Hz screens at a steady 30.
  // Hidden tabs and minimised windows need nothing extra: browsers stop
  // requestAnimationFrame there, and dt is clamped when it comes back.
  const MIN_FRAME_MS = 1000 / 30 - 4;
  let last = performance.now();
  let running = false;
  const hasStrips = () => L.w > 4 || R.w > 4;
  function frame(now) {
    if (now - last < MIN_FRAME_MS) { requestAnimationFrame(frame); return; }
    let dt = (now - last) / 1000; last = now;
    if (dt > 0.05) dt = 0.05;          // clamp after tab switch
    const fx = effects[cfg.fx];
    if (!fx || !hasStrips()) {
      ctx.clearRect(0, 0, vw, vh);
      running = false;
      return;
    }
    fx.draw(dt);
    requestAnimationFrame(frame);
  }
  function start() {
    if (running || reduce || !hasStrips()) return;
    running = true;
    last = performance.now();
    requestAnimationFrame(frame);
  }

  /* ---------------- boot ---------------- */
  // prefers-reduced-motion: render a single static frame, no loop
  const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  geom();
  let rt;
  window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(geom, 150); });
  window.addEventListener('load', geom);  // re-measure once layout settles
  if (reduce) {
    if (effects[cfg.fx]) effects[cfg.fx].draw(0.016);
  }
  // Otherwise geom() above already called start().
})();
