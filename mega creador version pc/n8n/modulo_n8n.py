import json
import requests
import os
import shutil
import subprocess
from datetime import datetime
from modulo3_gestion import get_db_connection

# URL base de n8n (cámbiala si n8n está en otro puerto o servidor)
N8N_BASE = "http://localhost:5678/webhook"

# =============================================================================
# PEDIDO MANUAL (Flujo 01)
# =============================================================================

def enviar_pedido_a_n8n(cliente: str, telefono: str, items: list,
                         canal: str = "manual", notas: str = "") -> dict:
    """
    Envía un pedido nuevo a n8n para que lo guarde en MySQL.
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
    """
    payload = {"texto": texto_voz}
    try:
        r = requests.post(f"{N8N_BASE}/asistente", json=payload, timeout=8)
        data = r.json()
        return data.get("respuesta", "No pude obtener respuesta del asistente.")
    except Exception as e:
        return f"Error al conectar con el asistente: {e}"

# =============================================================================
# NOTIFICACIONES (leer alertas generadas por n8n en MySQL)
# =============================================================================

def obtener_notificaciones_nuevas() -> list:
    """
    Lee las notificaciones no leídas que n8n ha guardado en MySQL.
    """
    conn = get_db_connection()
    if not conn: return []
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute(
            "SELECT id, tipo, mensaje, fecha FROM notificaciones WHERE leida = 0 ORDER BY fecha DESC"
        )
        return cursor.fetchall()
    except Exception:
        return []
    finally:
        conn.close()

def marcar_notificacion_leida(notif_id: int):
    """Marca una notificación como leída en MySQL."""
    conn = get_db_connection()
    if not conn: return
    try:
        cursor = conn.cursor()
        cursor.execute("UPDATE notificaciones SET leida = 1 WHERE id = %s", (notif_id,))
        conn.commit()
    except Exception:
        pass
    finally:
        conn.close()

def obtener_seguimientos_pendientes() -> list:
    """
    Lee los mensajes de seguimiento post-venta que n8n preparó.
    """
    conn = get_db_connection()
    if not conn: return []
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute(
            "SELECT id, numero_pedido, telefono, nombre_cliente, texto_mensaje FROM seguimientos_pendientes WHERE enviado = 0"
        )
        return cursor.fetchall()
    except Exception:
        return []
    finally:
        conn.close()

def marcar_seguimiento_enviado(seguimiento_id: int):
    """Marca un seguimiento como enviado."""
    conn = get_db_connection()
    if not conn: return
    try:
        cursor = conn.cursor()
        cursor.execute("UPDATE seguimientos_pendientes SET enviado = 1 WHERE id = %s", (seguimiento_id,))
        conn.commit()
    except Exception:
        pass
    finally:
        conn.close()

# =============================================================================
# TABLAS MYSQL NECESARIAS
# =============================================================================

SQL_TABLAS = [
    '''CREATE TABLE IF NOT EXISTS pedidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_pedido VARCHAR(100) UNIQUE,
        telefono VARCHAR(50) DEFAULT '',
        nombre_cliente TEXT,
        items_json TEXT,
        total DOUBLE DEFAULT 0,
        estado TEXT DEFAULT 'pendiente',
        fecha_pedido TEXT,
        canal TEXT DEFAULT 'manual',
        notas TEXT DEFAULT '',
        fecha_entrega TEXT,
        seguimiento_enviado INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4''',
    
    '''CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo TEXT,
        mensaje TEXT,
        fecha TEXT,
        leida INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4''',
    
    '''CREATE TABLE IF NOT EXISTS seguimientos_pendientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_pedido VARCHAR(100),
        telefono VARCHAR(50),
        nombre_cliente TEXT,
        texto_mensaje TEXT,
        fecha_creacion TEXT,
        enviado INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4''',
    
    '''CREATE TABLE IF NOT EXISTS tareas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        descripcion TEXT NOT NULL,
        prioridad TEXT DEFAULT 'media',
        fecha_limite TEXT,
        completada INT DEFAULT 0,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'''
]

