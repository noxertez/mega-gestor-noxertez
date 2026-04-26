<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);

require_once '../api/config.php';
$db = conectar();

// ── ESTADOS UNIFICADOS ─────────────────────────────────────
$estados_flujo = [
    'por_empezar'        => ['label' => 'Por Empezar',        'color' => '#6366f1', 'icon' => 'fa-inbox'],
    'en_proceso'         => ['label' => 'En Proceso',         'color' => '#f59e0b', 'icon' => 'fa-hammer'],
    'montado'            => ['label' => 'Montado',            'color' => '#06b6d4', 'icon' => 'fa-puzzle-piece'],
    'tintado'            => ['label' => 'Tintado',            'color' => '#ec4899', 'icon' => 'fa-paint-roller'],
    'barnizado'          => ['label' => 'Barnizado',          'color' => '#84cc16', 'icon' => 'fa-shield-halved'],
    'listo_para_entregar'=> ['label' => 'Listo para Entregar','color' => '#fb923c', 'icon' => 'fa-box'],
    'entregado'          => ['label' => 'Entregado',          'color' => '#10b981', 'icon' => 'fa-circle-check'],
    'cancelado'          => ['label' => 'Cancelado',          'color' => '#ef4444', 'icon' => 'fa-ban'],
];

// Plantillas disponibles para el selector
$plantillas = $db->query("SELECT id, nombre FROM flujo_plantillas WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Pedidos NO entregados para el dropdown
$pedidos_lista = $db->query("
    SELECT p.id, p.numero_pedido, p.estado,
           COALESCE(c.nombre, p.nombre_cliente, 'Sin cliente') AS cliente_nombre
    FROM pedidos p
    LEFT JOIN clientes c ON p.id_cliente = c.id
    WHERE p.estado NOT IN ('entregado','cancelado')
    ORDER BY p.id DESC
    LIMIT 200
")->fetchAll();

$id_pedido_sel = isset($_GET['id']) ? (int)$_GET['id'] : ($pedidos_lista[0]['id'] ?? 0);

$page_class = 'management-page';
require_once '../includes/session.php';
include('../includes/header.php');
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.1">
<link rel="stylesheet" href="<?= $base_path ?>assets/css/flujo_pedidos.css?v=1.0">
<!-- Drawflow.js CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.css">
<script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.js"></script>

<div class="flujo-app" id="flujoApp">

  <!-- ══ BARRA SUPERIOR ══════════════════════════════════════════════ -->
  <div class="flujo-topbar">
    <div class="flujo-topbar-left">
      <h1><i class="fas fa-diagram-project"></i> Flujo de Pedidos</h1>
      <select id="selectorPedido" class="flujo-select" onchange="cargarFlujo(this.value)">
        <option value="">— Selecciona Pedido —</option>
        <?php foreach($pedidos_lista as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $p['id']==$id_pedido_sel?'selected':'' ?>>
            #<?= $p['id'] ?> — <?= htmlspecialchars($p['cliente_nombre']) ?> (<?= $p['estado'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flujo-topbar-right">
      <button class="flujo-btn flujo-btn-blue" onclick="abrirVistaMult()">
        <i class="fas fa-th-large"></i> Vista Multi-Pedido
      </button>
      <button class="flujo-btn flujo-btn-amber" onclick="abrirAnalitica()">
        <i class="fas fa-chart-bar"></i> Analítica
      </button>
      <button class="flujo-btn flujo-btn-green" id="btnAsignarPlantilla" onclick="abrirAsignarPlantilla()">
        <i class="fas fa-plus-circle"></i> Asignar Plantilla
      </button>
    </div>
  </div>

  <!-- ══ LÍNEA DE TIEMPO ═════════════════════════════════════════════ -->
  <div class="flujo-timeline-bar" id="timelineBar" style="display:none">
    <span id="timelineDias">—</span>
    <span id="timelineAlerta"></span>
    <span id="timelineRestantes"></span>
  </div>

  <!-- ══ CUERPO PRINCIPAL ════════════════════════════════════════════ -->
  <div class="flujo-body">

    <!-- Drawflow Canvas -->
    <div class="flujo-canvas-wrap" id="drawflowWrap">
      <div id="drawflowCanvas"></div>
      <div id="flujoEmpty" class="flujo-empty-state">
        <i class="fas fa-diagram-project fa-4x"></i>
        <p>Selecciona un pedido para ver su flujo de producción</p>
      </div>
    </div>

    <!-- Panel Lateral de Detalle -->
    <div class="flujo-panel-lateral" id="panelLateral">
      <div class="panel-header">
        <h3 id="panelTituloNodo">Detalle del Nodo</h3>
        <button class="flujo-btn-icon" onclick="cerrarPanel()"><i class="fas fa-times"></i></button>
      </div>
      <div class="panel-body" id="panelBody">
        <!-- Cargado dinámicamente -->
      </div>
    </div>

  </div><!-- /flujo-body -->

</div><!-- /flujo-app -->

<!-- ══ MODAL: ASIGNAR PLANTILLA ════════════════════════════════════ -->
<div class="modal-overlay-wow" id="modalPlantilla" onclick="if(event.target==this)this.style.display='none'">
  <div class="modal-content-wow" style="max-width:500px">
    <div class="modal-header-wow">
      <h2><i class="fas fa-file-lines"></i> Asignar Plantilla de Flujo</h2>
      <button class="btn-premium-wow" onclick="document.getElementById('modalPlantilla').style.display='none'" style="background:none;color:var(--accent-gold);font-size:1.5rem">&times;</button>
    </div>
    <div style="padding:1.5rem">
      <p style="color:#94a3b8;margin-bottom:1rem">Esto generará todos los nodos del flujo para el pedido actual.</p>
      <div class="nox-form-group">
        <label>Plantilla de Producción</label>
        <select id="selPlantilla" class="nox-input" style="width:100%">
          <?php foreach($plantillas as $pt): ?>
            <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn-premium-wow btn-gold" style="width:100%;margin-top:1rem;justify-content:center" onclick="asignarPlantilla()">
        <i class="fas fa-bolt"></i> Generar Flujo
      </button>
    </div>
  </div>
</div>

<!-- ══ MODAL: VISTA MULTI-PEDIDO ═══════════════════════════════════ -->
<div class="modal-overlay-wow" id="modalMulti" onclick="if(event.target==this)this.style.display='none'" style="display:none">
  <div class="modal-content-wow" style="max-width:1100px;max-height:85vh;overflow-y:auto">
    <div class="modal-header-wow">
      <h2><i class="fas fa-th-large"></i> Carga de Trabajo Global</h2>
      <button class="btn-premium-wow" onclick="document.getElementById('modalMulti').style.display='none'" style="background:none;color:var(--accent-gold);font-size:1.5rem">&times;</button>
    </div>
    <div id="multiPedidoGrid" style="padding:1.5rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
      <div style="text-align:center;padding:2rem;opacity:0.5">Cargando...</div>
    </div>
  </div>
</div>

<!-- ══ MODAL: ANALÍTICA ════════════════════════════════════════════ -->
<div class="modal-overlay-wow" id="modalAnalitica" onclick="if(event.target==this)this.style.display='none'" style="display:none">
  <div class="modal-content-wow" style="max-width:800px;max-height:85vh;overflow-y:auto">
    <div class="modal-header-wow">
      <h2><i class="fas fa-chart-bar"></i> Analítica de Flujos</h2>
      <button class="btn-premium-wow" onclick="document.getElementById('modalAnalitica').style.display='none'" style="background:none;color:var(--accent-gold);font-size:1.5rem">&times;</button>
    </div>
    <div id="analiticaBody" style="padding:1.5rem"></div>
  </div>
</div>

<!-- ══ MODAL: INCIDENCIA ═══════════════════════════════════════════ -->
<div class="modal-overlay-wow" id="modalIncidencia" onclick="if(event.target==this)this.style.display='none'" style="display:none">
  <div class="modal-content-wow" style="max-width:480px">
    <div class="modal-header-wow">
      <h2><i class="fas fa-triangle-exclamation" style="color:#ef4444"></i> Registrar Incidencia</h2>
      <button class="btn-premium-wow" onclick="document.getElementById('modalIncidencia').style.display='none'" style="background:none;color:var(--accent-gold);font-size:1.5rem">&times;</button>
    </div>
    <div style="padding:1.5rem">
      <input type="hidden" id="incNodoId">
      <div class="nox-form-group">
        <label>Tipo de Incidencia</label>
        <select id="incTipo" class="nox-input" style="width:100%">
          <option value="rotura">🔨 Rotura / Defecto</option>
          <option value="reclamacion">💬 Reclamación Cliente</option>
          <option value="retraso">⏰ Retraso</option>
          <option value="material">📦 Falta de Material</option>
          <option value="otro">❓ Otro</option>
        </select>
      </div>
      <div class="nox-form-group">
        <label>Descripción</label>
        <textarea id="incDesc" class="nox-input" style="width:100%;height:80px" placeholder="Describe la incidencia..."></textarea>
      </div>
      <button class="btn-premium-wow btn-red" style="width:100%;justify-content:center" onclick="guardarIncidencia()">
        <i class="fas fa-save"></i> Registrar Incidencia
      </button>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════════════════════════════════
// ESTADO GLOBAL
// ══════════════════════════════════════════════════════════
const BASE = '<?= $base_path ?>';
let dfEditor = null;         // Instancia Drawflow
let flujoData = null;        // Datos del flujo actual
let pedidoActualId = <?= $id_pedido_sel ?: 'null' ?>;

// ══════════════════════════════════════════════════════════
// INIT DRAWFLOW
// ══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Drawflow === 'undefined') {
        alert("CRÍTICO: La librería Drawflow no se ha cargado. Verifica tu conexión o revisa los archivos locales.");
        console.error("Drawflow is undefined. Check CDN/Include.");
        return;
    }

    const container = document.getElementById('drawflowCanvas');
    if (!container) {
        console.error("Container #drawflowCanvas not found.");
        return;
    }

    dfEditor = new Drawflow(container);
    dfEditor.reroute = true;
    dfEditor.reroute_fix_curvature = true;
    dfEditor.curvature = 0.4;
    dfEditor.start();

    // Soporte para móviles: abrir panel al seleccionar nodo
    dfEditor.on('nodeSelected', (id) => {
        const node = dfEditor.getNodeFromId(id);
        if (node && node.data && node.data.nodo_id) {
            abrirPanelNodo(node.data.nodo_id);
        }
    });

    // Hacer de solo lectura (no mover nodos por accidente)
    dfEditor.editor_mode = 'view';

    if (pedidoActualId) cargarFlujo(pedidoActualId);
    else mostrarDashboard(); // Mostrar todos los pedidos si no hay uno seleccionado
});

