# Panel del Laboratorio Forense

Front web local para el banco forense de MySQL (aislado y *air-gapped*). Ejecuta
los scripts existentes `bin/forensics/*.sh` igual que lo harías a mano — sin
`docker.sock`, sin contenedor, sin privilegios extra. Solo Python de la stdlib,
sin dependencias.

> Regla de diseño (no romper): el panel opera **únicamente a nivel de
> MySQL/sistema de ficheros y NUNCA llama a `php artisan`.** La capa Laravel
> (cache/optimize/config/queue/meilisearch/sync) es propiedad exclusiva del panel
> de staff del tracker en `/dashboard/commands`. Así se evita la carrera que tumba
> el sitio con `config:cache`.

## Qué hace
- **Estado** — ¿lab levantado? ¿sano? ¿hay un job corriendo? + CPU/mem en vivo.
- **Wake / Sleep** — `lab-up.sh` / `lab-down.sh`, con la salida en streaming.
- **Restore (PITR)** — restaura un dump y reproduce binlogs hasta un punto en el
  tiempo, dentro del lab aislado. Nunca toca producción.
- **Diff / Export** — compara lab vs prod por `id` y exporta como `INSERT IGNORE`
  las filas que le faltan a prod (para revisión manual, jamás auto-aplicado).
- **Torrent validate** — cruza fila de BD ↔ fichero `.torrent` ↔ `info_hash`.
- **Reset** — borra el datadir del lab (solo el lab).
- **Apply / DR / Maintenance** — caminos que **escriben en producción**
  (password-gated): aplicar export, DR completo (DROP+recreate+replay) y
  mantenimiento (`check`/`analyze`/`optimize`). Ver más abajo.
- **Timeline de daños, topología de datos, salud de almacenamiento, backups.**

## Cómo se ejecuta
```bash
cp forensics/dashboard/dashboard.env.example forensics/dashboard/dashboard.env   # la primera vez
bash deploy/install.sh        # instala + arranca la unit systemd --user
```
Luego abre http://127.0.0.1:8780 en la máquina.

Ejecución manual (sin systemd):
```bash
cd ~/UNIT3D_Docker
set -a; . forensics/dashboard/dashboard.env; set +a
python3 forensics/dashboard/server.py
```

> La **interfaz está en inglés** a propósito (los comandos y la terminología
> MySQL/Docker son en inglés); la documentación del repo es en español.

## Autenticación y exposición segura

Este panel puede **DROP y restaurar la base de datos de producción** — es lo más
privilegiado de todo el stack. Por eso **no implementa su propio login**: deja la
autenticación a una capa que ya hable OAuth/MFA, y se limita a verificar que esa
capa avaló al usuario. Hay dos modos, vía `AUTH_MODE` en `dashboard.env`:

### `AUTH_MODE=none` (por defecto — solo local)
Sin login. Solo se permite cuando `BIND_ADDR` es local (`127.0.0.1`/`::1`). Si
intentas levantar el panel en una dirección no-local con `AUTH_MODE=none`, el
servidor **se niega a arrancar** (fail-closed): es imposible exponer por error un
panel sin auth que puede borrar prod.

### `AUTH_MODE=cf-access` (acceso remoto en la nube)
Pensado para operadores que corren su tracker en la nube. El login real (Google,
GitHub, OIDC, passkey, MFA…) lo hace **Cloudflare Access en el edge**, antes de
que la petición llegue al panel. Nosotros solo **verificamos el JWT** que
Cloudflare adjunta a cada petición:

- firma RS256 validada contra las claves públicas de tu equipo (JWKS), en Python
  puro (sin PyJWT ni `cryptography`);
- `aud` = el tag de la aplicación de Access;
- `iss` = `https://<tu-equipo>.cloudflareaccess.com`;
- `exp`/`nbf` dentro de rango;
- y el email del token tiene que estar en `ALLOWED_EMAILS`.

Un header falsificado sin un JWT firmado por Cloudflare no vale nada. Variables
requeridas en `dashboard.env`:

```ini
AUTH_MODE=cf-access
CF_ACCESS_TEAM=tu-equipo            # tu-equipo.cloudflareaccess.com
CF_ACCESS_AUD=<AUD tag de la app>   # del panel de Cloudflare Access
ALLOWED_EMAILS=duenio@example.com   # coma-separado; para "solo el dueño", un email
```

Montaje recomendado: **Cloudflare Tunnel** (sin puerto abierto, sin IP pública) →
**Cloudflare Access** (política = ese único email + MFA) → el panel escuchando en
**localhost**, de forma que el túnel es la *única* vía de entrada. Hacer ambas
cosas (origen solo-local **y** verificación de JWT) cierra el agujero de confiar
en un header en texto plano.

**Defensa en profundidad:** la auth te deja *entrar*; la **contraseña de la BD de
prod**, que se teclea por cada acción destructiva, es el segundo factor para el
DROP. Se sigue exigiendo aunque estés autenticado.

**Sin Cloudflare:** el mismo patrón funciona sobre **Tailscale** (cifrado
extremo-a-extremo, sin un tercero en medio). `tailscale serve` puede aportar la
identidad; alternativamente bind a la IP del tailnet y mantenlo solo en la red
privada. Nunca `0.0.0.0`.

### Salvaguardas que aplican siempre
- **Bind fail-closed:** se niega a escuchar fuera de local sin un `AUTH_MODE`
  configurado.
- **Chequeo de Origin (CSRF)** en todos los POST cuando el panel está expuesto.
- **Contraseña de prod por acción** en los caminos destructivos (no se loguea
  nunca, no se persiste, no va en la línea de comandos).

## Estructura
- `server.py` — `ThreadingHTTPServer` de la stdlib; rutas, jobs por subprocess,
  lockfile, estado, y el shim de auth (verificación de JWT de CF Access).
- `static/` — UI de una sola página (`index.html` + `app.js` + `style.css`), sin
  paso de build.
- `run/` — gitignored: `<job_id>.log` (en streaming), `lab.lock`, `jobs.json`.
- `dashboard.env` — config local gitignored.

## Concurrencia
Un solo job mutante a la vez: un segundo wake/sleep mientras hay uno en vuelo
devuelve `409` con el id del job en curso. Un `run/lab.lock` rancio de una
ejecución caída se limpia al arrancar si su pid está muerto.
