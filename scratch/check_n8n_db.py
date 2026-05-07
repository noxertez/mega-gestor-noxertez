import sqlite3
import json
import os

db_path = r'C:\Users\usuario\.n8n\database.sqlite'

if not os.path.exists(db_path):
    print(f"ERROR: No se encuentra la base de datos en {db_path}")
    exit(1)

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

print("--- LISTADO DE FLUJOS EN BD ---")
cursor.execute("SELECT id, name, active FROM workflow_entity")
rows = cursor.fetchall()
for row in rows:
    print(f"ID: {row[0]} | Nombre: {row[1]} | Activo: {row[2]}")

conn.close()
