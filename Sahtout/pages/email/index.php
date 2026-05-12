<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../../includes/session.php';

// Protección: solo admin/moderator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'moderator'])) {
    header('Location: ' . $base_path . 'login?error=unauthorized');
    exit;
}

$page_class = 'management-page';
include('../../includes/header.php');
?>

<link rel="stylesheet" href="<?= $base_path ?>pages/email/style_email.css?v=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<div class="email-module">

    <!-- ===== CABECERA ===== -->
    <div class="email-header">
        <div>
            <h1>✉️ Centro de Email</h1>
            <p class="email-header-sub">Gestión de comunicaciones Noxertez · noxertez@gmail.com</p>
        </div>
        <button class="btn-email btn-email-primary" id="btn-redactar" onclick="abrirRedaccion()">
            <i class="fas fa-pen"></i> Redactar
        </button>
    </div>

    <!-- ===== BANNER ERROR IMAP ===== -->
    <div class="imap-error-banner" id="imap-error-banner">
        <i class="fas fa-exclamation-triangle"></i>
        <span id="imap-error-text">Error de conexión IMAP.</span>
    </div>

    <!-- ===== PESTAÑAS ===== -->
    <div class="email-tabs" id="email-tabs">
        <button class="email-tab active" data-alias="info"        onclick="cambiarTab(this, 'info')">
            <span class="tab-icon">📋</span> Info
            <span class="badge-unread" id="badge-info" style="display:none">0</span>
        </button>
        <button class="email-tab" data-alias="pedidos"    onclick="cambiarTab(this, 'pedidos')">
            <span class="tab-icon">📦</span> Pedidos
            <span class="badge-unread" id="badge-pedidos" style="display:none">0</span>
        </button>
        <button class="email-tab" data-alias="influencers" onclick="cambiarTab(this, 'influencers')">
            <span class="tab-icon">🤝</span> Influencers
            <span class="badge-unread" id="badge-influencers" style="display:none">0</span>
        </button>
        <button class="email-tab" data-alias="ayuda"      onclick="cambiarTab(this, 'ayuda')">
            <span class="tab-icon">🆘</span> Ayuda
            <span class="badge-unread" id="badge-ayuda" style="display:none">0</span>
        </button>
    </div>

    <!-- ===== ESTADÍSTICAS ===== -->
    <div class="email-stats" id="email-stats">
        <div class="stat-card">
            <div class="stat-icon recibidos">📥</div>
            <div>
                <div class="stat-value" id="stat-recibidos">—</div>
                <div class="stat-label">Recibidos hoy</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon enviados">📤</div>
            <div>
                <div class="stat-value" id="stat-enviados">—</div>
                <div class="stat-label">Enviados hoy</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pendientes">⏳</div>
            <div>
                <div class="stat-value" id="stat-pendientes">—</div>
                <div class="stat-label">Sin responder</div>
            </div>
        </div>
    </div>

    <!-- ===== FORMULARIO DE REDACCIÓN ===== -->
    <div class="compose-panel" id="compose-panel">
        <h3><i class="fas fa-pen-alt"></i> <span id="compose-title">Nuevo Email</span></h3>

        <div class="compose-from-badge" id="compose-from-badge">
            <i class="fas fa-paper-plane"></i>
            <span id="compose-from-label">info@noxertez.com</span>
        </div>

        <div class="compose-field">
            <label>Para (destinatario)</label>
            <input type="email" id="compose-to" placeholder="destinatario@ejemplo.com">
        </div>
        <div class="compose-field">
            <label>Asunto</label>
            <input type="text" id="compose-asunto" placeholder="Escribe el asunto...">
        </div>
        <div class="compose-field">
            <label>Mensaje</label>
            <textarea id="compose-cuerpo" placeholder="Escribe tu mensaje aquí..."></textarea>
        </div>

        <input type="hidden" id="compose-en-respuesta" value="">

        <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-top:0.5rem;">
            <button class="btn-email btn-email-gray" onclick="cerrarRedaccion()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button class="btn-email btn-email-primary" id="btn-enviar" onclick="enviarEmail()">
                <i class="fas fa-paper-plane"></i> Enviar
            </button>
        </div>
    </div>

    <!-- ===== GRID: BANDEJA + DETALLE ===== -->
    <div class="email-grid">

        <!-- Bandeja de entrada -->
        <div class="inbox-panel">
            <div class="panel-header">
                <span><i class="fas fa-inbox"></i> Bandeja de entrada</span>
                <button class="btn-email btn-email-gray" style="padding:0.3rem 0.7rem; font-size:0.75rem;" onclick="recargarBandeja()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="email-list" id="email-list">
                <div class="email-spinner">
                    <div class="spinner-ring"></div> Conectando a IMAP...
                </div>
            </div>
        </div>

        <!-- Panel de detalle -->
        <div class="detail-panel" id="detail-panel">
            <div class="detail-empty" id="detail-empty">
                <div class="detail-empty-icon">✉️</div>
                <p>Selecciona un email para ver su contenido</p>
            </div>
            <div class="detail-content" id="detail-content" style="display:none;">
                <div class="detail-meta">
                    <div class="detail-asunto" id="det-asunto"></div>
                    <div class="detail-from">De: <span id="det-from"></span></div>
                    <div class="detail-to">Para: <span id="det-to"></span></div>
                    <div class="detail-date">Fecha: <span id="det-fecha"></span></div>
                </div>
                <div class="detail-body" id="det-body">
                    <div class="email-spinner"><div class="spinner-ring"></div> Cargando...</div>
                </div>
                <div class="detail-actions">
                    <button class="btn-email btn-email-primary" id="btn-responder" onclick="prepararRespuesta()">
                        <i class="fas fa-reply"></i> Responder
                    </button>
                    <button class="btn-email btn-email-gray" onclick="cerrarDetalle()">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- ===== EMAILS ENVIADOS ===== -->
    <div class="sent-panel">
        <div class="panel-header">
            <span><i class="fas fa-paper-plane"></i> Enviados recientes desde este alias</span>
            <button class="btn-email btn-email-gray" style="padding:0.3rem 0.7rem; font-size:0.75rem;" onclick="cargarEnviados()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        <div id="sent-container">
            <div class="email-spinner"><div class="spinner-ring"></div> Cargando enviados...</div>
        </div>
    </div>

