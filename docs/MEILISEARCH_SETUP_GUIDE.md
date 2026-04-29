# 🔍 Guía de Configuración de Meilisearch - NO_BS Edition

> **Script Flexible para Todos los Entornos**
> 
> `NO_BS_meilisearch.sh` es un script auto-consciente que detecta su contexto de ejecución (Docker vs Host) y configura automáticamente los índices duales de Torrents + People.

---

## 📋 Tabla de Contenidos

1. [Quick Start](#quick-start)
2. [Instalación en Docker (Recomendado)](#instalación-en-docker-recomendado)
3. [Instalación Manual en Host](#instalación-manual-en-host)
4. [Limpieza y Reseteo](#limpieza-y-reseteo)
5. [Troubleshooting](#troubleshooting)
6. [Arquitectura del Script](#arquitectura-del-script)

---

## 🚀 Quick Start

### Docker (Automático - Recomendado)

```bash
# El script se ejecuta automáticamente durante docker compose up
cd /home/rawserver/UNIT3D_Docker
docker compose up -d

# Verificar que se configuró correctamente
docker compose logs app | grep -i meilisearch
```

**¿Qué ocurre internamente?**
- `.docker/entrypoint.sh` espera a que Meilisearch esté disponible (health check)
- Ejecuta automáticamente `NO_BS_meilisearch.sh`
- Crea/configura índices `torrents` y `people`
- Inicia 6,071,734+ documentos de búsqueda

### Host / Manual

```bash
# 1. Asegúrate de que Meilisearch está corriendo
curl http://127.0.0.1:7700/health
# Esperado: {"status":"available"}

# 2. Ejecuta el script
bash /home/rawserver/UNIT3D_Docker/NO_BS_meilisearch.sh local

# 3. Verifica logs
cat /tmp/meilisearch-config.log
```

---

## 📦 Instalación en Docker (Recomendado)

### Escenario 1: Primer Spinup (Limpio)

```bash
cd /home/rawserver/UNIT3D_Docker

# Opción A: Inicio normal (primera vez)
docker compose up -d

# El entrypoint automáticamente:
# ✓ Crea la BD
# ✓ Ejecuta migraciones
# ✓ Corre NO_BS_meilisearch.sh
# ✓ Configura índices
# ✓ Inicia PHP-FPM
```

**Tiempo esperado:** 2-3 minutos (según velocidad de DB)

**Verificación:**
```bash
# Logs en tiempo real
docker compose logs -f app

# Test de búsqueda (después de 3 min)
curl -s http://localhost/api/quicksearch?query=ubuntu | jq .
```

---

### Escenario 2: Rebuild Limpio (Purge Dockerizado)

**Caso de uso:** Quieres limpiar images/contenedores pero **preservar datos** (torrents, usuarios, comentarios)

```bash
cd /home/rawserver/UNIT3D_Docker

# 1. Detener servicios
docker compose down

# 2. Limpiar Docker (pero NO volúmenes de datos)
docker image prune -af --filter="label!=keep"  # Elimina images no usadas
docker container prune -f                       # Elimina contenedores parados
docker network prune -f                         # Elimina redes no usadas
# Los volúmenes de datos (db, meilisearch, storage) quedan intactos ✓

# 3. Rebuild desde cero
docker compose up -d

# 4. Esperar y validar
sleep 30
docker compose logs app | grep -E "(Meilisearch|✓|successfully)"
```

**Resultado esperado:**
```
unit3d-app  | ✓ Meilisearch is available. Configuring dual indexes...
unit3d-app  | ✓ Meilisearch configuration completed successfully
```

---

### Escenario 3: Reset Total (Borra TODO)

⚠️ **Advertencia:** Borra ALL datos (usuarios, torrents, comentarios, etc.)

```bash
cd /home/rawserver/UNIT3D_Docker

# Opción nuclear
docker compose down -v  # -v = elimina volúmenes también

# Rebuild desde cero
docker compose up -d

# Se creará una BD vacía + Meilisearch limpio
# Necesitarás reimportar datos o ejecutar seeders
```

---

## 🔧 Instalación Manual en Host

### Prerequisitos

```bash
# Requerimientos del sistema
- PHP 8.4+
- Composer (para Laravel)
- Meilisearch corriendo en http://127.0.0.1:7700
- Acceso a UNIT3D en /home/rawserver/UNIT3D_Docker/
```

### Paso 1: Verificar Conectividad a Meilisearch

```bash
# Desde el host (NO dentro de Docker)
curl -v http://127.0.0.1:7700/health

# Esperado:
# HTTP/1.1 200 OK
# {"status":"available"}
```

Si falla, revisa [Troubleshooting](#troubleshooting).

### Paso 2: Obtener la Llave Maestra de Meilisearch

El script necesita la `MEILISEARCH_KEY` para crear/actualizar índices.

```bash
# Opción A: Desde docker-compose.yml
grep MEILISEARCH_KEY /home/rawserver/UNIT3D_Docker/docker-compose.yml
# Esperado: MEILISEARCH_KEY=d0552b4153...

# Opción B: Desde el contenedor de Meilisearch
docker compose exec meilisearch env | grep MEILISEARCH_KEY

# Opción C: Desde tu .env en la raíz del proyecto
grep MEILISEARCH_KEY /home/rawserver/UNIT3D_Docker/.env
```

### Paso 3: Ejecutar el Script Manualmente

```bash
cd /home/rawserver/UNIT3D_Docker

# El script detecta automáticamente que NO está en Docker
# y usa configuración local (localhost:7700)
bash ./NO_BS_meilisearch.sh local

# ¿Qué ocurre internamente?
# ✓ Detecta que ejecutas desde host (/home/rawserver/UNIT3D_Docker)
# ✓ Lee MEILISEARCH_KEY del .env
# ✓ Conecta a http://127.0.0.1:7700
# ✓ Configura índices Torrents + People
# ✓ Re-indexa todos los documentos
```

### Paso 4: Verificar Configuración

```bash
# Ver logs completos
cat /tmp/meilisearch-config.log

# Esperado: Verás PASO 1, 2, 2B, 3, 3B sin errores

# Test de búsqueda
curl -s "http://localhost/api/quicksearch?query=ubuntu" | jq .
```

---

## 🧹 Limpieza y Reseteo

### Escenario A: Reset Solo de Meilisearch (NO datos de app)

```bash
# 1. Accede al contenedor de Meilisearch
docker compose exec meilisearch sh

# 2. Dentro del contenedor:
# Elimina los índices
curl -X DELETE http://127.0.0.1:7700/indexes/torrents \
  -H "Authorization: Bearer $MEILI_MASTER_KEY"

curl -X DELETE http://127.0.0.1:7700/indexes/people \
  -H "Authorization: Bearer $MEILI_MASTER_KEY"

# 3. Sale (`exit`) y re-ejecuta script
docker compose exec app bash ./NO_BS_meilisearch.sh
```

### Escenario B: Limpiar Storage + Meilisearch (Pero NO DB)

```bash
cd /home/rawserver/UNIT3D_Docker

# Elimina solo volúmenes de búsqueda
docker volume rm unit3d_docker_meilisearch-data

# Reinicia solo ese servicio
docker compose up -d meilisearch

# Ejecuta script para re-indexar
sleep 10
docker compose exec app bash ./NO_BS_meilisearch.sh
```

### Escenario C: Reset Completo (Docker + Datos)

```bash
cd /home/rawserver/UNIT3D_Docker

# NUCLEAR - borra TODO
docker compose down -v

# Limpia images
docker image prune -af

# Rebuild
docker compose up -d
```

---

## 🐛 Troubleshooting

### ❌ "Meilisearch is not available"

**Síntoma:** Script termina sin configurar índices

```bash
# Diagnosis
docker compose ps | grep meilisearch

# Si está DOWN, revisa logs
docker compose logs meilisearch | tail -20

# Solutions
# 1. Esperar más tiempo (Meilisearch tarda ~15s en iniciar)
sleep 20
docker compose exec app bash ./NO_BS_meilisearch.sh

# 2. Verificar healthy status
curl http://meilisearch:7700/health

# 3. Revisar volumen
docker inspect unit3d_docker_meilisearch-data
```

---

### ❌ "Authorization failed" / API Key inválida

**Síntoma:**
```
Error authenticating with Meilisearch
Status: 401
```

**Solución:**
```bash
# 1. Verifica que MEILISEARCH_KEY está en docker-compose.yml
grep -A2 "environment:" /home/rawserver/UNIT3D_Docker/docker-compose.yml | grep MEILISEARCH

# 2. Si no está, añádelo:
# - MEILISEARCH_KEY=tu_llave_aquí

# 3. Rebuild
docker compose down && docker compose up -d
```

---

### ❌ "Connection refused" en host manual

**Síntoma:**
```
curl: (7) Failed to connect to 127.0.0.1 port 7700
```

**Soluciones:**
```bash
# Opción 1: Meilisearch no está corriendo
docker compose logs meilisearch

# Opción 2: Puerto forwarding no configurado
docker compose ps | grep meilisearch
# Debe mostrar: 127.0.0.1:57700->7700

# Opción 3: Firewall bloqueando
sudo ufw allow 57700

# Opción 4: Docker daemon no activo
sudo systemctl start docker
```

---

### ⚠️ "grep: unrecognized option: P"

**Síntoma:** Script se ejecuta pero muestra warning en Alpine

```
grep: unrecognized option: P
BusyBox v1.37.0
```

**Explicación:** Alpine Linux usa BusyBox grep (sin soporte PCRE). El script incluye fallback automático.

**Impacto:** Ninguno - el re-indexado completa exitosamente de todas formas.

**Fix (opcional):** Usar POSIX regex en lugar de Perl regex (ya implantado en versiones recientes)

---

## 🏗️ Arquitectura del Script

### Detección de Contexto

```bash
# El script detecta automáticamente dónde corre:

if [ -f "/.dockerenv" ] || [ "$(pwd)" = "/var/www/html" ]; then
    # ===== MODO CONTENEDOR =====
    MEILISEARCH_URL="http://meilisearch:7700"      # hostname interno
    MEILISEARCH_KEY="${MEILISEARCH_KEY:-}"         # env var de docker-compose
    COMPOSE_DIR="."
else
    # ===== MODO HOST =====
    MEILISEARCH_URL="http://127.0.0.1:57700"       # puerto forward del host
    MEILISEARCH_KEY=$(grep MEILISEARCH_KEY .env | cut -d= -f2)  # parsea .env
    COMPOSE_DIR="/home/rawserver/UNIT3D_Docker"
fi
```

### Pasos de Ejecución

| PASO | Acción | Tiempo |
|------|--------|--------|
| **1** | Validar conexión a Meilisearch | <1s |
| **2** | Verificar/crear índice `torrents` | 1s |
| **2B** | Verificar/crear índice `people` | 1s |
| **3** | Configurar atributos filterables/sortables de torrents | 5-10s |
| **3B** | Configurar atributos filterables/sortables de people | 2-5s |
| **4** | Re-indexar todos los Torrents | variable (minutos) |
| **4B** | Re-indexar todos los People | variable (minutos) |

**Tiempo total esperado:** 5-15 minutos (según número de documentos)

---

## 📊 Validación Post-Instalación

```bash
# 1. ¿Están los índices creados?
curl -s http://meilisearch:7700/indexes \
  -H "Authorization: Bearer $MEILISEARCH_KEY" | jq '.results[] | .name'
# Esperado: "torrents", "people"

# 2. ¿Cuántos documentos se indexaron?
curl -s http://meilisearch:7700/indexes/torrents/stats \
  -H "Authorization: Bearer $MEILISEARCH_KEY" | jq '.numberOfDocuments'
# Esperado: 2,325+ (o tu número local)

# 3. ¿Funciona la búsqueda?
curl "http://localhost/api/quicksearch?query=ubuntu"
# Esperado: JSON con resultados de torrents + people

# 4. ¿Aparecen People en resultados?
curl "http://localhost/api/quicksearch?query=jennifer" | jq .data
# Esperado: Mix de películas + actrices indexadas
```

---

## 🔐 Seguridad

- **MEILISEARCH_KEY** nunca debe estar en Git
- Ve a `.env` para la configuración local
- En Docker, se pasa como env var en `docker-compose.yml`
- El script resetea permisos automáticamente post-configuración

---

## 📞 Support

Si algo falla:

1. Revisa `/tmp/meilisearch-config.log`
2. Comprueba los logs del contenedor: `docker compose logs app`
3. Valida conectividad a Meilisearch manualmente
4. Intenta un reset limpio (Escenario B de Limpieza)

¡Ojalá funcione a la primera! 🎉