def inicializar_tablas():
    """Crea las tablas necesarias si no existen."""
    conn = get_db_connection()
    if not conn: return
    try:
        cursor = conn.cursor()
        for sql in SQL_TABLAS:
            try:
                cursor.execute(sql)
            except Exception as e:
                print(f"[n8n] Error creando tabla: {e}")
        
        # Columnas extra en productos (antes 'articulos')
        # Nota: La tabla principal ahora es 'productos' según modulo3_gestion
        extras = [
            "ALTER TABLE productos ADD COLUMN precio_modificado INT DEFAULT 0",
            "ALTER TABLE productos ADD COLUMN stock_minimo INT DEFAULT 5",
            "ALTER TABLE productos ADD COLUMN alerta_stock_enviada INT DEFAULT 0",
        ]
        for sql in extras:
            try:
                cursor.execute(sql)
            except Exception:
                pass 
        
        conn.commit()
        print("[n8n] Tablas inicializadas correctamente en MySQL")
    except Exception as e:
        print(f"[n8n] Error inicializando tablas: {e}")
    finally:
        conn.close()

def arrancar_n8n_local():
    """
    Arranca la instancia local de n8n v1.123.23.
    """
    import subprocess
    import os
    
    # Ajustamos la ruta base: n8n_local está en el raíz del proyecto
    # "C:\mis app de noxertez 2\SahtoutCMS-main\mega creador version pc\n8n\modulo_n8n.py"
    # base_dir -> "C:\mis app de noxertez 2\SahtoutCMS-main\mega creador version pc"
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    n8n_bin = os.path.join(base_dir, "n8n_local", "node_modules", ".bin", "n8n.cmd")
    n8n_local_dir = os.path.join(base_dir, "n8n_local")
    n8n_bin = os.path.join(n8n_local_dir, "node_modules", ".bin", "n8n.cmd")
    
    # Si no existe la local, buscamos la global
    if not os.path.exists(n8n_bin):
        n8n_bin = shutil.which("n8n")
        if not n8n_bin:
            # En Windows a veces n8n está en el PATH pero shutil.which no lo pilla bien si es .cmd
            n8n_bin = "n8n"
            
    if not os.path.exists(n8n_local_dir):
        # Usar el directorio base si no existe n8n_local
        n8n_local_dir = base_dir

    # Archivos de log para depuración
    try:
        log_out = open(os.path.join(n8n_local_dir, "n8n_stdout.log"), "a")
        log_err = open(os.path.join(n8n_local_dir, "n8n_stderr.log"), "a")
    except:
        # Fallback a directorio temporal si no hay permisos
        import tempfile
        tmp = tempfile.gettempdir()
        log_out = open(os.path.join(tmp, "n8n_stdout.log"), "a")
        log_err = open(os.path.join(tmp, "n8n_stderr.log"), "a")

    # Verificar si ya está corriendo antes de intentar arrancar
    import socket
    try:
        with socket.create_connection(('127.0.0.1', 5678), timeout=1):
            print("[n8n] n8n ya está en ejecución en el puerto 5678. No es necesario arrancar otra instancia.")
            return None
    except:
        pass

    print(f"[n8n] Iniciando n8n (Binario: {n8n_bin})...")
    
    env = os.environ.copy()
    user_home = os.path.expanduser("~")
    env["N8N_USER_FOLDER"] = user_home
    env["N8N_SKIP_V1_NODE_VERSION_CHECK"] = "true"
    
    import sys
    python_dir = os.path.dirname(sys.executable)
    if python_dir not in env.get("PATH", ""):
        env["PATH"] = python_dir + os.pathsep + env.get("PATH", "")

    # Comando de arranque
    cmd = [n8n_bin, "start", "--force-node"]
    
    try:
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