// Nueva función Dashboard
async function mostrarDashboard() {
    document.getElementById('flujoEmpty').style.display = 'none';
    abrirVistaMult();
}

// ══════════════════════════════════════════════════════════
// CARGAR FLUJO DE UN PEDIDO
// ══════════════════════════════════════════════════════════
async function cargarFlujo(id_pedido) {
    if (!id_pedido) return;
    pedidoActualId = id_pedido;

    // Actualizar selector
    document.getElementById('selectorPedido').value = id_pedido;

    try {
        console.log("Cargando flujo para pedido:", id_pedido);
        const res = await fetch(`${BASE}api/index.php?ruta=flujo&id_pedido=${id_pedido}`);
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        
        const text = await res.text();
        try {
            flujoData = JSON.parse(text);
        } catch (je) {
            console.error("Response is not JSON:", text);
            throw new Error("La API no devolvió un JSON válido.");
        }

        if (flujoData.error || !flujoData.nodos || flujoData.nodos.length === 0) {
            // Si el pedido no tiene flujo, mostrar estado vacío con botón
            mostrarVacio(id_pedido);
            return;
        }

        document.getElementById('flujoEmpty').style.display = 'none';
        renderDiagrama(flujoData);
        actualizarTimeline(flujoData);
    } catch (e) {
        console.error("Error cargando flujo:", e);
        document.getElementById('flujoEmpty').innerHTML = `<p style="color:#ef4444">Error al cargar datos: ${e.message}</p>`;
        document.getElementById('flujoEmpty').style.display = 'flex';
    }
}

