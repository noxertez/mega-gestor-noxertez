import sqlite3
import mysql.connector

# Conectar a MySQL
try:
    conn = mysql.connector.connect(
        host='localhost',
        user='root',
        password='',
        database='noxertez'
    )
    cursor = conn.cursor()
    
    print("--- COLUMNAS EN TABLA 'productos' ---")
    cursor.execute("SHOW COLUMNS FROM productos")
    for col in cursor.fetchall():
        print(f"Columna: {col[0]} | Tipo: {col[1]}")
        
    print("\n--- COLUMNAS EN TABLA 'articulos' ---")
    cursor.execute("SHOW COLUMNS FROM articulos")
    for col in cursor.fetchall():
        print(f"Columna: {col[0]} | Tipo: {col[1]}")
        
    conn.close()
except Exception as e:
    print(f"Error: {e}")
