Escenas.registrar({
  id: 'puerta',
  titulo: 'La puerta',
  zona: 'Login',
  fotograma: 8.6,
  ejemplo: {},
  crear(M, D) {
    const W = 320, H = 180, L = 16;
    let lastT = null;
    const GY = 154;   // pies de los porteros
    const HY = 157;   // pies del protagonista (un poco más cerca de la cámara)

    const mk = (w, h) => {
      const c = document.createElement('canvas');
      c.width = w; c.height = h;
      const x = c.getContext('2d');
      x.imageSmoothingEnabled = false;
      return [c, x];
    };
    const [buf, ctx] = mk(W, H);
    const [bg, bctx] = mk(W, H);
    const [hc, hctx] = mk(20, 30);

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
    const seg = (t, a, b) => Math.min(1, Math.max(0, (t - a) / (b - a)));
    const ease = x => x < .5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2;

    // ---------- fuente de píxel 3x5 ----------
    const SM = {
      'A': ['.#.', '#.#', '###', '#.#', '#.#'],
      'B': ['##.', '#.#', '##.', '#.#', '##.'],
      'C': ['.##', '#..', '#..', '#..', '.##'],
      'D': ['##.', '#.#', '#.#', '#.#', '##.'],
      'E': ['###', '#..', '##.', '#..', '###'],
      'I': ['###', '.#.', '.#.', '.#.', '###'],
      'K': ['#.#', '#.#', '##.', '#.#', '#.#'],
      'L': ['#..', '#..', '#..', '#..', '###'],
      'N': ['#..#', '##.#', '#.##', '#..#', '#..#'],
      'O': ['###', '#.#', '#.#', '#.#', '###'],
      'P': ['##.', '#.#', '##.', '#..', '#..'],
      'R': ['##.', '#.#', '##.', '#.#', '#.#'],
      'S': ['.##', '#..', '.#.', '..#', '##.'],
      'T': ['###', '.#.', '.#.', '.#.', '.#.'],
      'V': ['#.#', '#.#', '#.#', '#.#', '.#.'],
      '?': ['##.', '..#', '.#.', '...', '.#.'],
      '¿': ['.#.', '...', '.#.', '#..', '.##'],
      ' ': ['..', '..', '..', '..', '..']
    };
    const glyph = ch => SM[ch] || SM[' '];
    function tw(s) {
      let w = 0;
      for (const ch of s) w += glyph(ch)[0].length + 1;
      return Math.max(0, w - 1);
    }
    function txt(c, s, x, y, col) {
      c.fillStyle = col;
      let cx = x;
      for (const ch of s) {
        const g = glyph(ch);
        for (let r = 0; r < g.length; r++)
          for (let i = 0; i < g[r].length; i++)
            if (g[r][i] === '#') c.fillRect(cx + i, y + r, 1, 1);
        cx += g[0].length + 1;
      }
    }

    // ---------- letras grandes del neón ----------
    const BIG = {
      N: ['#...#', '##..#', '##..#', '#.#.#', '#..##', '#..##', '#...#'],
      O: ['.###.', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
      B: ['####.', '#...#', '#...#', '####.', '#...#', '#...#', '####.'],
      S: ['.####', '#....', '#....', '.###.', '....#', '....#', '####.']
    };
    function spriteBig(ch) {
      const g = BIG[ch];
      const [c, x] = mk(10, 14);
      const on = (r, i) => g[r] && g[r][i] === '#';
      for (let r = 0; r < 7; r++) for (let i = 0; i < 5; i++) {
        if (!on(r, i)) continue;
        R(x, i * 2, r * 2, 2, 2, '#ff3ea5');
      }
      // núcleo claro del tubo
      for (let r = 0; r < 7; r++) for (let i = 0; i < 5; i++) {
        if (!on(r, i)) continue;
        R(x, i * 2, r * 2, 1, 1, '#ffc9e7');
        if (on(r, i + 1)) R(x, i * 2 + 1, r * 2, 1, 1, '#ffc9e7');
        if (on(r + 1, i)) R(x, i * 2, r * 2 + 1, 1, 1, '#ffc9e7');
      }
      return c;
    }
    function spriteText(s, col) {
      const [c, x] = mk(tw(s), 5);
      txt(x, s, 0, 0, col);
      return c;
    }
    const NOBS = ['N', 'O', 'B', 'S'].map(spriteBig);
    const TAG = spriteText('TRACKER PRIVADO', '#9ff4ff');
    const SOCIOS = spriteText('SOLO SOCIOS', '#9ff4ff');
    const BAR = spriteText('BAR', '#ffb3dc');
    const CHECK = (() => {
      const p = ['........#', '.......##', '#.....##.', '##...##..', '.##.##...', '..###....', '...#.....'];
      const [c, x] = mk(9, 7);
      p.forEach((row, r) => [...row].forEach((v, i) => { if (v === '#') R(x, i, r, 1, 1, '#4dff8a'); }));
      return c;
    })();
    const CARD = (() => {
      const [c, x] = mk(4, 3);
      R(x, 0, 0, 4, 3, '#ffc94a'); R(x, 0, 0, 4, 1, '#ffe7a3'); R(x, 1, 1, 2, 1, '#fff6d6');
      return c;
    })();

    function glow(img, x, y, col, blur, a = 1) {
      ctx.save();
      ctx.globalAlpha = a;
      ctx.shadowColor = col;
      ctx.shadowBlur = blur;
      ctx.drawImage(img, Math.round(x), Math.round(y));
      ctx.restore();
    }
    function glowRects(col, blur, rects) {
      ctx.save();
      ctx.shadowColor = col; ctx.shadowBlur = blur; ctx.fillStyle = col;
      rects.forEach(r => ctx.fillRect(r[0], r[1], r[2], r[3]));
      ctx.restore();
    }

    // ---------- fondo fijo ----------
    function buildBg() {
      const c = bctx, r = rng(7);
      R(c, 0, 0, W, 40, '#0a0816');
      for (let i = 0; i < 30; i++) R(c, (r() * W) | 0, (r() * 14) | 0, 1, 1, '#3b3566');
      let x = 0;
      while (x < W) {
        const w = 12 + ((r() * 22) | 0), h = 8 + ((r() * 18) | 0);
        R(c, x, 30 - h, w, h + 4, '#151028');
        for (let wy = 30 - h + 2; wy < 27; wy += 4)
          for (let wx = x + 2; wx < x + w - 2; wx += 4)
            if (r() < 0.2) R(c, wx, wy, 1, 1, r() < 0.5 ? '#ffcf6b' : '#5b4f98');
        x += w + ((r() * 3) | 0);
      }
      // muro de ladrillo
      R(c, 0, 28, W, 122, '#22121d');
      for (let row = 0, y = 32; y < 150; y += 4, row++) {
        const off = row % 2 ? 5 : 0;
        for (let bx = -off; bx < W; bx += 10) {
          const v = r();
          R(c, bx, y, 9, 3, v < 0.12 ? '#2e1828' : v < 0.55 ? '#3a2130' : '#43263a');
          if (r() < 0.07) R(c, bx + 1, y, 3, 1, '#4e2d43');
        }
      }
      R(c, 0, 28, W, 3, '#4a3043'); R(c, 0, 31, W, 1, '#160b13');

      // ventanas altas
      const win = (x, y, w, h, lit) => {
        R(c, x - 1, y - 1, w + 2, h + 2, '#1a0f18');
        R(c, x, y, w, h, lit ? '#d8964a' : '#1d1832');
        if (lit) R(c, x, y, w, 2, '#ffd27a');
        R(c, x + (w >> 1), y, 1, h, '#1a0f18');
        R(c, x, y + (h >> 1), w, 1, '#1a0f18');
        R(c, x - 2, y + h + 1, w + 4, 1, '#4a3043');
      };
      win(22, 36, 18, 18, true);
      win(64, 36, 18, 18, false);
      win(240, 36, 18, 18, false);
      win(284, 36, 18, 18, true);
      win(134, 34, 16, 14, false);
      win(170, 34, 16, 14, true);
      R(c, 290, 44, 4, 4, '#3a2414'); R(c, 289, 48, 6, 6, '#3a2414');

      // farola
      R(c, 10, 64, 2, 86, '#1b1826'); R(c, 8, 146, 6, 4, '#1b1826');
      R(c, 10, 64, 9, 2, '#1b1826'); R(c, 16, 66, 6, 2, '#2c2840'); R(c, 17, 68, 4, 1, '#ffe3a1');
      for (let y = 69; y < 150; y++) {
        const half = (y - 69) * 0.33 + 2;
        for (let xx = Math.floor(19 - half); xx <= 19 + half; xx++) {
          if (((xx + y) & 1) === 0 && r() < 0.55 * (1 - (y - 69) / 95))
            R(c, xx, y, 1, 1, 'rgba(255,214,150,0.12)');
        }
      }

      // bar con toldo
      for (let sx = 32; sx < 104; sx += 6) { R(c, sx, 96, 3, 6, '#c2366f'); R(c, sx + 3, 96, 3, 6, '#d9d0e6'); }
      for (let sx = 33; sx < 104; sx += 3) R(c, sx, 102, 1, 1, '#c2366f');
      R(c, 34, 105, 70, 42, '#170c16');
      R(c, 36, 107, 66, 38, '#6a3c26');
      R(c, 36, 107, 66, 9, '#8a512e');
      R(c, 50, 108, 3, 2, '#ffd38a'); R(c, 84, 108, 3, 2, '#ffd38a');
      R(c, 48, 121, 5, 5, '#2a160f'); R(c, 46, 126, 9, 10, '#2a160f');
      R(c, 63, 119, 5, 5, '#2a160f'); R(c, 61, 124, 9, 12, '#2a160f');
      R(c, 80, 123, 4, 4, '#2a160f'); R(c, 79, 127, 6, 9, '#2a160f');
      R(c, 36, 136, 66, 9, '#3a2016'); R(c, 36, 136, 66, 1, '#5a3320');

      // derecha: aire acondicionado, cartel, bajante, cubo
      R(c, 252, 70, 18, 11, '#5d6172');
      for (let gy = 72; gy < 80; gy += 2) R(c, 254, gy, 14, 1, '#40434f');
      R(c, 258, 104, 14, 18, '#cbbb93'); R(c, 260, 106, 10, 8, '#7a2b4a');
      R(c, 260, 116, 10, 1, '#5a4a3a'); R(c, 260, 118, 7, 1, '#5a4a3a'); R(c, 271, 120, 1, 2, '#22121d');
      R(c, 306, 31, 3, 119, '#2a2a36');
      for (let by = 50; by < 150; by += 30) R(c, 305, by, 5, 1, '#3a3a48');
      R(c, 280, 136, 14, 14, '#24352e'); R(c, 279, 134, 16, 2, '#2f4a3e');
      for (let lx = 282; lx < 293; lx += 3) R(c, lx, 138, 1, 10, '#1c2a24');

      // hueco de la puerta
      R(c, 136, 90, 48, 2, '#4a3043');
      R(c, 138, 92, 44, 58, '#0d0914');

      // acera, bordillo, calzada
      R(c, 0, 150, W, 10, '#2a2436');
      for (let xx = 0; xx < W; xx += 16) R(c, xx, 150, 1, 10, '#221d2c');
      R(c, 0, 160, W, 2, '#463d5a'); R(c, 0, 160, W, 1, '#5a5072');
      R(c, 0, 162, W, 18, '#110e1a');
      for (let xx = 4; xx < W; xx += 30) R(c, xx, 175, 12, 1, '#2e2942');
      for (let i = 0; i < 70; i++) R(c, (r() * W) | 0, 163 + ((r() * 17) | 0), 2, 1, '#1b1729');
    }
    buildBg();

    // ---------- línea de tiempo ----------
    function state(t) {
      let hx, hy = 0, walking = false, alpha = 1, sil = 0, show = true;
      if (t < 0.4) { hx = -14; walking = true; }
      else if (t < 5) { hx = -14 + (t - 0.4) / 4.6 * 126; walking = true; }
      else if (t < 9.8) { hx = 112; }
      else if (t < 11.6) { hx = 112 + (t - 9.8) / 1.8 * 43; walking = true; }
      else {
        hx = 155;
        walking = t < 12.6;
        hy = -Math.round(5 * seg(t, 11.6, 12.6));
        sil = seg(t, 11.6, 12.6);
        alpha = 1 - seg(t, 12.2, 12.9);
        show = t < 12.9;
      }
      const arm = ease(seg(t, 6.3, 6.7)) * (1 - ease(seg(t, 8.6, 9.0)));
      const out = ease(seg(t, 8.8, 9.6)) * (1 - ease(seg(t, 13.2, 14.0)));
      const door = ease(seg(t, 8.9, 9.7)) * (1 - ease(seg(t, 13.0, 13.8)));
      return {
        hx, hy, walking, alpha, sil, show, arm, out, door,
        b1: t >= 5.2 && t < 7.4 ? Math.floor(seg(t, 5.2, 5.8) * 14) : -1,
        b2: t >= 8.4 && t < 9.9 ? Math.floor(seg(t, 8.4, 8.8) * 8) : -1,
        chk: t >= 8.0 && t < 10.6 ? { pop: seg(t, 8.0, 8.25), fade: 1 - seg(t, 10.1, 10.6) } : null,
        scan: t >= 6.7 && t < 7.9
      };
    }

    // ---------- personajes ----------
    const STR = [0, 1, 2, 1, 0, -1, -2, -1];
    function leg(c, hx, y, s, col) {
      const hip = y - 9;
      R(c, hx + Math.round(s / 2), hip, 2, 4, col);
      R(c, hx + s, hip + 4, 2, 4, col);
      R(c, hx + s, y - 2, 2, 1, '#0e0b13');
      R(c, hx + s, y - 1, 3, 1, '#0e0b13');
    }
    function heroSprite(st, walking, arm) {
      const c = hctx;
      c.clearRect(0, 0, 20, 30);
      const x = 5, y = 29;
      const s = walking ? STR[st] : 0;
      const u = y + (walking && (st === 0 || st === 4) ? -1 : 0);
      const aS = walking ? -Math.round(s / 2) : 0;
      leg(c, x + 2, y, -s, '#1c1925');
      leg(c, x + 3, y, s, '#2b2638');
      // brazo de atrás
      R(c, x + 2 - aS, u - 15, 2, 5, '#a9b0c4'); R(c, x + 2 - aS, u - 10, 2, 1, '#c4865f');
      // camisa
      R(c, x + 1, u - 16, 6, 7, '#eef0f6'); R(c, x + 1, u - 16, 1, 7, '#b9c0d2');
      R(c, x + 5, u - 16, 1, 2, '#eab28a');
      // fajín rojo
      R(c, x + 1, u - 10, 6, 2, '#d8344a'); R(c, x + 1, u - 9, 6, 1, '#a8243a');
      R(c, x - (walking && st % 4 < 2 ? 1 : 0), u - 9, 1, 3, '#a8243a');
      // cabeza
      R(c, x + 3, u - 17, 2, 1, '#c4865f');
      R(c, x + 2, u - 22, 5, 5, '#eab28a'); R(c, x + 2, u - 18, 2, 1, '#c4865f');
      R(c, x + 7, u - 20, 1, 1, '#eab28a');
      R(c, x + 5, u - 20, 1, 1, '#1a1420');
      // pelo
      R(c, x + 1, u - 23, 6, 2, '#f0c66b'); R(c, x + 1, u - 21, 2, 4, '#f0c66b');
      R(c, x + 6, u - 22, 1, 2, '#f0c66b'); R(c, x + 1, u - 18, 2, 1, '#b98a3a');
      R(c, x + 3, u - 23, 2, 1, '#ffe39a');
      // brazo de delante
      if (arm > 0.5) {
        R(c, x + 4, u - 15, 5, 2, '#eef0f6'); R(c, x + 9, u - 15, 1, 2, '#eab28a');
      } else if (arm > 0.15) {
        R(c, x + 4, u - 15, 2, 2, '#eef0f6'); R(c, x + 6, u - 13, 2, 2, '#eef0f6'); R(c, x + 8, u - 12, 1, 1, '#eab28a');
      } else {
        R(c, x + 3 + aS, u - 15, 2, 5, '#eef0f6'); R(c, x + 3 + aS, u - 10, 2, 1, '#eab28a');
      }
    }
    function bouncer(c, x, y, v, b, scanColor) {
      const skin = v ? '#8a5a3c' : '#d39a74', skinD = v ? '#6b4329' : '#a8724f';
      const suit = '#181a26', suitD = '#0f1019', suitL = '#262a3d';
      x = Math.round(x); y = Math.round(y);
      R(c, x + 2, y - 10, 2, 9, suit); R(c, x + 6, y - 10, 2, 9, suit);
      R(c, x + 1, y - 1, 3, 1, '#06050a'); R(c, x + 6, y - 1, 3, 1, '#06050a');
      const u = y - b;
      R(c, x, u - 20, 10, 11, suit); R(c, x, u - 20, 1, 11, suitD); R(c, x + 9, u - 20, 1, 11, suitD);
      R(c, x + 3, u - 20, 4, 3, '#e6e8ef'); R(c, x + 4, u - 20, 2, 1, '#7a1a33'); R(c, x + 4, u - 19, 2, 4, '#5a1225');
      R(c, x + 2, u - 20, 1, 4, suitL); R(c, x + 7, u - 20, 1, 4, suitL);
      R(c, x - 1, u - 19, 1, 8, suit); R(c, x + 10, u - 19, 1, 8, suit);
      R(c, x, u - 14, 10, 3, suitL); R(c, x + 1, u - 13, 2, 1, skin); R(c, x + 7, u - 14, 2, 1, skin);
      R(c, x, u - 10, 10, 1, '#0a0a10');
      R(c, x + 4, u - 21, 2, 1, skinD);
      R(c, x + 2, u - 27, 6, 6, skin); R(c, x + 2, u - 22, 6, 1, skinD); R(c, x + 4, u - 23, 2, 1, skinD);
      R(c, x + 2, u - 25, 6, 2, '#05050a'); R(c, x + 3, u - 25, 1, 1, scanColor || '#7ff6ff');
      if (v) R(c, x + 2, u - 28, 6, 2, '#15100c'); else R(c, x + 4, u - 27, 2, 1, '#ecb995');
      R(c, x + 1, u - 25, 1, 2, skinD); R(c, x + 1, u - 24, 1, 1, '#cfd3dd'); R(c, x, u - 23, 1, 3, '#8a90a0');
      return u;
    }
    function bubble(cx, tipY, full, n) {
      const w = tw(full) + 6, h = 11;
      const x = Math.round(Math.min(Math.max(cx - w / 2, 2), W - w - 2));
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
    function flick(t, s) {
      const v = Math.sin(t * 11.3 + s) + Math.sin(t * 4.7 + s * 2.1) + Math.sin(t * 23.1 + s * 0.7);
      return v > 2.2 ? 0.2 : 1;
    }

    // ---------- lluvia ----------
    const rr = rng(99);
    const newDrop = init => ({ x: rr() * (W + 60), y: init ? rr() * H : -rr() * 40, v: 140 + rr() * 80, l: 2 + ((rr() * 3) | 0), g: 150 + rr() * 30 });
    const drops = Array.from({ length: 150 }, () => newDrop(true));
    let splashes = [];
    function updateRain(dt) {
      for (const d of drops) {
        d.y += d.v * dt; d.x -= d.v * dt * 0.22;
        if (d.y >= d.g) {
          if (d.x >= 0 && d.x < W) splashes.push({ x: d.x | 0, y: d.g | 0, a: 0 });
          Object.assign(d, newDrop(false));
        }
      }
      splashes.forEach(s => s.a += dt);
      splashes = splashes.filter(s => s.a < 0.18);
    }
    function drawRain(vctx) {
      vctx.fillStyle = 'rgba(170,195,255,0.5)';
      for (const d of drops)
        for (let i = 0; i < d.l; i++) vctx.fillRect(Math.round(d.x + i * 0.22), Math.round(d.y - i), 1, 1);
      vctx.fillStyle = 'rgba(200,215,255,0.6)';
      for (const s of splashes) {
        if (s.a < 0.09) { vctx.fillRect(s.x - 1, s.y - 1, 1, 1); vctx.fillRect(s.x + 1, s.y - 1, 1, 1); }
        else { vctx.fillRect(s.x - 2, s.y, 1, 1); vctx.fillRect(s.x + 2, s.y, 1, 1); }
      }
    }

    // ---------- fotograma ----------
    function render(vctx, t) {
      const dt = lastT === null || t < lastT || t - lastT > 0.1 ? 0.016 : t - lastT;
      if (t !== lastT) updateRain(dt);
      lastT = t;
      const S = state(t);
      ctx.drawImage(bg, 0, 0);

      // tele en la ventana
      const tv = 0.5 + 0.5 * Math.sin(t * 9) * Math.sin(t * 2.3);
      ctx.fillStyle = `rgba(90,140,255,${0.2 + 0.35 * tv})`;
      ctx.fillRect(64, 36, 18, 18);
      R(ctx, 73, 36, 1, 18, '#1a0f18'); R(ctx, 64, 45, 18, 1, '#1a0f18');

      glow(BAR, 63, 89, '#ff3ea5', 3, flick(t, 4.2) < 1 ? 0.4 : 1);

      // puerta
      const o = S.door;
      ctx.save();
      ctx.beginPath(); ctx.rect(144, 98, 32, 52); ctx.clip();
      R(ctx, 144, 98, 32, 52, '#1b0b22');
      if (o > 0) {
        ctx.globalAlpha = o;
        R(ctx, 144, 98, 32, 52, '#4a1842');
        R(ctx, 148, 106, 24, 44, '#6d2660');
        R(ctx, 144, 102, 32, 2, '#ff9ad5');
        for (let i = 0; i < 4; i++) R(ctx, 149 + i * 6, 110, 2, 2, i % 2 ? '#3ee8ff' : '#ffd36b');
        R(ctx, 150, 140, 20, 10, '#2a0f2e');
        ctx.globalAlpha = 1;
      }
      const sl = Math.round(o * 16);
      for (const px of [144 - sl, 160 + sl]) {
        R(ctx, px, 98, 16, 52, '#141428');
        R(ctx, px + 2, 100, 12, 46, '#1b2142');
        for (let k = 0; k < 8; k++) R(ctx, px + 3 + k, 104 + k * 2, 1, 2, '#2f5f73');
        R(ctx, px, 98, 1, 52, '#0c0c1a');
      }
      ctx.restore();
      glowRects('#3ee8ff', 5, [[143, 97, 34, 1], [143, 97, 1, 53], [176, 97, 1, 53]]);
      if (o > 0) {
        ctx.fillStyle = `rgba(255,90,180,${0.22 * o})`;
        ctx.beginPath(); ctx.moveTo(144, 150); ctx.lineTo(176, 150); ctx.lineTo(190, 160); ctx.lineTo(130, 160); ctx.closePath(); ctx.fill();
      }

      // rótulo
      R(ctx, 140, 50, 1, 4, '#3a3346'); R(ctx, 179, 50, 1, 4, '#3a3346');
      R(ctx, 124, 54, 72, 34, '#170d20');
      glowRects('#ff3ea5', 4, [[124, 54, 72, 1], [124, 87, 72, 1], [124, 54, 1, 34], [195, 54, 1, 34]]);
      NOBS.forEach((spr, i) => {
        const a = i === 2 ? flick(t, 1.3) : 1;
        if (a < 1) { ctx.globalAlpha = a; ctx.drawImage(spr, 134 + i * 14, 58); ctx.globalAlpha = 1; }
        else glow(spr, 134 + i * 14, 58, '#ff3ea5', 6);
      });
      glow(TAG, 160 - Math.round(TAG.width / 2), 77, '#3ee8ff', 3);

      // placa
      R(ctx, 194, 99, 46, 11, '#120d1c');
      R(ctx, 194, 99, 46, 1, '#1f6b78'); R(ctx, 194, 109, 46, 1, '#1f6b78');
      R(ctx, 194, 99, 1, 11, '#1f6b78'); R(ctx, 239, 99, 1, 11, '#1f6b78');
      glow(SOCIOS, 217 - Math.round(SOCIOS.width / 2), 102, '#3ee8ff', 3);

      // porteros
      const blink = Math.floor(t / 0.15) % 2 ? '#ff4d6d' : '#7ff6ff';
      const bLx = 128 - 6 * S.out, bRx = 182 + 8 * S.out, bY = GY - Math.round(3 * S.out);
      const br = v => (Math.sin(t * 2.1 + v * 1.7) > 0.3 ? 1 : 0);
      const uL = bouncer(ctx, bLx, bY, 0, br(0), S.scan ? blink : null);
      const uR = bouncer(ctx, bRx, bY, 1, br(1), null);

      // protagonista
      if (S.show) {
        heroSprite(Math.floor(t * 10) % 8, S.walking, S.arm);
        if (S.sil > 0) {
          hctx.globalCompositeOperation = 'source-atop';
          hctx.fillStyle = `rgba(36,6,34,${S.sil * 0.9})`;
          hctx.fillRect(0, 0, 20, 30);
          hctx.globalCompositeOperation = 'source-over';
        }
        ctx.globalAlpha = S.alpha;
        ctx.drawImage(hc, Math.round(S.hx) - 5, HY - 29 + S.hy);
        ctx.globalAlpha = 1;
        if (S.arm > 0.5) {
          glow(CARD, S.hx + 9, HY - 18, '#ffc94a', 4, 0.8 + 0.2 * Math.sin(t * 12));
          if (S.scan) {
            // haz del escáner: de las gafas del portero a la invitación
            const x0 = Math.round(bLx) + 3, y0 = uL - 25, x1 = Math.round(S.hx) + 11, y1 = HY - 17;
            const n = Math.max(Math.abs(x1 - x0), Math.abs(y1 - y0));
            ctx.fillStyle = 'rgba(255,77,109,0.85)';
            for (let k = 0; k <= n; k += 2) ctx.fillRect(Math.round(x0 + (x1 - x0) * k / n), Math.round(y0 + (y1 - y0) * k / n), 1, 1);
          }
        }
      }

      if (S.chk) {
        glow(CHECK, Math.round(S.hx), HY - 34 + Math.round((1 - S.chk.pop) * 4), '#4dff8a', 5, S.chk.fade * Math.min(1, S.chk.pop * 2));
      }
      if (S.b1 >= 0) bubble(Math.round(bLx) + 5, uL - 30, '¿CREDENCIALES?', S.b1);
      if (S.b2 >= 0) bubble(Math.round(bRx) + 5, uR - 30, 'ADELANTE', S.b2);

      // a pantalla: escena + reflejos en el asfalto + lluvia
      vctx.drawImage(buf, 0, 0);
      for (let r = 0; r < 16; r++) {
        const off = Math.round(Math.sin(t * 2.6 + r * 0.9) * 1.2);
        vctx.globalAlpha = 0.3 * (1 - r / 16);
        vctx.drawImage(buf, 112, 88 - r * 2, 96, 1, 112 + off, 164 + r, 96, 1);
        vctx.drawImage(buf, 8, 70 - r * 2, 20, 1, 8 + off, 164 + r, 20, 1);
      }
      vctx.globalAlpha = 1;
      drawRain(vctx);
    }

    return { L, render };
  }
});