// ══════════════════════════════════════════════════════════
// RENDERIZAR DIAGRAMA CON DRAWFLOW
// ══════════════════════════════════════════════════════════
function renderDiagrama(data) {
    dfEditor.clear();

    const nodos = data.nodos;
    if (!nodos || nodos.length === 0) {
        mostrarVacio(pedidoActualId);
        return;
    }

    const ANCHO_NODO = 200;
    const ALTO_NODO  = 120;
    const GAP_X      = 240;
    const GAP_Y      = 180;
    const INICIO_X   = 80;
    const INICIO_Y   = 80;

    // Separar nodos principales e incidencias
    const nodosPrincipales = nodos.filter(n => n.tipo === 'nodo');
    const nodosIncidencias = nodos.filter(n => n.tipo === 'incidencia');

    const idMap = {}; // id_nodo_plantilla → id en Drawflow

    // Renderizar nodos principales en línea horizontal (con salto automático)
    const COLS_MAX = 5;
    nodosPrincipales.forEach((nodo, idx) => {
        const col = idx % COLS_MAX;
        const row = Math.floor(idx / COLS_MAX);
        const x = INICIO_X + col * GAP_X;
        const y = INICIO_Y + row * GAP_Y;

        const html = crearHtmlNodo(nodo, data);
        const dfId = dfEditor.addNode(
            `nodo_${nodo.id_nodo_plantilla}`,
            idx > 0 ? 1 : 0,  // inputs: 0 para el primero
            nodo.orden === nodosPrincipales[nodosPrincipales.length-1].orden ? 0 : 1, // outputs: 0 para el último
            x, y,
            `flujo-nodo flujo-nodo-${nodo.estado}${nodo.incidencias_abiertas > 0 ? ' flujo-nodo-incidencia' : ''}`,
            { nodo_id: nodo.id, pedido_id: pedidoActualId },
            html
        );
        idMap[nodo.id_nodo_plantilla] = dfId;
    });

    // Conectar nodos principales en secuencia
    for (let i = 0; i < nodosPrincipales.length - 1; i++) {
        const n1 = nodosPrincipales[i];
        const n2 = nodosPrincipales[i + 1];
        const id1 = idMap[n1.id_nodo_plantilla];
        const id2 = idMap[n2.id_nodo_plantilla];
        if (id1 && id2) {
            dfEditor.addConnection(id1, id2, 'output_1', 'input_1');
        }
    }

    // Renderizar nodos de incidencia (rama verde/roja lateral)
    nodosIncidencias.forEach((nodo, idx) => {
        const x = INICIO_X + (COLS_MAX + 1) * GAP_X;
        const y = INICIO_Y + idx * GAP_Y;
        const html = crearHtmlNodo(nodo, data);
        const dfId = dfEditor.addNode(
            `nodo_inc_${nodo.id_nodo_plantilla}`,
            1, 0, x, y,
            'flujo-nodo flujo-nodo-tipo-incidencia',
            { nodo_id: nodo.id },
            html
        );
        // Conectar con el nodo más cercano (bloqueado o en curso)
        const nodeConBloq = nodosPrincipales.find(n => n.incidencias_abiertas > 0 || n.estado === 'bloqueado');
        if (nodeConBloq && idMap[nodeConBloq.id_nodo_plantilla]) {
            dfEditor.addConnection(idMap[nodeConBloq.id_nodo_plantilla], dfId, 'output_1', 'input_1');
        }
    });

    // Ajustar zoom
    dfEditor.zoom_reset();

    // Bind clicks en los nodos
    bindNodoClicks();
}

