Escenas.registrar({
  id: 'scumm',
  titulo: 'El SCUMM Bar',
  zona: 'Juegos · ScummVM',
  fotograma: 8.9,
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
    const BOUNCERS = [
      { x: 430, P: { hair: '#3a2a20', skin: '#d39a74', skinD: '#a8724f', skinL: '#ecb995', shirt: '#181a26', shirtD: '#0f1019', sleeveB: '#0f1019', sash: null, leg: '#181a26', legB: '#0f1019', boot: '#06050a', style: 'bald' } },
      { x: 482, P: { hair: '#15100c', skin: '#8a5a3c', skinD: '#6b4329', shirt: '#181a26', shirtD: '#0f1019', sleeveB: '#0f1019', sash: null, leg: '#181a26', legB: '#0f1019', boot: '#06050a', style: 'short' } }
    ];
    const BY = 128;          // pies de los porteros
    const JUEGOS = Math.max(0, Math.floor(Number(D.juegos) || 0));

    // ----- recorrido -----
    const T0 = 0.3, XS = 150, XP = 290, XV = 457;
    const T1 = T0 + (XS + 14) / V;
    const T2 = 10.6;                    // deja el escenario
    const T3 = T2 + (XP - XS) / V;      // en la mesa de los piratas
    const T4 = 22.6;                    // camino del reservado
    const T5 = T4 + (XV - XP) / V;      // ante el cordón
    const T6 = 29.5, T7 = 30.7;         // cruza la cortina
    const ROPE = 29.2;                  // sueltan el cordón
    const L = Math.ceil(T7 + 0.8);
    function heroAt(t) {
      if (t < T0) return { x: -14, y: HY, walking: true, alpha: 1 };
      if (t < T1) return { x: -14 + (t - T0) * V, y: HY, walking: true, alpha: 1 };
      if (t < T2) return { x: XS, y: HY, walking: false, alpha: 1 };
      if (t < T3) return { x: XS + (t - T2) * V, y: HY, walking: true, alpha: 1 };
      if (t < T4) return { x: XP, y: HY, walking: false, alpha: 1 };
      if (t < T5) return { x: XP + (t - T4) * V, y: HY, walking: true, alpha: 1 };
      if (t < T6) return { x: XV, y: HY, walking: false, alpha: 1 };
      const k = seg(t, T6, T7);
      return { x: XV, y: Math.round(HY - 8 * k), walking: k < 1, alpha: 1 - k };
    }
    const LINES = [
      { who: 'hero', t0: 4.8, t1: 6.2, s: '¡CUÁNTO EQUILIBRIO!' },
      { who: 'd1', t0: 8.8, t1: 10.0, s: '¡GRACIAS, GUAPO!' },
      { who: 'hero', t0: 14.0, t1: 15.7, s: 'SOY GUYBRUSH THREEPWOOD' },
      { who: 'p0', t0: 15.8, t1: 17.6, s: '¡QUÉ NOMBRE MÁS RIDÍCULO!' },
      { who: 'p1', t0: 17.7, t1: 19.3, s: 'SUPERA LAS TRES PRUEBAS' },
      { who: 'p2', t0: 21.0, t1: 22.4, s: '¡GROG, GROG, GROG!' },
      { who: 'b0', t0: 26.1, t1: 27.2, s: '¿NOMBRE?' },
      { who: 'hero', t0: 27.3, t1: 28.6, s: M.protagonista(D, 100) || 'GUYBRUSH THREEPWOOD' },
      { who: 'b1', t0: 28.7, t1: 29.9, s: 'ESTÁS EN LA LISTA' }
    ];
    // billetes que vuelan hasta el escenario y caen bailando
    const BILLS = Array.from({ length: 16 }, (_, i) => ({ t0: 7.9 + i * 0.09, tx: 150 + (i * 37) % 90, ty: 44 + (i * 13) % 20, sw: i % 3 }));

    // ----- interfaz SCUMM: verbos, frase e inventario -----
    const VERBS = [
      ['DAR', 10, 148], ['ABRIR', 52, 148], ['CERRAR', 98, 148],
      ['COGER', 10, 158], ['MIRAR', 52, 158], ['HABLAR', 98, 158],
      ['USAR', 10, 168], ['EMPUJAR', 52, 168], ['TIRAR', 98, 168]
    ];
    const SLOT = i => [150 + (i % 4) * 42, 147 + Math.floor(i / 4) * 16];
    function sentence(t) {
      const S = [
        [3.5, 4.4, 'MIRAR'], [4.4, 6.4, 'MIRAR ESCENARIO'],
        [6.4, 7.1, 'TIRAR'], [7.1, 7.6, 'TIRAR DÓLARES A'], [7.6, 9.8, 'TIRAR DÓLARES A BAILARINAS'],
        [9.8, 10.6, 'HABLAR CON'], [10.6, 19.4, 'HABLAR CON PIRATAS IMPORTANTES'],
        [19.4, 20.1, 'DAR'], [20.1, 20.6, 'DAR GROG A'], [20.6, 22.4, 'DAR GROG A PIRATAS IMPORTANTES'],
        [22.4, 99, 'IR A RESERVADO']
      ];
      const f = S.find(x => t >= x[0] && t < x[1]);
      return f ? f[2] : 'IR A';
    }
    function verbOn(t) {
      if (t >= 3.5 && t < 6.4) return 'MIRAR';
      if (t >= 6.4 && t < 9.8) return 'TIRAR';
      if (t >= 9.8 && t < 19.4) return 'HABLAR';
      if (t >= 19.4 && t < 22.4) return 'DAR';
      return '';
    }
    // cursor: fotogramas clave en pantalla (ui) o en el mundo (world); pulsa al llegar a cada uno
    const CUR = [
      [0.0, 'ui', 250, 60], [3.5, 'ui', 62, 160], [4.4, 'world', 190, 64],
      [6.4, 'ui', 106, 170], [7.1, 'ui', 170, 170], [7.6, 'world', 190, 70],
      [9.8, 'ui', 110, 160], [10.6, 'world', 336, 100],
      [19.4, 'ui', 18, 150], [20.1, 'ui', 170, 154], [20.6, 'world', 336, 100],
      [22.4, 'world', 462, 92], [60, 'world', 462, 92]
    ];
    const CLICKS = CUR.slice(1, -1).map(k => k[0]);
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
      (c, x, y) => { for (const [dx, dy] of [[0, 4], [5, 2], [3, 7], [8, 6]]) { R(c, x + dx, y + dy, 4, 4, '#e8c23a'); R(c, x + dx + 1, y + dy + 1, 1, 1, '#fff3a0'); } },
      (c, x, y) => { for (const [dx, dy] of [[0, 5], [2, 3], [4, 1]]) { R(c, x + dx, y + dy, 11, 6, '#3a8a4a'); R(c, x + dx + 1, y + dy + 1, 9, 4, '#5ab06a'); R(c, x + dx + 4, y + dy + 2, 3, 2, '#2a6a3a'); } }
    ];
    const ITEM_NAMES = ['GROG', 'POLLO CON POLEA', 'MAPA', 'PIEZAS DE A OCHO', 'DÓLARES'];

    // ----- capa fija -----
    const [back, bk] = mk(WW, UIY);
    const SIGN = spriteText('SCUMM BAR', '#ff3ea5', 2);
    const VIP = spriteText('VIP', '#ffd23e', 2);
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
      // reservado VIP: cortina recogida y el hueco por el que se pasa a la oficina
      R(bk, 424, 40, 76, 68, '#2a0a12');
      R(bk, 446, 46, 32, 62, '#0a0408');
      for (let y = 50; y < 108; y += 3) R(bk, 448, y, 28, 1, 'rgba(62,232,255,0.05)');
      for (const [x0, x1] of [[424, 446], [478, 500]]) for (let x = x0; x < x1; x += 4) { R(bk, x, 40, 3, 68, '#7a1a2a'); R(bk, x + 3, 40, 1, 68, '#4a0a14'); }
      R(bk, 424, 37, 76, 3, '#c9a24a'); R(bk, 443, 78, 4, 3, '#c9a24a'); R(bk, 477, 78, 4, 3, '#c9a24a');
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
      glowImg(ctx, VIP, 462 - (VIP.width >> 1), 22, '#ffd23e', 6, flick(t, 7.7));
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

      // billetes en el aire y sobre la tarima
      for (const b of BILLS) {
        if (t < b.t0) continue;
        const fly = 0.5, x0 = XS + 8, y0 = HY - 24;
        let x, y;
        if (t < b.t0 + fly) {
          const k = (t - b.t0) / fly;
          x = x0 + (b.tx - x0) * k; y = y0 + (b.ty - y0) * k - 18 * Math.sin(Math.PI * k);
        } else {
          const k = t - b.t0 - fly;
          y = b.ty + Math.min(98 - b.ty, k * 22);
          x = b.tx + (y < 98 ? Math.round(Math.sin(k * 5 + b.sw) * 3) : 0);
        }
        const flat = y >= 98, flip = Math.floor(t * 8 + b.tx) % 2;
        R(ctx, Math.round(x), Math.round(y), flat || flip ? 3 : 2, flat ? 1 : 2, flip ? '#5ab06a' : '#3a8a4a');
      }

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

      // porteros del reservado, postes y cordón
      BOUNCERS.forEach((b, i) => {
        const talk = LINES.some(l => l.who === 'b' + i && t >= l.t0 && t < l.t1);
        drawPerson(ctx, b.x, BY, 0, false, talk ? 'wave' : 'down', b.P, { flip: true });
        R(ctx, b.x + 2, BY - 20, 4, 1, '#05050a'); R(ctx, b.x + 3, BY - 20, 1, 1, '#7ff6ff');
      });
      for (const px of [446, 476]) { R(ctx, px, 116, 2, 14, '#c9a24a'); R(ctx, px - 1, 114, 4, 2, '#e8c23a'); R(ctx, px - 1, 129, 4, 1, '#8a6a2a'); }
      if (t < ROPE) {
        for (let x = 448; x < 476; x++) R(ctx, x, 118 + Math.round(Math.sin(Math.PI * (x - 448) / 28) * 4), 1, 2, '#a8243a');
      } else {
        const k = seg(t, ROPE, ROPE + 0.4);
        for (let i = 0; i < 12; i++) R(ctx, 448 + Math.round(i * (1 - k) * 2), 118 + Math.round(i * k), 1, 2, '#a8243a');
      }

      // protagonista
      const throwing = t >= 7.8 && t < 9.4, giving = t >= 20.6 && t < 22.0;
      ctx.save(); ctx.globalAlpha = hs.alpha;
      drawPerson(ctx, hs.x, hs.y, st, !!hs.walking, throwing ? 'wave' : (giving ? 'tray' : 'down'), HERO);
      ctx.restore();
      if (giving) ITEMS[0](ctx, Math.round(hs.x) + 8, HY - 22);

      // bocadillos
      for (const l of LINES) {
        if (t < l.t0 || t >= l.t1) continue;
        const n = Math.floor(seg(t, l.t0, l.t0 + 0.4) * [...l.s].length + 0.001);
        let cx, tip;
        if (l.who === 'hero') { cx = Math.round(hs.x) + 5; tip = HY - 27; }
        else if (l.who === 'd1') { cx = POLES[1].x; tip = 100 - 31; }
        else if (l.who[0] === 'b') { cx = BOUNCERS[+l.who.slice(1)].x + 4; tip = BY - 27; }
        else { const p = PIR[+l.who.slice(1)]; cx = p.x + 4; tip = p.base - 31; }
        bubble(ctx, cx, tip, l.s, n, cam, cam + W);
      }
      ctx.restore();

      // interfaz SCUMM
      R(ctx, 0, UIY, W, H - UIY, '#000000');
      const [cx, cy] = cursorAt(t, cam);
      const hover = cy >= UIY ? ITEMS.findIndex((_, i) => { const [sx, sy] = SLOT(i); return cx >= sx && cx < sx + 40 && cy >= sy && cy < sy + 14; }) : -1;
      const base = sentence(t);
      const sen = hover >= 0 && base === 'IR A' ? ITEM_NAMES[hover] : base;
      txt(ctx, sen, 160 - (tw(sen) >> 1), UIY + 3, '#d8d8ff');
      const vOn = verbOn(t);
      VERBS.forEach(([v, x, y]) => txt(ctx, v, x, y, v === vOn ? '#ffffff' : '#5a5ad8'));
      for (let i = 0; i < 8; i++) {
        const [sx, sy] = SLOT(i);
        R(ctx, sx, sy, 40, 14, '#0c0c24'); R(ctx, sx, sy, 40, 1, '#1c1c44');
        if (i < ITEMS.length && !(i === 0 && t >= 20.6 && t < 22.0)) ITEMS[i](ctx, sx + 14, sy + 2);
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
