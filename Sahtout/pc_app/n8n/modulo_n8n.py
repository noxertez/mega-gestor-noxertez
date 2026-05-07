"""
modulo_n8n.py - Integración de Noxertez con n8n
Añade este archivo a tu proyecto y llama a sus funciones desde tus módulos existentes.

REQUISITOS: pip install requests mysql-connector-python
"""

import mysql.connector
import os
import json
import requests
import subprocess
import time
from datetime import datetime

# ========================================
# CONFIGURACIÓN MYSQL
# ========================================

DB_CONFIG = {
    'host': 'localhost',
    'database': 'noxertez',
    'user': 'noxertez_user',
    'password': 'Noxertez2024!',  # <<< CAMBIA ESTO
    'charset': 'utf8mb4'
}

def get_db_connection(*args, **kwargs):
    """Sustituye sqlite3.connect(). Devuelve conexion MySQL."""
    conn = mysql.connector.connect(**DB_CONFIG)
    return conn


# URL base de n8n (cámbiala si n8n está en otro puerto o servidor)
N8N_BASE = "http://localhost:5678/webhook"


# =============================================================================
# PEDIDO MANUAL (Flujo 01)
# =============================================================================

def enviar_pedido_a_n8n(cliente: str, telefono: str, items: list,
                         canal: str = "manual", notas: str = "") -> dict:
    """
    Envía un pedido nuevo a n8n para que lo guarde en SQLite.

    items = lista de dicts: [{"referencia": "M6", "nombre": "Tornillo M6", "cantidad": 10, "precio": 0.05}]

    Devuelve: {"ok": True, "numero_pedido": "PED-xxx", "total": 0.50}
    """
    payload = {
        "cliente": cliente,
        "telefono": telefono,
        "items": items,
        "canal": canal,
        "notas": notas
    }
    try:
        r = requests.post(f"{N8N_BASE}/nuevo-pedido", json=payload, timeout=5)
        return r.json()
    except Exception as e:
        return {"ok": False, "error": str(e)}


# =============================================================================
# PARSEAR MENSAJE DE WHATSAPP (Flujo 02)
# =============================================================================

def parsear_mensaje_whatsapp(texto: str, telefono: str = "", nombre: str = "") -> dict:
    """
    Manda un texto copiado de WhatsApp a n8n para que detecte
    la intención y extraiga items si es un pedido.

    Devuelve: {"ok": True, "intencion": "pedido", "items_detectados": [...], "numero_pedido": "PED-xxx"}
    """
    payload = {
        "texto": texto,
        "telefono": telefono,
        "nombre": nombre
    }
    try:
        r = requests.post(f"{N8N_BASE}/parsear-mensaje", json=payload, timeout=5)
        return r.json()
    except Exception as e:
        return {"ok": False, "error": str(e)}


# =============================================================================
# ASISTENTE DE VOZ (Flujo 05)
# =============================================================================

def consultar_asistente(texto_voz: str) -> str:
    """
    Manda el texto transcrito por el micrófono a n8n.
    Devuelve el texto de respuesta para que la app lo lea en voz alta.

    Ejemplos de texto_voz:
      "faltan tornillos M6"
      "qué hay que hacer hoy"
      "cuántos tornillos M8 hay"
      "pedidos pendientes"
      "resumen del día"
    """
    payload = {
        "texto": texto_voz, 
        "query": texto_voz, 
        "message": texto_voz, 
        "body": {"texto": texto_voz}
    }
    try:
        r = requests.post(f"{N8N_BASE}/asistente", json=payload, timeout=8)
        data = r.json()
        return data.get("respuesta", "No pude obtener respuesta del asistente.")
    except Exception as e:
        return f"Error al conectar con el asistente: {e}"


# =============================================================================
# NOTIFICACIONES (leer alertas generadas por n8n en SQLite)
# =============================================================================

def obtener_notificaciones_nuevas(db_path: str) -> list:
    """
    Lee las notificaciones no leídas que n8n ha guardado en la tabla 'notificaciones'.
    Llama a esta función al arrancar la app o periódicamente.
    """
    try:
        conn = get_db_connection()
        rows = conn.execute(
            "SELECT id, tipo, mensaje, fecha FROM notificaciones WHERE leida = 0 ORDER BY fecha DESC"
        ).fetchall()
        conn.close()
        return rows
    except Exception:
        return []


def marcar_notificacion_leida(db_path: str, notif_id: int):
    """Marca una notificación como leída."""
    try:
        conn = get_db_connection()
        conn.execute("UPDATE notificaciones SET leida = 1 WHERE id = ?", (notif_id,))
        conn.commit()
        conn.close()
    except Exception:
        pass


def obtener_seguimientos_pendientes(db_path: str) -> list:
    """
    Lee los mensajes de seguimiento post-venta que n8n preparó
    y están esperando a que los envíes tú manualmente por WhatsApp.
    """
    try:
        conn = get_db_connection()
        rows = conn.execute(
            "SELECT id, numero_pedido, telefono, nombre_cliente, texto_mensaje FROM seguimientos_pendientes WHERE enviado = 0"
        ).fetchall()
        conn.close()
        return rows
    except Exception:
        return []