function crearHtmlNodo(nodo, data) {
    const estadoLabel = { pendiente: 'Pendiente', en_curso: 'En Curso', completado: 'Completado', bloqueado: '⚠️ Bloqueado' };
    const estadoClass = { pendiente: 'badge-gray', en_curso: 'badge-blue', completado: 'badge-green', bloqueado: 'badge-red' };

    let progresoPct = 0;
    if (nodo.estado === 'completado') progresoPct = 100;
    else if (nodo.estado === 'en_curso' && nodo.tiempo_estimado_min > 0) {
        progresoPct = Math.min(100, Math.round((nodo.minutos_transcurridos / nodo.tiempo_estimado_min) * 100));
    }

    const tiempoInfo = nodo.tiempo_estimado_min > 0
        ? `<span class="nodo-tiempo">${nodo.estado==='completado' ? nodo.tiempo_real_minutos+'m real' : nodo.tiempo_estimado_min+'m est.'}</span>`
        : '';

    const incBadge = nodo.incidencias_abiertas > 0
        ? `<span class="nodo-inc-badge">${nodo.incidencias_abiertas} ⚠️</span>`
        : '';

    return `
        <div class="nodo-inner" data-nodo-id="${nodo.id}">
            <div class="nodo-icon" style="background:${nodo.color}">
                <i class="fas fa-${nodo.icono}"></i>
            </div>
            <div class="nodo-info">
                <div class="nodo-nombre">${escHtml(nodo.nombre)}${incBadge}</div>
                <span class="nodo-badge ${estadoClass[nodo.estado] || 'badge-gray'}">${estadoLabel[nodo.estado] || nodo.estado}</span>
                ${tiempoInfo}
            </div>
            <div class="nodo-progress">
                <div class="nodo-progress-bar" style="width:${progresoPct}%;background:${nodo.color}"></div>
            </div>
        </div>
    `;
}