</div><!-- /email-module -->

<!-- ===== TOAST ===== -->
<div class="email-toast" id="email-toast"></div>

<script>
// ================================================================
//  MÓDULO EMAIL — Mega Gestor Noxertez
// ================================================================

const BASE = '<?= $base_path ?>pages/email/';

let aliasActual   = 'info';
let emailActualUID = null;
let emailActualData = {};

// ---- Alias → email completo ----
const ALIASES = {
    info:        'info@noxertez.com',
    pedidos:     'pedidos@noxertez.com',
    influencers: 'influencers@noxertez.com',
    ayuda:       'ayuda@noxertez.com'
};

// ================================================================
//  INIT
// ================================================================
document.addEventListener('DOMContentLoaded', () => {
    cargarBandeja('info');
    cargarBadges();
});

// ================================================================
//  PESTAÑAS
// ================================================================
function cambiarTab(btn, alias) {
    document.querySelectorAll('.email-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    aliasActual = alias;
    cerrarDetalle();
    cerrarRedaccion();
    cargarBandeja(alias);
    cargarEnviados();
}

// ================================================================
//  CARGAR BANDEJA (IMAP vía AJAX)
// ================================================================
function cargarBandeja(alias) {
    document.getElementById('email-list').innerHTML =
        '<div class="email-spinner"><div class="spinner-ring"></div> Conectando a IMAP…</div>';

    fetch(BASE + 'emails_ajax.php?action=emails&alias=' + alias)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                mostrarErrorImap(data.error || 'Error desconocido');
                document.getElementById('email-list').innerHTML =
                    '<div class="email-spinner" style="color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Error IMAP') + '</div>';
                return;
            }

            // Errores parciales IMAP
            if (data.errores_imap && data.errores_imap.length > 0) {
                mostrarErrorImap(data.errores_imap[0]);
            } else {
                ocultarErrorImap();
            }

            // Estadísticas
            document.getElementById('stat-recibidos').textContent  = data.stats.recibidos_hoy;
            document.getElementById('stat-enviados').textContent   = data.stats.enviados_hoy;
            document.getElementById('stat-pendientes').textContent = data.stats.sin_responder;

            renderizarLista(data.emails);
        })
        .catch(e => {
            mostrarErrorImap('Error de red: ' + e.message);
            document.getElementById('email-list').innerHTML =
                '<div class="email-spinner" style="color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> No se pudo contactar al servidor.</div>';
        });
}

function recargarBandeja() {
    cargarBandeja(aliasActual);
    cargarEnviados();
}

