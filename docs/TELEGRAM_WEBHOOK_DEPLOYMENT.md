# Telegram Webhook Registration - Production Deployment Checklist

## 🔴 PROBLEMA ACTUAL
- Usuario RawSmoke tiene token válido (`TRK-Hiw0gpaQAGD6vzaFGAedlrRKNYo2xiwT`)
- Pero cuando intenta vincular, **Telegram no envía el webhook** a la app
- Razón: **El webhook de Telegram nunca fue registrado** con Telegram API
- Prueba: `/getWebhookInfo` devuelve `{"url": "", "pending_update_count": 0}`

## ✅ SOLUCIÓN IMPLEMENTADA
- Nuevo comando Artisan: `php artisan telegram:set-webhook`
- Ubicación: `app/Console/Commands/SetTelegramWebhook.php`
- Características:
  - ✓ Valida configuración (.env variables)
  - ✓ Llamadas HTTP con timeout (10-15 segundos)
  - ✓ Error handling completo
  - ✓ Modo `--test` para inspeccionar sin modificar
  - ✓ Modo `--force` para re-registrar
  - ✓ Logging en `storage/logs/`

## 📋 PASO 1: PRE-DEPLOYMENT (Ahora)

### Verificación en Staging
```bash
cd /home/rawserver/UNIT3D_Develop
docker compose exec -T app php artisan telegram:set-webhook --test
```

**Resultado esperado en staging:**
```
🔍 Checking current webhook status...
⚠️  No hay webhook registrado
Command exited with code 1
```

**Explicación:** Staging NO es una URL pública, así que es normal que no haya webhook. ✓ Esto valida que el comando funciona.

---

## 📋 PASO 2: PRODUCTION DEPLOYMENT

### 2A. Pre-requisito: Verificar valor de APP_URL en prod
```bash
# En el servidor de producción:
grep "^APP_URL=" /home/rawserver/UNIT3D_Docker/.env
# Esperado: una URL HTTPS pública (ej: https://tracker.domain.com)
```

### 2B. Ejecutar el comando en prod
```bash
cd /home/rawserver/UNIT3D_Docker

# Primero: inspeccionar estado actual (sin modificar)
docker compose exec -T app php artisan telegram:set-webhook --test

# Si no hay webhook, registrar:
docker compose exec -T app php artisan telegram:set-webhook
```

**Salida esperada:**
```
Registrando webhook: https://[TU_DOMAIN]/api/telegram/webhook
✓ Webhook registrado en Telegram API
✅ Webhook registrado correctamente:
  URL: https://[TU_DOMAIN]/api/telegram/webhook
```

### 2C. Verificación manual (opcional)
```bash
# Confirmar via curl (reemplaza {TOKEN} con el real)
curl "https://api.telegram.org/bot{TOKEN}/getWebhookInfo" | jq '.result.url'
# Esperado: "https://[TU_DOMAIN]/api/telegram/webhook"
```

---

## 📋 PASO 3: VERIFICACIÓN POST-DEPLOYMENT

### Usuario prueba: Regenerar token
1. User va a Notification Settings
2. Haz clic en "Reset Token"
3. Copia el nuevo token `TRK-XXXXXXXX`

### User intenta vinculación
1. Click en botón azul "🚀 VINCULAR CON EL BOT"
2. Se abre Telegram
3. **Bot muestra:** "✅ Handshake Successful, [username]!"
4. **BD actualizada:** `users.telegram_chat_id` recibe el chat ID

### Verificación final
```bash
# En producción, verificar logs
docker compose logs app | grep -i telegram

# En BD (producción), verificar que telegram_chat_id se guarde:
# SELECT username, telegram_chat_id, telegram_token FROM users 
# WHERE telegram_token IS NOT NULL;
```

---

## 🔧 ROLLBACK (Si falla)

Si el webhook se registra pero algo no funciona:

```bash
# 1. Desregistrar webhook (vacío la URL)
docker compose exec -T app php artisan telegram:set-webhook --force
# (Telegram lo rechazará o lo ignorará, es seguro)

# 2. O manualmente via curl:
curl -X POST "https://api.telegram.org/bot{TOKEN}/setWebhook" \
  -d '{"url": ""}'
# Devuelve: {"ok": true, "result": true}
```

---

## 📅 DOCUMENTACIÓN DE CAMBIOS

| Archivo | Cambio | Motivo |
|---------|--------|--------|
| `app/Console/Commands/SetTelegramWebhook.php` | ✨ Nuevo | Registra webhook automáticamente |
| `routes/api.php` | — Sin cambios | Ya tiene endpoint `/api/telegram/webhook` |
| `app/Http/Controllers/API/TelegramWebhookController.php` | — Sin cambios | Ya implementado, espera webhook |
| `.env` | — Sin cambios | Ya tiene TELEGRAM_BOT_TOKEN, etc. |

---

## 🚨 NOTAS CRÍTICAS

1. **Webhook solo funciona con HTTPS pública**
   - Localhost ❌
   - IPs privadas (192.168.x.x) ❌
   - Dominios públicos HTTPS ✅

2. **Token es one-time use**
   - Después de primer `/start`, `telegram_token` se nullifica
   - User debe regenerar token para reintentarlo

3. **Si Telegram rechaza la URL**
   - Error típico: "Webhook URL is not accessible"
   - Verifica: firewall, certificado HTTPS válido, endpoint reachable

4. **Logs importantes**
   - `storage/logs/laravel.log` → errores de php artisan
   - `docker compose logs app` → stderr/stdout del container

---

## ✅ CHECKLIST FINAL

- [ ] Revisado `.env` tiene `TELEGRAM_BOT_TOKEN` válido
- [ ] Revisado `APP_URL` es HTTPS pública
- [ ] Ejecutado comando en staging (`--test` mode)
- [ ] Ejecutado comando en producción (sin flags)
- [ ] Verificado webhook está registrado via `getWebhookInfo`
- [ ] Usuario RawSmoke regeneró token y intentó vincular
- [ ] BD muestra `telegram_chat_id` con valor (NO NULL)
- [ ] Bot envió mensaje "✅ Handshake Successful"
- [ ] Documentación completada

---

## 📞 TROUBLESHOOTING

### ❌ Error: "TELEGRAM_BOT_TOKEN not configured"
- **Solución**: Verificar `.env` tiene la variable
  ```bash
  grep "TELEGRAM_BOT_TOKEN" /path/to/.env
  ```

### ❌ Error: "APP_URL is localhost"
- **Solución**: Cambiar `.env`
  ```env
  APP_URL=https://tu-dominio-publico.com
  ```

### ❌ Error: "Webhook URL is not accessible"
- **Solución**: Verificar:
  - Certificado HTTPS es válido
  - Firewall permite port 443 (HTTPS)
  - URL responde sin error HTTP:
    ```bash
    curl -v https://tu-dominio.com/api/telegram/webhook
    # Debe retornar algo, no error 404/500
    ```

### ❌ No llega webhook aunque esté registrado
- **Solución**: Verificar logs en `/tmp/meilisearch-config.log` o similar
  - ¿El archivo existe?
  - ¿Hay errores?
  - Buscar: "pending_update_count" en API response

---

**Creado**: 27 de Marzo 2026  
**Estado**: Listo para producción  
**Riesgo**: BAJO (comando manual, no automático)
