Escenas.registrar({
  id: 'scumm',
  titulo: 'El SCUMM Bar',
  zona: 'Juegos · ScummVM',
  fotograma: 12.2,
  ejemplo: { juegos: 60 },
  crear(M, D) {
    const { W, H, mk, rng, R, clamp, seg, tw, txt, spriteText, glowImg, glowRects, flick, drawPerson, bubble, miles } = M;

    const WW = 500;
    const HY = 132;         // pies del protagonista
    const UIY = 136;        // empieza la interfaz de verbos
    const V = 46;
    const HERO = { hair: '#f0c66b', hairD: '#b98a3a', hairL: '#ffe39a', skin: '#eab28a', skinD: '#c4865f', shirt: '#eef0f6', shirtD: '#b9c0d2', sleeveB: '#a9b0c4', sash: '#d8344a', sashD: '#a8243a', leg: '#2b2638', legB: '#1c1925', boot: '#0e0b13', style: 'pony' };
    const PIR = [
      { x: 310, base: 126, flip: false, P: { hair: '#1a1410', skin: '#c99068', skinD: '#a06e4c', shirt: '#7a1a33', shirtD: '#5a1225', sleeveB: '#5a1225', sash: '#c9a24a', sashD: '#8a6a2a', leg: '#1d1d26', legB: '#16161e', boot: '#0e0b13', style: 'short', beard: '#1a1410' } },
      { x: 336, base: 120, flip: false, P: { hair: '#6a6a72', skin: '#d8a27c', skinD: '#b07a58', shirt: '#2a3a7a', shirtD: '#1d2a5a', sleeveB: '#1d2a5a', sash: '#c9a24a', sashD: '#8a6a2a', leg: '#1d1d26', legB: '#16161e', boot: '#0e0b13', style: 'short', beard: '#c9c9d2' } },
      { x: 362, base: 126, flip: true, P: { hair: '#3a2418', skin: '#8a5a3c', skinD: '#6b4329', shirt: '#2a5a3a', shirtD: '#1d4028', sleeveB: '#1d4028', sash: '#c9a24a', sashD: '#8a6a2a', leg: '#1d1d26', legB: '#16161e', boot: '#0e0b13', style: 'short', patch: true } }
    ];
    const POLES = [
      { x: 164, ph: 0, P: { hair: '#ff3ea5', skin: '#eab28a', skinD: '#c4865f', shirt: '#3ee8ff', shirtD: '#2ab0c8', sleeveB: '#2ab0c8', sash: null, leg: '#ff3ea5', legB: '#c0287a', boot: '#f4f4f8', style: 'bun' } },
      { x: 216, ph: 1.6, P: { hair: '#1a1410', skin: '#8a5a3c', skinD: '#6b4329', shirt: '#ffd23e', shirtD: '#c9a024', sleeveB: '#c9a024', sash: null, leg: '#b18cff', legB: '#8a68d0', boot: '#f4f4f8', style: 'bun' } }
    ];
    const BOOTH = [
      { x: 36, flip: false, P: { hair: '#e0c070', skin: '#eab28a', skinD: '#c4865f', shirt: '#4a6a9a', shirtD: '#3a5078', sleeveB: '#3a5078', sash: null, leg: '#2a2536', legB: '#1e1a28', boot: '#120e16', style: 'bun' } },
      { x: 84, flip: true, P: { hair: '#2a1a12', skin: '#c99068', skinD: '#a06e4c', shirt: '#9a3a2a', shirtD: '#7a2a1a', sleeveB: '#7a2a1a', sash: null, leg: '#2d3a5a', legB: '#202a44', boot: '#120e0a', style: 'short' } }
    ];
    const JUEGOS = Math.max(0, Math.floor(Number(D.juegos) || 0));

    // ----- recorrido -----
    const T0 = 0.3, XS = 150, XP = 290, XE = WW + 20;
    const T1 = T0 + (XS + 14) / V;
    const T2 = 7.6;
    const T3 = T2 + (XP - XS) / V;
    const T4 = 18.6;
    const T5 = T4 + (XE - XP) / V;
    const L = Math.ceil(T5 + 0.5);
    function heroAt(t) {
      if (t < T0) return { x: -14, walking: true };
      if (t < T1) return { x: -14 + (t - T0) * V, walking: true };
      if (t < T2) return { x: XS, walking: false };
      if (t < T3) return { x: XS + (t - T2) * V, walking: true };
      if (t < T4) return { x: XP, walking: false };
      return { x: XP + (t - T4) * V, walking: true };
    }
    const LINES = [
      { who: 'hero', t0: 5.4, t1: 7.0, s: '¡CUÁNTO EQUILIBRIO!' },
      { who: 'hero', t0: 11.0, t1: 12.7, s: 'SOY GUYBRUSH THREEPWOOD' },
      { who: 'p0', t0: 12.8, t1: 14.6, s: '¡QUÉ NOMBRE MÁS RIDÍCULO!' },
      { who: 'p1', t0: 14.7, t1: 16.3, s: 'SUPERA LAS TRES PRUEBAS' },
      { who: 'p2', t0: 17.3, t1: 18.7, s: '¡GROG, GROG, GROG!' }
    ];

    // ----- interfaz SCUMM: verbos, frase e inventario -----
    const VERBS = [
      ['DAR', 10, 148], ['ABRIR', 52, 148], ['CERRAR', 98, 148],
      ['COGER', 10, 158], ['MIRAR', 52, 158], ['HABLAR', 98, 158],
      ['USAR', 10, 168], ['EMPUJAR', 52, 168], ['TIRAR', 98, 168]
    ];
    const SLOT = i => [150 + (i % 4) * 42, 147 + Math.floor(i / 4) * 16];
    function sentence(t) {
      if (t >= 4.4 && t < 6.8) return 'MIRAR ESCENARIO';
      if (t >= 7.6 && t < 15.6) return 'HABLAR CON PIRATAS IMPORTANTES';
      if (t >= 16.9 && t < 18.4) return 'DAR GROG A PIRATAS IMPORTANTES';
      if (t >= 16.5 && t < 16.9) return 'DAR GROG A';
      if (t >= 15.8 && t < 16.5) return 'DAR';
      if (t >= 18.4) return 'IR A PUERTA';
      return 'IR A';
    }
    function verbOn(t) {
      if (t >= 3.8 && t < 6.8) return 'MIRAR';
      if (t >= 7.0 && t < 15.6) return 'HABLAR';
      if (t >= 15.8 && t < 18.4) return 'DAR';
      return '';
    }
    // cursor: fotogramas clave en pantalla (ui) o en el mundo (world)
    const CUR = [
      [0.0, 'ui', 250, 60], [3.5, 'ui', 62, 160], [4.4, 'world', 190, 64],
      [6.6, 'ui', 110, 160], [7.6, 'world', 336, 100], [15.4, 'ui', 18, 150],
      [16.2, 'ui', 166, 154], [16.9, 'world', 336, 100], [18.4, 'world', 450, 92], [30, 'world', 450, 92]
    ];
    const CLICKS = [3.8, 7.0, 15.8, 16.5, 16.9, 18.4];
    function cursorAt(t, cam) {
      const pos = k => (k[1] === 'world' ? [k[2] - cam, k[3]] : [k[2], k[3]]);
      for (let i = 0; i < CUR.length - 1; i++) {
        const a = CUR[i], b = CUR[i + 1];
        if (t < b[0]) {
          const pa = pos(a), pb = pos(b);
          const dur = Math.min(0.6, b[0] - a[0]);
          const k = clamp((t - (b[0] - dur)) / dur, 0, 1);
          const e = k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
          return [Math.round(pa[0] + (pb[0] - pa[0]) * e), Math.round(pa[1] + (pb[1] - pa[1]) * e)];
        }
      }
      return pos(CUR[CUR.length - 1]);
    }
    // objetos del inventario
    const ITEMS = [
      (c, x, y) => { R(c, x + 2, y + 2, 4, 9, '#5a3416'); R(c, x + 3, y, 2, 2, '#5a3416'); R(c, x + 2, y + 5, 4, 3, '#e8dcb0'); R(c, x + 3, y + 6, 1, 1, '#111'); },
      (c, x, y) => { R(c, x, y + 4, 9, 4, '#ffd23e'); R(c, x + 8, y + 2, 3, 3, '#ffd23e'); R(c, x + 11, y + 3, 2, 1, '#ff8a3e'); R(c, x + 9, y + 1, 1, 1, '#d8344a'); R(c, x - 1, y + 5, 2, 1, '#ffd23e'); R(c, x + 3, y + 1, 3, 3, '#9aa3b5'); R(c, x + 4, y + 2, 1, 1, '#000'); R(c, x + 2, y + 8, 1, 3, '#ff8a3e'); R(c, x + 6, y + 8, 1, 3, '#ff8a3e'); },
      (c, x, y) => { R(c, x, y + 1, 12, 9, '#e0cc98'); R(c, x, y + 1, 1, 9, '#c0a870'); R(c, x + 3, y + 3, 4, 1, '#8a6a3a'); R(c, x + 4, y + 6, 1, 1, '#8a6a3a'); R(c, x + 8, y + 5, 3, 3, '#d8344a'); R(c, x + 9, y + 6, 1, 1, '#e0cc98'); },
      (c, x, y) => { for (const [dx, dy] of [[0, 4], [5, 2], [3, 7], [8, 6]]) { R(c, x + dx, y + dy, 4, 4, '#e8c23a'); R(c, x + dx + 1, y + dy + 1, 1, 1, '#fff3a0'); } }
    ];
    const ITEM_NAMES = ['GROG', 'POLLO CON POLEA', 'MAPA', 'PIEZAS DE A OCHO'];

    // ----- capa fija -----
    const [back, bk] = mk(WW, UIY);
    const SIGN = spriteText('SCUMM BAR', '#ff3ea5', 2);
    const PRIV = spriteText('PRIVADO', '#ffb347');
    (function build() {
      const r = rng(55);
      R(bk, 0, 0, WW, 12, '#140a06');
      for (let x = 0; x < WW; x += 36) R(bk, x, 0, 6, 12, '#2a1a10');
      R(bk, 0, 12, WW, 96, '#2a1810');
      for (let x = 0; x < WW; x += 9) R(bk, x, 12, 1, 96, '#22140c');
      for (let i = 0; i < 160; i++) R(bk, (r() * WW) | 0, 12 + ((r() * 94) | 0), 1, 1, '#33200f');
      R(bk, 0, 12, WW, 2, '#4a3020');
      // escenario: telón, tarima y barras
      R(bk, 136, 14, 108, 86, '#5a0f1c');
      for (let x = 138; x < 244; x += 6) { R(bk, x, 14, 2, 86, '#7a1a2a'); R(bk, x + 4, 14, 1, 86, '#3a0a14'); }
      R(bk, 132, 100, 116, 8, '#3a2418'); R(bk, 132, 100, 116, 1, '#5a3a24');
      for (const px of [164, 216]) { R(bk, px, 14, 2, 86, '#c9a24a'); R(bk, px, 14, 1, 86, '#fff3a0'); R(bk, px - 2, 98, 6, 2, '#8a6a2a'); }
      // reservado de la izquierda
      R(bk, 22, 80, 84, 28, '#7a1a2a'); R(bk, 22, 80, 84, 2, '#9a2a3a');
      for (let x = 26; x < 104; x += 10) R(bk, x, 84, 1, 22, '#5a0f1c');
      // puerta PRIVADO
      R(bk, 428, 58, 44, 50, '#3a2418'); R(bk, 432, 62, 36, 46, '#1a0e08');
      R(bk, 448, 84, 4, 4, '#c9a24a');
      // suelo de tablones
      R(bk, 0, 108, WW, 28, '#3a2416');
      for (let y = 108; y < 136; y += 4) {
        R(bk, 0, y, WW, 1, '#2a1a0e');
        for (let x = ((y / 4) % 3) * 17; x < WW; x += 51) R(bk, x, y, 1, 4, '#2a1a0e');
      }
    })();

    const [buf, ctx] = mk(W, H);
    function render(vctx, t) {
      const hs = heroAt(t);
      const cam = Math.round(clamp(hs.x - 130, 0, WW - W));
      const st = Math.floor(t * 10) % 8;
      ctx.clearRect(0, 0, W, H);
      ctx.save();
      ctx.beginPath(); ctx.rect(0, 0, W, UIY); ctx.clip();
      ctx.translate(-cam, 0);
      ctx.drawImage(back, 0, 0);

      // neones
      glowImg(ctx, SIGN, 64 - (SIGN.width >> 1), 26, '#ff3ea5', 6, flick(t, 2.2));
      if (JUEGOS > 0) { const j = miles(JUEGOS) + ' AVENTURAS'; ctx.save(); ctx.shadowColor = '#3ee8ff'; ctx.shadowBlur = 3; txt(ctx, j, 64 - (tw(j) >> 1), 44, '#9ff4ff'); ctx.restore(); }
      glowImg(ctx, PRIV, 450 - (PRIV.width >> 1), 50, '#ffb347', 4);
      glowRects(ctx, '#ff3ea5', 5, [[132, 100, 116, 1]]);

      // focos del escenario
      ctx.save();
      ctx.globalCompositeOperation = 'lighter';
      for (const [sx, col, ph] of [[150, '#ff3ea5', 0], [230, '#b18cff', 2]]) {
        const tx = 190 + Math.sin(t * 1.4 + ph) * 30;
        ctx.globalAlpha = 0.10; ctx.fillStyle = col;
        ctx.beginPath(); ctx.moveTo(sx - 2, 12); ctx.lineTo(sx + 2, 12); ctx.lineTo(tx + 16, 100); ctx.lineTo(tx - 16, 100); ctx.closePath(); ctx.fill();
      }
      ctx.restore();

      // barras: giran alrededor del poste agarradas con las dos manos
      POLES.forEach(p => {
        const a = t * 2.4 + p.ph, side = Math.cos(a) < 0;
        const x = p.x - 4 + Math.round(Math.sin(a) * 4);
        const mode = Math.floor((t + p.ph) * 2) % 3 === 2 ? 'wave' : 'hang';
        drawPerson(ctx, x, 100 - (mode === 'hang' ? 4 : 0), Math.floor(t * 6) % 8, true, mode, p.P, { flip: side });
      });

      // reservado con su mesa y su vela
      R(ctx, 22, 111, 84, 7, '#7a1a2a'); R(ctx, 22, 111, 84, 1, '#9a2a3a');
      BOOTH.forEach(b => drawPerson(ctx, b.x, 118, 0, false, 'down', b.P, { sit: true, flip: b.flip }));
      R(ctx, 54, 104, 26, 3, '#5a3416'); R(ctx, 65, 107, 4, 11, '#3a2418');
      R(ctx, 66, 100, 2, 4, '#f0e6c0'); glowRects(ctx, '#ffb347', 5, [[66, 98 + (Math.sin(t * 13) > 0.3 ? 1 : 0), 2, 2]]);

      // mesa de los piratas de aspecto importante
      const drawPirate = (p, i) => {
        R(ctx, p.x - 1, p.base - 8, 9, 8, '#5a3416'); R(ctx, p.x - 1, p.base - 6, 9, 1, '#3a3a3a'); R(ctx, p.x - 1, p.base - 2, 9, 1, '#3a3a3a');
        const talk = LINES.some(l => l.who === 'p' + i && t >= l.t0 && t < l.t1);
        drawPerson(ctx, p.x, p.base, 0, false, talk ? 'wave' : 'down', p.P, { sit: true, flip: p.flip });
        R(ctx, p.x, p.base - 25, 9, 2, '#14141a'); R(ctx, p.x + 1, p.base - 28, 7, 3, '#14141a');
        R(ctx, p.x + 4, p.base - 27, 1, 1, '#f4f4f8'); R(ctx, p.x + 1, p.base - 26, 7, 1, '#c9a24a');
      };
      drawPirate(PIR[1], 1);
      R(ctx, 322, 108, 34, 3, '#5a3416'); R(ctx, 322, 108, 34, 1, '#7a4a22'); R(ctx, 337, 111, 4, 15, '#3a2418');
      R(ctx, 330, 102, 4, 6, '#c9a24a'); R(ctx, 330, 101, 4, 2, '#f4ead0'); R(ctx, 344, 103, 4, 5, '#5a3416'); R(ctx, 345, 101, 2, 2, '#5a3416');
      drawPirate(PIR[0], 0); drawPirate(PIR[2], 2);

      // protagonista
      drawPerson(ctx, hs.x, HY, st, !!hs.walking, t >= 16.9 && t < 18.4 ? 'tray' : 'down', HERO);
      if (t >= 16.9 && t < 18.4) ITEMS[0](ctx, Math.round(hs.x) + 8, HY - 22);

      // bocadillos
      for (const l of LINES) {
        if (t < l.t0 || t >= l.t1) continue;
        const n = Math.floor(seg(t, l.t0, l.t0 + 0.4) * [...l.s].length + 0.001);
        let cx, tip;
        if (l.who === 'hero') { cx = Math.round(hs.x) + 5; tip = HY - 27; }
        else { const p = PIR[+l.who.slice(1)]; cx = p.x + 4; tip = p.base - 31; }
        bubble(ctx, cx, tip, l.s, n, cam, cam + W);
      }
      ctx.restore();

      // interfaz SCUMM
      R(ctx, 0, UIY, W, H - UIY, '#000000');
      const [cx, cy] = cursorAt(t, cam);
      const hover = cy >= UIY ? ITEMS.findIndex((_, i) => { const [sx, sy] = SLOT(i); return cx >= sx && cx < sx + 40 && cy >= sy && cy < sy + 14; }) : -1;
      const sen = hover >= 0 && !(t >= 15.8 && t < 18.4) ? ITEM_NAMES[hover] : sentence(t);
      txt(ctx, sen, 160 - (tw(sen) >> 1), UIY + 3, '#d8d8ff');
      const vOn = verbOn(t);
      VERBS.forEach(([v, x, y]) => txt(ctx, v, x, y, v === vOn ? '#ffffff' : '#5a5ad8'));
      for (let i = 0; i < 8; i++) {
        const [sx, sy] = SLOT(i);
        R(ctx, sx, sy, 40, 14, '#0c0c24'); R(ctx, sx, sy, 40, 1, '#1c1c44');
        if (i < ITEMS.length && !(i === 0 && t >= 16.9 && t < 18.4)) ITEMS[i](ctx, sx + 14, sy + 2);
      }
      // cursor de cruz
      const clicked = CLICKS.some(c => t >= c && t < c + 0.18);
      const cc = clicked ? '#ffd23e' : (Math.floor(t * 4) % 2 ? '#ffffff' : '#c9c9d2');
      R(ctx, cx - 4, cy, 3, 1, cc); R(ctx, cx + 2, cy, 3, 1, cc); R(ctx, cx, cy - 4, 1, 3, cc); R(ctx, cx, cy + 2, 1, 3, cc);

      vctx.drawImage(buf, 0, 0);
    }
    return { L, render };
  }
});
