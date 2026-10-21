Escenas.registrar({
  id: 'oficina',
  titulo: 'La oficina de la rata',
  zona: 'Panel de staff',
  fotograma: 5.2,
  ejemplo: {
    enjambre: { pares: 12345, semillas: 12000, descargas: 345, socios: 678 }, reportes: 2,
    telegram: ['Series', 'Películas', 'Anime', 'Audiolibros', 'Cine de anime'],
    ajustes: { freeleech: true, doble: false, registro: false, invitaciones: true, hitrun: true }
  },
  crear(M, D) {
    const { W, H, mk, rng, R, clamp, seg, SM, glyph, tw, txt, spriteText, glowImg, glowRects, flick, STR, leg, sitLeg, person, drawPerson, bubble, miles } = M;
    // ================= ESCENA: la oficina de la rata (panel de staff) =================
    const HY = 156;          // pies del protagonista
    const V = 46;
    const HERO = { hair: '#f0c66b', hairD: '#b98a3a', hairL: '#ffe39a', skin: '#eab28a', skinD: '#c4865f', shirt: '#eef0f6', shirtD: '#b9c0d2', sleeveB: '#a9b0c4', sash: '#d8344a', sashD: '#a8243a', leg: '#2b2638', legB: '#1c1925', boot: '#0e0b13', style: 'pony' };
    const RX = 178, SEATY = 144;   // la rata: borde izquierdo del cuerpo y asiento de la silla
    const HERO_X = 150;
    const L = 17;

    const E = D.enjambre || {};
    const num = (v, d) => { const n = Math.floor(Number(v)); return Number.isFinite(n) && n >= 0 ? n : d; };
    const STATS = {
      pares: num(E.pares, 12345), semillas: num(E.semillas, 12000),
      descargas: num(E.descargas, 345), socios: num(E.socios, 678), reportes: num(D.reportes, 0)
    };

    // ----- línea de tiempo -----
    const doorOpen = t => seg(t, 0.8, 1.8) * (1 - seg(t, 15.2, 16.2));
    function heroAt(t) {
      if (t < 1.6) return null;
      const x0 = 26, d = (HERO_X - x0) / V;
      if (t < 1.6 + d) return { x: x0 + (t - 1.6) * V, walking: true, alpha: seg(t, 1.6, 2.0) };
      return { x: HERO_X, walking: false, alpha: 1 };
    }
    const T_TURN = 4.3, T_BACK = 14.2;
    const LINES = [
      { who: 'rat', t0: 4.6, t1: 6.4, s: '¡BIENVENIDO, COLEGA!' },
      { who: 'rat', t0: 6.6, t1: 8.2, s: 'TOMA UN GROG' },
      { who: 'hero', t0: 9.8, t1: 11.0, s: '¡GLUP!' },
      { who: 'hero', t0: 11.2, t1: 12.6, s: '¡ESTO DERRITE JARRAS!' },
      { who: 'rat', t0: 12.6, t1: 14.0, s: '¡JA, JA, JA!' },
      { who: 'rat', t0: 14.4, t1: 15.8, s: '¡AL LÍO!' }
    ];
    function ratMode(t) {
      if (t < T_TURN || t >= T_BACK) return 'type';
      if (t >= 7.0 && t < 8.9) return 'offer';
      if (t >= 12.6 && t < 14.0) return 'laugh';
      return 'rest';
    }

    // ----- la rata punki en su silla (mirando a la derecha) -----
    const [rc, rctx] = mk(48, 48);
    function rat(c, x, y, mode, t) {
      // cola
      const tail = [[-1, -1], [-2, 0], [-3, 1], [-4, 2], [-5, 3], [-6, 3], [-7, 2], [-8, 1], [-9, 0], [-10, -1], [-10, -2], [-9, -3]];
      tail.forEach(([dx, dy], i) => R(c, x + dx, y + dy + (i > 6 ? Math.round(Math.sin(t * 3 + i * 0.6)) : 0), 1, 1, '#f0a0b8'));
      // silla
      R(c, x - 3, y - 15, 3, 13, '#1f2a5a'); R(c, x - 3, y - 15, 3, 1, '#2f3a7a');
      R(c, x - 2, y, 14, 3, '#2a3a7a'); R(c, x - 2, y, 14, 1, '#3a4a9a');
      R(c, x + 4, y + 3, 2, 6, '#4a4a52'); R(c, x - 1, y + 9, 14, 1, '#3a3a42');
      R(c, x - 1, y + 10, 2, 1, '#0a0a0e'); R(c, x + 5, y + 10, 2, 1, '#0a0a0e'); R(c, x + 11, y + 10, 2, 1, '#0a0a0e');
      // patas
      R(c, x + 2, y - 2, 8, 3, '#8a8a94'); R(c, x + 9, y + 1, 2, 5, '#8a8a94'); R(c, x + 9, y + 6, 3, 1, '#f0a0b8');
      // brazo de atrás y chupa de cuero con tachuelas
      R(c, x + 2, y - 10, 2, 6, '#0c0c10');
      R(c, x, y - 12, 10, 11, '#16161c'); R(c, x + 6, y - 11, 3, 9, '#d0d0d8'); R(c, x + 5, y - 12, 1, 10, '#2a2a34');
      for (const [sx, sy] of [[1, -12], [3, -12], [1, -9], [1, -6], [3, -3], [2, -13], [4, -13]]) R(c, x + sx, y + sy, 1, 1, '#c9cdd8');
      // cabeza
      const laugh = mode === 'laugh';
      R(c, x + 4, y - 20, 8, 8, '#8a8a94'); R(c, x + 4, y - 13, 8, 1, '#7a7a84');
      R(c, x + 12, y - 17, 3, 4, '#8a8a94'); R(c, x + 15, y - 16, 1, 2, '#9a9aa4'); R(c, x + 16, y - 16, 1, 1, '#ff7aa0');
      R(c, x + 16, y - 18, 3, 1, '#b9b9c2'); R(c, x + 16, y - 14, 3, 1, '#b9b9c2');
      if (laugh) {
        R(c, x + 9, y - 18, 3, 1, '#0a0a0a');
        R(c, x + 11, y - 14, 5, 3, '#3a0a14'); R(c, x + 12, y - 14, 3, 1, '#f4f4f8'); R(c, x + 12, y - 12, 2, 1, '#ff7aa0');
      } else {
        R(c, x + 10, y - 18, 1, 1, '#0a0a0a');
        R(c, x + 12, y - 14, 3, 1, '#3a0a14'); R(c, x + 13, y - 13, 2, 1, '#f4f4f8');
      }
      R(c, x + 4, y - 23, 4, 4, '#9a9aa4'); R(c, x + 5, y - 22, 2, 2, '#f0a0b8'); R(c, x + 4, y - 19, 1, 1, '#ffd23e');
      // cresta
      R(c, x + 5, y - 21, 7, 1, '#c0287a');
      [4, 6, 5, 3].forEach((h, k) => {
        const sx = x + 5 + k * 2 - (laugh ? Math.round(Math.sin(t * 20 + k)) : 0) * 0;
        R(c, sx, y - 21 - h, 2, h, '#ff3ea5'); R(c, sx, y - 21 - h, 1, 1, '#ff9ad0');
      });
      // brazo de delante
      if (mode === 'type') {
        const w = Math.sin(t * 22) > 0 ? 0 : 1;
        R(c, x + 6, y - 9, 5, 2, '#16161c'); R(c, x + 11, y - 9 + w, 2, 2, '#f0a0b8');
      } else if (mode === 'offer') {
        R(c, x + 6, y - 11, 6, 2, '#16161c'); R(c, x + 12, y - 12, 2, 2, '#f0a0b8');
      } else if (mode === 'laugh') {
        R(c, x + 6, y - 7, 4, 2, '#16161c'); R(c, x + 8, y - 6, 2, 2, '#f0a0b8');
      } else {
        R(c, x + 6, y - 8, 3, 2, '#16161c'); R(c, x + 8, y - 6, 2, 2, '#f0a0b8');
      }
    }
    function drawRat(ctx, t) {
      const mode = ratMode(t);
      const flip = t >= T_TURN && t < T_BACK;
      rctx.clearRect(0, 0, 48, 48);
      rat(rctx, 16, 34, mode, t);
      ctx.save();
      if (flip) { ctx.translate(RX + 10, 0); ctx.scale(-1, 1); ctx.drawImage(rc, -16, SEATY - 34); }
      else ctx.drawImage(rc, RX - 16, SEATY - 34);
      ctx.restore();
    }
    function grog(ctx, x, y, lying) {
      if (lying) {
        R(ctx, x, y, 6, 3, '#5a3416'); R(ctx, x + 6, y + 1, 2, 1, '#5a3416'); R(ctx, x + 2, y, 2, 3, '#e8dcb0');
        R(ctx, x + 2, y + 1, 1, 1, '#111111'); R(ctx, x, y, 6, 1, 'rgba(255,255,255,0.3)');
      } else {
        R(ctx, x, y + 2, 4, 7, '#5a3416'); R(ctx, x + 1, y, 2, 2, '#5a3416'); R(ctx, x, y + 4, 4, 3, '#e8dcb0');
        R(ctx, x + 1, y + 5, 1, 1, '#111111'); R(ctx, x, y + 2, 1, 6, 'rgba(255,255,255,0.3)');
      }
    }

    // ----- capa fija -----
    const [back, bk] = mk(W, H);
    const NODES = [], EDGES = [];
    function buildLayers() {
      const r = rng(41);
      R(bk, 0, 0, W, 14, '#0e1218');
      R(bk, 0, 6, W, 3, '#2a3240'); R(bk, 0, 6, W, 1, '#3a4454');
      for (let x = 10; x < W; x += 40) R(bk, x, 9, 2, 5, '#2a3240');
      R(bk, 0, 14, W, 136, '#18202c');
      for (let x = 0; x < W; x += 32) R(bk, x, 14, 1, 136, '#121822');
      for (let x = 3; x < W; x += 32) for (const y of [18, 146]) R(bk, x, y, 1, 1, '#2e3848');
      R(bk, 0, 60, W, 1, '#121822');
      // puerta acorazada: marco y pasillo
      R(bk, 14, 72, 58, 78, '#3a4252'); R(bk, 14, 72, 58, 1, '#4a5466');
      R(bk, 20, 78, 46, 72, '#0a0d12');
      for (let y = 82; y < 150; y += 4) R(bk, 22, y, 42, 1, 'rgba(120,200,255,0.06)');
      for (let y = 76; y < 150; y += 10) { R(bk, 16, y, 1, 1, '#6a7484'); R(bk, 69, y, 1, 1, '#6a7484'); }
      // teclado de acceso
      R(bk, 76, 102, 11, 17, '#2a3240'); R(bk, 76, 102, 11, 1, '#3a4454');
      for (let k = 0; k < 9; k++) R(bk, 78 + (k % 3) * 3, 109 + Math.floor(k / 3) * 3, 2, 2, '#5a6474');
      // cajas con franjas de peligro
      R(bk, 94, 134, 24, 16, '#5a4a32'); R(bk, 94, 134, 24, 1, '#7a6a4a');
      for (let k = 0; k < 6; k++) R(bk, 95 + k * 4, 146, 2, 3, k % 2 ? '#1a1a1a' : '#e8c23a');
      R(bk, 98, 126, 14, 8, '#6a5a3a'); R(bk, 98, 126, 14, 1, '#8a7a5a');
      // pantalla grande SWARM INTEL
      R(bk, 166, 22, 142, 78, '#2a3240'); R(bk, 168, 24, 138, 74, '#06120c');
      txt(bk, 'SWARM INTEL', 172, 27, '#4dff8a');
      R(bk, 172, 33, 76, 1, '#1d5a34'); R(bk, 252, 26, 1, 70, '#1d5a34');
      for (let i = 0; i < 16; i++) NODES.push({ x: 174 + ((r() * 72) | 0), y: 38 + ((r() * 54) | 0), ph: r() * 6 });
      const seen = new Set();
      NODES.forEach((a, i) => {
        NODES.map((b, j) => ({ j, d: Math.hypot(a.x - b.x, a.y - b.y) })).filter(o => o.j !== i)
          .sort((p, q) => p.d - q.d).slice(0, 2).forEach(o => {
            const key = Math.min(i, o.j) + '-' + Math.max(i, o.j);
            if (seen.has(key)) return;
            seen.add(key);
            EDGES.push({ a: i, b: o.j, sp: 0.4 + r() * 0.8, off: r() });
          });
      });
      for (const e of EDGES) {
        const a = NODES[e.a], b = NODES[e.b], n = Math.max(Math.abs(b.x - a.x), Math.abs(b.y - a.y));
        for (let k = 0; k <= n; k += 2) R(bk, Math.round(a.x + (b.x - a.x) * k / n), Math.round(a.y + (b.y - a.y) * k / n), 1, 1, '#14402a');
      }
      // mesa, teclado y monitores de cámaras
      R(bk, 188, 128, 128, 3, '#4a5262'); R(bk, 188, 128, 128, 1, '#6a7484');
      R(bk, 188, 131, 128, 19, '#2a3240');
      for (const dx of [196, 236, 276]) { R(bk, dx, 134, 30, 7, '#323a4a'); R(bk, dx + 13, 137, 4, 1, '#8a93a6'); R(bk, dx, 143, 30, 6, '#323a4a'); R(bk, dx + 13, 145, 4, 1, '#8a93a6'); }
      R(bk, 189, 125, 18, 3, '#1a1e26');
      for (let k = 0; k < 8; k++) R(bk, 190 + k * 2, 126, 1, 1, '#4a5262');
      for (const mx of [212, 248, 284]) {
        R(bk, mx, 104, 30, 22, '#2a3240'); R(bk, mx + 13, 126, 4, 2, '#2a3240');
      }
      // rack de servidores
      R(bk, 308, 40, 12, 110, '#141820'); R(bk, 308, 40, 1, 110, '#2a3240');
      for (let y = 44; y < 148; y += 8) R(bk, 310, y, 10, 6, '#1e2430');
      // suelo de hormigón con cable
      R(bk, 0, 150, W, 30, '#141820');
      for (let x = 0; x < W; x += 24) R(bk, x, 150, 1, 30, '#1a2030');
      for (let y = 158; y < 180; y += 10) R(bk, 0, y, W, 1, '#1a2030');
      for (let k = 0; k < 9; k++) R(bk, 14 + k * 4, 150 + (k % 2), 4, 1, k % 2 ? '#1a1a1a' : '#e8c23a');
      for (let x = 200; x < 300; x++) R(bk, x, 168 + Math.round(Math.sin(x * 0.08) * 3), 1, 1, '#0a0c10');
    }
    buildLayers();

    // escenas pequeñas en los monitores: la puerta, el bufé y el bar
    function camView(ctx, x, y, kind, t) {
      if (kind === 0) {
        R(ctx, x, y, 28, 18, '#14122a'); R(ctx, x + 9, y + 3, 10, 4, '#ff3ea5'); R(ctx, x + 11, y + 8, 6, 10, '#3ee8ff');
        R(ctx, x + 12, y + 9, 4, 9, '#14162a');
        for (let k = 0; k < 6; k++) R(ctx, x + ((k * 7 + Math.floor(t * 20)) % 28), y + ((k * 5 + Math.floor(t * 40)) % 18), 1, 1, '#8aa8ff');
      } else if (kind === 1) {
        R(ctx, x, y, 28, 18, '#331a2d'); R(ctx, x + 2, y + 2, 10, 3, '#ff3ea5'); R(ctx, x + 15, y + 2, 10, 3, '#3ee8ff');
        R(ctx, x, y + 11, 28, 2, '#b7bfcc'); R(ctx, x, y + 13, 28, 5, '#4a2434');
        for (let k = 0; k < 5; k++) R(ctx, x + 2 + k * 5, y + 9, 3, 2, '#f0c050');
        R(ctx, x + Math.floor((t * 6) % 28), y + 6, 2, 5, '#eef0f6');
      } else {
        R(ctx, x, y, 28, 18, '#1d2a25'); R(ctx, x + 2, y + 2, 24, 8, '#2a1a10');
        for (let k = 0; k < 8; k++) R(ctx, x + 3 + k * 3, y + 4, 1, 4, ['#3a7a4a', '#c9a24a', '#9a3a6a', '#d9d9e4'][k % 4]);
        R(ctx, x, y + 12, 28, 6, '#4a2a18');
        for (let k = 0; k < 3; k++) R(ctx, x + 5 + k * 8, y + 9 + (Math.sin(t * 4 + k) > 0 ? 0 : 1), 2, 3, '#eab28a');
      }
      ctx.fillStyle = 'rgba(0,0,0,0.25)';
      for (let k = 0; k < 18; k += 2) ctx.fillRect(x, y + k, 28, 1);
      R(ctx, x, y + Math.floor((t * 9) % 18), 28, 1, 'rgba(255,255,255,0.12)');
    }

    // pantalla del bot de Telegram sobre la puerta: va anunciando los últimos torrents
    const TG = (Array.isArray(D.telegram) && D.telegram.length ? D.telegram : ['Series']).map(s => String(s).toUpperCase());
    function wrap(s, max, lines) {
      const out = [];
      let cur = '';
      for (const w of s.split(' ')) {
        const tryS = cur ? cur + ' ' + w : w;
        if (tw(tryS) <= max) cur = tryS;
        else { if (cur) out.push(cur); cur = w; }
      }
      if (cur) out.push(cur);
      return out.slice(0, lines).map(l => { while (tw(l) > max) l = l.slice(0, -1); return l; });
    }
    function tgScreen(ctx, t) {
      R(ctx, 10, 18, 66, 50, '#2a3240'); R(ctx, 10, 18, 66, 1, '#3a4454');
      R(ctx, 12, 20, 62, 46, '#0e1621');
      R(ctx, 12, 20, 62, 7, '#229ed9');
      R(ctx, 14, 22, 5, 1, '#ffffff'); R(ctx, 15, 23, 4, 1, '#ffffff'); R(ctx, 16, 24, 2, 1, '#ffffff'); R(ctx, 16, 25, 1, 1, '#d6efff');
      txt(ctx, 'NOBS BOT', 22, 21, '#ffffff');
      const P = 2.8, k = Math.floor(t / P), frac = (t % P) / P;
      const BH = 20, bottom = 64, slide = Math.round((1 - Math.min(1, frac * 4)) * (BH + 2));
      ctx.save();
      ctx.beginPath(); ctx.rect(12, 28, 62, 38); ctx.clip();
      for (let j = 0; j < 3; j++) {
        const msg = TG[((k - j) % TG.length + TG.length) % TG.length];
        const y = bottom - BH - j * (BH + 2) + slide;
        R(ctx, 14, y, 58, BH, '#182533'); R(ctx, 14, y, 1, BH, '#2b5278');
        txt(ctx, 'NUEVO TORRENT', 17, y + 2, '#6ab3f3');
        wrap(msg, 52, 2).forEach((l, i) => txt(ctx, l, 17, y + 8 + i * 6, '#e8eef5'));
      }
      ctx.restore();
      if (frac < 0.25 && Math.floor(t * 8) % 2 === 0) R(ctx, 70, 21, 3, 3, '#ff3a4a');
    }
    // panel de palancas: los ajustes del staff, con su estado real
    const AJ = D.ajustes || {};
    const LEVERS = [['FL', AJ.freeleech], ['X2', AJ.doble], ['REG', AJ.registro], ['INV', AJ.invitaciones], ['H&R', AJ.hitrun]];
    function leverPanel(ctx, t) {
      R(ctx, 89, 67, 76, 54, '#1a1e26'); R(ctx, 90, 68, 74, 52, '#3a4252'); R(ctx, 90, 68, 74, 1, '#4a5466');
      for (const [rx, ry] of [[92, 70], [161, 70], [92, 117], [161, 117]]) R(ctx, rx, ry, 1, 1, '#7a8494');
      R(ctx, 110, 70, 34, 7, '#1a1e26');
      txt(ctx, 'AJUSTES', 127 - (tw('AJUSTES') >> 1), 71, '#ffb347');
      LEVERS.forEach(([lab, on], i) => {
        const x = 94 + i * 14, y0 = 84;
        if (on) glowRects(ctx, '#4dff8a', 4, [[x + 2, 79, 4, 2]], 0.75 + 0.25 * Math.sin(t * 4 + i));
        else R(ctx, x + 2, 79, 4, 2, '#5a1a1a');
        R(ctx, x, y0 + 10, 8, 4, '#16161c');
        R(ctx, x + 1, y0 + 8, 2, 2, '#c9a24a'); R(ctx, x + 5, y0 + 8, 2, 2, '#c9a24a');
        R(ctx, x + 1, y0 + 14, 2, 2, '#c9a24a'); R(ctx, x + 5, y0 + 14, 2, 2, '#c9a24a');
        if (on) { R(ctx, x + 3, y0 + 1, 2, 11, '#c9cdd8'); R(ctx, x + 2, y0 - 2, 4, 3, '#c0303a'); R(ctx, x + 2, y0 - 2, 4, 1, '#e05060'); }
        else { R(ctx, x + 3, y0 + 12, 2, 11, '#c9cdd8'); R(ctx, x + 2, y0 + 23, 4, 3, '#c0303a'); R(ctx, x + 2, y0 + 23, 4, 1, '#e05060'); }
        txt(ctx, lab, x + 4 - (tw(lab) >> 1), 112, '#c9cdd8');
      });
    }

    const [buf, ctx] = mk(W, H);

    function render(vctx, t) {
      ctx.clearRect(0, 0, W, H);
      ctx.drawImage(back, 0, 0);
      tgScreen(ctx, t);
      leverPanel(ctx, t);

      // tubo fluorescente que parpadea
      const fl = flick(t, 5.1);
      glowRects(ctx, '#bff6ff', 8, [[118, 14, 54, 2]], fl);
      ctx.fillStyle = `rgba(190,240,255,${0.05 * fl})`;
      ctx.fillRect(100, 16, 90, 134);

      // puerta acorazada que se abre hacia la izquierda
      const o = doorOpen(t);
      if (o > 0) ctx.fillStyle = `rgba(120,200,255,${0.10 * o})`, ctx.fillRect(20, 78, 46, 72);
      const dw = Math.max(5, Math.round(46 * (1 - o * 0.88)));
      R(ctx, 20, 78, dw, 72, '#4a5466'); R(ctx, 20 + dw - 1, 78, 1, 72, '#2a3240'); R(ctx, 20, 78, dw, 1, '#5a6476');
      for (let y = 82; y < 148; y += 9) for (const fx of [0.15, 0.85]) R(ctx, 20 + Math.round(dw * fx), y, 1, 1, '#7a8494');
      if (dw > 24) {
        const wx = 20 + Math.round(dw * 0.3);
        R(ctx, wx, 88, Math.round(dw * 0.4), 16, '#2a3444');
        for (let y = 89; y < 103; y += 2) for (let x = wx + 1; x < wx + Math.round(dw * 0.4) - 1; x += 2) R(ctx, x + (y % 4 ? 1 : 0), y, 1, 1, '#4a5466');
        R(ctx, 20 + dw - 7, 108, 2, 14, '#9aa3b5');
      }
      R(ctx, 79, 104, 5, 3, t >= 0.6 && t < 16.2 ? '#4dff8a' : (Math.floor(t * 4) % 2 ? '#ff3a4a' : '#7a1a22'));

      // pantalla SWARM INTEL: nodos, paquetes y cifras
      const leech = Math.max(1, Math.round(NODES.length * STATS.descargas / Math.max(1, STATS.pares)));
      for (const e of EDGES) {
        const a = NODES[e.a], b = NODES[e.b], k = (t * e.sp + e.off) % 1;
        R(ctx, Math.round(a.x + (b.x - a.x) * k), Math.round(a.y + (b.y - a.y) * k), 1, 1, '#b8ffd0');
      }
      NODES.forEach((n, i) => {
        const on = Math.sin(t * 3 + n.ph) > -0.3;
        R(ctx, n.x - 1, n.y - 1, 2, 2, i < leech ? '#ffb347' : (on ? '#4dff8a' : '#2a9a5a'));
      });
      const rows = [['PARES', STATS.pares], ['SEMILLAS', STATS.semillas], ['DESCARGAS', STATS.descargas], ['SOCIOS', STATS.socios], ['REPORTES', STATS.reportes]];
      rows.forEach(([lab, v], i) => {
        const y = 37 + i * 12;
        txt(ctx, lab, 256, y, '#2a9a5a');
        const alert = lab === 'REPORTES' && v > 0;
        txt(ctx, miles(v), 256, y + 6, alert ? (Math.floor(t * 3) % 2 ? '#ff3a4a' : '#7a1a22') : '#b8ffd0');
      });
      ctx.fillStyle = 'rgba(0,0,0,0.18)';
      for (let y = 24; y < 98; y += 2) ctx.fillRect(168, y, 138, 1);
      R(ctx, 168, 24 + Math.floor((t * 12) % 74), 138, 1, 'rgba(77,255,138,0.10)');

      [212, 248, 284].forEach((mx, k) => camView(ctx, mx + 1, 106, k, t));
      txt(ctx, 'CAM1', 214, 98, '#5a6474'); txt(ctx, 'CAM2', 250, 98, '#5a6474'); txt(ctx, 'CAM3', 286, 98, '#5a6474');
      for (let y = 44; y < 148; y += 8) {
        const blink = Math.sin(t * 6 + y) > 0.2;
        R(ctx, 312, y + 2, 1, 1, blink ? '#4dff8a' : '#14402a'); R(ctx, 315, y + 2, 1, 1, Math.sin(t * 9 + y * 2) > 0.6 ? '#ffb347' : '#3a2a10');
      }

      drawRat(ctx, t);

      // el grog: en la mano de la rata, de camino y en la mano del protagonista
      const hs = heroAt(t);
      const ratPaw = [RX - 6, SEATY - 20];
      if (t >= 7.0 && t < 8.4) grog(ctx, ratPaw[0], ratPaw[1], false);

      if (hs) {
        const mode = t >= 9.6 && t < 11.0 ? 'wave' : (t >= 8.2 && t < 9.6 ? 'tray' : 'down');
        ctx.save();
        ctx.globalAlpha = hs.alpha;
        const st = Math.floor(t * 10) % 8;
        drawPerson(ctx, hs.x, HY, st, hs.walking, mode, HERO);
        ctx.restore();
        const hx = Math.round(hs.x);
        if (t >= 8.4 && t < 8.9) {
          const k = seg(t, 8.4, 8.9);
          grog(ctx, ratPaw[0] + (hx + 8 - ratPaw[0]) * k, ratPaw[1] + (HY - 20 - ratPaw[1]) * k - 6 * Math.sin(Math.PI * k), false);
        } else if (t >= 8.9 && t < 9.6) grog(ctx, hx + 8, HY - 20, false);
        else if (t >= 9.6 && t < 11.0) grog(ctx, hx + 6, HY - 25, true);
        else if (t >= 11.0) grog(ctx, hx + 3, HY - 17, false);
      }

      // bocadillos
      for (const l of LINES) {
        if (t < l.t0 || t >= l.t1) continue;
        const n = Math.floor(seg(t, l.t0, l.t0 + 0.4) * [...l.s].length + 0.001);
        const flip = t >= T_TURN && t < T_BACK;
        const cx = l.who === 'rat' ? (flip ? RX + 2 : RX + 8) : Math.round((hs ? hs.x : HERO_X) + 5);
        const tip = l.who === 'rat' ? SEATY - 29 : HY - 27;
        bubble(ctx, cx, tip, l.s, n, 0, W);
      }

      vctx.drawImage(buf, 0, 0);
    }

    return { L, render };
  }
});