function bindNodoClicks() {
    document.querySelectorAll('.nodo-inner').forEach(el => {
        el.style.cursor = 'pointer';
        
        // Listener para PC
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            const id = el.getAttribute('data-nodo-id');
            if (id) abrirPanelNodo(id);
        });

        // Listener para Móvil (Touch)
        el.addEventListener('touchstart', (e) => {
            // Nota: no usamos stopPropagation aquí para no romper el scroll del canvas
            const id = el.getAttribute('data-nodo-id');
            if (id) abrirPanelNodo(id);
        }, { passive: true });
    });
}

// ══════════════════════════════════════════════════════════
// TIMELINE BAR
// ══════════════════════════════════════════════════════════
function actualizarTimeline(data) {
    const bar = document.getElementById('timelineBar');
    bar.style.display = 'flex';

    document.getElementById('timelineDias').innerHTML =
        `<i class="fas fa-calendar-day"></i> Día <strong>${data.dias_desde_pedido}</strong> del pedido`;

    const restEl = document.getElementById('timelineRestantes');
    if (data.dias_restantes !== null) {
        if (data.dias_restantes < 0) {
            restEl.innerHTML = `<span class="badge-red"><i class="fas fa-fire"></i> ¡Entrega VENCIDA hace ${Math.abs(data.dias_restantes)} días!</span>`;
        } else if (data.dias_restantes <= 2) {
            restEl.innerHTML = `<span class="badge-amber"><i class="fas fa-exclamation-triangle"></i> Entrega en ${data.dias_restantes}d — ¡URGENTE!</span>`;
        } else {
            restEl.innerHTML = `<span class="badge-green"><i class="fas fa-check"></i> Entrega en ${data.dias_restantes} días</span>`;
        }
    } else {
        restEl.innerHTML = '';
    }

    const alertEl = document.getElementById('timelineAlerta');
    alertEl.innerHTML = data.con_retraso
        ? `<span class="badge-red"><i class="fas fa-clock"></i> ${data.tiempo_real_min}m real vs ${data.tiempo_estimado_min}m est.</span>`
        : `<span class="badge-green"><i class="fas fa-check-circle"></i> En tiempo</span>`;
}

