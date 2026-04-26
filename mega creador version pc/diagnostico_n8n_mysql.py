"""
DIAGNÓSTICO DE CONEXIÓN n8n + MySQL
Ejecuta este script para verificar que todo está correcto antes de configurar n8n.
"""
import subprocess
import sys
import os

# ─── 1. Verificar que MySQL responde ──────────────────────────────────────────
print("=" * 60)
print("  DIAGNÓSTICO n8n + MySQL — NOXERTEZ")
print("=" * 60)

MYSQL_BIN = r"c:\xampp\mysql\bin\mysql.exe"
HOST     = "localhost"
PORT     = "3306"
DB       = "noxertez"
USER     = "noxertez_user"
PASS     = "Noxertez2024!"

def run_query(query):
    cmd = [MYSQL_BIN, "-h", HOST, "-P", PORT, f"-u{USER}", f"-p{PASS}", DB, "-e", query]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
        return result.stdout, result.stderr
    except Exception as e:
        return "", str(e)

# Test conexión
print("\n🔌 Test 1: Conexión a MySQL")
out, err = run_query("SELECT 'OK' as estado;")
if "OK" in out:
    print("   ✅ MySQL responde correctamente")
else:
    print(f"   ❌ ERROR conectando a MySQL: {err}")
    print("   → Asegúrate de que XAMPP/MySQL está iniciado")
    sys.exit(1)

# Verificar usuario
print("\n👤 Test 2: Permisos del usuario noxertez_user")
out, err = run_query("SHOW GRANTS FOR 'noxertez_user'@'localhost';")
if "noxertez_user" in out:
    print("   ✅ Usuario existe y tiene permisos")
else:
    print("   ❌ Usuario no encontrado. Ejecuta:")
    print("   CREATE USER 'noxertez_user'@'localhost' IDENTIFIED BY 'Noxertez2024!';")
    print("   GRANT ALL PRIVILEGES ON noxertez.* TO 'noxertez_user'@'localhost';")

# Verificar tablas necesarias para n8n
print("\n📦 Test 3: Tablas necesarias para los flujos de n8n")
tablas_requeridas = ["tareas", "pedidos", "notificaciones", "seguimientos_pendientes", "productos"]
out, _ = run_query("SHOW TABLES;")
faltantes = []
for tabla in tablas_requeridas:
    if tabla in out:
        print(f"   ✅ {tabla}")
    else:
        print(f"   ❌ Falta tabla: {tabla}")
        faltantes.append(tabla)

# Crear tablas faltantes
if faltantes:
    print("\n🔧 Creando tablas faltantes...")
    sql_tablas = {
        "notificaciones": """
            CREATE TABLE IF NOT EXISTS notificaciones (
                id INT AUTO_INCREMENT PRIMARY KEY, tipo TEXT,
                mensaje TEXT, fecha TEXT, leida INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;""",
        "seguimientos_pendientes": """
            CREATE TABLE IF NOT EXISTS seguimientos_pendientes (
                id INT AUTO_INCREMENT PRIMARY KEY, numero_pedido VARCHAR(100),
                telefono VARCHAR(50), nombre_cliente TEXT, texto_mensaje TEXT,
                fecha_creacion TEXT, enviado INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;""",
        "tareas": """
            CREATE TABLE IF NOT EXISTS tareas (
                id INT AUTO_INCREMENT PRIMARY KEY, descripcion TEXT NOT NULL,
                prioridad TEXT DEFAULT 'media', fecha_limite TEXT,
                completada INT DEFAULT 0,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"""
    }
    for tabla in faltantes:
        if tabla in sql_tablas:
            run_query(sql_tablas[tabla])
            print(f"   ✅ Tabla {tabla} creada")

# Contar registros
print("\n📊 Test 4: Contenido de las tablas")
for tabla in ["tareas", "pedidos", "notificaciones"]:
    out, _ = run_query(f"SELECT COUNT(*) as total FROM {tabla};")
    lineas = [l for l in out.strip().split('\n') if l.strip().isdigit()]
    total = lineas[0] if lineas else "?"
    print(f"   {tabla}: {total} registros")

# Verificar n8n
print("\n🤖 Test 5: Estado de n8n")
try:
    import socket
    with socket.create_connection(("127.0.0.1", 5678), timeout=3):
        print("   ✅ n8n responde en http://localhost:5678")
except:
    print("   ❌ n8n NO responde en el puerto 5678")
    print("   → Inícialo desde el Panel de Control de Servicios")

print("\n" + "=" * 60)
print("  📋 PRÓXIMOS PASOS PARA CONFIGURAR n8n:")
print("=" * 60)
print("""
1. Abre http://localhost:5678 en el navegador
2. Ve a Settings > Credentials > New Credential
3. Busca "MySQL" y configura:
     Host:     localhost
     Port:     3306
     Database: noxertez
     User:     noxertez_user
     Password: Noxertez2024!
4. Guarda con el nombre exacto: "Noxertez MySQL"
5. Importa los flujos desde:
     c:\\mis app de noxertez 2\\SahtoutCMS-main\\mega creador version pc\\flujos de n9n\\
   - flujo5_asistente_voz.json   (Asistente de voz)
   - flujo3_postventa.json       (Seguimiento post-venta)
6. Activa cada flujo haciendo clic en el toggle "Active"
""")
print("=" * 60)
