"""Configuración del panel, leída del entorno que inyecta el compose."""
import os


def _int(name, default):
    try:
        return int(os.environ.get(name, "") or default)
    except ValueError:
        return default


def _bool(name, default=False):
    v = os.environ.get(name, "").strip().lower()
    if not v:
        return default
    return v in ("1", "true", "yes", "on", "si", "sí")


class Config:
    bind_addr = os.environ.get("BIND_ADDR", "127.0.0.1")
    port = _int("PORT", 8781)

    db_host = os.environ.get("AUDITOR_DB_HOST", "db")
    db_port = _int("AUDITOR_DB_PORT", 3306)
    db_name = os.environ.get("AUDITOR_DB_NAME", "unit3d")
    db_user = os.environ.get("AUDITOR_DB_USER", "auditro")
    db_pass = os.environ.get("AUDITOR_DB_PASS", "")

    loki_url = os.environ.get("AUDITOR_LOKI_URL", "http://loki:3100").rstrip("/")
    prom_url = os.environ.get("AUDITOR_PROM_URL", "http://prometheus:9090").rstrip("/")

    # Retención real de cada fuente. Se usa para AVISAR cuando se pide más
    # atrás, nunca para recortar en silencio.
    retention_days = {
        "loki": _int("AUDITOR_LOKI_RETENTION_DAYS", 7),
        "prom": _int("AUDITOR_PROM_RETENTION_DAYS", 15),
        "binlog": _int("AUDITOR_BINLOG_RETENTION_DAYS", 30),
    }

    max_rows = _int("AUDITOR_MAX_ROWS", 5000)
    max_exec_ms = _int("AUDITOR_MAX_EXEC_MS", 15000)
    max_link_keys = _int("AUDITOR_MAX_LINK_KEYS", 10000)
    # El binlog no lo cubre max_execution_time: eso es de MySQL. Aquí el techo
    # es de reloj, porque leer 1,7 GB por día de ventana no tiene otro freno.
    binlog_max_seconds = _int("AUDITOR_BINLOG_MAX_SECONDS", 45)

    # Recolección automática de IPs. Sin esto la ventana sigue siendo los 7 días
    # de Loki: lo que no se recoja a tiempo se pierde para siempre. Se recoge
    # una ventana MÁS LARGA que el intervalo a propósito, para que un reinicio o
    # un fallo puntual no deje un hueco.
    ips_auto_horas = _int("AUDITOR_IPS_AUTO_HORAS", 3)
    ips_auto_ventana = _int("AUDITOR_IPS_AUTO_VENTANA", 8)
    archive_row_days = _int("AUDITOR_ARCHIVE_ROW_DAYS", 365)

    auth_mode = os.environ.get("AUTH_MODE", "none").strip().lower()
    allowed_emails = [
        e.strip().lower()
        for e in os.environ.get("ALLOWED_EMAILS", "").split(",")
        if e.strip()
    ]
    cf_team = os.environ.get("CF_ACCESS_TEAM", "").strip()
    cf_aud = os.environ.get("CF_ACCESS_AUD", "").strip()

    run_dir = os.environ.get("AUDITOR_RUN_DIR", "/app/run")
    binlog_dir = os.environ.get("AUDITOR_BINLOG_DIR", "/prod/mysql")

    # Columnas cuyo VALOR no sale nunca.
    #
    # Por defecto SÓLO el hash de la contraseña. Todo lo demás —secreto y
    # códigos de 2FA, tokens, correos, IPs, alias de Telegram— ya es visible
    # para un admin en la web del tracker, así que taparlo aquí no protegería
    # nada y sí impediría corroborar un informe: si no puedes leer una IP, no
    # puedes comprobar si la coincidencia que te enseña el panel es real.
    #
    # El mecanismo se queda porque esto es FOSS público y otro operador puede
    # querer otra política: añadir nombres de columna a esta lista basta.
    secretos = {
        "password",
    }


cfg = Config()
