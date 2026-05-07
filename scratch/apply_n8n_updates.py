import sqlite3
import json
import os
from datetime import datetime

db_path = r'C:\Users\usuario\.n8n\database.sqlite'
json_path = r'c:\mis app de noxertez 2\SahtoutCMS-main\status_final.json'

if not os.path.exists(db_path):
    print(f"ERROR: No se encuentra la base de datos en {db_path}")
    exit(1)

with open(json_path, 'r', encoding='utf-8') as f:
    workflows_data = json.load(f)

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

ids_to_update = ['53d429fa-bc39-48', 'd3f79c0d-6014-44', '435ec350-1a0f-46']

for wf in workflows_data:
    wf_id = wf.get('id')
    if wf_id in ids_to_update:
        print(f"Actualizando flujo: {wf.get('name')} ({wf_id})")
        
        nodes_json = json.dumps(wf.get('nodes'))
        connections_json = json.dumps(wf.get('connections'))
        updated_at = datetime.now().isoformat()
        
        # Actualizamos nodos y conexiones
        cursor.execute("""
            UPDATE workflow_entity 
            SET nodes = ?, connections = ?, updatedAt = ?
            WHERE id = ?
        """, (nodes_json, connections_json, updated_at, wf_id))

conn.commit()
print("--- ACTUALIZACIÓN COMPLETADA ---")
conn.close()
