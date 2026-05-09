import mysql.connector

try:
    conn = mysql.connector.connect(
        host='localhost',
        user='root',
        password='',
        database='noxertez'
    )
    cursor = conn.cursor()
    
    sku = 'NXTRELCAÑ0072P01'
    print(f"--- BUSCANDO SKU: {sku} ---")
    
    # Buscar en productos
    cursor.execute("SELECT * FROM productos WHERE SKU_REF = %s", (sku,))
    prod = cursor.fetchone()
    if prod:
        print("Encontrado en 'productos':")
        # Mostrar columnas y valores
        cursor.execute("SHOW COLUMNS FROM productos")
        cols = [c[0] for c in cursor.fetchall()]
        for col, val in zip(cols, prod):
            print(f"  {col}: {val}")
    else:
        print("No encontrado en 'productos'.")
        
    # Buscar en articulos
    cursor.execute("SELECT * FROM articulos WHERE referencia = %s", (sku,))
    art = cursor.fetchone()
    if art:
        print("\nEncontrado en 'articulos':")
        cursor.execute("SHOW COLUMNS FROM articulos")
        cols = [c[0] for c in cursor.fetchall()]
        for col, val in zip(cols, art):
            print(f"  {col}: {val}")
    else:
        print("No encontrado en 'articulos'.")
        
    conn.close()
except Exception as e:
    print(f"Error: {e}")