// ══════════════════════════════════════════════════════════
// PANEL LATERAL DE DETALLE DE NODO
// ══════════════════════════════════════════════════════════
async function abrirPanelNodo(idPedidoNodo) {
    const panel = document.getElementById('panelLateral');
    const body  = document.getElementById('panelBody');
    panel.classList.add('open');
    body.innerHTML = '<div style="text-align:center;padding:2rem;opacity:0.5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    // Buscar el nodo en flujoData
    const nodo = flujoData?.nodos?.find(n => n.id == idPedidoNodo);
    if (!nodo) { body.innerHTML = '<p>No encontrado</p>'; return; }

    document.getElementById('panelTituloNodo').innerHTML = `<i class="fas fa-${nodo.icono}" style="color:${nodo.color}"></i> ${escHtml(nodo.nombre)}`;

    const estadoOpts = ['pendiente','en_curso','completado','bloqueado'].map(e =>
        `<option value="${e}" ${nodo.estado===e?'selected':''}>${e}</option>`).join('');

    body.innerHTML = `
        <div class="panel-section">
            <label>Estado del Nodo</label>
            <select id="pnEstado" class="nox-input" style="width:100%" onchange="cambiarEstadoNodo(${nodo.id}, this.value)">
                ${estadoOpts}
            </select>
        </div>
        <div class="panel-section">
            <label>Tiempo Real (minutos)</label>
            <input type="number" id="pnTiempo" class="nox-input" style="width:100%" value="${nodo.tiempo_real_minutos||0}" min="0">
        </div>
        <div class="panel-section">
            <label>Tiempo estimado</label>
            <div class="nodo-stat">${nodo.tiempo_estimado_min} min</div>
        </div>
        ${nodo.fecha_inicio ? `<div class="panel-section"><label>Inicio</label><div class="nodo-stat">${nodo.fecha_inicio}</div></div>` : ''}
        ${nodo.fecha_fin   ? `<div class="panel-section"><label>Fin</label><div class="nodo-stat">${nodo.fecha_fin}</div></div>` : ''}
        <div class="panel-section">
            <label>📝 Notas del artesano</label>
            <textarea id="pnNotas" class="nox-input" style="width:100%;height:90px;resize:vertical">${escHtml(nodo.notas||'')}</textarea>
            <button class="flujo-btn flujo-btn-green" style="margin-top:6px;width:100%" onclick="guardarNotasNodo(${nodo.id})">
                <i class="fas fa-save"></i> Guardar Notas
            </button>
        </div>
        <div class="panel-section">
            <button class="flujo-btn flujo-btn-blue" style="width:100%"
                onclick="cambiarEstadoCompleto(${nodo.id})">
                <i class="fas fa-check-circle"></i> Marcar Completado
            </button>
        </div>
        ${nodo.incidencias_abiertas > 0 ? `<div class="panel-section badge-red" style="padding:8px;border-radius:6px">
            <i class="fas fa-exclamation-triangle"></i> ${nodo.incidencias_abiertas} incidencia(s) abierta(s)
        </div>` : ''}
        <div class="panel-section">
            <button class="flujo-btn flujo-btn-red" style="width:100%" onclick="abrirModalIncidencia(${nodo.id})">
                <i class="fas fa-triangle-exclamation"></i> Registrar Incidencia
            </button>
        </div>
    `;
}

function cerrarPanel() {
    document.getElementById('panelLateral').classList.remove('open');
}

// ══════════════════════════════════════════════════════════
// ACCIONES DE NODO
// ══════════════════════════════════════════════════════════
async function cambiarEstadoNodo(idNodo, estado) {
    const tiempo = parseInt(document.getElementById('pnTiempo')?.value || 0);
    await fetch(`${BASE}api/index.php?ruta=flujo_nodo`, {
        method: 'PUT',
        body: JSON.stringify({ id_pedido_nodo: idNodo, estado, tiempo_real_minutos: tiempo })
    });
    await cargarFlujo(pedidoActualId);
    abrirPanelNodo(idNodo);
}

async function cambiarEstadoCompleto(idNodo) {
    const tiempo = parseInt(document.getElementById('pnTiempo')?.value || 0);
    await fetch(`${BASE}api/index.php?ruta=flujo_nodo`, {
        method: 'PUT',
        body: JSON.stringify({ id_pedido_nodo: idNodo, estado: 'completado', tiempo_real_minutos: tiempo })
    });
    cerrarPanel();
    await cargarFlujo(pedidoActualId);
}

async function guardarNotasNodo(idNodo) {
    const notas = document.getElementById('pnNotas').value;
    await fetch(`${BASE}api/index.php?ruta=flujo_nodo`, {
        method: 'POST',
        body: JSON.stringify({ id_pedido_nodo: idNodo, notas })
    });
    const btn = event.target;
    btn.innerHTML = '<i class="fas fa-check"></i> Guardado';
    setTimeout(() => btn.innerHTML = '<i class="fas fa-save"></i> Guardar Notas', 2000);
}

// ══════════════════════════════════════════════════════════
// INCIDENCIAS
// ══════════════════════════════════════════════════════════
function abrirModalIncidencia(idNodo) {
    document.getElementById('incNodoId').value = idNodo;
    document.getElementById('incDesc').value = '';
    document.getElementById('modalIncidencia').style.display = 'block';
}