// ================================================================
//  RENDERIZAR LISTA DE EMAILS
// ================================================================
function renderizarLista(emails) {
    const lista = document.getElementById('email-list');

    if (!emails || emails.length === 0) {
        lista.innerHTML = '<div class="email-spinner" style="padding:2rem;">No hay emails para este alias.</div>';
        return;
    }

    lista.innerHTML = emails.map(em => `
        <div class="email-item ${em.leido ? '' : 'unread'}" id="item-${em.uid}"
             onclick="verDetalle(${em.uid}, this)">
            <div class="email-item-from">${escHtml(em.from)}</div>
            <div class="email-item-asunto">${escHtml(em.asunto)}</div>
            <div class="email-item-preview">${escHtml(em.preview)}</div>
            <div class="email-item-date">${formatFecha(em.fecha)}</div>
        </div>
    `).join('');
}

// ================================================================
//  VER DETALLE DE EMAIL
// ================================================================
function verDetalle(uid, el) {
    // Resaltar seleccionado
    document.querySelectorAll('.email-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');

    emailActualUID = uid;

    // Mostrar panel con spinner
    document.getElementById('detail-empty').style.display   = 'none';
    document.getElementById('detail-content').style.display = 'flex';
    document.getElementById('det-body').innerHTML =
        '<div class="email-spinner"><div class="spinner-ring"></div> Cargando email...</div>';

    fetch(BASE + 'emails_ajax.php?action=detalle&alias=' + aliasActual + '&uid=' + uid)
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !data.email) {
                document.getElementById('det-body').innerHTML =
                    '<span style="color:#ef4444;">Error al cargar el email.</span>';
                return;
            }
            const em = data.email;
            emailActualData = em;

            document.getElementById('det-asunto').textContent = em.asunto;
            document.getElementById('det-from').textContent   = em.from;
            document.getElementById('det-to').textContent     = em.to;
            document.getElementById('det-fecha').textContent  = formatFecha(em.fecha);
            document.getElementById('det-body').innerHTML     = em.cuerpo_completo || '<em>(Sin contenido)</em>';
        })
        .catch(() => {
            document.getElementById('det-body').innerHTML =
                '<span style="color:#ef4444;">Error de red al cargar el email.</span>';
        });
}

function cerrarDetalle() {
    emailActualUID  = null;
    emailActualData = {};
    document.getElementById('detail-empty').style.display   = 'flex';
    document.getElementById('detail-content').style.display = 'none';
    document.querySelectorAll('.email-item').forEach(i => i.classList.remove('selected'));
}

