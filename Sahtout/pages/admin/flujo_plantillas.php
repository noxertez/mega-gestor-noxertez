<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);

require_once '../../api/config.php';
$db = conectar();

$page_class = 'management-page';
include('../../includes/header.php');

if (!isset($_SESSION['user_id'])) { header('Location:' . $base_path . 'pages/login.php'); exit; }
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.1">
<style>
    .admin-flujo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; padding: 20px; }
    .tpl-card { background: #1a2742; border: 1px solid #2d3f5e; border-radius: 12px; padding: 20px; transition: transform 0.2s; position: relative; }
    .tpl-card:hover { transform: translateY(-5px); border-color: #C89B3C; }
    .tpl-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
    .tpl-title { font-size: 1.2rem; font-weight: 700; color: #C89B3C; }
    .tpl-desc { color: #94a3b8; font-size: 0.9rem; margin-bottom: 15px; min-height: 40px; }
    .node-list { background: rgba(0,0,0,0.2); border-radius: 8px; padding: 10px; margin-bottom: 15px; }
    .node-item { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .node-item:last-child { border-bottom: none; }
    .node-icon { width: 24px; text-align: center; }
    .node-name { flex: 1; font-size: 0.85rem; color: #e2e8f0; }
    .node-time { font-size: 0.75rem; color: #64748b; }
    .tpl-actions { display: flex; gap: 8px; justify-content: flex-end; }
    
    .modal-flujo { max-width: 750px !important; }
    .node-editor-row { display: grid; grid-template-columns: 48px 110px 1fr 60px 40px 80px 40px; gap: 8px; align-items: center; margin-bottom: 8px; background: rgba(255,255,255,0.03); padding: 5px; border-radius: 6px; }
    .node-editor-row input, .node-editor-row select { padding: 4px 8px !important; height: 32px !important; }
    
    /* Icon Picker */
    .icon-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; max-height: 400px; overflow-y: auto; padding: 15px; }
    .icon-picker-item { background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; transition: 0.2s; }
    .icon-picker-item:hover { border-color: #C89B3C; background: rgba(200,155,60,0.1); }
    .icon-picker-item i { font-size: 1.2rem; color: #e2e8f0; display: block; margin-bottom: 5px; }
    .icon-picker-item span { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; }
    .icon-btn-preview { width: 100%; font-size: 1.1rem; padding: 5px !important; }
</style>

<div class="flujo-app">
    <div style="padding: 20px 40px; background: rgba(15,23,42,0.9); border-bottom: 1px solid #1e3a5f; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="color: #C89B3C; margin: 0;"><i class="fas fa-cogs"></i> Gestión de Plantillas de Flujo</h1>
            <p style="color: #94a3b8; font-size: 0.9rem; margin: 5px 0 0;">Define las fases de producción para tus productos artesanales</p>
        </div>
        <button class="btn-premium-wow btn-gold" onclick="abrirModalTemplate()">
            <i class="fas fa-plus"></i> Nueva Plantilla
        </button>
    </div>

    <div class="admin-flujo-grid" id="tplGrid">
        <!-- Cargado dinámicamente -->
    </div>
</div>

<!-- MODAL: EDICIÓN PLANTILLA -->
<div class="modal-overlay-wow" id="modalTpl" style="display:none" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content-wow modal-flujo">
        <div class="modal-header-wow">
            <h2 id="modalTitle">Nueva Plantilla</h2>
            <button onclick="document.getElementById('modalTpl').style.display='none'" style="background:none; border:none; color:#C89B3C; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <div style="padding: 20px;">
            <input type="hidden" id="tplId">
            <div class="nox-form-group">
                <label>Nombre de la Plantilla</label>
                <input type="text" id="tplNombre" class="nox-input" style="width:100%" placeholder="Ej: Pedido Especial Madera">
            </div>
            <div class="nox-form-group">
                <label>Descripción</label>
                <textarea id="tplDesc" class="nox-input" style="width:100%; height:60px"></textarea>
            </div>
            
            <h3 style="color: #C89B3C; font-size: 1rem; margin: 20px 0 10px; border-bottom: 1px solid #1e3a5f; padding-bottom: 5px;">Nodos del Flujo</h3>
            <div id="nodeEditorContainer">
                <!-- Nodos se añaden aquí -->
            </div>
            <button class="flujo-btn flujo-btn-blue" style="width: 100%; margin-top: 10px;" onclick="addNodeRow()">
                <i class="fas fa-plus"></i> Añadir Fase
            </button>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button class="btn-premium-wow btn-gold" style="flex: 1; justify-content: center;" onclick="guardarPlantilla()">
                    <i class="fas fa-save"></i> Guardar Plantilla
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: SELECTOR DE ICONOS -->
<div class="modal-overlay-wow" id="modalIconPicker" style="display:none; z-index:2000" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content-wow" style="max-width: 500px">
        <div class="modal-header-wow">
            <h3>Seleccionar Icono</h3>
            <input type="text" id="iconSearchInput" placeholder="Buscar..." oninput="filtrarIconos()" style="background:#0f172a; border:1px solid #1e293b; color:#fff; border-radius:4px; padding:4px 8px; font-size:0.8rem">
        </div>
        <div class="icon-picker-grid" id="iconGrid">
            <!-- Cargado por JS -->
        </div>
    </div>
</div>

<script>
const ICONS_ES = {
    "hammer": "Martillo / Taller",
    "tools": "Herramientas",
    "paint-roller": "Pintura / Barniz",
    "scissors": "Corte",
    "inbox": "Recibido",
    "box": "Embalaje",
    "truck": "Envío / Transporte",
    "check-all": "Completado",
    "circle-check": "OK",
    "clock": "En espera",
    "clock-rotate-left": "Retrasado",
    "exclamation-triangle": "Incidencia",
    "info-circle": "Info",
    "search": "Revisión",
    "eye": "Inspección Visual",
    "vial": "Calidad",
    "bolt": "Urgente",
    "fire": "Prioridad Alta",
    "gem": "Producto Lujo",
    "leaf": "Ecológico / Madera",
    "mountain": "Cantera / Piedra",
    "tshirt": "Textil",
    "shopping-cart": "Venta",
    "user": "Asignado a cliente",
    "users": "Equipo / Colaboración",
    "calendar": "Agendado",
    "file-invoice": "Facturación",
    "stamp": "Certificado",
    "award": "Finalizado Especial",
    "medal": "Top Ventas",
    "handshake": "Trato Cerrado",
    "money-bill": "Pagado",
    "warehouse": "Stock / Almacén",
    "tags": "Etiquetado",
    "pen-nib": "Diseño",
    "pencil-alt": "Trazado",
    "drafting-compass": "Planos",
    "ruler-combined": "Medición",
    "microchip": "Electrónica",
    "cog": "Mecánica",
    "cogs": "Proceso Complejo",
    "recycle": "Reciclaje",
    "flask": "Químicos / Pegamento",
    "sun": "Secado Natural",
    "snowflake": "Frío / Curado",
    "envelope": "Comunicación",
    "phone": "Llamada cliente"
};

const BASE = '<?= $base_path ?>';
const API_BASE = BASE + 'api/index.php?ruta=';
let allTemplates = [];
let initialNodeIds = []; 
let currentRowForIcon = null; // Fila que está editando el icono

document.addEventListener('DOMContentLoaded', () => {
    generarGridIconos();
    cargarPlantillas();
});

function generarGridIconos() {
    const grid = document.getElementById('iconGrid');
    grid.innerHTML = '';
    Object.entries(ICONS_ES).forEach(([key, val]) => {
        const item = document.createElement('div');
        item.className = 'icon-picker-item';
        item.innerHTML = `<i class="fas fa-${key}"></i><span>${val}</span>`;
        item.onclick = () => seleccionarIcono(key);
        grid.appendChild(item);
    });
}

function filtrarIconos() {
    const q = document.getElementById('iconSearchInput').value.toLowerCase();
    const items = document.querySelectorAll('.icon-picker-item');
    items.forEach(it => {
        const txt = it.innerText.toLowerCase();
        it.style.display = txt.includes(q) ? 'block' : 'none';
    });
}

function abrirIconPicker(btn) {
    currentRowForIcon = btn.closest('.node-editor-row');
    document.getElementById('modalIconPicker').style.display = 'block';
    document.getElementById('iconSearchInput').value = '';
    filtrarIconos();
}

function seleccionarIcono(key) {
    if(!currentRowForIcon) return;
    const input = currentRowForIcon.querySelector('.node-icon-input');
    const preview = currentRowForIcon.querySelector('.icon-preview-i');
    
    input.value = key;
    preview.className = `fas fa-${key}`;
    
    document.getElementById('modalIconPicker').style.display = 'none';
    currentRowForIcon = null;
}

async function cargarPlantillas() {
    const grid = document.getElementById('tplGrid');
    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 100px;"><i class="fas fa-spinner fa-spin fa-3x" style="color:#C89B3C"></i><p style="margin-top:20px; color:#94a3b8">Cargando plantillas...</p></div>';

    try {
        const res = await fetch(API_BASE + 'flujo_plantillas');
        allTemplates = await res.json();
        
        if (allTemplates.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 100px; color: #475569;"><i class="fas fa-copy fa-4x" style="margin-bottom: 20px;"></i><p>No hay plantillas creadas todavía.</p></div>';
            return;
        }

        grid.innerHTML = '';
        allTemplates.forEach(t => {
            const nodes = t.nodos || [];
            const card = document.createElement('div');
            card.className = 'tpl-card';
            card.innerHTML = `
                <div class="tpl-header">
                    <div class="tpl-title">${t.nombre}</div>
                    <span class="badge-blue">${nodes.length} fases</span>
                </div>
                <div class="tpl-desc">${t.descripcion || 'Sin descripción'}</div>
                <div class="node-list">
                    ${nodes.slice(0, 5).map(n => `
                        <div class="node-item">
                            <div class="node-icon"><i class="fas fa-${n.icono}" style="color:${n.color || '#C89B3C'}"></i></div>
                            <div class="node-name">${n.nombre}</div>
                            <div class="node-time">${n.tiempo_estimado_min}m</div>
                        </div>
                    `).join('')}
                    ${nodes.length > 5 ? `<div style="text-align:center; font-size:0.7rem; color:#64748b; padding:5px;">+ ${nodes.length - 5} fases más</div>` : ''}
                </div>
                <div class="tpl-actions">
                    <button class="flujo-btn flujo-btn-red" title="Borrar" onclick="borrarPlantilla(${t.id})"><i class="fas fa-trash"></i></button>
                    <button class="flujo-btn flujo-btn-gold" title="Duplicar" onclick="duplicarPlantilla(${t.id})"><i class="fas fa-copy"></i></button>
                    <button class="flujo-btn flujo-btn-blue" onclick="editarPlantilla(${t.id})"><i class="fas fa-edit"></i> Editar</button>
                </div>
            `;
            grid.appendChild(card);
        });
    } catch (e) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 50px; color: #ef4444;"><i class="fas fa-exclamation-triangle fa-3x"></i><p>Error al cargar las plantillas.</p></div>';
    }
}

function abrirModalTemplate() {
    document.getElementById('modalTitle').innerText = 'Nueva Plantilla';
    document.getElementById('tplId').value = '';
    document.getElementById('tplNombre').value = '';
    document.getElementById('tplDesc').value = '';
    document.getElementById('nodeEditorContainer').innerHTML = '';
    document.getElementById('modalTpl').style.display = 'block';
    // Añadir 3 nodos por defecto
    addNodeRow('Pedido Recibido', 'inbox', '#6366f1', 5);
    addNodeRow('Producción', 'hammer', '#f59e0b', 60);
    addNodeRow('Envío', 'box', '#10b981', 10);
}

async function editarPlantilla(id) {
    const res = await fetch(API_BASE + 'flujo_plantillas&id=' + id);
    const data = await res.json();
    
    document.getElementById('modalTitle').innerText = 'Editar Plantilla';
    document.getElementById('tplId').value = data.id;
    document.getElementById('tplNombre').value = data.nombre;
    document.getElementById('tplDesc').value = data.descripcion;
    document.getElementById('nodeEditorContainer').innerHTML = '';
    
    initialNodeIds = [];
    if (data.nodos && data.nodos.length > 0) {
        data.nodos.forEach(n => {
            initialNodeIds.push(n.id);
            addNodeRow(n.nombre, n.icono, n.color, n.tiempo_estimado_min, n.id, n.estado_pedido_mapeo);
        });
    } else {
        addNodeRow();
    }
    
    document.getElementById('modalTpl').style.display = 'block';
}

async function duplicarPlantilla(id) {
    if (!confirm('¿Quieres crear una copia de esta plantilla?')) return;
    try {
        const res = await fetch(API_BASE + 'flujo_plantilla_duplicar&id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.ok) cargarPlantillas();
        else alert("Error al duplicar: " + (data.error || "Desconocido"));
    } catch (e) {
        console.error(e);
        alert("Error de conexión");
    }
}

function addNodeRow(nombre='', icono='circle', color='#3b82f6', tiempo=0, id='', mapeo='') {
    const container = document.getElementById('nodeEditorContainer');
    const div = document.createElement('div');
    div.className = 'node-editor-row';
    div.dataset.id = id;
    div.innerHTML = `
        <input type="hidden" class="node-icon-input" value="${icono}">
        <button class="flujo-btn flujo-btn-blue icon-btn-preview" onclick="abrirIconPicker(this)" title="Cambiar Icono">
            <i class="fas fa-${icono} icon-preview-i"></i>
        </button>
        <input type="color" class="nox-input node-color-input" value="${color}" title="Color" style="padding:2px !important">
        <input type="text" class="nox-input node-name-input" value="${nombre}" placeholder="Nombre" style="font-size:0.8rem">
        <input type="number" class="nox-input node-time-input" value="${tiempo}" placeholder="Min" style="font-size:0.8rem">
        <select class="nox-input node-mapeo-input" style="font-size:0.7rem">
            <option value="">Map Kanpur</option>
            <option value="por_empezar" ${mapeo=='por_empezar'?'selected':''}>Por Empezar</option>
            <option value="en_proceso" ${mapeo=='en_proceso'?'selected':''}>En Proceso</option>
            <option value="montado" ${mapeo=='montado'?'selected':''}>Montado</option>
            <option value="tintado" ${mapeo=='tintado'?'selected':''}>Tintado</option>
            <option value="barnizado" ${mapeo=='barnizado'?'selected':''}>Barnizado</option>
            <option value="listo_para_entregar" ${mapeo=='listo_para_entregar'?'selected':''}>Listo Entrega</option>
            <option value="entregado" ${mapeo=='entregado'?'selected':''}>Entregado</option>
        </select>
        <button class="flujo-btn-icon" onclick="this.parentElement.remove()" style="color:#ef4444"><i class="fas fa-trash-alt"></i></button>
    `;
    container.appendChild(div);
}

async function guardarPlantilla() {
    const id = document.getElementById('tplId').value;
    const nombre = document.getElementById('tplNombre').value;
    const desc = document.getElementById('tplDesc').value;
    
    if (!nombre) { alert('El nombre es obligatorio'); return; }
    
    const btn = event.currentTarget.tagName === 'BUTTON' ? event.currentTarget : event.target.closest('button');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    // Recopilar nodos
    const rows = document.querySelectorAll('.node-editor-row');
    const nodesData = Array.from(rows).map((row, idx) => ({
        id: row.dataset.id || null,
        nombre: row.querySelector('.node-name-input').value,
        tiempo_estimado_min: row.querySelector('.node-time-input').value,
        estado_pedido_mapeo: row.querySelector('.node-mapeo-input').value,
        icono: row.querySelector('.node-icon-input').value || 'circle',
        color: row.querySelector('.node-color-input').value || '#3b82f6',
        orden: idx
    }));

    // Detectar borrados
    const finalIds = nodesData.filter(n => n.id).map(n => n.id);
    const toDelete = initialNodeIds.filter(id => !finalIds.includes(id));

    // Guardado Atómico (UNA SOLA PETICIÓN PARA TODO)
    try {
        const res = await fetch(API_BASE + 'flujo_plantilla_save_all', {
            method: 'POST',
            body: JSON.stringify({ id, nombre, descripcion: desc, nodos: nodesData, borrados: toDelete })
        });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('modalTpl').style.display = 'none';
            cargarPlantillas();
        } else {
            alert("Error: " + (data.error || "No se pudo guardar"));
        }
    } catch (e) {
        console.error(e);
        alert("Error de conexión al servidor");
    } finally {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
    }
}

async function borrarPlantilla(id) {
    if (!confirm('¿Seguro que quieres borrar esta plantilla?')) return;
    await fetch(API_BASE + 'flujo_plantillas&id=' + id, { method: 'DELETE' });
    cargarPlantillas();
}

document.addEventListener('DOMContentLoaded', cargarPlantillas);
</script>

<?php include('../../includes/footer.php'); ?>