async function guardarIncidencia() {
    const idNodo = document.getElementById('incNodoId').value;
    const tipo   = document.getElementById('incTipo').value;
    const desc   = document.getElementById('incDesc').value.trim();
    if (!desc) { alert('Describe la incidencia'); return; }
    await fetch(`${BASE}api/index.php?ruta=flujo_incidencia`, {
        method: 'POST',
        body: JSON.stringify({ id_pedido_nodo: idNodo, tipo, descripcion: desc })
    });
    document.getElementById('modalIncidencia').style.display = 'none';
    await cargarFlujo(pedidoActualId);
}

// ══════════════════════════════════════════════════════════
// ASIGNAR PLANTILLA
// ══════════════════════════════════════════════════════════
function abrirAsignarPlantilla() {
    if (!pedidoActualId) { alert('Selecciona un pedido primero'); return; }
    document.getElementById('modalPlantilla').style.display = 'block';
}

async function asignarPlantilla() {
    const idPlantilla = document.getElementById('selPlantilla').value;
    if (!idPlantilla) return;
    const res = await fetch(`${BASE}api/index.php?ruta=flujo_plantillas`, {
        method: 'POST',
        body: JSON.stringify({ id_pedido: pedidoActualId, id_plantilla: idPlantilla })
    });
    const data = await res.json();
    document.getElementById('modalPlantilla').style.display = 'none';
    if (data.ok) {
        await cargarFlujo(pedidoActualId);
    } else {
        alert('Error: ' + (data.error || 'desconocido'));
    }
}

// ══════════════════════════════════════════════════════════
// VISTA MULTI-PEDIDO
// ══════════════════════════════════════════════════════════
async function abrirVistaMult() {
    document.getElementById('modalMulti').style.display = 'block';
    const res = await fetch(`${BASE}api/index.php?ruta=flujo`);
    const pedidos = await res.json();
    const grid = document.getElementById('multiPedidoGrid');

    const estadoColor = {
        por_empezar:'#6366f1', en_proceso:'#f59e0b', montado:'#06b6d4',
        tintado:'#ec4899', barnizado:'#84cc16', listo_para_entregar:'#fb923c',
        entregado:'#10b981', cancelado:'#ef4444'
    };

    if (!pedidos.length) {
        grid.innerHTML = '<p style="opacity:0.5;text-align:center">No hay pedidos activos con flujo asignado</p>';
        return;
    }

    grid.innerHTML = pedidos.map(p => {
        const pct = p.total_nodos > 0 ? Math.round((p.nodos_completados / p.total_nodos) * 100) : 0;
        const color = estadoColor[p.estado] || '#6366f1';
        const bloqClass = p.nodos_bloqueados > 0 ? 'multi-card-bloqueado' : '';
        return `
            <div class="multi-card ${bloqClass}" onclick="document.getElementById('modalMulti').style.display='none'; cargarFlujo(${p.id})">
                <div class="multi-card-header" style="border-left:4px solid ${color}">
                    <span class="multi-card-id">#${p.id}</span>
                    ${p.canal_origen ? `<span class="canal-badge canal-${p.canal_origen}">${p.canal_origen}</span>` : ''}
                    ${p.nodos_bloqueados > 0 ? `<span class="badge-red">⚠️ ${p.nodos_bloqueados} bloq.</span>` : ''}
                </div>
                <div class="multi-card-cliente">${escHtml(p.cliente_nombre)}</div>
                <div class="multi-card-estado" style="color:${color}">${p.estado.replace(/_/g,' ')}</div>
                <div class="multi-card-progress-wrap">
                    <div class="multi-card-progress-bar" style="width:${pct}%;background:${color}"></div>
                </div>
                <div class="multi-card-stats">
                    <span>${p.nodos_completados}/${p.total_nodos} nodos</span>
                    <span>${pct}%</span>
                </div>
            </div>
        `;
    }).join('');
}

