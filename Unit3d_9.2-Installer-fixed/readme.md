🛡️ GUÍA DE RECUPERACIÓN COMUNITARIA DE UNIT3D
Para: Miembros de la comunidad de UNIT3D cuyo repositorio está roto
De: RawSmokeTerribilus & la Escena
Fecha: Mayo 2026

📋 ANTECEDENTES: Por qué sucedió esto
La UNIT3D Community Edition original (v9.2.0) de HDInnovations se entregó con brechas significativas:

Sin instalador automatizado — el script de instalación fue eliminado, dejando a los operadores que descubran las dependencias manualmente
Meilisearch no configurado — motor de búsqueda incluido pero no indexado ni sincronizado
Sin soporte para Docker — sin containerización proporcionada (configuraciones de host frágiles)
Validación de correo electrónico frágil — si la CDN externa cae, los registros se rompen
Bloqueo de fuerza bruta demasiado agresivo — los usuarios se bloquean durante 24+ horas en errores de escritura
Sin estrategia de copia de seguridad — recuperación de desastres manual y frágil

Un operador de rastreador mantuvo un fork comunitario para corregir estos problemas. Cuando se retiró por razones personales/de salud, publicó su instalador en Rentry para que la comunidad no perdiera el trabajo.
Esta guía resucita ese trabajo para tu comunidad.

🚀 LO QUE ESTÁS OBTENIENDO
El Script Instalador (unit3d-installer.sh)
Este es un instalador bash automatizado que:
✅ Detecta tu SO (solo Ubuntu 22.04 o 24.04 LTS)
✅ Instala todas las dependencias (PHP 8.4, MySQL, Redis, Nginx, Node.js, Bun, Composer, Certbot)
✅ Configura e inicia todos los servicios
✅ Inicializa la base de datos y Meilisearch
✅ Crea activos frontend
✅ Configura SSL con Let's Encrypt
✅ Guarda tus credenciales de forma segura
Tiempo de ejecución: ~20-25 minutos en un servidor Ubuntu limpio.

⚠️ NOTAS CRÍTICAS PARA TU IMPLEMENTACIÓN
Soporte de Plataforma

✅ Ubuntu 22.04 LTS — Soportado
✅ Ubuntu 24.04 LTS — Soportado
❌ RHEL / CentOS / AlmaLinux — NO soportado — necesitarás adaptar comandos del gestor de paquetes (dnf en lugar de apt)
❌ Debian (no Ubuntu) — No probado; resultados variables

Requisitos de Hardware

Mínimo: 2 núcleos de CPU, 4GB de RAM, 50GB de disco (para crecimiento de base de datos)
Recomendado: 4+ núcleos de CPU, 8GB+ de RAM, 100GB+ de disco (para base de datos de torrents)
Red: IP estática altamente recomendada; IPs dinámicas requieren actualizador DNS

Lo que este Script NO hace

❌ Gestionar renovación de certificados SSL (Certbot auto-renovación está configurado, pero debes verificar /etc/letsencrypt/renewal/ cron)
❌ Configurar un firewall (deberías añadir reglas UFW/iptables)
❌ Configurar copias de seguridad automatizadas (usa backup.sh del fork N.O.B.S por separado)
❌ Instalar Docker (este es el instalador bare-metal; usa el fork N.O.B.S si quieres contenedores)
❌ Configurar bots de Telegram o integraciones avanzadas (esos están en la versión Docker)


