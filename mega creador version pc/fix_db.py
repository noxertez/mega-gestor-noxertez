import sqlite3
import os
import sys

# Añadir el directorio actual al path
sys.path.append(os.getcwd())

try:
    from modulo3_gestion import GestorProductos, DB_PATH
    print(f"Iniciando actualización de base de datos: {DB_PATH}")
    
    # Esto disparará crear_tablas_si_no_existen()
    gestor = GestorProductos()
    print("✅ Inicialización de tablas completada.")
    
    # Verificación manual de tablas críticas
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    tablas_esperadas = ['productos', 'clientes', 'pedidos', 'cliente_articulos', 'materiales', 'despiece_articulos', 'futuros_proyectos']
    
    print("\nVerificando tablas:")
    for tabla in tablas_esperadas:
        cursor.execute(f"SELECT name FROM sqlite_master WHERE type='table' AND name='{tabla}'")
        if cursor.fetchone():
            print(f"  [OK] {tabla}")
        else:
            print(f"  [ERROR] Faltante: {tabla}")
            
    print("\nVerificando columnas críticas:")
    pruebas_columnas = [
        ('pedidos', 'unboxing_checklist'),
        ('clientes', 'direccion'),
        ('productos', 'peso_envio')
    ]
    for tabla, col in pruebas_columnas:
        cursor.execute(f"PRAGMA table_info({tabla})")
        cols = [c[1] for c in cursor.fetchall()]
        if col in cols:
            print(f"  [OK] {tabla}.{col}")
        else:
            print(f"  [ERROR] Faltante: {tabla}.{col}")
            
    conn.close()

except Exception as e:
    print(f"❌ Error durante la verificación: {e}")
    import traceback
    traceback.print_exc()