// ══════════════════════════════════════════════════════════
// ANALÍTICA
// ══════════════════════════════════════════════════════════
async function abrirAnalitica() {
    document.getElementById('modalAnalitica').style.display = 'block';
    const res = await fetch(`${BASE}api/index.php?ruta=flujo_analytics`);
    const data = await res.json();
    const body = document.getElementById('analiticaBody');

    body.innerHTML = `
        <h3 style="color:#f59e0b"><i class="fas fa-traffic-light"></i> Cuellos de Botella Actuales</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:2rem">
            ${(data.cuellos_botella||[]).map(n => `
                <div class="analitica-card ${n.bloqueados > 0 ? 'analitica-bloqueado' : ''}">
                    <div style="font-weight:bold;color:${n.color || '#94a3b8'}">
                        <i class="fas fa-${n.icono}"></i> ${escHtml(n.nodo_nombre)}
                    </div>
                    <div class="analitica-stat">${n.pedidos_en_nodo} pedidos activos</div>
                    ${n.bloqueados > 0 ? `<div class="badge-red">${n.bloqueados} bloqueados</div>` : ''}
                </div>
            `).join('') || '<p style="opacity:0.5">Sin datos todavía</p>'}
        </div>
        <h3 style="color:#ef4444"><i class="fas fa-triangle-exclamation"></i> Nodos con más Incidencias</h3>
        <table style="width:100%;border-collapse:collapse;color:#e2e8f0">
            <thead><tr style="border-bottom:1px solid #334155">
                <th style="text-align:left;padding:8px">Nodo</th>
                <th>Total</th><th>Abiertas</th>
            </tr></thead>
            <tbody>
            ${(data.incidencias_por_nodo||[]).map(n => `
                <tr style="border-bottom:1px solid #1e293b">
                    <td style="padding:8px">${escHtml(n.nodo_nombre)}</td>
                    <td style="text-align:center">${n.total_incidencias}</td>
                    <td style="text-align:center;color:${n.abiertas > 0 ? '#ef4444' : '#10b981'}">${n.abiertas}</td>
                </tr>
            `).join('') || '<tr><td colspan="3" style="opacity:0.5;padding:1rem">Sin incidencias aún</td></tr>'}
            </tbody>
        </table>
        <h3 style="color:#3b82f6;margin-top:2rem"><i class="fas fa-clock"></i> Tiempos por Nodo</h3>
        <table style="width:100%;border-collapse:collapse;color:#e2e8f0">
            <thead><tr style="border-bottom:1px solid #334155">
                <th style="text-align:left;padding:8px">Nodo</th>
                <th>Estimado</th><th>Real Prom.</th><th></th>
            </tr></thead>
            <tbody>
            ${(data.tiempos_por_nodo||[]).map(n => {
                const real = Math.round(n.avg_real || 0);
                const desv = real - n.estimado;
                return `
                <tr style="border-bottom:1px solid #1e293b">
                    <td style="padding:8px">${escHtml(n.nodo_nombre)}</td>
                    <td style="text-align:center">${n.estimado}m</td>
                    <td style="text-align:center">${real}m</td>
                    <td style="text-align:center;color:${desv>0?'#ef4444':'#10b981'}">${desv>0?'+':''}${desv}m</td>
                </tr>`;
            }).join('') || '<tr><td colspan="4" style="opacity:0.5;padding:1rem">Sin datos de tiempo aún</td></tr>'}
            </tbody>
        </table>
    `;
}

// ══════════════════════════════════════════════════════════
// ESTADO VACÍO (sin flujo asignado)
// ══════════════════════════════════════════════════════════
function mostrarVacio(id_pedido) {
    document.getElementById('flujoEmpty').style.display = 'flex';
    document.getElementById('flujoEmpty').innerHTML = `
        <i class="fas fa-diagram-project fa-4x" style="color:#334155;margin-bottom:1rem"></i>
        <p style="color:#94a3b8">Este pedido <strong>#${id_pedido}</strong> no tiene flujo asignado todavía.</p>
        <button class="flujo-btn flujo-btn-green" onclick="abrirAsignarPlantilla()" style="margin-top:1rem">
            <i class="fas fa-plus-circle"></i> Asignar Plantilla de Flujo
        </button>
    `;
    document.getElementById('timelineBar').style.display = 'none';
}

// ══════════════════════════════════════════════════════════
// UTILS
// ══════════════════════════════════════════════════════════
function escHtml(str) {
    if (!str) return '';
    return str.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php include('../includes/footer.php'); ?>
