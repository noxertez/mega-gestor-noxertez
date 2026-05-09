import json
import sqlite3
import os
from datetime import datetime

file_path = r'c:\mis app de noxertez 2\SahtoutCMS-main\status_final.json'
db_path = r'C:\Users\usuario\.n8n\database.sqlite'

# 1. Modificar el JSON con lógica "SIN FILTROS"
with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

for wf in data:
    # Flujo 07 (Influencers)
    if wf['id'] == 'd3f79c0d-6014-44':
        for node in wf['nodes']:
            if node['name'] == "Filtrar y extraer datos influencer":
                node['parameters']['jsCode'] = """const email = $input.first().json;
// BYPASS TOTAL DE FILTROS
return [{ json: {
  ignorar: false,
  nombre: email.from || 'Remitente desconocido',
  email: email.from || '',
  red_social: 'Instagram',
  usuario_ig: '',
  seguidores: 0,
  nicho: '',
  vibe_estilo: '',
  likes_promedio: 0,
  activo: 1,
  notas_contacto: `Asunto: ${email.subject || ''} | Mensaje: ${email.text || ''}`,
  fecha: new Date().toISOString()
}}];"""

    # Flujo 08 (Info y Ayuda)
    if wf['id'] == '435ec350-1a0f-46':
        for node in wf['nodes']:
            if node['name'] == "Filtrar y clasificar":
                node['parameters']['jsCode'] = """const email = $input.first().json;
// BYPASS TOTAL DE FILTROS
return [{ json: {
  ignorar: false,
  tipo: 'info',
  emoji: 'ℹ️',
  nombre: email.from || 'Remitente',
  email: email.from || '',
  asunto: email.subject || '(sin asunto)',
  cuerpo: (email.text || '').substring(0, 400),
  fecha: new Date().toISOString()
}}];"""

    # Flujo 06 (Pedidos)
    if wf['id'] == '53d429fa-bc39-48':
        for node in wf['nodes']:
            if node['name'] == "Filtrar spam y bucles":
                node['parameters']['jsCode'] = """const email = $input.first().json;
// BYPASS TOTAL DE FILTROS
return [{ json: {
  ignorar: false,
  motivo_ignorar: null,
  tipo: 'pedido_directo',
  de: email.from || '',
  para: email.to || '',
  asunto: email.subject || '',
  cuerpo_raw: (email.text || '').substring(0, 500),
  fecha: new Date().toISOString()
}}];"""

# Guardar el JSON actualizado
with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

# 2. Inyectar directamente en la DB de n8n
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

ids_to_update = ['53d429fa-bc39-48', 'd3f79c0d-6014-44', '435ec350-1a0f-46']
for wf in data:
    wf_id = wf.get('id')
    if wf_id in ids_to_update:
        nodes_json = json.dumps(wf.get('nodes'))
        connections_json = json.dumps(wf.get('connections'))
        cursor.execute("UPDATE workflow_entity SET nodes = ?, connections = ?, updatedAt = ? WHERE id = ?", 
                       (nodes_json, connections_json, datetime.now().isoformat(), wf_id))

conn.commit()
conn.close()

print("Filtros eliminados y base de datos actualizada.")