// ================================================================
//  REDACCIÓN / RESPUESTA
// ================================================================
function abrirRedaccion(para = '', asunto = '', cuerpoInicial = '', enRespuestaA = '') {
    document.getElementById('compose-title').textContent      = enRespuestaA ? 'Responder' : 'Nuevo Email';
    document.getElementById('compose-from-label').textContent = ALIASES[aliasActual];
    document.getElementById('compose-to').value              = para;
    document.getElementById('compose-asunto').value          = asunto;
    document.getElementById('compose-cuerpo').value          = cuerpoInicial;
    document.getElementById('compose-en-respuesta').value    = enRespuestaA;

    const panel = document.getElementById('compose-panel');
    panel.classList.add('visible');
    document.getElementById('compose-to').focus();
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cerrarRedaccion() {
    document.getElementById('compose-panel').classList.remove('visible');
    document.getElementById('compose-to').value       = '';
    document.getElementById('compose-asunto').value   = '';
    document.getElementById('compose-cuerpo').value   = '';
    document.getElementById('compose-en-respuesta').value = '';
}

function prepararRespuesta() {
    if (!emailActualData.asunto) return;
    const asuntoRe = emailActualData.asunto.startsWith('Re:')
        ? emailActualData.asunto
        : 'Re: ' + emailActualData.asunto;

    // Extraer email del remitente
    const fromRaw = emailActualData.from || '';
    const matchEmail = fromRaw.match(/<(.+?)>/);
    const para = matchEmail ? matchEmail[1] : fromRaw;

    // Hilo anterior en el cuerpo
    const fecha   = formatFecha(emailActualData.fecha);
    const cuerpoAnterior = '\n\n---\nEl ' + fecha + ', ' + fromRaw + ' escribió:\n' +
        (emailActualData.cuerpo_completo
            ? '> ' + strip(emailActualData.cuerpo_completo).replace(/\n/g, '\n> ')
            : '');

    abrirRedaccion(para, asuntoRe, cuerpoAnterior, emailActualData.asunto);
}

// ================================================================
//  ENVIAR EMAIL
// ================================================================
async function enviarEmail() {
    const btn = document.getElementById('btn-enviar');
    const to   = document.getElementById('compose-to').value.trim();
    const asunto = document.getElementById('compose-asunto').value.trim();
    const cuerpo = document.getElementById('compose-cuerpo').value.trim();
    const enRespuestaA = document.getElementById('compose-en-respuesta').value;

    if (!to || !asunto || !cuerpo) {
        showToast('❌ Rellena todos los campos obligatorios.', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-ring" style="width:16px;height:16px;border-width:2px;"></div> Enviando…';

    const fd = new FormData();
    fd.append('alias_from',    ALIASES[aliasActual]);
    fd.append('destinatario',  to);
    fd.append('asunto',        asunto);
    fd.append('cuerpo',        cuerpo);
    fd.append('en_respuesta_a', enRespuestaA);

    try {
        const res  = await fetch(BASE + 'send.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
            showToast('✅ Email enviado correctamente.', 'success');
            cerrarRedaccion();
            cargarEnviados();
            // Actualizar stats
            const env = document.getElementById('stat-enviados');
            env.textContent = (parseInt(env.textContent) || 0) + 1;
        } else {
            showToast('❌ ' + (data.error || 'Error al enviar.'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de red: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar';
    }
}

// ================================================================
//  EMAILS ENVIADOS
// ================================================================
function cargarEnviados() {
    document.getElementById('sent-container').innerHTML =
        '<div class="email-spinner"><div class="spinner-ring"></div> Cargando enviados…</div>';

    fetch(BASE + 'emails_ajax.php?action=enviados&alias=' + aliasActual)
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !data.enviados) {
                document.getElementById('sent-container').innerHTML =
                    '<div class="email-spinner" style="color:#ef4444;">Error al cargar enviados.</div>';
                return;
            }
            renderizarEnviados(data.enviados);
        })
        .catch(() => {
            document.getElementById('sent-container').innerHTML =
                '<div class="email-spinner" style="color:#ef4444;">Error de red.</div>';
        });
}

function renderizarEnviados(enviados) {
    const cont = document.getElementById('sent-container');
    if (!enviados || enviados.length === 0) {
        cont.innerHTML = '<div class="email-spinner" style="padding:1.5rem;">No hay emails enviados desde este alias.</div>';
        return;
    }

    cont.innerHTML = `
        <table class="table-sent">
            <thead>
                <tr>
                    <th>Destinatario</th>
                    <th>Asunto</th>
                    <th>Fecha</th>
                    <th>Respuesta a</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                ${enviados.map(e => `
                <tr>
                    <td>${escHtml(e.destinatario)}</td>
                    <td>${escHtml(e.asunto)}</td>
                    <td style="white-space:nowrap;">${formatFecha(e.fecha_envio)}</td>
                    <td style="font-size:0.78rem;color:#64748b;">${e.en_respuesta_a ? escHtml(e.en_respuesta_a) : '—'}</td>
                    <td><span style="background:rgba(16,185,129,0.15);color:#10b981;padding:2px 8px;border-radius:4px;font-size:0.75rem;">${escHtml(e.estado)}</span></td>
                </tr>`).join('')}
            </tbody>
        </table>
    `;
}

// ================================================================
//  BADGES (no leídos en pestañas)
// ================================================================
function cargarBadges() {
    fetch(BASE + 'emails_ajax.php?action=badges&alias=info')
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !data.badges) return;
            Object.entries(data.badges).forEach(([alias, count]) => {
                const badge = document.getElementById('badge-' + alias);
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                }
            });
        })
        .catch(() => {}); // silencioso
}

// ================================================================
//  TOAST
// ================================================================
function showToast(msg, tipo = 'success') {
    const t = document.getElementById('email-toast');
    t.textContent = msg;
    t.className   = 'email-toast ' + tipo + ' show';
    setTimeout(() => { t.classList.remove('show'); }, 4000);
}

// ================================================================
//  ERROR IMAP BANNER
// ================================================================
function mostrarErrorImap(msg) {
    const b = document.getElementById('imap-error-banner');
    document.getElementById('imap-error-text').textContent = '⚠️ IMAP: ' + msg;
    b.classList.add('visible');
}

function ocultarErrorImap() {
    document.getElementById('imap-error-banner').classList.remove('visible');
}

// ================================================================
//  HELPERS
// ================================================================
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatFecha(fecha) {
    if (!fecha) return '—';
    const d = new Date(fecha.replace(' ', 'T'));
    if (isNaN(d)) return fecha;
    const hoy = new Date();
    const esHoy = d.toDateString() === hoy.toDateString();
    if (esHoy) {
        return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
}

function strip(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}
</script>

<?php include('../../includes/footer.php'); ?>
