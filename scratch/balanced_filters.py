import json
import sqlite3
import os
from datetime import datetime

file_path = r'c:\mis app de noxertez 2\SahtoutCMS-main\status_final.json'
db_path = r'C:\Users\usuario\.n8n\database.sqlite'

with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

for wf in data:
    # Flujo 07 (Influencers) - EQUILIBRIO: Solo errores de sistema
    if wf['id'] == 'd3f79c0d-6014-44':
        for node in wf['nodes']:
            if node['name'] == "Filtrar y extraer datos influencer":
                node['parameters']['jsCode'] = """const email = $input.first().json;
const de = (email.from || '').toLowerCase();
const para = (email.to || '').toLowerCase();

// Solo ignorar errores de servidor
const esPropio = de.includes('mailer-daemon') || de.includes('postmaster');
const esParaInfluencer = para.includes('influencer@noxertez.com');

if (!esParaInfluencer || esPropio) {
  return [{ json: { ignorar: true, motivo: esPropio ? 'sistema' : 'otro_destinatario' } }];
}

return [{ json: {
  ignorar: false,
  nombre: email.from || 'Influencer',
  email: email.from || '',
  red_social: 'Instagram',
  usuario_ig: (email.text || '').match(/@([a-zA-Z0-9._]{2,30})/) ? (email.text || '').match(/@([a-zA-Z0-9._]{2,30})/)[1] : '',
  seguidores: 0,
  nicho: '',
  vibe_estilo: '',
  likes_promedio: 0,
  activo: 1,
  notas_contacto: `Asunto: ${email.subject || ''} | Mensaje: ${email.text || ''}`,
  fecha: new Date().toISOString()
}}];"""

    # Flujo 08 (Info y Ayuda) - EQUILIBRIO: Solo errores de sistema
    if wf['id'] == '435ec350-1a0f-46':
        for node in wf['nodes']:
            if node['name'] == "Filtrar y clasificar":
                node['parameters']['jsCode'] = """const email = $input.first().json;
const de = (email.from || '').toLowerCase();
const para = (email.to || '').toLowerCase();

const esPropio = de.includes('mailer-daemon') || de.includes('postmaster');
const esParaInfo = para.includes('info@noxertez.com');
const esParaAyuda = para.includes('ayuda@noxertez.com');

if ((!esParaInfo && !esParaAyuda) || esPropio) {
  return [{ json: { ignorar: true } }];
}

const tipo = esParaAyuda ? 'ayuda' : 'info';
return [{ json: {
  ignorar: false,
  tipo,
  emoji: tipo === 'ayuda' ? '🆘' : 'ℹ️',
  nombre: email.from || 'Remitente',
  email: email.from || '',
  asunto: email.subject || '(sin asunto)',
  cuerpo: (email.text || '').substring(0, 400),
  fecha: new Date().toISOString()
}}];"""

    # Flujo 06 (Pedidos) - EQUILIBRIO: Marcado de SPAM sospechoso
    if wf['id'] == '53d429fa-bc39-48':
        for node in wf['nodes']:
            if node['name'] == "Filtrar spam y bucles":
                node['parameters']['jsCode'] = """const email = $input.first().json;
const de = (email.from || '').toLowerCase();
const asunto = (email.subject || '').toLowerCase();

const esPropio = de.includes('mailer-daemon') || de.includes('postmaster');
const esSpamSospechoso = ['newsletter','marketing','unsubscribe','publicidad','oferta'].some(s => asunto.includes(s) || de.includes(s));

return [{ json: {
  ignorar: esPropio,
  esSpam: esSpamSospechoso,
  tipo: 'pedido_directo',
  de: email.from || '',
  para: email.to || '',
  asunto: (esSpamSospechoso ? '[¿SPAM?] ' : '') + (email.subject || ''),
  cuerpo_raw: (email.text || '').substring(0, 500),
  fecha: new Date().toISOString()
}}];"""

# Guardar y actualizar DB
with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

conn = sqlite3.connect(db_path)
cursor = conn.cursor()
ids_to_update = ['53d429fa-bc39-48', 'd3f79c0d-6014-44', '435ec350-1a0f-46']
for wf in data:
    if wf.get('id') in ids_to_update:
        cursor.execute("UPDATE workflow_entity SET nodes = ?, updatedAt = ? WHERE id = ?", 
                       (json.dumps(wf.get('nodes')), datetime.now().isoformat(), wf.get('id')))
conn.commit()
conn.close()
print("Lógica de equilibrio aplicada correctamente.")