def marcar_seguimiento_enviado(db_path: str, seguimiento_id: int):
    """Marca un seguimiento como enviado después de que tú lo mandas por WhatsApp."""
    try:
        conn = get_db_connection()
        conn.execute("UPDATE seguimientos_pendientes SET enviado = 1 WHERE id = ?", (seguimiento_id,))
        conn.commit()
        conn.close()
    except Exception:
        pass


# =============================================================================
# TABLAS SQLITE NECESARIAS (ejecuta esto una sola vez al iniciar la app)
# =============================================================================

SQL_CREAR_TABLAS = """
CREATE TABLE IF NOT EXISTS pedidos (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    numero_pedido TEXT UNIQUE,
    telefono TEXT DEFAULT '',
    nombre_cliente TEXT,
    items_json TEXT,
    total REAL DEFAULT 0,
    estado TEXT DEFAULT 'pendiente',
    fecha_pedido TEXT,
    canal TEXT DEFAULT 'manual',
    notas TEXT DEFAULT '',
    fecha_entrega TEXT,
    seguimiento_enviado INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS notificaciones (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    tipo TEXT,
    mensaje TEXT,
    fecha TEXT,
    leida INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS seguimientos_pendientes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    numero_pedido TEXT,
    telefono TEXT,
    nombre_cliente TEXT,
    texto_mensaje TEXT,
    fecha_creacion TEXT,
    enviado INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tareas (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    descripcion TEXT NOT NULL,
    prioridad TEXT DEFAULT 'media',
    fecha_limite TEXT,
    completada INTEGER DEFAULT 0,
    fecha_creacion TEXT DEFAULT (NOW())
);
"""


def inicializar_tablas(db_path: str):
    """Crea las tablas necesarias si no existen. Llama al arrancar la app."""
    try:
        conn = get_db_connection()
        conn.executescript(SQL_CREAR_TABLAS)
        # Columnas extra en articulos (si ya tienes la tabla)
        extras = [
            "ALTER TABLE articulos ADD COLUMN precio_modificado INTEGER DEFAULT 0",
            "ALTER TABLE articulos ADD COLUMN stock_minimo INTEGER DEFAULT 5",
            "ALTER TABLE articulos ADD COLUMN alerta_stock_enviada INTEGER DEFAULT 0",
        ]
        for sql in extras:
            try:
                conn.execute(sql)
            except Exception:
                pass  # Ya existe la columna, ignorar
        conn.commit()
        conn.close()
        print("[n8n] Tablas inicializadas correctamente")
    except Exception as e:
        print(f"[n8n] Error inicializando tablas: {e}")


def arrancar_n8n_local():
    """
    Arranca la instancia local de n8n v1.123.23.
    """
    import subprocess
    import os
    
    base_dir = os.path.dirname(os.path.dirname(__file__))
    n8n_bin = os.path.join(base_dir, "n8n_local", "node_modules", ".bin", "n8n.cmd")
    n8n_local_dir = os.path.join(base_dir, "n8n_local")
    
    if not os.path.exists(n8n_bin):
        print(f"[n8n] ERROR: No se encuentra el binario local en {n8n_bin}")
        return None

    # Archivos de log para depuración
    log_out = open(os.path.join(n8n_local_dir, "n8n_stdout.log"), "a")
    log_err = open(os.path.join(n8n_local_dir, "n8n_stderr.log"), "a")

    print(f"[n8n] Arrancando instancia local de n8n...")
    
    env = os.environ.copy()
    
    # n8n v1+ suele usar ~/.n8n por defecto. 
    # Forzamos que use la carpeta del usuario
    user_home = os.path.expanduser("~")
    env["N8N_USER_FOLDER"] = user_home
    env["N8N_SKIP_V1_NODE_VERSION_CHECK"] = "true"

    # --force-node es necesario para evitar bloqueos por versión en n8n
    cmd = [n8n_bin, "start", "--force-node"]
    
    try:
        # Arrancamos en segundo plano redirigiendo a logs
        process = subprocess.Popen(
            cmd,
            cwd=n8n_local_dir,
            stdout=log_out,
            stderr=log_err,
            env=env,
            creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
        )
        return process
    except Exception as e:
        print(f"[n8n] Error al arrancar: {e}")
        return None

def detener_n8n_local():
    """
    Detiene cualquier instancia local de n8n.cmd que esté corriendo en el sistema.
    """
    import subprocess
    import os
    
    print("[n8n] Intentando detener n8n...")
    try:
        if os.name == 'nt':
            # En Windows buscamos el proceso n8n.cmd o node.exe que use n8n
            # Usamos taskkill para ser efectivos
            subprocess.run(["taskkill", "/F", "/IM", "node.exe", "/T"], capture_output=True)
            print("[n8n] Procesos de Node.js finalizados.")
        else:
            subprocess.run(["pkill", "-f", "n8n"], capture_output=True)
            print("[n8n] Procesos de n8n finalizados.")
        return True
    except Exception as e:
        print(f"[n8n] Error al detener: {e}")
        return False

def reiniciar_n8n_local():
    """
    Detiene e inicia n8n de nuevo.
    """
    detener_n8n_local()
    import time
    time.sleep(2) # Esperar a que se liberen los puertos
    return arrancar_n8n_local()

