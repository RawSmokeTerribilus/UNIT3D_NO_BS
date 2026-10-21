Escenas.registrar({
  id: 'trofeos',
  titulo: 'La sala de trofeos',
  zona: 'Estadísticas',
  fotograma: 15.4,
  ejemplo: {
    ranking: [
      { titulo: 'Más subidas', nombre: 'LeChuck', n: 1234 },
      { titulo: 'Más semilla', nombre: 'Elaine', n: 8765 },
      { titulo: 'Más gracias', nombre: 'Stan', n: 432 }
    ],
    totales: { subido_tb: 512.4, bajado_tb: 128.7 }
  },
  crear(M, D) {
    const { W, H, mk, rng, R, clamp, seg, tw, txt, spriteText, glowImg, glowRects, flick, drawPerson, bubble, miles } = M;

    const WW = 660;
    const HY = 156;
    const V = 46;
    const HERO = { hair: '#f0c66b', hairD: '#b98a3a', hairL: '#ffe39a', skin: '#eab28a', skinD: '#c4865f', shirt: '#eef0f6', shirtD: '#b9c0d2', sleeveB: '#a9b0c4', sash: '#d8344a', sashD: '#a8243a', leg: '#2b2638', legB: '#1c1925', boot: '#0e0b13', style: 'pony' };
    const CURATOR = { hair: '#3a6a9a', skin: '#c99068', skinD: '#a06e4c', shirt: '#e6e8ef', shirtD: '#b9bfcc', sleeveB: '#b9bfcc', stripes: '#3a3f6a', sash: null, leg: '#3a3026', legB: '#2a221a', boot: '#100c08', style: 'bandana', beard: '#c9c9d2' };

    // ----- datos: ranking de las vitrinas y totales del marcador -----
    const fit = (s, max) => { s = String(s || '').toUpperCase(); while (tw(s) > max) s = s.slice(0, -1); return s; };
    const RANK = (Array.isArray(D.ranking) && D.ranking.length ? D.ranking : [
      { titulo: 'Más subidas', nombre: 'LeChuck', n: 1234 }
    ]).slice(0, 3).map(r => ({ titulo: fit(r.titulo, 52), nombre: fit(r.nombre, 52), n: Math.max(0, Math.floor(Number(r.n) || 0)) }));
    const TOT = D.totales || {};
    const SUB = Math.max(0, Number(TOT.subido ?? TOT.subido_tb) || 0), BAJ = Math.max(0, Number(TOT.bajado ?? TOT.bajado_tb) || 0);
    const UNI = ' ' + String(TOT.unidad || 'TB').toUpperCase().slice(0, 3);
    const NOMBRE = M.protagonista(D, 56);
    const dec = (v, d) => { const [a, b] = v.toFixed(d).split('.'); return miles(Number(a)) + (b ? ',' + b : ''); };

    // ----- recorrido -----
    const T0 = 0.3, XS = 380, XP = 497, XT = 515, XD = 540, XE = WW + 20;
    const T1 = T0 + (XS + 14) / V;      // llega al marcador
    const T2 = T1 + 2.0;
    const T3 = T2 + (XP - XS) / V;      // al pie del podio
    const T4 = T3 + 0.35;               // arriba del todo
    const T5 = T4 + 2.8;                // se baja
    const T6 = T5 + 0.35;
    const T7 = T6 + (XE - XD) / V;
    const L = Math.ceil(T7 + 0.5);
    function heroAt(t) {
      if (t < T0) return { x: -14, y: HY, walking: true };
      if (t < T1) return { x: -14 + (t - T0) * V, y: HY, walking: true };
      if (t < T2) return { x: XS, y: HY, walking: false };
      if (t < T3) return { x: XS + (t - T2) * V, y: HY, walking: true };
      if (t < T4) { const k = seg(t, T3, T4); return { x: XP + (XT - XP) * k, y: Math.round(HY - 10 * k - 8 * Math.sin(Math.PI * k)), walking: false }; }
      if (t < T5) return { x: XT, y: HY - 10, walking: false, top: true };
      if (t < T6) { const k = seg(t, T5, T6); return { x: XT + (XD - XT) * k, y: Math.round(HY - 10 + 10 * k - 8 * Math.sin(Math.PI * k)), walking: false }; }
      return { x: XD + (t - T6) * V, y: HY, walking: true };
    }
    const LINES = [
      { who: 'cur', t0: 5.0, t1: 6.4, s: '¡SIN TOCAR!' },
      { who: 'hero', t0: T1 + 0.3, t1: T1 + 1.8, s: '¡VAYA RATIO!' },
      { who: 'hero', t0: T4 + 0.2, t1: T4 + 2.5, s: '¡QUIERO SER PIRATA!' }
    ];
    const CURX = 236;

    // ----- piezas -----
    function cup(c, x, y, k, col) {
      const [m, hi, lo] = col;
      R(c, x, y - 2, 6 + k, 2, lo); R(c, x + 2, y - 5, 2 + k, 3, m);
      R(c, x, y - 10 - k, 6 + k, 5 + k, m); R(c, x - 1, y - 9 - k, 1, 3, m); R(c, x + 6 + k, y - 9 - k, 1, 3, m);
      R(c, x + 1, y - 9 - k, 1, 3 + k, hi);
    }
    const GOLD = ['#e8c23a', '#fff3a0', '#a8862a'], SILVER = ['#c9cdd8', '#ffffff', '#8a909e'], BRONZE = ['#c07a3a', '#f0b070', '#7a4a1a'];
    function medal(c, x, y) {
      R(c, x + 1, y - 9, 1, 4, '#3a6aff'); R(c, x + 3, y - 9, 1, 4, '#d8344a');
      R(c, x, y - 5, 5, 5, '#e8c23a'); R(c, x + 1, y - 4, 1, 2, '#fff3a0');
    }
    function plate(c, x, y) {
      R(c, x, y - 9, 9, 9, '#a8862a'); R(c, x + 1, y - 8, 7, 7, '#e8c23a'); R(c, x + 3, y - 6, 3, 3, '#fff3a0');
      R(c, x + 3, y - 1, 3, 1, '#5a3a24');
    }
    function bottleShip(c, x, y) {
      R(c, x, y - 6, 14, 6, 'rgba(170,230,200,0.45)'); R(c, x + 14, y - 4, 2, 2, '#8a5a2a');
      R(c, x + 3, y - 2, 8, 1, '#6a3a1a'); R(c, x + 6, y - 5, 1, 3, '#3a2410'); R(c, x + 4, y - 5, 2, 2, '#efe6cc'); R(c, x + 7, y - 5, 2, 2, '#efe6cc');
    }

    // ----- capa fija -----
    const [back, bk] = mk(WW, H);
    const CABS = [110, 180, 250];
    const MARCADOR = spriteText('MARCADOR', '#ffb347');
    const PISTA = spriteText('PISTA', '#3ee8ff', 2);
    (function build() {
      const r = rng(77);
      R(bk, 0, 0, WW, 18, '#140810');
      R(bk, 0, 18, WW, 130, '#3a0f1c');
      for (let x = 0; x < WW; x += 8) R(bk, x, 18, 2, 130, '#41121f');
      R(bk, 0, 18, WW, 3, '#c9a24a'); R(bk, 0, 21, WW, 1, '#6a4a1a');
      R(bk, 0, 146, WW, 2, '#c9a24a');
      // banderines del techo
      for (let x = 8; x < WW; x += 22) {
        const col = ['#d8344a', '#e8c23a', '#3a6aff', '#2a8a4a'][(x / 22 | 0) % 4];
        R(bk, x, 22, 8, 3, col); R(bk, x + 1, 25, 6, 2, col); R(bk, x + 3, 27, 2, 2, col);
      }
      for (let x = 0; x < WW; x++) R(bk, x, 22 + Math.round(Math.sin(x / 22 * Math.PI) * 0), 1, 1, '#8a6a2a');

      // arco de entrada desde el bar
      R(bk, 0, 70, 40, 78, '#2a1a10'); R(bk, 4, 74, 32, 74, '#0d1416');

      // vitrinas con sus trofeos
      CABS.forEach((cx, i) => {
        R(bk, cx - 28, 46, 56, 84, '#5a3a24'); R(bk, cx - 26, 48, 52, 80, '#1a0c12');
        R(bk, cx - 26, 48, 52, 2, '#fff0c0');
        for (const sy of [76, 102, 127]) R(bk, cx - 26, sy, 52, 1, '#8ab8c8');
        cup(bk, cx - 20, 76, 0, GOLD); cup(bk, cx - 6, 76, 2, i === 1 ? SILVER : GOLD); cup(bk, cx + 12, 76, 0, BRONZE);
        medal(bk, cx - 20, 102); plate(bk, cx - 6, 102); bottleShip(bk, cx + 8, 102);
        cup(bk, cx - 18, 127, 1, SILVER); medal(bk, cx, 127); cup(bk, cx + 12, 127, 0, GOLD);
        bk.fillStyle = 'rgba(170,230,255,0.08)'; bk.fillRect(cx - 26, 48, 52, 80);
        for (let k = 0; k < 10; k++) R(bk, cx - 22 + k, 54 + k * 2, 1, 2, 'rgba(255,255,255,0.18)');
        R(bk, cx - 30, 128, 60, 20, '#3a2418');
      });
      // marcador
      R(bk, 318, 28, 154, 66, '#2a2a30'); R(bk, 320, 30, 150, 62, '#0a0806');
      for (const lx of [330, 460]) { R(bk, lx, 94, 2, 54, '#2a2a30'); }
      // puerta hacia la pista
      R(bk, 606, 80, 48, 68, '#2a1a10'); R(bk, 610, 84, 40, 64, '#120818');
      for (let y = 88; y < 148; y += 3) R(bk, 612, y, 36, 1, 'rgba(255,62,165,0.08)');
      // suelo de mármol
      R(bk, 0, 148, WW, 32, '#2a2430');
      for (let row = 0, y = 148; y < 180; y += 8, row++)
        for (let x = (row % 2) * 12; x < WW; x += 24) R(bk, x, y, 12, 8, '#3a3440');
      for (let i = 0; i < 120; i++) { const x = (r() * WW) | 0, y = 150 + ((r() * 28) | 0); R(bk, x, y, 2, 1, '#4a4452'); }
      // podio (encima del suelo)
      R(bk, 483, HY - 7, 24, 7, '#c9cdd8'); R(bk, 483, HY - 7, 24, 1, '#ffffff');
      R(bk, 507, HY - 10, 26, 10, '#e8c23a'); R(bk, 507, HY - 10, 26, 1, '#fff3a0');
      R(bk, 533, HY - 5, 24, 5, '#c07a3a'); R(bk, 533, HY - 5, 24, 1, '#f0b070');
      txt(bk, '2', 493, HY - 6, '#5a5e6a'); txt(bk, '1', 518, HY - 8, '#7a5a10'); txt(bk, '3', 543, HY - 5, '#5a3010');
      cup(bk, 492, HY - 7, 0, SILVER); cup(bk, 542, HY - 5, 0, BRONZE);
    })();

    // primer plano: cordones de terciopelo (más rápidos que la cámara)
    function ropes(ctx) {
      for (let px = 20; px < WW * 1.25 + 40; px += 90) {
        for (let x = px + 3; x < px + 90; x++) R(ctx, x, 163 + Math.round(Math.sin(Math.PI * (x - px) / 90) * 5), 1, 2, '#a8243a');
        R(ctx, px, 158, 3, 22, '#c9a24a'); R(ctx, px - 1, 156, 5, 3, '#e8c23a'); R(ctx, px, 156, 1, 1, '#fff3a0');
      }
    }

    const [buf, ctx] = mk(W, H);
    function render(vctx, t) {
      const hs = heroAt(t);
      const cam = Math.round(clamp(hs.x - 130, 0, WW - W));
      const st = Math.floor(t * 10) % 8;
      ctx.clearRect(0, 0, W, H);
      ctx.save();
      ctx.translate(-cam, 0);
      ctx.drawImage(back, 0, 0);

      // placas de las vitrinas (ranking)
      RANK.forEach((rk, i) => {
        const cx = CABS[i];
        R(ctx, cx - 28, 129, 56, 18, '#c9a24a'); R(ctx, cx - 28, 129, 56, 1, '#fff3a0');
        txt(ctx, rk.titulo, cx - (tw(rk.titulo) >> 1), 131, '#3a2410');
        txt(ctx, rk.nombre, cx - (tw(rk.nombre) >> 1), 137, '#7a1a33');
        const n = miles(rk.n);
        txt(ctx, n, cx - (tw(n) >> 1), 143 - 1, '#3a2410');
      });

      // marcador con cifras que ruedan al llegar
      glowImg(ctx, MARCADOR, 395 - (MARCADOR.width >> 1), 32, '#ffb347', 3);
      const k = (x => x < .5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2)(seg(t, T1 - 1.2, T1 + 0.8));
      const rows = [['SUBIDO', dec(SUB * k, 1) + UNI], ['BAJADO', dec(BAJ * k, 1) + UNI], ['RATIO', BAJ > 0 ? dec(SUB / BAJ * k, 2) : '-']];
      rows.forEach(([lab, v], i) => {
        const y = 44 + i * 16;
        txt(ctx, lab, 326, y + 3, '#7a5a20');
        ctx.save(); ctx.shadowColor = '#ffb347'; ctx.shadowBlur = 3;
        txt(ctx, v, 464 - tw(v, 2), y, '#ffc870', 2);
        ctx.restore();
      });
      ctx.fillStyle = 'rgba(0,0,0,0.25)';
      for (let y = 30; y < 92; y += 2) ctx.fillRect(320, y, 150, 1);

      // focos que barren el podio
      for (const [sx, ph] of [[470, 0], [570, 2]]) {
        const tx = 520 + Math.sin(t * 1.3 + ph) * 18;
        ctx.fillStyle = 'rgba(255,240,190,0.07)';
        ctx.beginPath(); ctx.moveTo(sx - 2, 18); ctx.lineTo(sx + 2, 18); ctx.lineTo(tx + 22, HY); ctx.lineTo(tx - 22, HY); ctx.closePath(); ctx.fill();
        R(ctx, sx - 3, 16, 7, 4, '#2a2a30'); R(ctx, sx - 1, 19, 3, 1, '#fff0c0');
      }
      // placa del primer puesto
      const top = !!hs.top;
      R(ctx, 490, 84, 60, 10, '#c9a24a'); R(ctx, 490, 84, 60, 1, '#fff3a0');
      const plaque = top ? (NOMBRE || 'GUYBRUSH') : 'TU NOMBRE AQUÍ';
      txt(ctx, plaque, 520 - (tw(plaque) >> 1), 87, top ? '#7a1a33' : '#5a3a10');
      if (top && Math.floor(t * 8) % 2 === 0) {
        ctx.fillStyle = '#ffffff';
        [[488, 83], [552, 85], [487, 93], [551, 94]].forEach(p => ctx.fillRect(p[0], p[1], 1, 1));
      }
      // neón de la pista y notas que salen por la puerta
      glowImg(ctx, PISTA, 630 - (PISTA.width >> 1), 68, '#3ee8ff', 5, flick(t, 6.1));
      for (let i = 0; i < 4; i++) {
        const ph = (t * 0.6 + i * 0.25) % 1;
        const nx = 620 + i * 7 + Math.round(Math.sin(t * 2 + i) * 2), ny = 140 - ph * 50;
        ctx.globalAlpha = 1 - ph;
        R(ctx, nx, ny, 2, 2, i % 2 ? '#ff3ea5' : '#3ee8ff'); R(ctx, nx + 1, ny - 4, 1, 4, i % 2 ? '#ff3ea5' : '#3ee8ff');
        ctx.globalAlpha = 1;
      }

      // conservador sacando brillo
      const curTalk = LINES.some(l => l.who === 'cur' && t >= l.t0 && t < l.t1);
      drawPerson(ctx, CURX, 151, 0, false, curTalk ? 'wave' : 'tray', CURATOR, { flip: true });
      if (!curTalk) R(ctx, CURX - 1 + Math.round(Math.sin(t * 8)), 151 - 15, 3, 2, '#f4f4f8');

      // protagonista
      const mode = hs.top ? 'wave' : 'down';
      drawPerson(ctx, hs.x, hs.y, st, hs.walking, mode, HERO);
      if (hs.top) {
        const hx = Math.round(hs.x);
        glowRects(ctx, '#fff3a0', 5, [[hx + 5, hs.y - 32, 1, 1]], 0.8);
        cup(ctx, hx + 4, hs.y - 22, 1, GOLD);
      }
      // confeti
      if (t >= T4 && t < T5 + 1.2) {
        const cols = ['#ff3ea5', '#3ee8ff', '#ffd23e', '#4dff8a', '#ffffff'];
        for (let i = 0; i < 46; i++) {
          const x = 470 + ((i * 37) % 100) + Math.round(Math.sin(t * 3 + i) * 2);
          const y = 24 + (((t - T4) * 38 + i * 11) % 130);
          R(ctx, x, Math.round(y), 1 + (i % 2), 1, cols[i % cols.length]);
        }
      }

      // bocadillos
      for (const l of LINES) {
        if (t < l.t0 || t >= l.t1) continue;
        const n = Math.floor(seg(t, l.t0, l.t0 + 0.4) * [...l.s].length + 0.001);
        const cx = l.who === 'cur' ? CURX + 4 : Math.round(hs.x) + 5;
        const tip = l.who === 'cur' ? 151 - 27 : hs.y - 27 - (hs.top ? 8 : 0);
        bubble(ctx, cx, tip, l.s, n, cam, cam + W);
      }
      ctx.restore();

      ctx.save();
      ctx.translate(-Math.round(cam * 1.25), 0);
      ropes(ctx);
      ctx.restore();

      vctx.drawImage(buf, 0, 0);
    }
    return { L, render };
  }
});
