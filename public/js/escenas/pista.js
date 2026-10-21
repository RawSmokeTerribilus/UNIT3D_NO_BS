Escenas.registrar({
  id: 'pista',
  titulo: 'La pista',
  zona: 'Juegos · RetroArch',
  fotograma: 9.0,
  ejemplo: {
    juegos: 1234,
    sistemas: [{ nombre: 'NES' }, { nombre: 'SNES' }, { nombre: 'Sega' }, { nombre: 'GB' }]
  },
  crear(M, D) {
    const { W, H, mk, rng, R, clamp, seg, tw, txt, spriteText, glowImg, glowRects, flick, drawPerson, bubble, miles } = M;

    const WW = 660;
    const HY = 156;
    const V = 46;
    const BEAT = 60 / 128;     // 128 pulsaciones por minuto
    const DJY = 133;           // el DJ, de pie detrás de su mesa
    const HERO = { hair: '#f0c66b', hairD: '#b98a3a', hairL: '#ffe39a', skin: '#eab28a', skinD: '#c4865f', shirt: '#eef0f6', shirtD: '#b9c0d2', sleeveB: '#a9b0c4', sash: '#d8344a', sashD: '#a8243a', leg: '#2b2638', legB: '#1c1925', boot: '#0e0b13', style: 'pony' };
    const DJ = { hair: '#c0303a', skin: '#c99068', skinD: '#a06e4c', shirt: '#3ab0a0', shirtD: '#2a8478', sleeveB: '#2a8478', sash: null, leg: '#2a2e50', legB: '#1e2240', boot: '#100c14', style: 'short' };
    const DANCERS = [
      { x: 214, y: 151, ph: 0.0, flip: false, P: { hair: '#1a1410', skin: '#d8a27c', skinD: '#b07a58', shirt: '#d8344a', shirtD: '#a8243a', sleeveB: '#a8243a', sash: null, leg: '#2d3a5a', legB: '#202a44', boot: '#120e0a', style: 'bun' } },
      { x: 258, y: 158, ph: 0.2, flip: true, P: { hair: '#e0c070', skin: '#eab28a', skinD: '#c4865f', shirt: '#2a8a4a', shirtD: '#1d6a38', sleeveB: '#1d6a38', sash: null, leg: '#3a3026', legB: '#2a221a', boot: '#100c08', style: 'short' } },
      { x: 342, y: 151, ph: 0.45, flip: true, P: { hair: '#6a3420', skin: '#c99068', skinD: '#a06e4c', shirt: '#6a5acd', shirtD: '#4f42a0', sleeveB: '#4f42a0', sash: null, leg: '#2a2536', legB: '#1e1a28', boot: '#120e16', style: 'bun' } },
      { x: 392, y: 158, ph: 0.7, flip: false, P: { hair: '#2a1a12', skin: '#8a5a3c', skinD: '#6b4329', shirt: '#ffd23e', shirtD: '#c9a024', sleeveB: '#c9a024', sash: null, leg: '#1d1d26', legB: '#16161e', boot: '#0e0b13', style: 'short' } },
      { x: 430, y: 152, ph: 0.3, flip: true, P: { hair: '#b18cff', skin: '#eab28a', skinD: '#c4865f', shirt: '#1d1d26', shirtD: '#14141c', sleeveB: '#14141c', sash: null, leg: '#3a6a9a', legB: '#2a5078', boot: '#120e16', style: 'bun' } }
    ];

    // ----- datos: sistemas de las recreativas y número de juegos -----
    const fit = (s, max) => { s = String(s || '').toUpperCase(); while (tw(s) > max) s = s.slice(0, -1); return s; };
    const SIS = (Array.isArray(D.sistemas) && D.sistemas.length ? D.sistemas : [{ nombre: 'NES' }]).slice(0, 4).map(s => fit(s.nombre, 26));
    while (SIS.length < 4) SIS.push('ARCADE');
    const JUEGOS = Math.max(0, Math.floor(Number(D.juegos) || 0));

    // ----- recorrido -----
    const T0 = 0.3, XF = 300, XA = 490, XE = WW + 20;
    const T1 = T0 + (XF + 14) / V;     // llega al centro de la pista
    const T2 = T1 + 3.6;               // deja de bailar
    const T3 = T2 + (XA - XF) / V;     // delante de la recreativa
    const T4 = T3 + 3.0;               // deja de jugar
    const T5 = T4 + (XE - XA) / V;
    const L = Math.ceil(T5 + 0.5);
    function heroAt(t) {
      if (t < T0) return { x: -14, walking: true };
      if (t < T1) return { x: -14 + (t - T0) * V, walking: true };
      if (t < T2) return { x: XF, dance: true };
      if (t < T3) return { x: XF + (t - T2) * V, walking: true };
      if (t < T4) return { x: XA, play: true };
      return { x: XA + (t - T4) * V, walking: true };
    }
    const LINES = [
      { who: 'dj', t0: T1 - 0.6, t1: T1 + 0.9, s: '¡A BAILAR!' },
      { who: 'hero', t0: T1 + 1.3, t1: T1 + 2.6, s: '¡YUJU!' },
      { who: 'd2', t0: T1 + 2.4, t1: T1 + 3.6, s: '¡QUÉ ESTILO!' },
      { who: 'hero', t0: T3 + 1.5, t1: T3 + 2.9, s: '¡UNA MÁS!' }
    ];
    function dance(t, ph) {
      const b = Math.floor((t + ph) / BEAT) % 4;
      return { mode: ['wave', 'hang', 'wave', 'down'][b], flip: b === 2, hop: b % 2 ? -1 : 0, st: (b * 2) % 8 };
    }

    // ----- capa fija -----
    const [back, bk] = mk(WW, H);
    const CABX = [450, 484, 518, 552];
    const NOVENTA = spriteText('90S', '#ff3ea5', 3);
    const RETRO = spriteText('RETROARCH', '#3ee8ff', 2);
    const SCUMM = spriteText('SCUMM', '#ff3ea5');
    const SBAR = spriteText('BAR', '#ff3ea5');
    (function build() {
      const r = rng(90);
      R(bk, 0, 0, WW, 20, '#0a0814');
      // cercha de focos
      R(bk, 0, 14, WW, 2, '#3a3a48'); R(bk, 0, 20, WW, 1, '#3a3a48');
      for (let x = 0; x < WW; x += 8) { R(bk, x, 15, 1, 6, '#2a2a36'); R(bk, x + 4, 16, 1, 1, '#3a3a48'); }
      // pared de ladrillo morado
      R(bk, 0, 21, WW, 127, '#141228');
      for (let row = 0, y = 24; y < 146; y += 5, row++)
        for (let x = (row % 2) * 7; x < WW; x += 14) R(bk, x, y, 13, 4, r() < 0.3 ? '#1c1836' : '#18152f');
      // altavoces y cabina del DJ
      for (const sx of [70, 162]) {
        R(bk, sx, 86, 22, 62, '#0c0c12'); R(bk, sx, 86, 22, 1, '#2a2a36');
        for (const cy of [98, 120, 138]) { R(bk, sx + 5, cy - 5, 12, 10, '#1a1a24'); }
      }
      // recreativas
      CABX.forEach(cx => {
        R(bk, cx, 104, 30, 46, '#1a1a2e'); R(bk, cx, 104, 30, 1, '#3a3a5a');
        R(bk, cx + 2, 116, 26, 16, '#000000');
        R(bk, cx + 1, 133, 28, 6, '#2a2a46');
        R(bk, cx + 6, 134, 2, 3, '#c9cdd8'); R(bk, cx + 5, 133, 4, 1, '#d8344a');
        R(bk, cx + 16, 135, 2, 2, '#ffd23e'); R(bk, cx + 20, 135, 2, 2, '#3ee8ff'); R(bk, cx + 24, 135, 2, 2, '#4dff8a');
        R(bk, cx + 12, 142, 6, 3, '#0a0a10');
      });
      // puerta del SCUMM BAR
      R(bk, 606, 80, 48, 68, '#2a1a10'); R(bk, 610, 84, 40, 64, '#140810');
      for (let y = 88; y < 148; y += 3) R(bk, 612, y, 36, 1, 'rgba(255,170,90,0.08)');
      // suelo oscuro (la pista se pinta en vivo)
      R(bk, 0, 148, WW, 32, '#0e0c18');
    })();

    // mini juegos en las pantallas de las recreativas
    function screen(ctx, i, x, y, t, playing) {
      R(ctx, x, y, 26, 16, '#05050c');
      if (i === 0) {
        const bx = 4 + Math.abs(((t * 30) % 36) - 18), by = 2 + Math.abs(((t * 22) % 24) - 12);
        R(ctx, x + 1, y + 4 + Math.round(Math.sin(t * 3) * 3), 1, 5, '#e8eef5'); R(ctx, x + 24, y + 4 + Math.round(Math.sin(t * 3 + 1) * 3), 1, 5, '#e8eef5');
        R(ctx, x + Math.round(bx), y + Math.round(by), 1, 1, '#ffffff');
        for (let k = 0; k < 16; k += 3) R(ctx, x + 13, y + k, 1, 2, '#3a3a5a');
      } else if (i === 1) {
        const off = Math.round(Math.sin(t * 1.5) * 3);
        for (let k = 0; k < 5; k++) R(ctx, x + 4 + k * 4 + off, y + 2 + (Math.floor(t * 2) % 2), 3, 2, '#4dff8a');
        R(ctx, x + 11 + Math.round(Math.sin(t * 2.3) * 8), y + 13, 4, 2, '#3ee8ff');
        if (playing) {
          R(ctx, x + 13 + Math.round(Math.sin(t * 2.3) * 8), y + 13 - ((t * 20) % 10), 1, 2, '#ffffff');
          txt(ctx, String(Math.floor((t * 370) % 10000)), x + 1, y + 7, '#ffd23e');
        }
      } else if (i === 2) {
        const n = 8, hd = Math.floor(t * 8);
        for (let k = 0; k < n; k++) {
          const p = (hd - k + 400) % 40;
          const sx = p < 20 ? p : 39 - p, sy = p < 20 ? 5 : 10;
          R(ctx, x + 3 + sx, y + sy, 1, 1, k ? '#4dff8a' : '#ffffff');
        }
        R(ctx, x + 18, y + 12, 1, 1, '#ff3a4a');
      } else if (Math.floor(t * 2) % 2) {
        txt(ctx, 'INSERT', x + 1, y + 2, '#ff3ea5'); txt(ctx, 'COIN', x + 5, y + 9, '#ff3ea5');
      }
      ctx.fillStyle = 'rgba(0,0,0,0.3)';
      for (let k = 0; k < 16; k += 2) ctx.fillRect(x, y + k, 26, 1);
    }

    const [buf, ctx] = mk(W, H);
    const COLS = ['#ff3ea5', '#3ee8ff', '#ffd23e', '#4dff8a', '#b18cff'];
    function render(vctx, t) {
      const hs = heroAt(t);
      const cam = Math.round(clamp(hs.x - 130, 0, WW - W));
      const beat = Math.floor(t / BEAT), inBeat = (t % BEAT) / BEAT;
      const st = Math.floor(t * 10) % 8;
      ctx.clearRect(0, 0, W, H);
      ctx.save();
      ctx.translate(-cam, 0);
      ctx.drawImage(back, 0, 0);

      // pista que se enciende al ritmo
      for (let j = 0; j < 4; j++) for (let i = 0; i < 18; i++) {
        const x = 196 + i * 14, y = 148 + j * 8;
        const on = (i + j + beat) % 3 === 0 || (i - j + beat * 2) % 7 === 0;
        R(ctx, x, y, 13, 7, on ? COLS[(i + j + beat) % COLS.length] : '#1a1630');
        if (on) R(ctx, x, y, 13, 1, 'rgba(255,255,255,0.45)');
      }
      // focos de colores que barren desde la cercha
      ctx.save();
      ctx.globalCompositeOperation = 'lighter';
      [[230, 0, '#ff3ea5'], [300, 1.7, '#3ee8ff'], [370, 3.1, '#ffd23e'], [440, 4.4, '#4dff8a']].forEach(([sx, ph, col]) => {
        const tx = sx + Math.sin(t * 1.1 + ph) * 60;
        ctx.globalAlpha = 0.10;
        ctx.fillStyle = col;
        ctx.beginPath(); ctx.moveTo(sx - 2, 20); ctx.lineTo(sx + 2, 20); ctx.lineTo(tx + 18, 160); ctx.lineTo(tx - 18, 160); ctx.closePath(); ctx.fill();
      });
      ctx.restore();
      [230, 300, 370, 440].forEach(sx => R(ctx, sx - 3, 18, 7, 4, '#2a2a36'));

      // bola de espejos y destellos por las paredes
      const bx = 320, by = 34;
      R(ctx, bx, 21, 1, 4, '#6a6a72');
      for (let y = -8; y <= 8; y++) for (let x = -8; x <= 8; x++) {
        if (x * x + y * y > 70) continue;
        const lit = ((x + Math.floor(t * 6) + 40) % 4 === 0) !== ((y + 40) % 4 === 0);
        R(ctx, bx + x, by + y, 1, 1, lit ? '#e8eef5' : ((x + y) & 1 ? '#8a90a0' : '#5a6070'));
      }
      const sr = rng(beat * 7 + 3);
      ctx.fillStyle = '#ffffff';
      for (let k = 0; k < 26; k++) ctx.fillRect(Math.round(cam + sr() * W), Math.round(24 + sr() * 120), 1, 1);

      // neones
      glowImg(ctx, NOVENTA, 300 - (NOVENTA.width >> 1), 54, '#ff3ea5', 7, flick(t, 1.9));
      glowImg(ctx, RETRO, 501 - (RETRO.width >> 1), 82, '#3ee8ff', 5);
      if (JUEGOS > 0) { const j = miles(JUEGOS) + ' JUEGOS'; txt(ctx, j, 501 - (tw(j) >> 1), 97, '#9ff4ff'); }
      glowImg(ctx, SCUMM, 630 - (SCUMM.width >> 1), 66, '#ff3ea5', 4);
      glowImg(ctx, SBAR, 630 - (SBAR.width >> 1), 73, '#ff3ea5', 4);

      // altavoces que laten
      const pump = inBeat < 0.25 ? 1 : 0;
      for (const sx of [70, 162]) for (const cy of [98, 120, 138]) {
        R(ctx, sx + 7 - pump, cy - 3 - pump, 8 + pump * 2, 6 + pump * 2, '#3a3a4a'); R(ctx, sx + 10, cy - 1, 2, 2, '#6a6a7a');
      }
      // DJ con cascos
      const djUp = Math.floor(t / (BEAT * 2)) % 2 === 0;
      const djb = DJY - (inBeat < 0.5 ? 1 : 0);
      drawPerson(ctx, 122, djb, 0, false, djUp ? 'wave' : 'tray', DJ);
      R(ctx, 123, djb - 24, 6, 1, '#1a1a1a'); R(ctx, 123, djb - 23, 1, 4, '#1a1a1a'); R(ctx, 122, djb - 22, 2, 3, '#2a2a36');
      // mesa del DJ por delante de él, con los dos platos
      R(ctx, 96, 121, 62, 27, '#1a1428'); R(ctx, 96, 121, 62, 2, '#2a2440');
      R(ctx, 104, 117, 16, 4, '#2a2a36'); R(ctx, 134, 117, 16, 4, '#2a2a36');
      R(ctx, 106, 116, 12, 2, '#1a1a1a'); R(ctx, 110 + Math.round(Math.sin(t * 9) * 2), 116, 4, 1, '#c9cdd8');
      R(ctx, 136, 116, 12, 2, '#1a1a1a'); R(ctx, 140, 116, 4, 1, '#c9cdd8');
      glowRects(ctx, COLS[beat % COLS.length], 4, [[96, 130, 62, 1]]);

      // recreativas: rótulo del sistema y pantalla
      CABX.forEach((cx, i) => {
        const col = COLS[(i + 1) % COLS.length];
        R(ctx, cx + 1, 106, 28, 8, col);
        txt(ctx, SIS[i], cx + 15 - (tw(SIS[i]) >> 1), 107, '#0a0814');
        screen(ctx, i, cx + 2, 116, t, i === 1 && !!hs.play);
        if (Math.floor(t * 3 + i) % 2) R(ctx, cx + 13, 143, 4, 1, '#ff3a4a');
      });

      // bailarines
      DANCERS.forEach(d => {
        const p = dance(t, d.ph);
        drawPerson(ctx, d.x, d.y + p.hop, p.st, true, p.mode, d.P, { flip: d.flip !== p.flip });
      });
      // protagonista
      if (hs.dance) {
        const p = dance(t, 0.1);
        drawPerson(ctx, hs.x, HY + p.hop, p.st, true, p.mode, HERO, { flip: p.flip });
      } else {
        drawPerson(ctx, hs.x, HY, st, !!hs.walking, hs.play ? 'tray' : 'down', HERO);
      }

      // bocadillos
      const heads = {
        dj: [127, DJY - 27], d2: [DANCERS[1].x + 4, DANCERS[1].y - 27],
        hero: [Math.round(hs.x) + 5, HY - 27]
      };
      for (const l of LINES) {
        if (t < l.t0 || t >= l.t1) continue;
        const n = Math.floor(seg(t, l.t0, l.t0 + 0.4) * [...l.s].length + 0.001);
        const [cx, tip] = heads[l.who];
        bubble(ctx, cx, tip, l.s, n, cam, cam + W);
      }
      ctx.restore();

      // primer plano: público con las manos arriba
      ctx.save();
      ctx.translate(-Math.round(cam * 1.3), 0);
      for (let k = 0; k < 18; k++) {
        const px = 30 + k * 48, up = (Math.floor((t + k * 0.13) / BEAT) + k) % 2;
        const yy = 168 - (up ? 2 : 0);
        R(ctx, px, yy, 8, 8, '#07060e'); R(ctx, px - 2, yy + 7, 12, 10, '#07060e');
        if (up) { R(ctx, px - 3, yy - 8, 2, 9, '#07060e'); R(ctx, px + 9, yy - 8, 2, 9, '#07060e'); }
        R(ctx, px + 2, yy, 4, 1, '#3a1a3a');
      }
      ctx.restore();

      vctx.drawImage(buf, 0, 0);
    }
    return { L, render };
  }
});
