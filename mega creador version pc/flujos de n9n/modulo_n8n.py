"""
modulo_n8n.py - Integración de Noxertez con n8n
Añade este archivo a tu proyecto y llama a sus funciones desde tus módulos existentes.

REQUISITOS: pip install requests
"""

import sqlite3
import json
import requests
from datetime import datetime

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
    payload = {"texto": texto_voz}
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
        conn = sqlite3.connect(db_path)
        conn.row_factory = sqlite3.Row
        rows = conn.execute(
            "SELECT id, tipo, mensaje, fecha FROM notificaciones WHERE leida = 0 ORDER BY fecha DESC"
        ).fetchall()
        conn.close()
        return [dict(r) for r in rows]
    except Exception:
        return []


def marcar_notificacion_leida(db_path: str, notif_id: int):
    """Marca una notificación como leída."""
    try:
        conn = sqlite3.connect(db_path)
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
        conn = sqlite3.connect(db_path)
        conn.row_factory = sqlite3.Row
        rows = conn.execute(
            "SELECT id, numero_pedido, telefono, nombre_cliente, texto_mensaje FROM seguimientos_pendientes WHERE enviado = 0"
        ).fetchall()
        conn.close()
        return [dict(r) for r in rows]
    except Exception:
        return []


def marcar_seguimiento_enviado(db_path: str, seguimiento_id: int):
    """Marca un seguimiento como enviado después de que tú lo mandas por WhatsApp."""
    try:
        conn = sqlite3.connect(db_path)
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo TEXT,
    mensaje TEXT,
    fecha TEXT,
    leida INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS seguimientos_pendientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero_pedido TEXT,
    telefono TEXT,
    nombre_cliente TEXT,
    texto_mensaje TEXT,
    fecha_creacion TEXT,
    enviado INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tareas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    descripcion TEXT NOT NULL,
    prioridad TEXT DEFAULT 'media',
    fecha_limite TEXT,
    completada INTEGER DEFAULT 0,
    fecha_creacion TEXT DEFAULT (datetime('now'))
);
"""


def inicializar_tablas(db_path: str):
    """Crea las tablas necesarias si no existen. Llama al arrancar la app."""
    try:
        conn = sqlite3.connect(db_path)
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
