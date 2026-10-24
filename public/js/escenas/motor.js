/* NOBS · motor de escenas pixel art.
 * Lienzo de 320x180 ampliado sin suavizar, fuente de píxel propia y personajes por paletas.
 * Sin dependencias. Cada escena vive en su fichero y se registra con Escenas.registrar().
 * Los datos de cada escena llegan por montar(canvas, id, { datos }); sin datos, usa los de ejemplo.
 */
(function () {
  'use strict';
  const W = 320, H = 180;
  const mk = (w, h) => {
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    const x = c.getContext('2d');
    x.imageSmoothingEnabled = false;
    return [c, x];
  };
  function rng(seed) {
    return () => {
      seed = seed + 0x6D2B79F5 | 0;
      let t = Math.imul(seed ^ seed >>> 15, 1 | seed);
      t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    };
  }
  function R(c, x, y, w, h, col) {
    if (col) c.fillStyle = col;
    c.fillRect(Math.round(x), Math.round(y), w, h);
  }
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const seg = (t, a, b) => clamp((t - a) / (b - a), 0, 1);

  // fuente de píxel 3x5 (M, N, W más anchas; con tilde = 6 filas)
  const SM = {
    'A': ['.#.', '#.#', '###', '#.#', '#.#'], 'B': ['##.', '#.#', '##.', '#.#', '##.'],
    'C': ['.##', '#..', '#..', '#..', '.##'], 'D': ['##.', '#.#', '#.#', '#.#', '##.'],
    'E': ['###', '#..', '##.', '#..', '###'], 'F': ['###', '#..', '##.', '#..', '#..'],
    'G': ['.##', '#..', '#.#', '#.#', '.##'], 'H': ['#.#', '#.#', '###', '#.#', '#.#'],
    'I': ['###', '.#.', '.#.', '.#.', '###'], 'J': ['..#', '..#', '..#', '#.#', '.#.'],
    'K': ['#.#', '#.#', '##.', '#.#', '#.#'], 'L': ['#..', '#..', '#..', '#..', '###'],
    'M': ['#...#', '##.##', '#.#.#', '#...#', '#...#'], 'N': ['#..#', '##.#', '#.##', '#..#', '#..#'],
    'Ñ': ['.##.', '#..#', '##.#', '#.##', '#..#', '#..#'],
    'O': ['###', '#.#', '#.#', '#.#', '###'], 'P': ['##.', '#.#', '##.', '#..', '#..'],
    'Q': ['###', '#.#', '#.#', '###', '..#'], 'R': ['##.', '#.#', '##.', '#.#', '#.#'],
    'S': ['.##', '#..', '.#.', '..#', '##.'], 'T': ['###', '.#.', '.#.', '.#.', '.#.'],
    'U': ['#.#', '#.#', '#.#', '#.#', '###'], 'V': ['#.#', '#.#', '#.#', '#.#', '.#.'],
    'W': ['#...#', '#...#', '#.#.#', '##.##', '#...#'], 'X': ['#.#', '#.#', '.#.', '#.#', '#.#'],
    'Y': ['#.#', '#.#', '.#.', '.#.', '.#.'], 'Z': ['###', '..#', '.#.', '#..', '###'],
    'Á': ['..#', '.#.', '#.#', '###', '#.#', '#.#'], 'É': ['..#', '###', '#..', '##.', '#..', '###'],
    'Í': ['..#', '###', '.#.', '.#.', '.#.', '###'], 'Ó': ['..#', '###', '#.#', '#.#', '#.#', '###'],
    'Ú': ['..#', '#.#', '#.#', '#.#', '#.#', '###'],
    '0': ['###', '#.#', '#.#', '#.#', '###'], '1': ['.#.', '##.', '.#.', '.#.', '###'],
    '2': ['##.', '..#', '.#.', '#..', '###'], '3': ['##.', '..#', '.#.', '..#', '##.'],
    '4': ['#.#', '#.#', '###', '..#', '..#'], '5': ['###', '#..', '##.', '..#', '##.'],
    '6': ['.##', '#..', '###', '#.#', '###'], '7': ['###', '..#', '.#.', '.#.', '.#.'],
    '8': ['###', '#.#', '###', '#.#', '###'], '9': ['###', '#.#', '###', '..#', '##.'],
    '!': ['#', '#', '#', '.', '#'], '¡': ['#', '.', '#', '#', '#'],
    '?': ['##.', '..#', '.#.', '...', '.#.'], '¿': ['.#.', '...', '.#.', '#..', '.##'],
    '+': ['...', '.#.', '###', '.#.', '...'], '&': ['.#.', '#.#', '.#.', '#.#', '.##'], '_': ['...', '...', '...', '...', '###'], '-': ['...', '...', '###', '...', '...'],
    '.': ['.', '.', '.', '.', '#'], ',': ['.', '.', '.', '#', '#'], ':': ['.', '#', '.', '#', '.'],
    ' ': ['..', '..', '..', '..', '..']
  };
  const glyph = ch => SM[ch] || SM[' '];
  function tw(s, k = 1) {
    let w = 0;
    for (const ch of s) w += glyph(ch)[0].length + 1;
    return Math.max(0, w - 1) * k;
  }
  function txt(c, s, x, y, col, k = 1) {
    c.fillStyle = col;
    let cx = x;
    for (const ch of s) {
      const g = glyph(ch), oy = g.length > 5 ? -1 : 0;
      for (let r = 0; r < g.length; r++)
        for (let i = 0; i < g[r].length; i++)
          if (g[r][i] === '#') c.fillRect(cx + i * k, y + (r + oy) * k, k, k);
      cx += (g[0].length + 1) * k;
    }
  }
  function spriteText(s, col, k = 1) {
    const [c, x] = mk(tw(s, k), 6 * k + 1);
    txt(x, s, 0, k, col, k);
    return c;
  }
  function glowImg(ctx, img, x, y, col, blur, a = 1) {
    ctx.save();
    ctx.globalAlpha = a; ctx.shadowColor = col; ctx.shadowBlur = blur;
    ctx.drawImage(img, Math.round(x), Math.round(y));
    ctx.restore();
  }
  function glowRects(ctx, col, blur, rects, a = 1) {
    ctx.save();
    ctx.globalAlpha = a; ctx.shadowColor = col; ctx.shadowBlur = blur; ctx.fillStyle = col;
    rects.forEach(r => ctx.fillRect(r[0], r[1], r[2], r[3]));
    ctx.restore();
  }
  function flick(t, s) {
    const v = Math.sin(t * 11.3 + s) + Math.sin(t * 4.7 + s * 2.1) + Math.sin(t * 23.1 + s * 0.7);
    return v > 2.2 ? 0.25 : 1;
  }

  // personaje de perfil mirando a la derecha; (x, y) = esquina izquierda, fila bajo los pies
  const STR = [0, 1, 2, 1, 0, -1, -2, -1];
  function leg(c, hx, y, s, col, boot) {
    const hip = y - 9;
    R(c, hx + Math.round(s / 2), hip, 2, 4, col);
    R(c, hx + s, hip + 4, 2, 4, col);
    R(c, hx + s, y - 2, 2, 1, boot);
    R(c, hx + s, y - 1, 3, 1, boot);
  }
  function sitLeg(c, x, y, col, boot, dx) {
    R(c, x + 2 + dx, y - 9, 6, 2, col);
    R(c, x + 6 + dx, y - 7, 2, 5, col);
    R(c, x + 6 + dx, y - 2, 3, 1, boot);
  }
  function person(c, x, y, st, walking, mode, P, sit) {
    const s = walking ? STR[st] : 0;
    const u = y + (walking && (st === 0 || st === 4) ? -1 : 0);
    const aS = walking && mode === 'down' ? -Math.round(s / 2) : 0;
    const sleeve = P.sleeve || P.shirt;
    if (sit) { sitLeg(c, x, y, P.legB, P.boot, -1); sitLeg(c, x, y, P.leg, P.boot, 0); }
    else { leg(c, x + 2, y, -s, P.legB, P.boot); leg(c, x + 3, y, s, P.leg, P.boot); }
    if (mode === 'tray') R(c, x + 4, u - 13, 3, 2, P.sleeveB);
    else if (mode === 'hang') { R(c, x + 1, u - 28, 2, 12, P.sleeveB); R(c, x + 1, u - 29, 2, 1, P.skinD); }
    else { R(c, x + 2 - aS, u - 15, 2, 5, P.sleeveB); R(c, x + 2 - aS, u - 10, 2, 1, P.skinD); }
    R(c, x + 1, u - 16, 6, 7, P.shirt); R(c, x + 1, u - 16, 1, 7, P.shirtD);
    if (P.stripes) { R(c, x + 1, u - 14, 6, 1, P.stripes); R(c, x + 1, u - 12, 6, 1, P.stripes); }
    R(c, x + 5, u - 16, 1, 2, P.skin);
    if (P.sash) {
      R(c, x + 1, u - 10, 6, 2, P.sash); R(c, x + 1, u - 9, 6, 1, P.sashD);
      if (!sit) R(c, x - (walking && st % 4 < 2 ? 1 : 0), u - 9, 1, 3, P.sashD);
    }
    R(c, x + 3, u - 17, 2, 1, P.skinD);
    R(c, x + 2, u - 22, 5, 5, P.skin); R(c, x + 2, u - 18, 2, 1, P.skinD);
    R(c, x + 7, u - 20, 1, 1, P.skin);
    R(c, x + 5, u - 20, 1, 1, '#1a1420');
    if (P.style === 'pony') {
      R(c, x + 1, u - 23, 6, 2, P.hair); R(c, x + 1, u - 21, 2, 4, P.hair);
      R(c, x + 6, u - 22, 1, 2, P.hair); R(c, x + 1, u - 18, 2, 1, P.hairD);
      R(c, x + 3, u - 23, 2, 1, P.hairL);
    } else if (P.style === 'bun') {
      R(c, x + 1, u - 23, 6, 2, P.hair); R(c, x + 1, u - 21, 2, 3, P.hair);
      R(c, x - 1, u - 24, 3, 3, P.hair); R(c, x + 6, u - 22, 1, 1, P.hair);
    } else if (P.style === 'bandana') {
      R(c, x + 1, u - 23, 6, 2, P.hair); R(c, x + 1, u - 21, 1, 2, P.hair); R(c, x - 1, u - 22, 2, 2, P.hair);
    } else if (P.style === 'bald') {
      R(c, x + 2, u - 22, 2, 2, P.hair); R(c, x + 4, u - 22, 2, 1, P.skinL || P.skin);
    } else {
      R(c, x + 1, u - 23, 6, 2, P.hair); R(c, x + 1, u - 21, 2, 2, P.hair);
    }
    if (P.beard) R(c, x + 4, u - 19, 4, 2, P.beard);
    if (P.patch) { R(c, x + 2, u - 21, 4, 1, '#0a0806'); R(c, x + 5, u - 20, 1, 1, '#0a0806'); }
    if (P.mustache) R(c, x + 5, u - 19, 3, 1, P.mustache);
    if (P.badge) R(c, x + 4, u - 14, 1, 1, P.badge);
    if (mode === 'tray') {
      R(c, x + 4, u - 13, 4, 2, sleeve); R(c, x + 8, u - 13, 1, 2, P.skin);
    } else if (mode === 'wave') {
      R(c, x + 6, u - 21, 2, 6, sleeve); R(c, x + 6, u - 23, 2, 2, P.skin);
    } else if (mode === 'hang') {
      R(c, x + 6, u - 28, 2, 12, sleeve); R(c, x + 6, u - 29, 2, 1, P.skin);
    } else {
      R(c, x + 3 + aS, u - 15, 2, 5, sleeve); R(c, x + 3 + aS, u - 10, 2, 1, P.skin);
    }
    return u;
  }
  const [pc, pctx] = mk(24, 40);
  function drawPerson(ctx, wx, base, st, walking, mode, P, opt = {}) {
    pctx.clearRect(0, 0, 24, 40);
    person(pctx, 6, 39, st, walking, mode, P, opt.sit);
    ctx.save();
    ctx.globalAlpha = opt.alpha == null ? 1 : opt.alpha;
    if (opt.flip) {
      ctx.translate(Math.round(wx) + 9, 0);
      ctx.scale(-1, 1);
      ctx.drawImage(pc, -6, base - 39);
    } else {
      ctx.drawImage(pc, Math.round(wx) - 6, base - 39);
    }
    ctx.restore();
    return walking && (st === 0 || st === 4) ? -1 : 0;
  }
  function bubble(ctx, cx, tipY, full, n, minX = -1e9, maxX = 1e9) {
    const w = tw(full) + 6, h = 11;
    const x = Math.round(clamp(cx - w / 2, minX + 2, maxX - w - 2));
    const y = tipY - 2 - h;
    const ink = '#0b0a16', paper = '#f4f1ff';
    R(ctx, x, y - 1, w, 1, ink); R(ctx, x, y + h, w, 1, ink);
    R(ctx, x - 1, y, 1, h, ink); R(ctx, x + w, y, 1, h, ink);
    R(ctx, x, y, w, h, paper);
    R(ctx, cx - 2, y + h, 1, 1, ink); R(ctx, cx - 1, y + h, 3, 1, paper); R(ctx, cx + 2, y + h, 1, 1, ink);
    R(ctx, cx - 1, y + h + 1, 1, 1, ink); R(ctx, cx, y + h + 1, 1, 1, paper); R(ctx, cx + 1, y + h + 1, 1, 1, ink);
    R(ctx, cx, y + h + 2, 1, 1, ink);
    txt(ctx, [...full].slice(0, n).join(''), x + 3, y + 3, '#1a1030');
  }
  // nombre del protagonista para los bocadillos: en mayúsculas y recortado a lo que cabe
  function protagonista(D, max = 80) {
    const p = D && D.protagonista;
    let s = p && p.nombre ? String(p.nombre).toUpperCase() : '';
    while (s && tw(s) > max) s = s.slice(0, -1);
    return s;
  }
  const miles = n => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');


  // ----- registro y reproducción -----
  const ESCENAS = {};
  const M = { W, H, mk, rng, R, clamp, seg, SM, glyph, tw, txt, spriteText, glowImg, glowRects, flick, STR, leg, sitLeg, person, drawPerson, bubble, miles, protagonista };
  function registrar(def) { ESCENAS[def.id] = def; }

  // monta una escena en un <canvas>; devuelve un mando para reproducirla
  function montar(canvas, id, opts = {}) {
    const def = ESCENAS[id];
    if (!def) throw new Error('Escena desconocida: ' + id);
    canvas.width = W; canvas.height = H;
    const vctx = canvas.getContext('2d');
    vctx.imageSmoothingEnabled = false;
    const inst = def.crear(M, opts.datos || def.ejemplo || {});
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const loop = opts.loop !== false;
    let t = reduce ? (def.fotograma || 0) : (opts.desde || 0);
    let playing = !reduce && opts.autoplay !== false;
    let visible = true, last = performance.now(), raf = 0, dead = false;
    const emit = () => { if (opts.onTime) opts.onTime(t, inst.L); };
    function draw() { inst.render(vctx, t); emit(); }
    // Pixel art at 20 fps looks the same and costs a third of a 60 Hz loop. Off-screen,
    // folded panel or hidden tab: the loop stops outright (no idle wake-ups) and the
    // IntersectionObserver / visibilitychange start it again.
    const MIN_FRAME_MS = 1000 / 20 - 4;
    function frame(now) {
      raf = 0;
      if (dead || !playing) return;
      if (!visible || document.hidden) return;
      if (now - last < MIN_FRAME_MS) { raf = requestAnimationFrame(frame); return; }
      const dt = Math.min(0.1, (now - last) / 1000);
      last = now;
      t += dt;
      if (t >= inst.L) {
        if (loop) t %= inst.L;
        else { t = inst.L - 0.001; playing = false; draw(); if (opts.onEnd) opts.onEnd(); return; }
      }
      draw();
      raf = requestAnimationFrame(frame);
    }
    function start() { if (!raf && playing && !dead && visible && !document.hidden) { last = performance.now(); raf = requestAnimationFrame(frame); } }
    let io = null;
    if ('IntersectionObserver' in window) {
      io = new IntersectionObserver(es => { visible = es.some(e => e.isIntersecting); if (visible) start(); });
      io.observe(canvas);
    }
    const onVis = () => { if (!document.hidden) start(); };
    document.addEventListener('visibilitychange', onVis);
    draw();
    start();
    return {
      L: inst.L,
      get t() { return t; },
      get playing() { return playing; },
      play() { if (reduce && !opts.forzar) return; playing = true; start(); },
      pause() { playing = false; },
      toggle() { playing ? (playing = false) : (playing = true, start()); },
      seek(x) { t = clamp(x, 0, inst.L - 0.001); draw(); },
      destroy() { dead = true; if (io) io.disconnect(); document.removeEventListener('visibilitychange', onVis); if (raf) cancelAnimationFrame(raf); }
    };
  }

  window.Escenas = { registrar, montar, lista: () => Object.values(ESCENAS), M };
})();
