# Preámbulo: Marco de Infraestructura y Migración a RHEL (VM Proxmox)

Este documento detalla la arquitectura necesaria para replicar la estabilidad de una **Instancia de Referencia (Instancia A)** en un entorno de Máquina Virtual (**Instancia de Destino - Instancia B**). Para que UNIT3D funcione correctamente, no basta con el código; se requiere una infraestructura de soporte robusta en todas sus capas.

---

## 1. Arquitectura de Capas (El Stack Completo)

Para evitar los errores de "Redis connection refused", "Not Found" en Announces o "No meta found", la infraestructura debe estar alineada desde el hardware hasta la aplicación.

### Capa 1: Host y Virtualización (Proxmox / ESXi / KVM)
*   **Recursos:** UNIT3D es intensivo en E/S de base de datos y memoria (Redis). La VM debe tener CPUs con soporte de virtualización habilitado y, preferiblemente, almacenamiento SSD/NVMe.
*   **Red (Bridge Mode):** La VM debe operar en un bridge de red que le permita tener su propia IP en la LAN. Evitar NAT doble dentro del hipervisor para que la gestión de IPs reales sea más sencilla.

### Capa 2: Sistema Operativo (RHEL / AlmaLinux / Rocky)
*   **Límites de Archivos:** El tracker maneja miles de conexiones simultáneas. Es vital aumentar los descriptores de archivos (`ulimit -n 65535`).
*   **SELinux/Firewall:** Si SELinux está en modo `Enforcing`, debe configurarse para permitir que PHP-FPM se conecte a puertos de red (Redis, MySQL, Meilisearch).
    *   `setsebool -P httpd_can_network_connect 1`

### Capa 3: Servicios de Soporte (Redis & Meilisearch)
En entornos de contenedores, estos servicios están aislados. En una VM dedicada:
*   **Redis:** Debe estar configurado para aceptar conexiones locales y tener persistencia habilitada.
*   **Meilisearch:** Es el motor de búsqueda. Si este servicio falla, el "Dupe Check" lanzará un Error 500.

### Capa 4: El Motor de Laravel (PHP-FPM)
*   **Workers y Scheduler:** En una VM, no hay contenedores dedicados para el Scheduler y el Worker. **Deben crearse servicios de systemd** para asegurar que `php artisan schedule:work` y `php artisan queue:work` estén siempre corriendo y se reinicien tras un fallo.

---

## 2. Guía de Troubleshooting (Verificación en todas las capas)

Si algo falla, utiliza esta cascada de comandos para encontrar el culpable:

### Nivel 1: Conectividad Externa (Red)
Desde fuera de la VM:
*   `curl -I http://tu-dominio.com/announce/test` (Debe devolver algo distinto a 404 o Error de Conexión).
*   `ping <IP_VM>` (Verifica que la VM responde).

### Nivel 2: Servicios de Sistema
Dentro de la VM:
*   `systemctl status redis` (Verifica si Redis está vivo).
*   `systemctl status meilisearch` (Verifica el motor de búsqueda).
*   `netstat -tulpn | grep -E '6379|7700|3306'` (Asegúrate de que los puertos están escuchando).

### Nivel 3: Estabilidad de Redis (Punto crítico de fallos de red)
Si sospechas que Redis se cae:
*   `redis-cli monitor` (Muestra en tiempo real qué está pidiendo la aplicación).
*   `redis-cli info memory` (Verifica si te estás quedando sin RAM en Redis).

### Nivel 4: Laravel y Workers
*   `php artisan queue:summary` (Muestra si hay trabajos atascados en la cola).
*   `tail -f storage/logs/laravel.log` (El primer sitio donde mirar cuando hay un Error 500).
*   `php artisan about` (Verifica que Laravel detecta Redis y la DB correctamente).

---

## 3. Condiciones para la Estabilidad

Para evitar problemas de pérdida de datos o desconexión, la VM debe cumplir:
1.  **Persistencia de Redis:** Configurar `appendonly yes` en `redis.conf`.
2.  **Gestión de Procesos:** No ejecutar los workers manualmente. Usar **Supervisor** o **systemd**.
3.  **Trust Proxies:** El `.env` DEBE tener `TRUST_PROXIES=*` si hay un proxy inverso delante.
4.  **Permisos de Carpeta:** `storage` y `bootstrap/cache` deben ser escribibles por el usuario que corre PHP.

---

*Este preámbulo sirve como hoja de ruta para la migración y estabilización de la instancia de destino.*
