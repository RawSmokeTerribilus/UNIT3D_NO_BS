Escenas.registrar({
  id: 'bufe',
  titulo: 'El bufé',
  zona: 'Torrents',
  fotograma: 7.6,
  ejemplo: { categorias: { peliculas: 1500, series: 1100, anime: 420, cine_anime: 130, audiolibros: 900, libros: 60 } },
  crear(M, D) {
    const { W, H, mk, rng, R, clamp, seg, SM, glyph, tw, txt, spriteText, glowImg, glowRects, flick, STR, leg, sitLeg, person, drawPerson, bubble, miles } = M;
    // ================= ESCENA: el bufé =================
    const WW = 840;          // ancho del mundo
    const HY = 153;          // pies del protagonista
    const V = 46;            // velocidad al andar (px/s)
    const HERO = { hair: '#f0c66b', hairD: '#b98a3a', hairL: '#ffe39a', skin: '#eab28a', skinD: '#c4865f', shirt: '#eef0f6', shirtD: '#b9c0d2', sleeveB: '#a9b0c4', sash: '#d8344a', sashD: '#a8243a', leg: '#2b2638', legB: '#1c1925', boot: '#0e0b13', style: 'pony' };
    const CHEF = { hair: '#2a1a12', skin: '#d9a07a', skinD: '#b07a58', shirt: '#f4f4f8', shirtD: '#c9cdd8', sleeveB: '#c9cdd8', sash: null, leg: '#2a2a34', legB: '#1d1d26', boot: '#0e0b13', style: 'short', hat: true };
    const GIRL = { hair: '#6a3420', skin: '#d8a27c', skinD: '#b07a58', shirt: '#3ab0a0', shirtD: '#2a8478', sleeveB: '#2a8478', sash: null, leg: '#3a3f6a', legB: '#2a2e50', boot: '#141020', style: 'bun' };

    // categorías de NOBS; los recuentos llegan en D.categorias (el tracker los rellena)
    const CATS = [
      { key: 'peliculas', lines: ['PELÍCULAS'], n: 1500, col: '#ff3ea5', food: 'pop' },
      { key: 'series', lines: ['SERIES'], n: 1100, col: '#3ee8ff', food: 'pizza' },
      { key: 'anime', lines: ['ANIME'], n: 420, col: '#ffd23e', food: 'ramen' },
      { key: 'cine_anime', lines: ['CINE DE', 'ANIME'], n: 130, col: '#ff8a3e', food: 'onigiri' },
      { key: 'audiolibros', lines: ['AUDIOLIBROS'], n: 900, col: '#4dff8a', food: 'cafe' },
      { key: 'libros', lines: ['LIBROS'], n: 60, col: '#b18cff', food: 'sopa' }
    ];
    CATS.forEach(c => {
      const v = Number(D.categorias && D.categorias[c.key]);
      if (Number.isFinite(v) && v >= 0) c.n = Math.floor(v);
    });
    const SX = i => 60 + i * 120;        // borde izquierdo del puesto
    const CX = i => SX(i) + 50;          // centro del puesto
    const TRAY = (i, k) => SX(i) + 6 + k * 31;

    function steam(c, x, y, t) {
      c.fillStyle = 'rgba(255,255,255,0.35)';
      for (let k = 0; k < 3; k++) {
        const ph = (t * 5 + k * 2.7 + x * 0.13) % 6;
        c.fillRect(Math.round(x + Math.sin(t * 3 + k)), Math.round(y - ph), 1, 1);
      }
    }
    // (x, y) = esquina izquierda, fila bajo la comida
    const FOOD = {
      pop(c, x, y) {
        R(c, x, y - 7, 6, 7, '#eceaf2'); R(c, x + 1, y - 7, 1, 7, '#d23a4a'); R(c, x + 4, y - 7, 1, 7, '#d23a4a');
        R(c, x - 1, y - 9, 8, 2, '#ffe7a0'); R(c, x, y - 10, 2, 1, '#fff3c8'); R(c, x + 3, y - 10, 3, 1, '#fff3c8');
      },
      pizza(c, x, y) {
        R(c, x, y - 2, 8, 2, '#c98a34'); R(c, x + 1, y - 4, 6, 2, '#f0c050'); R(c, x + 2, y - 6, 4, 2, '#f0c050');
        R(c, x + 3, y - 7, 2, 1, '#f0c050'); R(c, x + 2, y - 4, 1, 1, '#c0392b'); R(c, x + 4, y - 6, 1, 1, '#c0392b'); R(c, x + 5, y - 3, 1, 1, '#c0392b');
      },
      ramen(c, x, y, t) {
        R(c, x, y - 6, 8, 1, '#f2d79b'); R(c, x - 1, y - 5, 10, 1, '#c23a48'); R(c, x, y - 4, 8, 3, '#8a1f2b');
        R(c, x + 1, y - 1, 6, 1, '#6a1620'); R(c, x + 5, y - 7, 2, 1, '#ffd23e'); R(c, x + 1, y - 7, 1, 1, '#4f9a4f');
        steam(c, x + 3, y - 8, t);
      },
      onigiri(c, x, y) {
        R(c, x + 3, y - 7, 2, 1, '#f4f4f8'); R(c, x + 2, y - 6, 4, 1, '#f4f4f8'); R(c, x + 1, y - 5, 6, 1, '#f4f4f8');
        R(c, x, y - 4, 8, 4, '#f4f4f8'); R(c, x + 2, y - 3, 4, 3, '#1d2b22');
      },
      cafe(c, x, y, t) {
        R(c, x, y - 1, 8, 1, '#cfd3dd'); R(c, x + 1, y - 6, 5, 5, '#eef0f6'); R(c, x + 1, y - 6, 5, 1, '#5a3420');
        R(c, x + 6, y - 5, 1, 3, '#eef0f6'); steam(c, x + 3, y - 7, t);
      },
      sopa(c, x, y) {
        R(c, x - 1, y - 5, 10, 1, '#efe0b8'); R(c, x, y - 4, 8, 3, '#d9c08a'); R(c, x + 1, y - 1, 6, 1, '#b49a64');
        R(c, x + 1, y - 5, 1, 1, '#e07a3a'); R(c, x + 4, y - 5, 1, 1, '#e07a3a'); R(c, x + 6, y - 5, 1, 1, '#e07a3a');
      }
    };

    // ----- recorrido del protagonista -----
    const SEGS = [], STOPS = [];
    let END = 0;
    (function buildPath() {
      let t = 0.3, x = -14;
      const go = x1 => { const d = (x1 - x) / V; SEGS.push({ t0: t, t1: t + d, x0: x, x1, walk: true }); t += d; x = x1; };
      const stop = i => { SEGS.push({ t0: t, t1: t + 1.6, x0: x, x1: x, walk: false }); STOPS.push({ i, tp: t + 0.3 }); t += 1.6; };
      go(CX(0) - 5); stop(0);
      go(CX(2) - 5); stop(2);
      go(CX(4) - 5); stop(4);
      go(WW + 30);
      END = t;
    })();
    const L = Math.ceil(END + 1.2);
    function heroAt(t) {
      if (t < SEGS[0].t0) return { x: -14, walking: true };
      for (const s of SEGS) if (t < s.t1) return { x: s.x0 + (s.x1 - s.x0) * seg(t, s.t0, s.t1), walking: s.walk };
      return { x: WW + 30, walking: false };
    }

    // ----- cocinero: sale de la cocina con pizza recién subida para Series -----
    const DOORX = 164, CHEFX = CX(1) - 6, PLACE = 7.4;
    const CHEFY = 141;       // el cocinero pisa una tarima detrás del mostrador
    function chefAt(t) {
      if (t < 5.6 || t > 10.4) return null;
      if (t < 6.0) return { x: DOORX, walking: false, mode: 'serve', alpha: seg(t, 5.6, 6.0), flip: false };
      if (t < 7.2) return { x: DOORX + (CHEFX - DOORX) * seg(t, 6.0, 7.2), walking: true, mode: 'serve', alpha: 1, flip: false };
      if (t < 8.8) return { x: CHEFX, walking: false, mode: t < PLACE ? 'serve' : 'down', alpha: 1, flip: false };
      if (t < 10.0) return { x: CHEFX + (DOORX - CHEFX) * seg(t, 8.8, 10.0), walking: true, mode: 'down', alpha: 1, flip: true };
      return { x: DOORX, walking: false, mode: 'down', alpha: 1 - seg(t, 10.0, 10.4), flip: true };
    }

    // ----- capas fijas del mundo -----
    const [back, bk] = mk(WW, H);
    const [trays, tk] = mk(WW, H);
    const [front, fk] = mk(WW, H);
    const SIGNS = CATS.map(c => c.lines.map(l => spriteText(l, c.col, 2)));
    const BAR = spriteText('BAR', '#ff3ea5', 2);
    function buildLayers() {
      const r = rng(11);
      // techo y papel pintado
      R(bk, 0, 0, WW, 18, '#140a16');
      for (let x = 0; x < WW; x += 60) R(bk, x, 0, 6, 18, '#1f1020');
      R(bk, 0, 18, WW, 3, '#5a3249'); R(bk, 0, 21, WW, 1, '#22101e');
      R(bk, 0, 22, WW, 126, '#331a2d');
      for (let x = 0; x < WW; x += 10) R(bk, x, 22, 3, 126, '#3a1f34');
      for (let i = 0; i < 260; i++) R(bk, (r() * WW) | 0, 22 + ((r() * 120) | 0), 1, 1, '#41233a');

      CATS.forEach((cat, i) => {
        const cx = CX(i);
        // cartel (panel; las letras de neón se pintan en vivo)
        const sw = Math.max(...SIGNS[i].map(s => s.width)) + 10;
        const sh = cat.lines.length * 13 + 5;
        R(bk, cx - (sw >> 1), 26, sw, sh, '#170d1d');
        R(bk, cx - (sw >> 1), 26, sw, 1, '#2c1a34'); R(bk, cx - (sw >> 1), 25 + sh, sw, 1, '#0c070e');
        // lámparas colgantes con su cono de luz
        for (const lx of [SX(i) + 12, SX(i) + 88]) {
          R(bk, lx, 18, 1, 40, '#0e070e');
          R(bk, lx - 3, 58, 7, 3, '#c79a3a'); R(bk, lx - 1, 61, 3, 1, '#fff0c0');
          for (let y = 62; y < 136; y++) {
            const half = (y - 62) * 0.22 + 1;
            for (let xx = Math.floor(lx - half); xx <= lx + half; xx++)
              if (((xx + y) & 1) === 0 && r() < 0.5 * (1 - (y - 62) / 80)) R(bk, xx, y, 1, 1, 'rgba(255,214,150,0.10)');
          }
        }
        // pizarra con el recuento
        R(bk, cx - 21, 65, 42, 30, '#5a3a24'); R(bk, cx - 19, 67, 38, 26, '#1d2a24');
        const num = miles(cat.n);
        const k = tw(num, 2) <= 36 ? 2 : 1;
        txt(bk, num, cx - (tw(num, k) >> 1), k === 2 ? 70 : 73, '#e8efe6', k);
        const lab = cat.n === 1 ? 'PLATO' : 'PLATOS';
        txt(bk, lab, cx - (tw(lab) >> 1), 85, '#b9c8bd');
      });

      // puerta de la cocina
      R(bk, DOORX - 3, 98, 18, 50, '#2a1620'); R(bk, DOORX - 1, 100, 14, 48, '#4a2a2e');
      R(bk, DOORX + 3, 104, 6, 6, '#7a8a96'); R(bk, DOORX + 4, 105, 4, 4, '#2a4250');
      // entrada desde la calle
      R(bk, 4, 88, 40, 60, '#0d0914');
      R(bk, 8, 92, 16, 56, '#141428'); R(bk, 24, 92, 16, 56, '#141428');
      R(bk, 10, 94, 12, 44, '#16203a'); R(bk, 26, 94, 12, 44, '#16203a');
      for (let k = 0; k < 6; k++) { R(bk, 11 + k, 98 + k * 2, 1, 2, '#2f5f73'); R(bk, 27 + k, 98 + k * 2, 1, 2, '#2f5f73'); }
      // arco hacia el bar
      R(bk, 788, 92, 44, 56, '#2a1620'); R(bk, 792, 96, 36, 52, '#120810');
      for (let y = 100; y < 148; y += 3) R(bk, 794, y, 32, 1, 'rgba(255,170,90,0.10)');
      // suelo
      R(bk, 0, 148, WW, 32, '#2c1528');
      for (let row = 0, y = 148; y < 180; y += 8, row++)
        for (let x = (row % 2) * 16; x < WW; x += 32) R(bk, x, y, 16, 8, '#3a1d34');
      R(bk, 0, 148, WW, 1, '#1a0c18');

      // bandejas sobre el mostrador
      for (let i = 0; i < CATS.length; i++)
        for (let k = 0; k < 3; k++) {
          R(tk, TRAY(i, k), 136, 26, 1, '#d5dbe6'); R(tk, TRAY(i, k), 137, 26, 1, '#8a93a6');
        }

      // cristal, encimera y frente del mostrador
      fk.fillStyle = 'rgba(170,230,255,0.10)'; fk.fillRect(52, 118, 716, 16);
      R(fk, 52, 118, 716, 1, 'rgba(210,245,255,0.45)');
      for (let i = 0; i <= CATS.length; i++) R(fk, SX(i) - 10, 118, 1, 20, '#9aa3b5');
      R(fk, 52, 138, 716, 2, '#b7bfcc');
      R(fk, 52, 140, 716, 8, '#4a2434');
      for (let x = 52; x < 768; x += 40) R(fk, x, 140, 1, 7, '#3a1c2a');
    }
    buildLayers();

    // mesas del primer plano (se mueven más rápido que la cámara)
    const TABLES = [40, 250, 470, 690, 910, 1130];
    function foreground(ctx, t) {
      for (const px of TABLES) {
        R(ctx, px - 9, 158, 7, 7, '#120812'); R(ctx, px - 11, 164, 11, 16, '#120812');
        R(ctx, px - 8, 158, 4, 1, '#5a2346');
        R(ctx, px + 34, 156, 7, 7, '#120812'); R(ctx, px + 32, 162, 11, 18, '#120812');
        R(ctx, px + 35, 156, 4, 1, '#5a2346');
        R(ctx, px, 167, 30, 3, '#1a0c1a'); R(ctx, px + 13, 170, 4, 10, '#120812');
        R(ctx, px, 167, 30, 1, '#4a2a44');
        // servilletero de lata
        R(ctx, px + 3, 161, 7, 6, '#7d8596'); R(ctx, px + 3, 161, 1, 6, '#c9d0dc'); R(ctx, px + 9, 161, 1, 6, '#596070');
        R(ctx, px + 4, 159, 5, 2, '#f4f4f8'); R(ctx, px + 4, 159, 5, 1, '#ffffff');
        // carta de pie
        R(ctx, px + 20, 160, 3, 1, '#e8d6a8'); R(ctx, px + 19, 161, 5, 1, '#e8d6a8'); R(ctx, px + 18, 162, 7, 5, '#e8d6a8');
        R(ctx, px + 19, 163, 5, 1, '#d23a4a'); R(ctx, px + 19, 165, 4, 1, '#9a8a6a');
        // vela en vaso rojo
        R(ctx, px + 13, 163, 3, 4, '#8a1f33'); R(ctx, px + 13, 163, 3, 1, '#c23a55');
        const fl = Math.sin(t * 17 + px) > 0.2 ? 1 : 0;
        glowRects(ctx, '#ffb347', 6, [[px + 14, 161 + fl, 1, 2 - fl]]);
      }
    }

    // ----- fotograma -----
    const [buf, ctx] = mk(W, H);

    function render(vctx, t) {
      const hs = heroAt(t);
      const cam = Math.round(clamp(hs.x - 130, 0, WW - W));
      const st = Math.floor(t * 10) % 8;
      ctx.clearRect(0, 0, W, H);
      ctx.save();
      ctx.translate(-cam, 0);
      ctx.drawImage(back, 0, 0);

      CATS.forEach((cat, i) => {
        const a = i === 5 ? flick(t, 2.4) : 1;
        SIGNS[i].forEach((spr, l) => {
          const x = CX(i) - (spr.width >> 1), y = 29 + l * 13;
          if (a < 1) { ctx.globalAlpha = a; ctx.drawImage(spr, x, y); ctx.globalAlpha = 1; }
          else glowImg(ctx, spr, x, y, cat.col, 5);
        });
      });
      glowImg(ctx, BAR, 810 - (BAR.width >> 1), 79, '#ff3ea5', 5);
      glowRects(ctx, '#3ee8ff', 4, [[3, 87, 42, 1], [3, 87, 1, 61], [44, 87, 1, 61]]);

      // cocinero (detrás del mostrador)
      const ch = chefAt(t);
      let chefBob = 0;
      if (ch) {
        chefBob = drawPerson(ctx, ch.x, CHEFY, st, ch.walking, ch.mode, CHEF, { alpha: ch.alpha, flip: ch.flip });
        if (ch.mode === 'serve') {
          const tx = ch.x + 3, ty = CHEFY - 32 + chefBob;
          R(ctx, tx, ty, 12, 1, '#d5dbe6'); R(ctx, tx, ty + 1, 12, 1, '#8a93a6');
          ctx.globalAlpha = ch.alpha; FOOD.pizza(ctx, tx + 2, ty); ctx.globalAlpha = 1;
        }
      }

      ctx.drawImage(trays, 0, 0);
      const picked = i => STOPS.some(s => s.i === i && t >= s.tp);
      CATS.forEach((cat, i) => {
        for (let k = 0; k < 3; k++) {
          if (k === 1 && picked(i)) continue;
          if (i === 1 && k === 1 && t < PLACE) continue;
          if (i === 5 && k !== 1) continue;
          FOOD[cat.food](ctx, TRAY(i, k) + 9, 136, t);
        }
      });
      if (t >= PLACE && t < PLACE + 1.6 && Math.floor(t * 8) % 2 === 0) {
        const sx = TRAY(1, 1) + 13;
        ctx.fillStyle = '#fff3a0';
        [[-8, -10], [8, -9], [-6, -2], [9, -3], [0, -13]].forEach(p => ctx.fillRect(sx + p[0], 136 + p[1], 1, 1));
      }
      ctx.drawImage(front, 0, 0);
      glowRects(ctx, '#ff3ea5', 4, [[52, 147, 716, 1]]);
      ctx.fillStyle = 'rgba(255,62,165,0.10)';
      ctx.fillRect(52, 148, 716, 3);

      // clienta esperando en Cine de anime
      const GX = SX(3) + 25;   // su onigiri cae en el hueco entre dos bandejas del mostrador
      const gb = drawPerson(ctx, GX, 151, 0, false, 'tray', GIRL);
      R(ctx, GX + 4, 151 - 14 + gb, 10, 1, '#d5dbe6'); R(ctx, GX + 4, 151 - 13 + gb, 10, 1, '#8a93a6');
      FOOD.onigiri(ctx, GX + 5, 151 - 14 + gb);

      // protagonista con su bandeja
      const hb = drawPerson(ctx, hs.x, HY, st, hs.walking, 'tray', HERO);
      const tx = Math.round(hs.x) + 4, ty = HY - 14 + hb;
      R(ctx, tx, ty, 11, 1, '#d5dbe6'); R(ctx, tx, ty + 1, 11, 1, '#8a93a6');
      STOPS.forEach((s, n) => {
        const food = FOOD[CATS[s.i].food];
        const dx = tx + 1 + n * 3;
        if (t >= s.tp + 0.5) food(ctx, dx, ty, t);
        else if (t >= s.tp) {
          const k = (t - s.tp) / 0.5;
          const x0 = TRAY(s.i, 1) + 9, y0 = 136;
          food(ctx, x0 + (dx - x0) * k, y0 + (ty - y0) * k - 14 * Math.sin(Math.PI * k), t);
        }
      });

      if (t >= 6.9 && t < 8.6) {
        const n = Math.floor(seg(t, 6.9, 7.4) * 15);
        bubble(ctx, CHEFX + 4, CHEFY - 35, '¡RECIÉN SUBIDO!', n);
      }
      ctx.restore();

      ctx.save();
      ctx.translate(-Math.round(cam * 1.35), 0);
      foreground(ctx, t);
      ctx.restore();

      vctx.drawImage(buf, 0, 0);
    }

    return { L, render };
  }
});
