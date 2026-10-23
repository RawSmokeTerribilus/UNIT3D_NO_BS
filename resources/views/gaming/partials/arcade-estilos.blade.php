{{--
    NOBS — Arcade: estilos compartidos por el hub (/gaming) y las tres secciones
    (aventuras gráficas, consolas, multijugador). Inline como el resto de páginas de
    juegos: ni build de Vite ni SCSS (una vista nueva no puede depender de un build).
--}}
<style>
    .arcade-puertas { display: grid; gap: 18px; margin: 0 0 22px; }
    /* En horizontal: tres puertas en fila; recortadas al centro (holograma + título) y crecen al pasar */
    @media (min-width: 900px) {
        .arcade-puertas { grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 14px 0; }
        .arcade-puertas .arcade-puerta { aspect-ratio: 16 / 10; }
        .arcade-puertas a.arcade-puerta:hover, .arcade-puertas a.arcade-puerta:focus-visible { transform: scale(1.12); z-index: 2; }
        .arcade-puertas a.arcade-puerta:hover img, .arcade-puertas a.arcade-puerta:focus-visible img { transform: none; }
    }
    .arcade-pista { margin: 0 0 10px; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; opacity: .55; }
    .arcade-puerta {
        --brillo: 255, 80, 200;
        position: relative; display: block; overflow: hidden; border-radius: 10px;
        aspect-ratio: 2400 / 895; background: #000;
        box-shadow: 0 0 0 1px rgba(var(--brillo), .25), 0 6px 24px rgba(0, 0, 0, .55);
        transition: transform .3s cubic-bezier(.2, .8, .2, 1), box-shadow .3s ease;
        outline: none;
    }
    .arcade-puerta img { display: block; width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease, filter .25s ease; filter: brightness(.88) saturate(1.05); }
    .arcade-puerta::after {
        content: ''; position: absolute; inset: 0; pointer-events: none; border-radius: inherit;
        box-shadow: inset 0 0 0 2px rgba(var(--brillo), 0); transition: box-shadow .25s ease;
    }
    a.arcade-puerta:hover, a.arcade-puerta:focus-visible {
        transform: translateY(-3px);
        box-shadow: 0 0 0 1px rgba(var(--brillo), .7), 0 0 34px rgba(var(--brillo), .45), 0 10px 30px rgba(0, 0, 0, .6);
    }
    a.arcade-puerta:hover img, a.arcade-puerta:focus-visible img { transform: scale(1.025); filter: brightness(1.05) saturate(1.15); }
    a.arcade-puerta:hover::after, a.arcade-puerta:focus-visible::after { box-shadow: inset 0 0 0 2px rgba(var(--brillo), .85); }
    /* La pista de «puerta»: aparece al pasar (y en táctil se queda visible, tenue) */
    .arcade-puerta__entrar {
        position: absolute; right: 18px; bottom: 16px; padding: 7px 14px; border-radius: 999px;
        font-weight: 700; font-size: 14px; letter-spacing: .06em; text-transform: uppercase; color: #fff;
        background: rgba(0, 0, 0, .55); box-shadow: 0 0 0 1px rgba(var(--brillo), .8), 0 0 18px rgba(var(--brillo), .5);
        opacity: 0; transform: translateX(-6px); transition: opacity .25s ease, transform .25s ease;
    }
    a.arcade-puerta:hover .arcade-puerta__entrar, a.arcade-puerta:focus-visible .arcade-puerta__entrar { opacity: 1; transform: none; }
    @media (hover: none) { a.arcade-puerta .arcade-puerta__entrar { opacity: .85; transform: none; } }
    .arcade-puerta--cerrada img { filter: grayscale(1) brightness(.45); }
    .arcade-puerta--cerrada .arcade-puerta__entrar { opacity: 1; transform: none; box-shadow: 0 0 0 1px rgba(255, 255, 255, .35); }
    .arcade-puerta--multijugador { --brillo: 255, 110, 40; }
    .arcade-puerta--consolas { --brillo: 255, 60, 200; }
    .arcade-puerta--aventuras { --brillo: 150, 255, 110; }
    /* Cabecera de sección: la misma puerta, ya «dentro» (más baja, sin enlace) */
    .arcade-cabecera { aspect-ratio: auto; height: clamp(120px, 22vw, 260px); margin: 0 0 18px; }
    .arcade-cabecera img { object-position: center 40%; }
</style>
