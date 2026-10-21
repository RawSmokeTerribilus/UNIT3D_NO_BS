Escenas.registrar({
  id: 'bar',
  titulo: 'El bar',
  zona: 'Foro',
  fotograma: 11.2,
  ejemplo: { foros: [
    { nombre: 'Preséntate', hilos: 120 }, { nombre: 'Servarr', hilos: 18 }, { nombre: 'Off Topic', hilos: 35 },
    { nombre: 'Tutoriales', hilos: 12 }, { nombre: 'Monkey Island', hilos: 7 }, { nombre: 'Singularity', hilos: 5 }
  ] },
  crear(M, D) {
    const { W, H, mk, rng, R, clamp, seg, SM, glyph, tw, txt, spriteText, glowImg, glowRects, flick, STR, leg, sitLeg, person, drawPerson, bubble, miles } = M;
    // ================= ESCENA: el bar (foro) =================
    const WW = 560;
    const V = 46;
    const HERO = { hair: '#f0c66b', hairD: '#b98a3a', hairL: '#ffe39a', skin: '#eab28a', skinD: '#c4865f', shirt: '#eef0f6', shirtD: '#b9c0d2', sleeveB: '#a9b0c4', sash: '#d8344a', sashD: '#a8243a', leg: '#2b2638', legB: '#1c1925', boot: '#0e0b13', style: 'pony' };
    const MOD = { hair: '#3a2a20', skin: '#d9a07a', skinD: '#b07a58', skinL: '#f0c09a', shirt: '#24382e', shirtD: '#1a2a22', sleeve: '#e6e8ef', sleeveB: '#c9cdd8', sash: null, leg: '#1d1d26', legB: '#16161e', boot: '#0e0b13', style: 'bald', mustache: '#3a2418', badge: '#4dff8a' };
    const P1 = { hair: '#1a1410', skin: '#c99068', skinD: '#a06e4c', shirt: '#b8443a', shirtD: '#8a2f28', sleeveB: '#8a2f28', sash: null, leg: '#2d3a5a', legB: '#202a44', boot: '#120e0a', style: 'short' };
    const P2 = { hair: '#e0c070', skin: '#eab28a', skinD: '#c4865f', shirt: '#6a5acd', shirtD: '#4f42a0', sleeveB: '#4f42a0', sash: null, leg: '#2a2536', legB: '#1e1a28', boot: '#120e16', style: 'bun' };
    const P4 = { hair: '#9a9aa4', skin: '#d8a27c', skinD: '#b07a58', shirt: '#3a6a9a', shirtD: '#2a5078', sleeveB: '#2a5078', sash: null, leg: '#3a3026', legB: '#2a221a', boot: '#100c08', style: 'short', beard: '#c9c9d2' };

    const PIRATE_A = { hair: '#b8243a', skin: '#d9a07a', skinD: '#b07a58', shirt: '#e6e8ef', shirtD: '#b9bfcc', sleeveB: '#b9bfcc', stripes: '#c23a48', sash: null, leg: '#2a3a5a', legB: '#1e2a44', boot: '#120e0a', style: 'bandana', beard: '#1a1410' };
    const PIRATE_B = { hair: '#2a5a9a', skin: '#c99068', skinD: '#a06e4c', shirt: '#e6e8ef', shirtD: '#b9bfcc', sleeveB: '#b9bfcc', stripes: '#2a3a5a', sash: '#3a2a1a', sashD: '#2a1a10', leg: '#4a3a2a', legB: '#3a2a1a', boot: '#120e0a', style: 'bandana', patch: true };
    const LAMPX = 88;
    const swingX = t => Math.round(Math.sin(t * 1.7) * 3);
    // lámpara de rueda de carro con dos piratas colgados pataleando
    function chandelier(ctx, t) {
      const sw = Math.sin(t * 1.7) * 3, dx = swingX(t);
      for (let y = 16; y < 40; y += 2) R(ctx, LAMPX + Math.round(sw * (y - 16) / 24), y, 1, 1, '#6a6a72');
      const st = Math.floor(t * 8) % 8;
      drawPerson(ctx, LAMPX - 17 + dx, 72, st, true, 'hang', PIRATE_A);
      drawPerson(ctx, LAMPX + 6 + dx, 72, (st + 4) % 8, true, 'hang', PIRATE_B, { flip: true });
      R(ctx, LAMPX - 20 + dx, 40, 41, 3, '#5a3a20'); R(ctx, LAMPX - 20 + dx, 40, 41, 1, '#7a5a30');
      for (let k = 0; k < 5; k++) {
        const vx = LAMPX - 18 + k * 9 + dx;
        R(ctx, vx, 36, 2, 4, '#f0e6c0');
        glowRects(ctx, '#ffb347', 5, [[vx, 34 + (Math.sin(t * 13 + k) > 0.3 ? 1 : 0), 2, 2]]);
      }
    }
    const STOOLS = [262, 312, 362, 412];
    const SEAT = 151;                 // fila bajo los pies de quien está sentado
    const LANE = 156;                 // carril por el que se camina, delante de los taburetes
    const HERO_SEAT_X = STOOLS[2] - 5;
    const BARMAN_X = 322, BARMAN_Y = 132;   // sobre una tarima: se le ve de cintura para arriba

    // foros reales (del bloque de datos)
    const FOROS = (Array.isArray(D.foros) && D.foros.length ? D.foros : [
      { nombre: 'Preséntate', hilos: 120 }, { nombre: 'Off Topic', hilos: 35 }
    ]).slice(0, 6).map(f => ({ nombre: String(f.nombre || '').toUpperCase(), hilos: Math.max(0, Math.floor(Number(f.hilos) || 0)) }));
    const PRESENTATE = FOROS.findIndex(f => f.nombre.startsWith('PRESÉNTATE'));

    // ----- recorrido -----
    const T_SIT0 = 0.3 + (HERO_SEAT_X + 14) / V;   // llega al taburete
    const T_SIT1 = T_SIT0 + 0.3;                    // ya sentado
    const T_UP = 15.8;
    const T_END = T_UP + (WW + 20 - HERO_SEAT_X) / V;
    const L = Math.ceil(T_END + 0.6);
    function heroAt(t) {
      if (t < 0.3) return { x: -14, y: LANE, walking: true, sit: false };
      if (t < T_SIT0) return { x: -14 + (t - 0.3) * V, y: LANE, walking: true, sit: false };
      if (t < T_SIT1) return { x: HERO_SEAT_X, y: Math.round(LANE + (SEAT - LANE) * seg(t, T_SIT0, T_SIT1)), walking: false, sit: false };
      if (t < T_UP) return { x: HERO_SEAT_X, y: SEAT, walking: false, sit: true };
      return { x: HERO_SEAT_X + (t - T_UP) * V, y: LANE, walking: true, sit: false };
    }
    const CAM_SIT = 128;   // sentado: encuadra el tablón y toda la barra
    function camAt(hx, t) {
      if (t < T_UP) return Math.round(clamp(hx - 130, 0, CAM_SIT));
      return Math.round(clamp(CAM_SIT + (hx - HERO_SEAT_X), CAM_SIT, WW - W));
    }

    // conversación: quién, cuándo, qué
    const NOMBRE = M.protagonista(D, 70);
    const LINES = [
      { who: 'pa', t0: 0.4, t1: 1.9, s: '¡MÁS GROG!' },
      { who: 'p1', t0: 2.0, t1: 3.4, s: '¿HAY SEMILLA?' },
      { who: 'p2', t0: 3.6, t1: 4.8, s: '+1' },
      { who: 'mod', t0: 5.2, t1: 6.8, s: '¡SIN SPOILERS!' },
      { who: 'hero', t0: 9.0, t1: 10.6, s: 'HOLA A TODOS' },
      { who: 'p4', t0: 10.8, t1: 12.2, s: NOMBRE ? '¡BIENVENIDO, ' + NOMBRE + '!' : '¡BIENVENIDO!' },
      { who: 'p1', t0: 12.4, t1: 13.6, s: '+1' },
      { who: 'mod', t0: 14.0, t1: 15.5, s: 'INVITA LA CASA' }
    ];
    const T_POST = 9.4;               // Preséntate suma un hilo
    const MUG0 = 13.0, MUG1 = 13.8;   // la jarra se desliza por la barra

    // ----- capas fijas -----
    const [back, bk] = mk(WW, H);
    const [front, fk] = mk(WW, H);
    const FORO_SIGN = spriteText('EL FORO', '#ffb347', 2);
    const TROFEOS = spriteText('TROFEOS', '#3ee8ff');
    // balda de arriba: mapache disecado, calavera con vela, galeón, cofre, cabeza de mono, mapa y catalejo
    function shelfTop(c) {
      for (let k = 0; k < 5; k++) R(c, 247, 46 + k * 3, 3, 3, k % 2 ? '#2a2a2e' : '#9a9aa2');
      R(c, 248, 60, 24, 2, '#5a3a24');
      R(c, 250, 51, 14, 9, '#7a7a82'); R(c, 252, 55, 8, 5, '#a8a8b0');
      R(c, 261, 47, 8, 7, '#7a7a82'); R(c, 261, 45, 2, 2, '#5a5a62'); R(c, 267, 45, 2, 2, '#5a5a62');
      R(c, 262, 49, 6, 2, '#1a1a1e'); R(c, 263, 49, 1, 1, '#ffffff'); R(c, 266, 49, 1, 1, '#ffffff');
      R(c, 269, 51, 1, 1, '#0a0a0a'); R(c, 251, 60, 2, 0, '#000');
      R(c, 280, 53, 8, 7, '#e8e2d0'); R(c, 281, 60, 6, 2, '#d8d0b8');
      R(c, 281, 55, 2, 2, '#1a1410'); R(c, 285, 55, 2, 2, '#1a1410'); R(c, 284, 58, 1, 1, '#1a1410');
      R(c, 282, 60, 1, 2, '#8a8270'); R(c, 285, 60, 1, 2, '#8a8270');
      R(c, 283, 48, 2, 5, '#f0e6c0');
      // galeón: casco curvo con popa alta, velas hinchadas por palos, jarcia y bandera
      R(c, 299, 54, 32, 2, '#7a4a22'); R(c, 300, 56, 30, 2, '#6a3a1a'); R(c, 302, 58, 26, 2, '#5a3016');
      R(c, 325, 50, 7, 4, '#7a4a22'); R(c, 325, 50, 7, 1, '#c9a24a'); R(c, 296, 52, 4, 2, '#7a4a22');
      R(c, 299, 55, 32, 1, '#c9a24a');
      for (let k = 0; k < 4; k++) R(c, 303 + k * 6, 57, 2, 1, '#1a0c06');
      R(c, 308, 40, 1, 14, '#3a2410'); R(c, 319, 39, 1, 15, '#3a2410');
      for (const [mx, top] of [[308, 42], [319, 41]]) {
        R(c, mx - 3, top, 7, 3, '#efe6cc'); R(c, mx - 4, top + 4, 9, 3, '#efe6cc'); R(c, mx - 4, top + 8, 9, 3, '#efe6cc');
        R(c, mx + 3, top, 1, 3, '#c9bf9e'); R(c, mx + 4, top + 4, 1, 3, '#c9bf9e'); R(c, mx + 4, top + 8, 1, 3, '#c9bf9e');
      }
      for (let k = 0; k < 10; k++) R(c, 297 + k, 52 - Math.round(k * 1.1), 1, 1, '#8a7a5a');
      R(c, 320, 39, 5, 3, '#111111'); R(c, 322, 40, 1, 1, '#ffffff');
      R(c, 311, 60, 8, 2, '#5a3a24');
      R(c, 338, 51, 15, 3, '#9a6a2a'); R(c, 338, 54, 15, 8, '#7a4a1a');
      R(c, 341, 51, 1, 11, '#c9a24a'); R(c, 349, 51, 1, 11, '#c9a24a');
      R(c, 344, 55, 3, 2, '#ffd23e'); R(c, 339, 50, 5, 1, '#ffd23e');
      R(c, 360, 50, 2, 6, '#6a6458'); R(c, 378, 50, 2, 6, '#6a6458');
      R(c, 362, 45, 16, 17, '#7a7468'); R(c, 362, 47, 16, 2, '#5a5448');
      R(c, 365, 50, 3, 2, '#1a1814'); R(c, 372, 50, 3, 2, '#1a1814');
      R(c, 366, 56, 8, 3, '#4a4438'); R(c, 368, 54, 1, 1, '#3a3428'); R(c, 371, 54, 1, 1, '#3a3428');
      R(c, 386, 58, 22, 4, '#e0cc98'); R(c, 386, 58, 2, 4, '#c0a870'); R(c, 406, 58, 2, 4, '#c0a870'); R(c, 396, 58, 2, 4, '#a8243a');
      R(c, 412, 58, 14, 3, '#c9a24a'); R(c, 412, 58, 4, 3, '#8a6a2a'); R(c, 424, 58, 2, 3, '#e8d690');
    }
    // balda del medio: botellas un 5 % más bajas, botellas de grog con calavera y el barril de GROG
    function shelfMid(c, r) {
      const bottles = ['#3a7a4a', '#7a3a2a', '#c9a24a', '#4a6a9a', '#9a3a6a', '#d9d9e4', '#6a4a2a'];
      const sy = 86;
      for (let x = 250; x < 426;) {
        if (x >= 322 && x < 352) { x = 352; continue; }
        if (r() < 0.25) {
          R(c, x, sy - 9, 5, 9, '#1f4a2a'); R(c, x + 1, sy - 12, 3, 3, '#1f4a2a');
          R(c, x + 1, sy - 7, 3, 3, '#e8dcb0'); R(c, x + 2, sy - 6, 1, 1, '#111111');
          R(c, x, sy - 8, 1, 6, 'rgba(255,255,255,0.25)');
          x += 7 + ((r() * 3) | 0);
        } else {
          const h = Math.round((10 + ((r() * 8) | 0)) * 0.95), col = bottles[(r() * bottles.length) | 0];
          R(c, x, sy - h, 4, h, col); R(c, x + 1, sy - h - 3, 2, 3, col);
          R(c, x, sy - h + 2, 1, h - 4, 'rgba(255,255,255,0.25)');
          x += 6 + ((r() * 3) | 0);
        }
      }
      R(c, 326, 72, 22, 14, '#7a4a22');
      for (let x = 328; x < 348; x += 4) R(c, x, 72, 1, 14, '#6a3a18');
      R(c, 326, 74, 22, 1, '#3a3a3a'); R(c, 326, 83, 22, 1, '#3a3a3a');
      R(c, 329, 76, 16, 6, '#e8dcb0'); txt(c, 'GROG', 330, 77, '#a8243a');
      R(c, 336, 84, 2, 2, '#c9a24a');
    }
    // balda de abajo: vasos (collins, pinta, roca) y copas (martini, vino)
    function shelfLow(c) {
      const G = 'rgba(200,230,255,0.55)', HL = 'rgba(255,255,255,0.85)';
      const kinds = ['collins', 'pinta', 'martini', 'vino', 'roca'];
      let k = 0;
      for (let x = 250; x < 422; k++) {
        const kind = kinds[k % kinds.length];
        if (kind === 'collins') { R(c, x, 97, 4, 13, G); R(c, x, 97, 1, 13, HL); x += 7; }
        else if (kind === 'pinta') { R(c, x, 99, 6, 11, G); R(c, x + 1, 99, 1, 10, HL); R(c, x, 99, 6, 1, HL); x += 9; }
        else if (kind === 'martini') {
          R(c, x, 101, 7, 1, G); R(c, x + 1, 102, 5, 1, G); R(c, x + 2, 103, 3, 1, G);
          R(c, x + 3, 104, 1, 5, G); R(c, x + 1, 109, 5, 1, G); R(c, x, 101, 1, 1, HL); x += 10;
        } else if (kind === 'vino') {
          R(c, x + 1, 100, 5, 4, G); R(c, x + 2, 104, 3, 1, G); R(c, x + 2, 100, 1, 3, HL);
          R(c, x + 3, 105, 1, 4, G); R(c, x + 1, 109, 5, 1, G); x += 10;
        } else { R(c, x, 104, 6, 6, G); R(c, x + 1, 104, 1, 5, HL); x += 9; }
      }
    }
    function buildLayers() {
      const r = rng(23);
      // techo y pared verde de taberna
      R(bk, 0, 0, WW, 16, '#0f1412');
      for (let x = 0; x < WW; x += 48) R(bk, x, 0, 5, 16, '#1a201c');
      R(bk, 0, 16, WW, 3, '#4a3020'); R(bk, 0, 19, WW, 1, '#140c08');
      R(bk, 0, 20, WW, 130, '#1d2a25');
      for (let x = 0; x < WW; x += 14) R(bk, x, 20, 1, 130, '#223129');
      for (let i = 0; i < 200; i++) R(bk, (r() * WW) | 0, 20 + ((r() * 125) | 0), 1, 1, '#25352d');
      // zócalo de madera
      R(bk, 0, 112, WW, 38, '#3a2418');
      for (let x = 0; x < WW; x += 20) R(bk, x, 112, 1, 38, '#2c1a10');
      R(bk, 0, 112, WW, 2, '#5a3a24');

      // arco de entrada desde el bufé
      R(bk, 0, 70, 40, 80, '#2a1620'); R(bk, 4, 74, 32, 76, '#120810');
      for (let y = 78; y < 150; y += 3) R(bk, 6, y, 28, 1, 'rgba(255,62,165,0.08)');

      // estanterías detrás de la barra
      R(bk, 246, 40, 184, 74, '#2a1a10');
      for (const sy of [62, 86, 110]) R(bk, 246, sy, 184, 3, '#5a3a24');
      shelfTop(bk); shelfMid(bk, r); shelfLow(bk);

      // diana de dardos entre la barra y la puerta de los trofeos
      const dcx = 471, dcy = 90;
      for (let y = -15; y <= 15; y++) for (let x = -15; x <= 15; x++)
        if (Math.hypot(x, y) <= 15.2) R(bk, dcx + x, dcy + y, 1, 1, '#3a2418');
      for (let y = -13; y <= 13; y++) for (let x = -13; x <= 13; x++) {
        const d = Math.hypot(x, y);
        if (d > 13.2) continue;
        const alt = Math.floor(((Math.atan2(y, x) + Math.PI) / (2 * Math.PI)) * 20 + 0.5) % 2;
        let col;
        if (d > 11.5) col = '#141414';
        else if (d > 10) col = alt ? '#c0303a' : '#2a8a4a';
        else if (d > 6.5) col = alt ? '#141414' : '#e8dcb0';
        else if (d > 5.2) col = alt ? '#c0303a' : '#2a8a4a';
        else if (d > 1.8) col = alt ? '#141414' : '#e8dcb0';
        else if (d > 0.9) col = '#2a8a4a';
        else col = '#c0303a';
        R(bk, dcx + x, dcy + y, 1, 1, col);
      }
      for (const [ox, oy, fc] of [[3, -4, '#3ee8ff'], [-5, 2, '#ffd23e'], [1, 6, '#ff3ea5']]) {
        R(bk, dcx + ox, dcy + oy, 1, 1, '#d9d9e4'); R(bk, dcx + ox + 1, dcy + oy - 1, 2, 1, '#c9a24a');
        R(bk, dcx + ox + 3, dcy + oy - 2, 2, 2, fc);
      }
      R(bk, dcx - 13, 108, 26, 13, '#5a3a24'); R(bk, dcx - 12, 109, 24, 11, '#1d2a24');
      txt(bk, '501', dcx - (tw('501') >> 1), 112, '#e8efe6');
      // espejo con reflejo
      R(bk, 300, 22, 76, 14, '#1a2430'); R(bk, 302, 24, 72, 10, '#24364a');
      for (let k = 0; k < 6; k++) R(bk, 310 + k * 2, 24 + k, 2, 1, '#3a5a7a');

      // tablón de corcho
      R(bk, 136, 30, 104, 92, '#4a2a18'); R(bk, 139, 33, 98, 86, '#9a6a3a');
      for (let i = 0; i < 160; i++) R(bk, 139 + ((r() * 98) | 0), 33 + ((r() * 86) | 0), 1, 1, r() < 0.5 ? '#8a5a2e' : '#aa7a48');

      // lámparas sobre la barra
      for (const lx of [270, 340, 410]) {
        R(bk, lx, 16, 1, 22, '#0a0806');
        R(bk, lx - 4, 38, 9, 3, '#3a6a4a'); R(bk, lx - 1, 41, 3, 1, '#fff0c0');
        for (let y = 42; y < 128; y++) {
          const half = (y - 42) * 0.25 + 1;
          for (let xx = Math.floor(lx - half); xx <= lx + half; xx++)
            if (((xx + y) & 1) === 0 && r() < 0.45 * (1 - (y - 42) / 95)) R(bk, xx, y, 1, 1, 'rgba(255,214,150,0.10)');
        }
      }
      // puerta hacia los trofeos
      R(bk, 500, 76, 40, 74, '#2a1a10'); R(bk, 504, 80, 32, 70, '#0d1416');
      for (let y = 84; y < 150; y += 3) R(bk, 506, y, 28, 1, 'rgba(62,232,255,0.07)');

      // suelo de tablones
      R(bk, 0, 150, WW, 30, '#2a1a12');
      for (let y = 150; y < 180; y += 5) {
        R(bk, 0, y, WW, 1, '#20140c');
        for (let x = ((y / 5) % 2) * 23; x < WW; x += 46) R(bk, x, y, 1, 5, '#20140c');
      }

      // barra
      R(fk, 236, 124, 204, 3, '#8a5a32'); R(fk, 236, 124, 204, 1, '#b07a48');
      R(fk, 238, 127, 200, 21, '#4a2a18');
      for (let x = 246; x < 436; x += 24) { R(fk, x, 130, 18, 14, '#3e2414'); R(fk, x, 130, 18, 1, '#5a3a24'); }
      R(fk, 238, 145, 200, 1, '#c9a24a'); R(fk, 238, 146, 200, 1, '#7a5a20');
    }
    buildLayers();

    function stool(ctx, cx) {
      R(ctx, cx - 5, 143, 11, 2, '#8a1f2b'); R(ctx, cx - 5, 143, 11, 1, '#b8323f');
      R(ctx, cx, 145, 1, 6, '#9aa3b5'); R(ctx, cx - 2, 148, 5, 1, '#9aa3b5');
      R(ctx, cx - 3, 151, 7, 1, '#6a7280');
    }
    function corkNotes(ctx, t) {
      txt(ctx, 'TABLÓN', 188 - (tw('TABLÓN') >> 1), 37, '#2a1408');
      const tints = ['#fff3a0', '#ffd1e6', '#c8f0ff', '#d6ffc8', '#ffe0b8', '#e6dcff'];
      FOROS.forEach((f, i) => {
        const col = i % 2, row = Math.floor(i / 2);
        const x = 139 + col * 50, y = 46 + row * 24;
        R(ctx, x + 1, y + 1, 48, 21, 'rgba(0,0,0,0.25)');
        R(ctx, x, y, 48, 21, tints[i % tints.length]);
        R(ctx, x + 22, y - 1, 3, 3, i % 2 ? '#3a6aff' : '#e0303a');
        // nombre en una o dos líneas
        const words = f.nombre.split(' ');
        let lines = [f.nombre];
        if (tw(f.nombre) > 44 && words.length > 1) lines = [words[0], words.slice(1).join(' ')];
        lines = lines.map(l => { let s = l; while (tw(s) > 44) s = s.slice(0, -1); return s; });
        lines.forEach((l, k) => txt(ctx, l, x + 24 - (tw(l) >> 1), y + 3 + k * 6, '#2a2030'));
        const n = f.hilos + (i === PRESENTATE && t >= T_POST ? 1 : 0);
        const lab = miles(n) + (n === 1 ? ' HILO' : ' HILOS');
        const bump = i === PRESENTATE && t >= T_POST && t < T_POST + 1.4;
        txt(ctx, lab, x + 24 - (tw(lab) >> 1), y + (lines.length > 1 ? 15 : 11), bump ? '#d8344a' : '#5a4a3a');
        if (bump && Math.floor(t * 8) % 2 === 0) {
          ctx.fillStyle = '#ffffff';
          [[-2, 2], [50, 4], [-1, 19], [49, 18]].forEach(p => ctx.fillRect(x + p[0], y + p[1], 1, 1));
        }
      });
    }
    // primer plano: gramola y planta (más rápidas que la cámara)
    function foreground(ctx, t) {
      const jx = 120;
      R(ctx, jx, 136, 34, 44, '#3a1a10');
      R(ctx, jx + 2, 138, 30, 14, '#6a2a18'); R(ctx, jx + 4, 140, 26, 10, '#1a0c08');
      // título de la canción pasando por la pantalla
      const songs = ['A PIRATE I WAS MEANT TO BE', 'MONKEY ISLAND - TEMA PRINCIPAL'];
      const span = s => tw(s) + 26;
      const total = songs.reduce((a, s) => a + span(s), 0);
      let pos = (t * 14) % total, song = songs[0];
      for (const s of songs) { if (pos < span(s)) { song = s; break; } pos -= span(s); }
      ctx.save();
      ctx.beginPath(); ctx.rect(jx + 5, 141, 24, 8); ctx.clip();
      ctx.shadowColor = '#ffb347'; ctx.shadowBlur = 3;
      txt(ctx, song, Math.round(jx + 29 - pos), 143, '#ffc870');
      ctx.restore();
      const hue = (t * 60) % 360;
      glowRects(ctx, `hsl(${hue},90%,60%)`, 6, [[jx + 2, 154, 30, 2], [jx + 2, 158, 2, 22], [jx + 30, 158, 2, 22]]);
      R(ctx, jx + 6, 160, 22, 20, '#24120a');
      for (let k = 0; k < 4; k++) R(ctx, jx + 8, 163 + k * 4, 18, 1, '#5a3a24');
      const bx = 330;
      R(ctx, bx, 158, 120, 22, '#4a2410'); R(ctx, bx, 158, 120, 1, '#7a4a24');
      R(ctx, bx + 5, 162, 110, 18, '#1f6b3a'); R(ctx, bx + 5, 162, 110, 1, '#2a8a4a');
      for (const [qx, qy] of [[bx + 3, 160], [bx + 58, 159], [bx + 113, 160]]) R(ctx, qx, qy, 4, 3, '#0a0806');
      R(ctx, bx + 12, 159, 44, 1, '#c9a24a'); R(ctx, bx + 12, 159, 3, 1, '#e8e8f0');
      const ball = (x, y, col) => { R(ctx, x, y, 3, 3, col); R(ctx, x, y, 1, 1, 'rgba(255,255,255,0.7)'); };
      ball(bx + 80, 168, '#111111'); ball(bx + 70, 173, '#ffd23e'); ball(bx + 92, 166, '#3a6aff'); ball(bx + 100, 174, '#7a2a9a');
      const k1 = seg(t, 9.0, 9.6), k2 = seg(t, 9.6, 10.2);
      ball(bx + 20 + 24 * k1, 170, '#f4f4f8');
      if (k2 < 1) ball(bx + 47 + 11 * k2, 170 - 9 * k2, '#d8344a');
      const px = 560;
      R(ctx, px, 166, 16, 14, '#5a2f2a'); R(ctx, px, 166, 16, 2, '#7a3f34');
      R(ctx, px - 4, 146, 10, 10, '#16301e'); R(ctx, px + 8, 140, 12, 14, '#1c3a24'); R(ctx, px + 2, 152, 14, 14, '#14281a');
    }

    const [buf, ctx] = mk(W, H);

    function render(vctx, t) {
      const hs = heroAt(t);
      const cam = camAt(hs.x, t);
      const st = Math.floor(t * 10) % 8;
      ctx.clearRect(0, 0, W, H);
      ctx.save();
      ctx.translate(-cam, 0);
      ctx.drawImage(back, 0, 0);
      glowImg(ctx, FORO_SIGN, 338 - (FORO_SIGN.width >> 1), 2, '#ffb347', 6, flick(t, 3.3));
      glowImg(ctx, TROFEOS, 520 - (TROFEOS.width >> 1), 69, '#3ee8ff', 4);
      corkNotes(ctx, t);
      chandelier(ctx, t);
      glowRects(ctx, '#ffb347', 4, [[283, 46 + (Math.sin(t * 15) > 0.4 ? 1 : 0), 2, 2]]);

      // camarero (moderador) limpiando un vaso detrás de la barra
      const talking = LINES.some(l => l.who === 'mod' && t >= l.t0 && t < l.t1);
      drawPerson(ctx, BARMAN_X, BARMAN_Y, 0, false, talking ? 'wave' : 'tray', MOD);
      if (!talking) {
        const wob = Math.round(Math.sin(t * 8));
        R(ctx, BARMAN_X + 8, BARMAN_Y - 18, 3, 4, 'rgba(200,230,255,0.7)');
        R(ctx, BARMAN_X + 8 + wob, BARMAN_Y - 15, 3, 2, '#f4f4f8');
      }
      ctx.drawImage(front, 0, 0);

      // jarra de grog que se desliza hasta el protagonista
      if (t >= MUG0) {
        const mx = Math.round(BARMAN_X + 10 + (HERO_SEAT_X + 9 - BARMAN_X - 10) * seg(t, MUG0, MUG1));
        R(ctx, mx, 117, 5, 7, '#c9a24a'); R(ctx, mx, 116, 5, 2, '#f4ead0'); R(ctx, mx + 5, 119, 1, 3, '#c9a24a');
      }

      STOOLS.forEach(cx => stool(ctx, cx));
      const sayer = who => LINES.find(l => l.who === who && t >= l.t0 && t < l.t1);
      drawPerson(ctx, STOOLS[0] - 5, SEAT, 0, false, sayer('p1') ? 'wave' : 'down', P1, { sit: true });
      drawPerson(ctx, STOOLS[1] - 5, SEAT, 0, false, 'down', P2, { sit: true, flip: true });
      drawPerson(ctx, STOOLS[3] - 5, SEAT, 0, false, sayer('p4') ? 'wave' : 'down', P4, { sit: true, flip: true });
      drawPerson(ctx, hs.x, hs.y, st, hs.walking, sayer('hero') ? 'wave' : 'down', HERO, { sit: hs.sit });

      // bocadillos
      const heads = {
        p1: [STOOLS[0], SEAT - 26], p2: [STOOLS[1], SEAT - 26], p4: [STOOLS[3], SEAT - 26],
        hero: [hs.x + 5, hs.y - 26], mod: [BARMAN_X + 5, BARMAN_Y - 26],
        pa: [LAMPX - 12 + swingX(t), 72 - 26]
      };
      for (const l of LINES) {
        if (t < l.t0 || t >= l.t1) continue;
        const n = Math.floor(seg(t, l.t0, l.t0 + 0.4) * [...l.s].length);
        const [hx, hy] = heads[l.who];
        bubble(ctx, Math.round(hx), hy, l.s, n, cam, cam + W);
      }
      ctx.restore();

      ctx.save();
      ctx.translate(-Math.round(cam * 1.3), 0);
      foreground(ctx, t);
      ctx.restore();

      vctx.drawImage(buf, 0, 0);
    }

    return { L, render };
  }
});
