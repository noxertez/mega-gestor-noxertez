import sqlite3
import json

db_path = r'C:\Users\usuario\.n8n\database.sqlite'

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

ids = ['53d429fa-bc39-48', 'd3f79c0d-6014-44', '435ec350-1a0f-46']

for wf_id in ids:
    cursor.execute("SELECT name, nodes FROM workflow_entity WHERE id = ?", (wf_id,))
    row = cursor.fetchone()
    if row:
        name, nodes_json = row
        nodes = json.loads(nodes_json)
        # Buscar el nodo de filtrado
        for node in nodes:
            if "Filtrar" in node['name']:
                print(f"--- FLUJO: {name} ---")
                print(f"NODO: {node['name']}")
                print(node['parameters'].get('jsCode', 'No jsCode found'))
                print("\n")

conn.close()
