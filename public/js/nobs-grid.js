/* ============================================================
   NOBS · Perspective Grid  (drop-in replacement for the static
   `body.fx-grid .fx-overlay` grid)

   - Mirrored floor + ceiling perspective grid, slow flow toward
     the viewer.
   - Transparent at the horizon (center), so page text stays
     readable. The old static grid's central "horizon glow" is gone.
   - Rendered on a transparent <canvas>, composited with
     mix-blend-mode:screen (adds light only — never darkens
     images), masked off the header banner + the central reading
     column (see _effects.scss #nobs-grid).

   Only runs while <body> has the `fx-grid` class — matches the
   existing effect-selection convention. Config is read from the
   mounted canvas's data-attrs (blade feeds the user's preference),
   keeping it CSP-safe (no inline script needed):
     <canvas id="nobs-grid" data-hue="322"></canvas>
   ============================================================ */
(function () {
  const READY = () => document.body && document.body.classList.contains('fx-grid');

  function start() {
    if (!READY()) return;

    let cv = document.getElementById('nobs-grid');
    if (!cv) {
      cv = document.createElement('canvas');
      cv.id = 'nobs-grid';
      cv.setAttribute('aria-hidden', 'true');
      document.body.prepend(cv);
    }
    const ds = cv.dataset;
    const opt = {
      hue:       +(ds.hue || 322),
      cell:      +(ds.cell || 42),
      speed:     +(ds.speed || 1),
      intensity: +(ds.intensity || 1),
      topFade:   ds.topFade || '16vh',
    };

    const ctx = cv.getContext('2d');
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

    let DPR = 1, vw = 0, vh = 0, t = 0;
    function geom() {
      DPR = Math.min(window.devicePixelRatio || 1, 2);
      vw = innerWidth; vh = innerHeight;
      cv.width = vw * DPR; cv.height = vh * DPR;
      cv.style.width = vw + 'px'; cv.style.height = vh + 'px';
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    }

    const dim = (a) => `hsl(${opt.hue} 85% 58% / ${a})`;

    function render() {
      ctx.clearRect(0, 0, vw, vh);
      const cx = vw * 0.5, hy = vh * 0.5;
      const N = 16, frac = (t * 0.12 * opt.speed) % 1;
      ctx.lineWidth = 1;

      for (const dir of [1, -1]) {                 // 1 = floor, -1 = ceiling
        // receding horizontal lines — transparent at the horizon, brighter toward the edges
        for (let i = 0; i < N; i++) {
          const tt = (i + frac) / N;
          const yy = hy + dir * (vh * 0.5) * (tt * tt);
          const a = Math.pow(tt, 1.15) * 0.55 * opt.intensity;
          ctx.strokeStyle = dim(a);
          ctx.beginPath(); ctx.moveTo(0, yy); ctx.lineTo(vw, yy); ctx.stroke();
        }
        // converging verticals — faint at the center, brighter at the edge
        const span = vw * 0.9, edgeY = hy + dir * vh * 0.5;
        for (let i = -7; i <= 7; i++) {
          const xb = cx + (i / 7) * span;
          const g = ctx.createLinearGradient(cx, hy, xb, edgeY);
          g.addColorStop(0, dim(0.02));
          g.addColorStop(1, dim(0.32 * opt.intensity));
          ctx.strokeStyle = g;
          ctx.beginPath(); ctx.moveTo(cx, hy); ctx.lineTo(xb, edgeY); ctx.stroke();
        }
      }
    }

    let last = performance.now(), raf = 0;
    function frame(now) {
      let dt = (now - last) / 1000; last = now; if (dt > 0.05) dt = 0.05;
      t += dt; render();
      raf = requestAnimationFrame(frame);
    }

    cv.style.setProperty('--grid-top-fade', opt.topFade);
    geom();
    addEventListener('resize', () => { clearTimeout(geom._t); geom._t = setTimeout(geom, 150); });

    if (reduce) { render(); }            // static single frame, no animation
    else raf = requestAnimationFrame(frame);

    // stop/clear if the effect is toggled off at runtime
    new MutationObserver(() => {
      if (!READY()) { cancelAnimationFrame(raf); ctx.clearRect(0, 0, vw, vh); cv.remove(); }
    }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
