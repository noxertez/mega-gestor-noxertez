<?php
/**
 * pages/chatbot_admin.php
 * Panel de configuración del Chatbot Noxertez (solo admin)
 * Permite: activar/desactivar widget público, editar textos, ver logs
 */
define('ALLOWED_ACCESS', true);

require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';

require_once __DIR__ . '/../api/config.php';

// Verificar que es admin
try {
    $pdo_check = conectar();
    $stmtRoleCheck = $pdo_check->prepare("SELECT role FROM user_currencies WHERE account_id = ?");
    $stmtRoleCheck->execute([$_SESSION['user_id'] ?? 0]);
    $roleCheck = $stmtRoleCheck->fetchColumn();
    if (!in_array($roleCheck, ['admin', 'moderator'])) {
        header('Location: ' . $base_path);
        exit();
    }
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}

$page_class = "chatbot_admin";
require_once $project_root . 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Chatbot — Panel de Gestión Noxertez</title>
    <style>
        :root {
            --cab-bg: #0d1117;
            --cab-surface: #161b22;
            --cab-surface2: #1c2330;
            --cab-border: rgba(99,102,241,0.25);
            --cab-accent: #818cf8;
            --cab-gold: #c9a84c;
            --cab-text: #c9d1d9;
            --cab-muted: #6e7681;
            --cab-green: #3fb950;
            --cab-red: #f85149;
            --cab-yellow: #f0c26e;
        }

        body {
            background: var(--cab-bg);
            color: var(--cab-text);
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }

        .cab-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        .cab-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .cab-nav {
            display: flex;
            gap: 10px;
        }

        .cab-nav-link {
            font-size: 0.8rem;
            color: var(--cab-accent);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            background: rgba(129,140,248,0.1);
            transition: all 0.2s;
        }

        .cab-nav-link:hover {
            background: rgba(129,140,248,0.2);
            transform: translateY(-1px);
        }

        .cab-page-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--cab-accent);
            margin: 0;
        }

        .cab-page-subtitle {
            font-size: 0.87rem;
            color: var(--cab-muted);
            margin: 4px 0 0;
        }

        /* === TOGGLE GRANDE === */
        .cab-toggle-card {
            background: var(--cab-surface);
            border: 1px solid var(--cab-border);
            border-radius: 14px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            transition: border-color 0.3s;
        }

        .cab-toggle-card.is-active {
            border-color: rgba(63, 185, 80, 0.4);
            box-shadow: 0 0 20px rgba(63, 185, 80, 0.08);
        }

        .cab-toggle-info { flex: 1; }
        .cab-toggle-title {
            font-size: 1rem;
            font-weight: 600;
            color: #e6edf3;
            margin: 0 0 4px;
        }
        .cab-toggle-desc {
            font-size: 0.82rem;
            color: var(--cab-muted);
            margin: 0;
        }

        .cab-switch {
            position: relative;
            width: 56px;
            height: 30px;
            flex-shrink: 0;
        }

        .cab-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .cab-switch-slider {
            position: absolute;
            inset: 0;
            background: var(--cab-surface2);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cab-switch-slider::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            left: 3px;
            top: 3px;
            background: #8b949e;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .cab-switch input:checked + .cab-switch-slider {
            background: var(--cab-green);
            border-color: var(--cab-green);
        }

        .cab-switch input:checked + .cab-switch-slider::before {
            transform: translateX(26px);
            background: #fff;
        }

        /* === SECCIÓN DE CONFIGURACIÓN === */
        .cab-section {
            background: var(--cab-surface);
            border: 1px solid var(--cab-border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .cab-section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--cab-accent);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--cab-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cab-field {
            margin-bottom: 18px;
        }

        .cab-field:last-child { margin-bottom: 0; }

        .cab-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #8b949e;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .cab-input,
        .cab-textarea {
            width: 100%;
            background: var(--cab-surface2);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            color: var(--cab-text);
            font-family: inherit;
            font-size: 0.9rem;
            padding: 10px 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
            outline: none;
        }

        .cab-textarea {
            resize: vertical;
            min-height: 80px;
            line-height: 1.5;
        }

        .cab-input:focus,
        .cab-textarea:focus {
            border-color: rgba(129,140,248,0.5);
        }

        /* === BOTÓN GUARDAR === */
        .cab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .cab-btn-primary {
            background: linear-gradient(135deg, #4f46e5, #2563eb);
            color: #fff;
            box-shadow: 0 2px 10px rgba(79,70,229,0.3);
        }

        .cab-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(79,70,229,0.5);
        }

        .cab-btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* === TOAST / NOTIFICACIÓN === */
        #cab-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #238636;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            transform: translateY(80px);
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 9999;
        }

        #cab-toast.is-visible {
            transform: translateY(0);
            opacity: 1;
        }

        #cab-toast.is-error { background: #da3633; }

        /* === LOGS === */
        .cab-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .cab-stat {
            background: var(--cab-surface2);
            border: 1px solid var(--cab-border);
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }

        .cab-stat-num {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--cab-accent);
            line-height: 1;
        }

        .cab-stat-label {
            font-size: 0.72rem;
            color: var(--cab-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 5px;
        }

        .cab-logs-filter {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .cab-filter-btn {
            padding: 5px 14px;
            border: 1px solid var(--cab-border);
            border-radius: 20px;
            background: transparent;
            color: var(--cab-muted);
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .cab-filter-btn.active,
        .cab-filter-btn:hover {
            background: rgba(129,140,248,0.15);
            border-color: var(--cab-accent);
            color: var(--cab-accent);
        }

        .cab-logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .cab-logs-table th {
            text-align: left;
            color: var(--cab-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.72rem;
            padding: 8px 12px;
            border-bottom: 1px solid var(--cab-border);
        }

        .cab-logs-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            color: var(--cab-text);
            vertical-align: top;
        }

        .cab-logs-table tr:hover td { background: rgba(255,255,255,0.02); }

        .cab-intent-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .cab-intent-stock    { background: rgba(63,185,80,0.15); color: #3fb950; }
        .cab-intent-precio   { background: rgba(240,194,110,0.15); color: #f0c26e; }
        .cab-intent-envio    { background: rgba(56,189,248,0.15); color: #38bdf8; }
        .cab-intent-catalogo { background: rgba(168,85,247,0.15); color: #c084fc; }
        .cab-intent-otro     { background: rgba(139,148,158,0.15); color: #8b949e; }

        .cab-wa-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #25d366;
        }

        .cab-empty-logs {
            text-align: center;
            padding: 40px;
            color: var(--cab-muted);
        }

        .cab-empty-logs .cab-empty-icon { font-size: 2.5rem; margin-bottom: 10px; }

        /* === KB EDITOR === */
        #kb-form-container {
            display: none;
            background: var(--cab-surface2);
            border: 1px solid var(--cab-accent);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .kb-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .kb-table th { text-align: left; padding: 12px; color: var(--cab-muted); border-bottom: 1px solid var(--cab-border); }
        .kb-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .kb-cat { font-weight: 700; color: var(--cab-gold); font-size: 0.75rem; text-transform: uppercase; }
        
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; border-radius: 4px; border: none; cursor: pointer; }
        .btn-edit { background: var(--cab-accent); color: white; margin-right: 5px; }
        .btn-delete { background: var(--cab-red); color: white; }
    </style>
</head>
<body>
<main>
<div class="cab-container">
    <!-- HEADER -->
    <div class="cab-page-header">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="font-size:2rem;">🤖</div>
            <div>
                <h1 class="cab-page-title">Panel del Chatbot</h1>
                <p class="cab-page-subtitle">Configura el Asistente Noxertez y gestiona la base de conocimiento</p>
            </div>
        </div>
        <nav class="cab-nav">
            <a href="#cfg-textos" class="cab-nav-link">💬 Textos</a>
            <a href="#logs-seccion" class="cab-nav-link">📊 Logs</a>
            <a href="#kb-seccion" class="cab-nav-link">🧠 Base de Conocimiento</a>
        </nav>
    </div>

    <!-- TOGGLE PRINCIPAL -->
    <div class="cab-toggle-card" id="cab-toggle-card">
        <div class="cab-toggle-info">
            <p class="cab-toggle-title">Widget Público en noxertez.com</p>
            <p class="cab-toggle-desc">Cuando está activo, el widget de chat aparece en todas las páginas públicas para los visitantes. Si lo desactivas, desaparece completamente.</p>
        </div>
        <label class="cab-switch" title="Activar/desactivar widget público">
            <input type="checkbox" id="cab-toggle-activo" aria-label="Activar widget público">
            <span class="cab-switch-slider"></span>
        </label>
    </div>

    <!-- CONFIGURACIÓN DE TEXTOS -->
    <div class="cab-section" id="cfg-textos">
        <h2 class="cab-section-title">💬 Textos y Respuestas Configurables</h2>

        <div class="cab-field">
            <label class="cab-label" for="cfg-saludo">Mensaje de bienvenida del bot</label>
            <textarea class="cab-textarea" id="cfg-saludo" data-key="saludo_bienvenida" rows="3"
                placeholder="¡Hola! 👋 Soy el Asistente Noxertez..."></textarea>
        </div>

        <div class="cab-field">
            <label class="cab-label" for="cfg-tiempo">Texto de tiempo de envío</label>
            <textarea class="cab-textarea" id="cfg-tiempo" data-key="tiempo_envio" rows="2"
                placeholder="Los pedidos estándar tardan entre 3 y 5 días hábiles..."></textarea>
        </div>

        <div class="cab-field">
            <label class="cab-label" for="cfg-zonas">Zonas de envío</label>
            <textarea class="cab-textarea" id="cfg-zonas" data-key="zonas_envio" rows="2"
                placeholder="Enviamos a toda España mediante Packlink..."></textarea>
        </div>

        <div class="cab-field">
            <label class="cab-label" for="cfg-precio-envio">Precio y condiciones del envío</label>
            <textarea class="cab-textarea" id="cfg-precio-envio" data-key="precio_envio" rows="2"
                placeholder="El envío estándar cuesta 4,99€. Envío gratis a partir de 50€..."></textarea>
        </div>

        <div class="cab-field">
            <label class="cab-label" for="cfg-horario">Horario de atención</label>
            <textarea class="cab-textarea" id="cfg-horario" data-key="horario_atencion" rows="2"
                placeholder="Lunes a viernes de 9:00 a 20:00h..."></textarea>
        </div>

        <div style="margin-top: 20px; display:flex; gap:12px; align-items:center;">
            <button class="cab-btn cab-btn-primary" id="cab-btn-save">
                💾 Guardar cambios
            </button>
            <span id="cab-save-status" style="font-size:0.82rem; color:#6e7681;"></span>
        </div>
    </div>

    <!-- LOGS DE PREGUNTAS -->
    <div class="cab-section" id="logs-seccion">
        <h2 class="cab-section-title">📊 Logs de Preguntas Públicas</h2>

        <div class="cab-stats-grid" id="cab-stats-grid">
            <div class="cab-stat">
                <div class="cab-stat-num" id="stat-total">—</div>
                <div class="cab-stat-label">Total consultas</div>
            </div>
            <div class="cab-stat">
                <div class="cab-stat-num" id="stat-stock">—</div>
                <div class="cab-stat-label">Stock</div>
            </div>
            <div class="cab-stat">
                <div class="cab-stat-num" id="stat-precio">—</div>
                <div class="cab-stat-label">Precio</div>
            </div>
            <div class="cab-stat">
                <div class="cab-stat-num" id="stat-envio">—</div>
                <div class="cab-stat-label">Envíos</div>
            </div>
            <div class="cab-stat">
                <div class="cab-stat-num" id="stat-otro">—</div>
                <div class="cab-stat-label">Otros</div>
            </div>
        </div>

        <div class="cab-logs-filter">
            <button class="cab-filter-btn active" data-tipo="">Todos</button>
            <button class="cab-filter-btn" data-tipo="stock">Stock</button>
            <button class="cab-filter-btn" data-tipo="precio">Precio</button>
            <button class="cab-filter-btn" data-tipo="envio">Envíos</button>
            <button class="cab-filter-btn" data-tipo="catalogo">Catálogo</button>
            <button class="cab-filter-btn" data-tipo="otro">Otros</button>
        </div>

        <div id="cab-logs-wrapper">
            <div class="cab-empty-logs">
                <div class="cab-empty-icon">📋</div>
                <p>Cargando logs...</p>
            </div>
        </div>
    </div>

    <!-- GESTIÓN DE BASE DE CONOCIMIENTO (NUEVO) -->
    <div class="cab-section" id="kb-seccion">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 class="cab-section-title" style="margin:0; border:none; padding:0;">🧠 Base de Conocimiento (Q&A)</h2>
            <button class="cab-btn cab-btn-primary" style="padding:6px 14px;" onclick="nuevoKB()">+ Añadir Pregunta</button>
        </div>

        <p class="cab-toggle-desc" style="margin-bottom:20px;">Estas preguntas y respuestas se usan para el modo offline y como guía para la IA. Se prioriza el matching por palabras clave.</p>

        <!-- Formulario oculto -->
        <div id="kb-form-container">
            <h3 id="kb-form-title" style="font-size:1rem; margin-top:0;">Añadir nueva Q&A</h3>
            <input type="hidden" id="kb-id">
            <div class="cab-field">
                <label class="cab-label">Categoría</label>
                <input type="text" id="kb-categoria" class="cab-input" placeholder="Ej: ENVÍOS">
            </div>
            <div class="cab-field">
                <label class="cab-label">Pregunta</label>
                <textarea id="kb-pregunta" class="cab-textarea" rows="2"></textarea>
            </div>
            <div class="cab-field">
                <label class="cab-label">Respuesta</label>
                <textarea id="kb-respuesta" class="cab-textarea" rows="4"></textarea>
            </div>
            <div class="cab-field">
                <label class="cab-label">Palabras clave (separadas por coma)</label>
                <input type="text" id="kb-keywords" class="cab-input" placeholder="envio, tiempo, dias, tardar">
                <small style="color:var(--cab-muted); font-size:0.7rem;">Ayudan al bot a encontrar la respuesta correcta en modo offline.</small>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="cab-btn cab-btn-primary" onclick="guardarKB()">Guardar</button>
                <button class="cab-btn" style="background:#444; color:white;" onclick="cancelarKB()">Cancelar</button>
            </div>
        </div>

        <div id="kb-list-wrapper">
            <div class="cab-empty-logs">
                <p>Cargando base de conocimiento...</p>
            </div>
        </div>
    </div>
</div>
</main>

<!-- Toast de notificación -->
<div id="cab-toast"></div>

<?php
$footer_file = $project_root . 'includes/footer.php';
if (file_exists($footer_file)) include $footer_file;
?>

<script>
(function () {
    const BASE    = '<?= htmlspecialchars($base_path) ?>';
    const API     = BASE + 'api/chatbot_api.php';
    let currentTipo = '';

    // ============================================================
    // CARGAR CONFIGURACIÓN INICIAL
    // ============================================================
    async function loadConfig() {
        try {
            const res  = await fetch(API + '?accion=get_config');
            const data = await res.json();
            if (!data.ok) return;

            const cfg = data.config;

            // Toggle activo
            const toggle = document.getElementById('cab-toggle-activo');
            toggle.checked = cfg.chatbot_activo === '1';
            updateToggleCard(toggle.checked);

            // Rellenar campos de texto
            document.querySelectorAll('[data-key]').forEach(el => {
                const key = el.dataset.key;
                if (cfg[key] !== undefined) el.value = cfg[key];
            });
        } catch (e) {
            showToast('Error al cargar la configuración', true);
        }
    }

    // ============================================================
    // TOGGLE ACTIVO
    // ============================================================
    document.getElementById('cab-toggle-activo').addEventListener('change', async function () {
        const val = this.checked ? '1' : '0';
        updateToggleCard(this.checked);
        try {
            await fetch(API + '?accion=save_config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chatbot_activo: val })
            });
            showToast(this.checked ? '✅ Widget público activado' : '🔴 Widget público desactivado');
        } catch (e) {
            showToast('Error al guardar', true);
        }
    });

    function updateToggleCard(isActive) {
        const card = document.getElementById('cab-toggle-card');
        if (isActive) {
            card.classList.add('is-active');
        } else {
            card.classList.remove('is-active');
        }
    }

    // ============================================================
    // GUARDAR CONFIGURACIÓN DE TEXTOS
    // ============================================================
    document.getElementById('cab-btn-save').addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        btn.textContent = '⏳ Guardando...';

        const payload = {};
        document.querySelectorAll('[data-key]').forEach(el => {
            payload[el.dataset.key] = el.value;
        });

        try {
            const res  = await fetch(API + '?accion=save_config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                showToast('✅ Configuración guardada correctamente');
            } else {
                showToast('Error al guardar la configuración', true);
            }
        } catch (e) {
            showToast('Error de conexión', true);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '💾 Guardar cambios';
        }
    });

    // ============================================================
    // CARGAR LOGS
    // ============================================================
    async function loadLogs(tipo = '') {
        currentTipo = tipo;
        const wrapper = document.getElementById('cab-logs-wrapper');
        wrapper.innerHTML = '<div class="cab-empty-logs"><div class="cab-empty-icon">⏳</div><p>Cargando...</p></div>';

        try {
            const url  = API + '?accion=get_logs&limite=200' + (tipo ? '&tipo=' + tipo : '');
            const res  = await fetch(url);
            const data = await res.json();

            // Actualizar estadísticas
            if (data.stats) {
                const totals = { total: 0, stock: 0, precio: 0, envio: 0, otro: 0 };
                data.stats.forEach(s => {
                    totals.total += parseInt(s.total);
                    if (['stock', 'precio', 'envio'].includes(s.tipo_intent)) {
                        totals[s.tipo_intent] += parseInt(s.total);
                    } else {
                        totals.otro += parseInt(s.total);
                    }
                });
                document.getElementById('stat-total').textContent  = totals.total;
                document.getElementById('stat-stock').textContent  = totals.stock;
                document.getElementById('stat-precio').textContent = totals.precio;
                document.getElementById('stat-envio').textContent  = totals.envio;
                document.getElementById('stat-otro').textContent   = totals.otro;
            }

            if (!data.logs || data.logs.length === 0) {
                wrapper.innerHTML = `
                    <div class="cab-empty-logs">
                        <div class="cab-empty-icon">🗂️</div>
                        <p>No hay logs registrados${tipo ? ' para este filtro' : ''}.</p>
                        <p style="font-size:0.8rem;color:#6e7681;">Los logs se registran cuando los clientes usan el chatbot público.</p>
                    </div>`;
                return;
            }

            const rows = data.logs.map(log => {
                const intentClass = 'cab-intent-' + (log.tipo_intent || 'otro');
                const fecha = new Date(log.created_at).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                const waIcon = log.whatsapp_btn == 1 ? '<span class="cab-wa-dot" title="Mostró botón WhatsApp"></span>' : '';
                return `
                    <tr>
                        <td>${escapeHtml(log.pregunta)}</td>
                        <td><span class="cab-intent-badge ${intentClass}">${log.tipo_intent || 'otro'}</span></td>
                        <td>${log.producto_ref ? `<code style="font-size:0.75rem;color:#8b949e;">${escapeHtml(log.producto_ref)}</code>` : '—'}</td>
                        <td style="text-align:center;">${waIcon}</td>
                        <td style="white-space:nowrap;color:#6e7681;">${fecha}</td>
                    </tr>`;
            }).join('');

            wrapper.innerHTML = `
                <table class="cab-logs-table">
                    <thead>
                        <tr>
                            <th>Pregunta</th>
                            <th>Tipo</th>
                            <th>Producto ref.</th>
                            <th>WA</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>`;
        } catch (e) {
            wrapper.innerHTML = '<div class="cab-empty-logs"><p>Error al cargar los logs.</p></div>';
        }
    }

    // Filtros
    document.querySelectorAll('.cab-filter-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cab-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadLogs(this.dataset.tipo || '');
        });
    });

    // ============================================================
    // TOAST
    // ============================================================
    function showToast(msg, isError = false) {
        const toast = document.getElementById('cab-toast');
        toast.textContent = msg;
        toast.className   = 'is-visible' + (isError ? ' is-error' : '');
        setTimeout(() => { toast.className = ''; }, 3500);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ============================================================
    // BASE DE CONOCIMIENTO (KB)
    // ============================================================
    let globalKB = [];

    async function loadKB() {
        const wrapper = document.getElementById('kb-list-wrapper');
        try {
            const res  = await fetch(API + '?accion=get_kb');
            const data = await res.json();
            if (!data.ok) throw new Error(data.error);

            globalKB = data.kb;
            renderKB();
        } catch (e) {
            wrapper.innerHTML = '<p style="color:red; padding:20px;">Error al cargar KB: ' + e.message + '</p>';
        }
    }

    function renderKB() {
        const wrapper = document.getElementById('kb-list-wrapper');
        if (globalKB.length === 0) {
            wrapper.innerHTML = '<div class="cab-empty-logs"><p>No hay preguntas registradas.</p></div>';
            return;
        }

        let html = `<table class="kb-table">
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Pregunta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>`;
        
        globalKB.forEach(item => {
            html += `<tr>
                <td class="kb-cat">${escapeHtml(item.categoria)}</td>
                <td>
                    <div style="font-weight:600; color:#e6edf3;">${escapeHtml(item.pregunta)}</div>
                    <div style="font-size:0.75rem; color:var(--cab-muted); margin-top:4px;">${escapeHtml(item.respuesta.substring(0, 100))}...</div>
                </td>
                <td style="white-space:nowrap;">
                    <button class="btn-sm btn-edit" onclick="editarKB(${item.id})">✏️</button>
                    <button class="btn-sm btn-delete" onclick="eliminarKB(${item.id})">🗑️</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        wrapper.innerHTML = html;
    }

    window.nuevoKB = function() {
        document.getElementById('kb-form-title').textContent = 'Añadir nueva Q&A';
        document.getElementById('kb-id').value = '';
        document.getElementById('kb-categoria').value = '';
        document.getElementById('kb-pregunta').value = '';
        document.getElementById('kb-respuesta').value = '';
        document.getElementById('kb-keywords').value = '';
        document.getElementById('kb-form-container').style.display = 'block';
        document.getElementById('kb-form-container').scrollIntoView({ behavior: 'smooth' });
    };

    window.editarKB = function(id) {
        const item = globalKB.find(i => i.id == id);
        if (!item) return;

        document.getElementById('kb-form-title').textContent = 'Editar Pregunta';
        document.getElementById('kb-id').value = item.id;
        document.getElementById('kb-categoria').value = item.categoria;
        document.getElementById('kb-pregunta').value = item.pregunta;
        document.getElementById('kb-respuesta').value = item.respuesta;
        document.getElementById('kb-keywords').value = item.keywords || '';
        document.getElementById('kb-form-container').style.display = 'block';
        document.getElementById('kb-form-container').scrollIntoView({ behavior: 'smooth' });
    };

    window.cancelarKB = function() {
        document.getElementById('kb-form-container').style.display = 'none';
    };

    window.guardarKB = async function() {
        const payload = {
            id: document.getElementById('kb-id').value,
            categoria: document.getElementById('kb-categoria').value,
            pregunta: document.getElementById('kb-pregunta').value,
            respuesta: document.getElementById('kb-respuesta').value,
            keywords: document.getElementById('kb-keywords').value
        };

        try {
            const res = await fetch(API + '?accion=save_kb', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                showToast('✅ KB actualizado');
                cancelarKB();
                loadKB();
            } else {
                showToast(data.error || 'Error al guardar', true);
            }
        } catch (e) {
            showToast('Error de conexión', true);
        }
    };

    window.eliminarKB = async function(id) {
        if (!confirm('¿Seguro que quieres eliminar esta pregunta?')) return;

        try {
            const res = await fetch(API + '?accion=delete_kb', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (data.ok) {
                showToast('🗑️ Pregunta eliminada');
                loadKB();
            }
        } catch (e) {
            showToast('Error al eliminar', true);
        }
    };

    // ============================================================
    // INICIALIZAR
    // ============================================================
    loadConfig();
    loadLogs();
    loadKB();
})();
</script>

</body>
</html>
