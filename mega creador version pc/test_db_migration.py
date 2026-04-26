import sys
import os

# Añadir la ruta del proyecto para importar los módulos
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), "n8n"))

try:
    import modulo3_gestion
    import modulo_n8n
    print("✅ Módulos importados correctamente.")
except ImportError as e:
    print(f"❌ Error al importar módulos: {e}")
    sys.exit(1)

def test_connection():
    print("\n--- Probando Conexión MySQL ---")
    conn = modulo3_gestion.get_db_connection()
    if conn and conn.is_connected():
        print("✅ Conexión exitosa a MySQL.")
        conn.close()
    else:
        print("❌ Falló la conexión a MySQL. Verifique XAMPP y la configuración.")

def test_table_initialization():
    print("\n--- Probando Inicialización de Tablas ---")
    try:
        # Inicializar productos (modulo3_gestion)
        gestor_prod = modulo3_gestion.GestorProductos()
        print("✅ Tablas de modulo3_gestion inicializadas.")
        
        # Inicializar n8n (modulo_n8n)
        modulo_n8n.inicializar_tablas()
        print("✅ Tablas de n8n inicializadas.")
        
    except Exception as e:
        print(f"❌ Error durante la inicialización de tablas: {e}")

if __name__ == "__main__":
    test_connection()
    test_table_initialization()
