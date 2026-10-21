Escenas.registrar({
  id: 'recepcion',
  titulo: 'La recepción',
  zona: 'Inicio',
  fotograma: 5.6,
  ejemplo: {
    novedades: ['12 películas nuevas', '8 series nuevas', '3 audiolibros nuevos', '1 juego nuevo'],
    noticia: 'Bienvenidos al cabaret de NOBS: siembra, comparte y brinda con grog',
    online: 42
  },
  crear(M, D) {
    const { W, H, mk, rng, R, clamp, seg, tw, txt, spriteText, glowImg, glowRects, flick, drawPerson, bubble, miles } = M;

    const WW = 520;
    const HY = 156;
    const V = 46;
    const HERO = { hair: '#f0c66b', hairD: '#b98a3a', hairL: '#ffe39a', skin: '#eab28a', skinD: '#c4865f', shirt: '#eef0f6', shirtD: '#b9c0d2', sleeveB: '#a9b0c4', sash: '#d8344a', sashD: '#a8243a', leg: '#2b2638', legB: '#1c1925', boot: '#0e0b13', style: 'pony' };
    // azafata: bikini rojo, pajarita, puños blancos y orejas de conejo
    const HOST = { hair: '#1a1014', skin: '#e8b090', skinD: '#c48a6a', shirt: '#e8b090', shirtD: '#c48a6a', sleeveB: '#c48a6a', sash: null, leg: '#e8b090', legB: '#c48a6a', boot: '#0a0a0e', style: 'short' };
    const HX = 128, HBY = 132;      // la azafata, detrás del mostrador (de cintura para arriba)

    // ----- datos -----
    const fit = (s, max) => { s = String(s || '').toUpperCase(); while (tw(s) > max) s = s.slice(0, -1); return s; };
    const NOV = (Array.isArray(D.novedades) && D.novedades.length ? D.novedades : ['Novedades']).slice(0, 4).map(s => fit(s, 104));
    const NEWS = String(D.noticia || '').toUpperCase();
    const ONLINE = Math.max(0, Math.floor(Number(D.online) || 0));

    // protagonista: su nombre en el saludo y su número de socio en la taquilla
    const NOMBRE = M.protagonista(D, 70);
    const NUM = Math.max(0, Math.floor(Number(D.protagonista && D.protagonista.numero) || 0));

    // ----- recorrido -----
    const T0 = 0.3, XD = 112, XL = 228, XB = 372, XE = WW + 20;
    const T1 = T0 + (XD + 14) / V;      // ante el mostrador
    const T2 = 6.6;
    const T3 = T2 + (XL - XD) / V;      // ante su taquilla
    const T4 = T3 + 2.1;
    const T5 = T4 + (XB - XL) / V;      // ante el cartel
    const T6 = T5 + 2.2;
    const T7 = T6 + (XE - XB) / V;
    const L = Math.ceil(T7 + 0.5);
    const KEY0 = 6.0, KEY1 = 6.4;       // la llave pasa de mano
    const OPEN = T3 + 0.2, TAKE = T3 + 0.8, SHUT = T3 + 1.5;
    function heroAt(t) {
      if (t < T0) return { x: -14, walking: true };
      if (t < T1) return { x: -14 + (t - T0) * V, walking: true };
      if (t < T2) return { x: XD, walking: false };
      if (t < T3) return { x: XD + (t - T2) * V, walking: true };
      if (t < T4) return { x: XL, walking: false };
      if (t < T5) return { x: XL + (t - T4) * V, walking: true };
      if (t < T6) return { x: XB, walking: false };
      return { x: XB + (t - T6) * V, walking: true };
    }
    const LINES = [
      { who: 'host', t0: T1 + 0.1, t1: T1 + 1.7, s: NOMBRE ? '¡BIENVENIDO, ' + NOMBRE + '!' : '¡BIENVENIDO A NOBS!' },
      { who: 'host', t0: T1 + 1.8, t1: 6.5, s: 'TU LLAVE, CIELO' },
      { who: 'hero', t0: TAKE + 0.1, t1: TAKE + 1.3, s: '¡MI BANDEJA!' },
      { who: 'hero', t0: T5 + 0.3, t1: T5 + 1.8, s: '¡CUÁNTA NOVEDAD!' }
    ];

    // ----- capa fija -----
    const [back, bk] = mk(WW, H);
    const MOULIN = spriteText('MOULIN NOBS', '#ff3ea5');
    const NOVS = spriteText('NOVEDADES', '#ffd23e', 2);
    const BUFE = spriteText('BUFÉ', '#3ee8ff', 2);
    const LOCKX = i => 196 + i * 16;   // seis columnas de taquillas
    (function build() {
      const r = rng(31);
      R(bk, 0, 0, WW, 16, '#1a0608');
      R(bk, 0, 16, WW, 132, '#5a0f1c');
      for (let x = 0; x < WW; x += 12) { R(bk, x, 16, 5, 132, '#661424'); R(bk, x + 5, 16, 1, 132, '#4a0a16'); }
      R(bk, 0, 16, WW, 3, '#c9a24a'); R(bk, 0, 19, WW, 1, '#7a5a1a');
      R(bk, 0, 120, WW, 2, '#c9a24a');
      // puerta de cristal por dentro, con la calle mojada detrás
      R(bk, 2, 82, 40, 66, '#3ee8ff'); R(bk, 4, 84, 36, 64, '#0a0d18');
      for (let k = 0; k < 14; k++) R(bk, 6 + ((k * 11) % 32), 88 + ((k * 17) % 56), 1, 2, '#3a4a7a');
      R(bk, 21, 84, 2, 64, '#2a2a3a');
      // cartel de cancán
      R(bk, 52, 56, 28, 40, '#c9a24a'); R(bk, 54, 58, 24, 36, '#f0e0b0');
      R(bk, 54, 58, 24, 8, '#d8344a'); txt(bk, 'CAN', 59, 59, '#f0e0b0');
      for (let k = 0; k < 3; k++) { const x = 57 + k * 7; R(bk, x, 70, 5, 5, '#d8344a'); R(bk, x + 1, 75, 2, 12, '#1a1014'); R(bk, x + 3, 73, 1, 9, '#1a1014'); R(bk, x - 1, 73, 7, 2, '#ffffff'); }
      // casilleros de llaves detrás del mostrador
      R(bk, 96, 92, 64, 26, '#3a2010');
      for (let j = 0; j < 3; j++) for (let i = 0; i < 8; i++) {
        R(bk, 98 + i * 8, 94 + j * 8, 6, 6, '#24140a');
        if ((i + j * 3) % 4 !== 1) R(bk, 100 + i * 8, 95 + j * 8, 2, 4, '#c9a24a');
      }
      // taquillas
      for (let i = 0; i < 6; i++) for (let j = 0; j < 2; j++) {
        const x = LOCKX(i), y = 72 + j * 38;
        R(bk, x, y, 15, 37, '#3a6a7a'); R(bk, x, y, 15, 1, '#5a8a9a'); R(bk, x + 14, y, 1, 37, '#2a4a5a');
        for (let k = 0; k < 4; k++) R(bk, x + 4, y + 4 + k * 2, 7, 1, '#2a4a5a');
        R(bk, x + 11, y + 18, 2, 4, '#c9cdd8');
        const n = String(1 + i + j * 6);
        txt(bk, n, x + 8 - (tw(n) >> 1), y + 26, '#c9e0e8');
      }
      // arco hacia el bufé
      R(bk, 470, 80, 44, 68, '#c9a24a'); R(bk, 474, 84, 36, 64, '#1a0a1a');
      for (let y = 88; y < 148; y += 3) R(bk, 476, y, 32, 1, 'rgba(255,62,165,0.07)');
      // alfombra roja con ribete dorado
      R(bk, 0, 148, WW, 32, '#3a0a14');
      R(bk, 0, 152, WW, 22, '#8a1a2a'); R(bk, 0, 152, WW, 1, '#c9a24a'); R(bk, 0, 173, WW, 1, '#c9a24a');
      for (let x = 6; x < WW; x += 24) R(bk, x, 162, 3, 3, '#a8243a');
    })();

    // molino de neón: aspas que giran sobre el mostrador
    function windmill(ctx, t) {
      const cx = 128, cy = 44, col = '#ff3a4a';
      ctx.save(); ctx.shadowColor = col; ctx.shadowBlur = 5; ctx.fillStyle = col;
      for (let y = 0; y < 26; y++) { const half = 6 + Math.floor(y / 4); ctx.fillRect(cx - half, cy + 6 + y, 1, 1); ctx.fillRect(cx + half, cy + 6 + y, 1, 1); }
      ctx.fillRect(cx - 12, cy + 31, 25, 1); ctx.fillRect(cx - 2, cy + 22, 5, 1); ctx.fillRect(cx - 2, cy + 22, 1, 9); ctx.fillRect(cx + 2, cy + 22, 1, 9);
      for (let a = 0; a < 4; a++) {
        const ang = t * 1.2 + a * Math.PI / 2, ca = Math.cos(ang), sa = Math.sin(ang);
        for (let k = 3; k < 22; k++) ctx.fillRect(Math.round(cx + ca * k), Math.round(cy + sa * k), 1, 1);
        for (let k = 8; k < 22; k += 3) ctx.fillRect(Math.round(cx + ca * k - sa * 3), Math.round(cy + sa * k + ca * 3), 1, 1);
      }
      ctx.fillStyle = '#ffd0d4'; ctx.fillRect(cx - 1, cy - 1, 3, 3);
      ctx.restore();
    }
    function chandelier(ctx, x, t) {
      R(ctx, x, 16, 1, 10, '#8a6a2a');
      R(ctx, x - 10, 26, 21, 2, '#c9a24a'); R(ctx, x - 6, 28, 13, 2, '#c9a24a');
      for (let k = -9; k <= 9; k += 3) {
        R(ctx, x + k, 30, 1, 3, '#d8f0ff');
        if (Math.floor(t * 3 + k) % 4 === 0) R(ctx, x + k, 31, 1, 1, '#ffffff');
      }
      glowRects(ctx, '#fff0c0', 6, [[x - 8, 24, 2, 2], [x + 7, 24, 2, 2], [x - 1, 23, 2, 2]], 0.9);
    }
    function key(ctx, x, y) { R(ctx, x, y, 3, 3, '#ffd23e'); R(ctx, x + 1, y + 1, 1, 1, '#5a0f1c'); R(ctx, x + 3, y + 1, 4, 1, '#ffd23e'); R(ctx, x + 5, y + 2, 1, 1, '#ffd23e'); }
    function tray(ctx, x, y) { R(ctx, x, y, 11, 1, '#d5dbe6'); R(ctx, x, y + 1, 11, 1, '#8a93a6'); }

    const [buf, ctx] = mk(W, H);
    function render(vctx, t) {
      const hs = heroAt(t);
      const cam = Math.round(clamp(hs.x - 130, 0, WW - W));
      const st = Math.floor(t * 10) % 8;
      ctx.clearRect(0, 0, W, H);
      ctx.save();
      ctx.translate(-cam, 0);
      ctx.drawImage(back, 0, 0);

      chandelier(ctx, 60, t); chandelier(ctx, 300, t);
      windmill(ctx, t);
      glowImg(ctx, MOULIN, 128 - (MOULIN.width >> 1), 80, '#ff3ea5', 4, flick(t, 3.7));
      glowImg(ctx, BUFE, 492 - (BUFE.width >> 1), 66, '#3ee8ff', 5);

      // cartel de novedades con bombillas que corren
      const bx = 326, by = 26, bw = 128, bh = 80;
      R(ctx, bx, by, bw, bh, '#120808'); R(ctx, bx + 3, by + 3, bw - 6, bh - 6, '#1a0a0a');
      const run = Math.floor(t * 8);
      for (let k = 0, x = bx + 1; x < bx + bw - 1; x += 4, k++) { const on = (k + run) % 3 === 0; R(ctx, x, by + 1, 2, 1, on ? '#fff3a0' : '#7a5a1a'); R(ctx, x, by + bh - 2, 2, 1, (k + run + 1) % 3 === 0 ? '#fff3a0' : '#7a5a1a'); }
      for (let k = 0, y = by + 1; y < by + bh - 1; y += 4, k++) { R(ctx, bx + 1, y, 1, 2, (k + run) % 3 === 0 ? '#fff3a0' : '#7a5a1a'); R(ctx, bx + bw - 2, y, 1, 2, (k + run + 2) % 3 === 0 ? '#fff3a0' : '#7a5a1a'); }
      glowImg(ctx, NOVS, bx + (bw >> 1) - (NOVS.width >> 1), by + 6, '#ffd23e', 4);
      NOV.forEach((l, i) => txt(ctx, l, bx + 10, by + 24 + i * 9, i % 2 ? '#ffc870' : '#f4f1ff'));
      if (NEWS) {
        R(ctx, bx + 4, by + bh - 12, bw - 8, 8, '#000000');
        ctx.save(); ctx.beginPath(); ctx.rect(bx + 5, by + bh - 11, bw - 10, 6); ctx.clip();
        const span = tw(NEWS) + bw;
        txt(ctx, NEWS, Math.round(bx + bw - (t * 24) % span), by + bh - 10, '#4dff8a');
        ctx.restore();
      }
      if (ONLINE > 0) {
        const o = 'EN LÍNEA ' + miles(ONLINE);
        R(ctx, bx + (bw >> 1) - (tw(o) >> 1) - 3, by + bh + 3, tw(o) + 6, 9, '#c9a24a');
        txt(ctx, o, bx + (bw >> 1) - (tw(o) >> 1), by + bh + 5, '#3a0a14');
      }

      // taquilla del protagonista (la 9): se abre, saca la bandeja y se cierra
      const lx = LOCKX(2), ly = 110;
      const open = seg(t, OPEN, OPEN + 0.3) * (1 - seg(t, SHUT, SHUT + 0.3));
      if (open > 0) {
        R(ctx, lx + 1, ly + 1, 13, 35, '#0e1418');
        if (t < TAKE) tray(ctx, lx + 2, ly + 20);
        const dw = Math.max(2, Math.round(15 * (1 - open * 0.8)));
        R(ctx, lx, ly, dw, 37, '#4a7a8a'); R(ctx, lx + dw - 1, ly, 1, 37, '#2a4a5a');
      }

      // placa de latón con su número: crece con las cifras y se centra en la puerta
      if (NUM && open === 0) {
        const ns = String(NUM), w = tw(ns) + 2, px = lx + 7 - (w >> 1);
        R(ctx, px, ly + 25, w, 7, '#c9a24a'); R(ctx, px, ly + 25, w, 1, '#fff3a0');
        txt(ctx, ns, px + 1, ly + 26, '#3a2410');
      }

      // mostrador con la azafata detrás
      const talking = LINES.some(l => l.who === 'host' && t >= l.t0 && t < l.t1);
      const giving = t >= KEY0 - 0.3 && t < KEY1;
      drawPerson(ctx, HX, HBY, 0, false, giving ? 'tray' : (talking ? 'wave' : 'down'), HOST, { flip: true });
      R(ctx, HX + 2, HBY - 15, 6, 2, '#d8344a'); R(ctx, HX + 3, HBY - 14, 1, 1, '#ff6a7a');
      R(ctx, HX + 4, HBY - 17, 2, 1, '#0a0a0e');
      R(ctx, HX + 2, HBY - 10, 6, 2, '#d8344a');
      const earBob = Math.sin(t * 3) > 0.6 ? 1 : 0;
      R(ctx, HX + 3, HBY - 29 + earBob, 2, 6, '#f4f4f8'); R(ctx, HX + 3, HBY - 28 + earBob, 1, 4, '#ffb0c8');
      R(ctx, HX + 6, HBY - 30, 2, 7, '#f4f4f8'); R(ctx, HX + 6, HBY - 29, 1, 5, '#ffb0c8');
      R(ctx, HX + 2, HBY - 24, 6, 1, '#0a0a0e');
      R(ctx, 90, 122, 76, 26, '#3a1a0e'); R(ctx, 90, 122, 76, 2, '#c9a24a');
      for (let x = 94; x < 164; x += 12) R(ctx, x, 126, 8, 18, '#4a2412');
      const rec = 'RECEPCIÓN';
      txt(ctx, rec, 128 - (tw(rec) >> 1), 130, '#c9a24a');
      R(ctx, 150, 118, 5, 3, '#c9a24a'); R(ctx, 152, 117, 1, 1, '#fff3a0');

      // la llave pasa de la azafata al protagonista
      if (t >= KEY0 && t < KEY1) {
        const k = seg(t, KEY0, KEY1);
        key(ctx, HX - 4 + (hs.x + 9 - HX + 4) * k, 119 + (HY - 15 - 119) * k - 6 * Math.sin(Math.PI * k));
      }

      // protagonista: sale de la taquilla con la bandeja
      const withTray = t >= TAKE;
      const hb = drawPerson(ctx, hs.x, HY, st, !!hs.walking, withTray ? 'tray' : 'down', HERO);
      if (withTray) tray(ctx, Math.round(hs.x) + 4, HY - 14 + hb);
      else if (t >= KEY1 && t < OPEN) key(ctx, Math.round(hs.x) + 6, HY - 11);

      for (const l of LINES) {
        if (t < l.t0 || t >= l.t1) continue;
        const n = Math.floor(seg(t, l.t0, l.t0 + 0.4) * [...l.s].length + 0.001);
        const cx = l.who === 'host' ? HX + 4 : Math.round(hs.x) + 5;
        const tip = l.who === 'host' ? HBY - 32 : HY - 27;
        bubble(ctx, cx, tip, l.s, n, cam, cam + W);
      }
      ctx.restore();
      vctx.drawImage(buf, 0, 0);
    }
    return { L, render };
  }
});
