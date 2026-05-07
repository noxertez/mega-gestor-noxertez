import json

file_path = r'c:\mis app de noxertez 2\SahtoutCMS-main\status_final.json'

with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

# Modificar Flujo 07 (Influencers)
for wf in data:
    if wf['id'] == 'd3f79c0d-6014-44':
        for node in wf['nodes']:
            if node['name'] == "Filtrar y extraer datos influencer":
                node['parameters']['jsCode'] = """const email = $input.first().json;

const de = (email.from || '').toLowerCase();
const para = (email.to || '').toLowerCase();
const asunto = (email.subject || '').toLowerCase();

// Solo procesar correos a influencer@noxertez.com
const esParaInfluencer = para.includes('influencer@noxertez.com');

// Anti-bucle (ignorar solo errores de sistema)
const esPropio = de.includes('mailer-daemon') || de.includes('postmaster');

// ACEPTAR TODO lo que vaya a influencer y no sea error de sistema
const ignorar = !esParaInfluencer || esPropio;

if (ignorar) {
  return [{ json: { ignorar: true, motivo: !esParaInfluencer ? 'no_es_influencer' : 'correo_sistema' } }];
}

// Extraer datos del remitente
const deRaw = email.from || '';
let nombre = deRaw.includes('<') ? deRaw.split('<')[0].trim().replace(/\"/g, '') : deRaw.split('@')[0];
if (!nombre || nombre.length < 2) nombre = deRaw.split('@')[0];

const emailCliente = deRaw.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/);
const emailLimpio = emailCliente ? emailCliente[0] : deRaw;

const cuerpo = (email.text || email.html || '').substring(0, 800);

// Intentar detectar red social e @usuario mencionados en el cuerpo
const igMatch = cuerpo.match(/@([a-zA-Z0-9._]{2,30})/);
const usuarioIG = igMatch ? igMatch[1] : '';

const redSocial = cuerpo.toLowerCase().includes('tiktok') ? 'TikTok' :
                  cuerpo.toLowerCase().includes('youtube') ? 'YouTube' :
                  cuerpo.toLowerCase().includes('instagram') ? 'Instagram' : 'Instagram';

// Intentar detectar seguidores mencionados
const segMatch = cuerpo.match(/(\\d[\\d.,]+)\\s*(?:seguidores|followers|subs)/i);
let seguidores = 0;
if (segMatch) {
  seguidores = parseInt(segMatch[1].replace(/[.,]/g, ''));
}

return [{ json: {
  ignorar: false,
  nombre,
  email: emailLimpio,
  red_social: redSocial,
  usuario_ig: usuarioIG,
  seguidores,
  nicho: '',
  vibe_estilo: '',
  likes_promedio: 0,
  activo: 1,
  notas_contacto: `Asunto: ${email.subject || ''} | Mensaje: ${cuerpo.substring(0, 300)}`,
  fecha: new Date().toISOString()
}}];"""

    # Modificar Flujo 08 (Info y Ayuda)
    if wf['id'] == '435ec350-1a0f-46':
        for node in wf['nodes']:
            if node['name'] == "Filtrar y clasificar":
                node['parameters']['jsCode'] = """const email = $input.first().json;

const de = (email.from || '').toLowerCase();
const para = (email.to || '').toLowerCase();
const asunto = (email.subject || '').toLowerCase();

// Solo procesar info@ y ayuda@
const esParaInfo = para.includes('info@noxertez.com');
const esParaAyuda = para.includes('ayuda@noxertez.com');

// Anti-bucle (solo sistema)
const esPropio = de.includes('mailer-daemon') || de.includes('postmaster');

// Si va a info o ayuda, procesar SIEMPRE
const ignorar = (!esParaInfo && !esParaAyuda) || esPropio;

if (ignorar) {
  return [{ json: { ignorar: true } }];
}

// Extraer remitente
const deRaw = email.from || '';
let nombre = deRaw.includes('<') ? deRaw.split('<')[0].trim().replace(/\"/g, '') : deRaw.split('@')[0];
const emailMatch = deRaw.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/);
const emailLimpio = emailMatch ? emailMatch[0] : deRaw;

const tipo = esParaAyuda ? 'ayuda' : 'info';
const emoji = esParaAyuda ? '🆘' : 'ℹ️';
const cuerpo = (email.text || '').substring(0, 400);

return [{ json: {
  ignorar: false,
  tipo,
  emoji,
  nombre,
  email: emailLimpio,
  asunto: email.subject || '(sin asunto)',
  cuerpo,
  fecha: new Date().toISOString()
}}];"""

    # Modificar Flujo 06 (Pedidos)
    if wf['id'] == '53d429fa-bc39-48':
        for node in wf['nodes']:
            if node['name'] == "Filtrar spam y bucles":
                node['parameters']['jsCode'] = """const email = $input.first().json;

const de = (email.from || '').toLowerCase();
const para = (email.to || '').toLowerCase();
const asunto = (email.subject || '').toLowerCase();
const cuerpo = (email.text || email.html || '').toLowerCase();

// Solo ignorar errores de sistema críticos
const esPropio = de.includes('mailer-daemon') || de.includes('postmaster');

// Detección de destinatario
let tipoPedido = 'desconocido';
if (para.includes('pedidos@noxertez.com')) tipoPedido = 'pedido_directo';
else if (para.includes('info@noxertez.com')) tipoPedido = 'info_general';
else if (para.includes('influencer@noxertez.com')) tipoPedido = 'influencer';
else if (para.includes('ayuda@noxertez.com')) tipoPedido = 'soporte';

// Ignorar solo si es error de sistema o no va a ninguna de nuestras cuentas
const ignorar = esPropio || tipoPedido === 'desconocido';

return [{ json: {
  ignorar,
  motivo_ignorar: esPropio ? 'correo_sistema' : tipoPedido === 'desconocido' ? 'destinatario_ajeno' : null,
  tipo: tipoPedido,
  de: email.from || '',
  para: email.to || '',
  asunto: email.subject || '',
  cuerpo_raw: (email.text || '').substring(0, 500),
  fecha: new Date().toISOString()
}}];"""

with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("status_final.json actualizado correctamente con códigos permisivos.")