📋 LISTA DE VERIFICACIÓN PREVIA A LA INSTALACIÓN
Antes de ejecutar el instalador, ten estos listos:
Dominio y SSL:

 Nombre de dominio válido (ej: rastreador.ejemplo.com)
 Capacidad de port-forward 80/443 a tu servidor (para validación de Let's Encrypt)
 Correo electrónico para registro de certificado SSL (Let's Encrypt te contactará)

Configuración SMTP:

 Host SMTP (ej: smtp.mailgun.org, mail.ejemplo.com)
 Puerto SMTP (generalmente 587 para TLS o 25 para plano)
 Nombre de usuario y contraseña SMTP
 Dirección de correo "De" para notificaciones del rastreador

Opcional:

 Clave API de TMDB (para metadatos de películas/TV) — Regístrate aquí

Acceso al Servidor:

 Acceso SSH como root o usuario con privilegios sudo
 Conocimiento de Linux (bash básico, vi/nano, systemctl)


🛠️ PASOS DE INSTALACIÓN
1. Descarga el Script
bash# Crea un directorio de trabajo
mkdir -p /opt/unit3d-install
cd /opt/unit3d-install

# Guarda el instalador (copia del bloque de código anterior)
nano unit3d-installer.sh
# Pega el script, guarda (Ctrl+X → Y → Enter)

# Hazlo ejecutable
chmod +x unit3d-installer.sh
2. Ejecuta el Instalador
bashsudo ./unit3d-installer.sh
El script hará:

✅ Verificar que eres root
✅ Detectar la versión de Ubuntu
✅ Pedir configuración (dominio, SMTP, etc.)
✅ Instalar todo
✅ Mostrar un resumen de finalización

3. Guarda Tus Credenciales
Después de que finalice la instalación, encuentra tu archivo de credenciales:
bashcat /root/unit3d-credentials.txt
IMPORTANTE: Guarda este archivo de forma segura. Contiene:

Credenciales de inicio de sesión del propietario
Contraseñas de MySQL
Clave API de Meilisearch
Comandos de administración útiles

Luego elimina el original:
bashrm /root/unit3d-credentials.txt
4. Verifica la Instalación
bash# Comprueba servicios
systemctl status nginx
systemctl status php8.4-fpm
systemctl status mysql
systemctl status redis-server
systemctl status supervisor

# Comprueba que se carga el rastreador
curl -I https://TU_DOMINIO

# Comprueba Meilisearch
curl http://localhost:7700/health
5. Inicia Sesión
Abre https://TU_DOMINIO en tu navegador.
Inicio de sesión del propietario predeterminado:

Nombre de usuario: UNIT3D (o el que configuraste)
Contraseña: (del archivo de credenciales)


🔧 DESPUÉS DE LA INSTALACIÓN: PRÓXIMOS PASOS
A. Configura el Sistema de Invitaciones
Por defecto, las invitaciones están deshabilitadas. Para habilitarlas:
bash# SSH a tu servidor
sudo -u www-data php artisan tinker

>>> Setting::query()->update(['invite_only' => false])
>>> exit
O usa el panel de administración: Admin → Settings → Site → Invite Only
B. Añade Contenido (Torrents)

Carga torrents de prueba a través del panel de administración
Meilisearch los indexará automáticamente
Configura feeds de anuncios si tienes bots de IRC/Discord

C. Configura Copias de Seguridad
El instalador no configura copias de seguridad automatizadas. Para añadirlas:
bash# Copia de seguridad diaria a las 2 AM
(crontab -l 2>/dev/null; echo "0 2 * * * /var/www/html/backup.sh") | crontab -
(Necesitarás crear backup.sh o usar el script de copia de seguridad de la versión Docker)
D. Configura Notificaciones de Correo Electrónico
Prueba la configuración SMTP:
bashcd /var/www/html

# Envía un correo de prueba
php artisan tinker
>>> Mail::raw('Correo de prueba', fn($m) => $m->to('admin@ejemplo.com')->send())
>>> exit
Si funciona, las notificaciones están habilitadas. Los usuarios recibirán correos en:

Confirmación de registro
Aprobación de torrent
Nuevos pares en torrents que están descargando

E. (Opcional) Configura Integración de Bot de Telegram
Esto requiere configuración adicional (no está en el instalador bare-metal). Consulta la documentación del fork N.O.B.S si quieres notificaciones de Telegram en tiempo real.

🚨 PROBLEMAS COMUNES Y SOLUCIONES
Problema: "Meilisearch devuelve 500"
bash# Reconstruye el índice de búsqueda
php artisan scout:import

# Reinicia Meilisearch
systemctl restart meilisearch
Problema: "Puerto MySQL 3306 en uso / no se puede iniciar base de datos"
bash# Comprueba qué lo está usando
sudo lsof -i :3306

# Si hay otra instancia MySQL, detenla
sudo systemctl stop mysql
sudo systemctl disable mysql
# Luego ejecuta el instalador en un puerto diferente
Problema: "Let's Encrypt - fallo en renovación de certificado"
bash# Prueba renovación manualmente
sudo certbot renew --dry-run

# Comprueba el cron de renovación
sudo systemctl status snap.certbot.renew.timer
Problema: "Los usuarios obtienen 'Permiso denegado' en cargas"
bash# Corrige los permisos de almacenamiento
sudo chown -R www-data:www-data /var/www/html/storage
sudo chmod -R 775 /var/www/html/storage
Problema: "La búsqueda no devuelve resultados"
bash# Reindexar todos los torrents
cd /var/www/html
sudo -u www-data php artisan scout:import

# Verifica que Meilisearch está ejecutándose
curl http://localhost:7700/health

📚 COMANDOS ÚTILES PARA ADMINISTRADORES
bash# Ver registros
tail -f /var/www/html/storage/logs/laravel.log

# Borrar todos los cachés
cd /var/www/html
sudo -u www-data php artisan optimize:clear

# Reconstruir cachés de Laravel (para rendimiento)
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Reinicia todos los servicios
sudo systemctl restart nginx php8.4-fpm mysql redis-server supervisor

# Comprueba trabajadores en cola
sudo supervisorctl status

# Reinicia trabajadores en cola
sudo supervisorctl restart all

# Accede a Laravel Tinker (shell interactivo)
cd /var/www/html
sudo -u www-data php artisan tinker

🛡️ ENDURECIMIENTO DE SEGURIDAD (Post-Instalación)
1. Firewall
bashsudo ufw allow 22/tcp     # SSH
sudo ufw allow 80/tcp     # HTTP
sudo ufw allow 443/tcp    # HTTPS
sudo ufw default deny incoming
sudo ufw enable
2. Fail2Ban (Protección contra Ataques de Fuerza Bruta)
bashsudo apt install fail2ban
sudo systemctl enable fail2ban
3. Copias de Seguridad Regulares
Configura un cron de copia de seguridad (necesitarás escribir el script):
bash(crontab -l 2>/dev/null; echo "0 2 * * * mysqldump -u root unit3d > /backups/unit3d_$(date +\%Y\%m\%d).sql") | crontab -
4. Desactiva Root SSH
bashsudo nano /etc/ssh/sshd_config
# Cambia: PermitRootLogin no
sudo systemctl restart sshd

💾 RECUPERACIÓN DE DESASTRES
Si algo se rompe catastróficamente:
Ubicación de Copia de Seguridad
El instalador no crea copias de seguridad automatizadas. Tus datos viven en:

Base de datos MySQL: /var/lib/mysql/unit3d/
Archivos del usuario: /var/www/html/storage/app/
Código de aplicación: /var/www/html/

Si Necesitas Restaurar desde Servidor Anterior

Vuelca la base de datos del servidor anterior:

bashmysqldump -u root unit3d > unit3d_backup.sql

En el nuevo servidor, después de la instalación:

bashmysql -u root unit3d < unit3d_backup.sql

Copia archivos de usuario:

bashscp -r servidor-anterior:/var/www/html/storage/app/* /var/www/html/storage/app/
sudo chown -R www-data:www-data /var/www/html/storage

🤝 OBTENER AYUDA
Si encuentras problemas:

Comprueba los registros:

bashtail -f /var/www/html/storage/logs/laravel.log
docker compose logs app  # (si usas la versión Docker)

Consulta el repositorio GitHub: https://github.com/RawSmokeTerribilus/UNIT3D_NO_BS
(Para la versión containerizada con más características)
Consulta la documentación oficial de UNIT3D: https://github.com/HDInnovations/UNIT3D-Community-Edition
Soporte Comunitario:
UNIT3D Original: https://unit3d.io/
Canales de la escena / foros de rastreadores privados


❤️ CRÉDITOS Y AGRADECIMIENTOS

HDInnovations — Creó UNIT3D, la plataforma subyacente brillante
RawSmokeTerribilus — Arregló y estabilizó UNIT3D para la comunidad
La Escena — Décadas de innovación en infraestructura de rastreadores privados

Este instalador se comparte bajo la licencia AGPL v3.0, igual que UNIT3D.

📝 NOTAS PARA TU COMUNIDAD
Ahora estás ejecutando la versión arreglada que:

✅ Se instala automáticamente
✅ Maneja configuración de Meilisearch
✅ No es frágil en validación de correo electrónico
✅ No bloquea usuarios excesivamente
✅ Funciona en servidores Ubuntu frescos

El operador que creó esto se retiró por razones personales/de salud. Este trabajo ahora está en manos de la comunidad. ¡Siéntete libre de mejorarlo, corregir errores y compartir mejoras!

Última actualización: Mayo 2026
Estado: ✅ Listo para Producción
Probado en: Ubuntu 22.04 LTS, Ubuntu 24.04 LTS

¡Esto debería dar a tu comunidad todo lo que necesita para resucitar y ejecutar un rastreador saludable! Copia tanto el script como esta guía a tus canales comunitarios. ¡Buena suerte! 🚀
