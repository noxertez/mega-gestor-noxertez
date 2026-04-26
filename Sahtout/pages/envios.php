<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once('../includes/paths.php');
require_once $project_root . 'includes/session.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: {$base_path}login");
    exit;
}
$page_class = 'envios';
include('../includes/header.php');
?>
<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════
   GESTIÓN DE ENVÍOS — Estilos Premium
═══════════════════════════════════════════════ */
.envios-wrapper {
    max-width: 1400px;
    margin: 24px auto;
    padding: 0 20px;
}
/* Pestañas principales */
.envios-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 0;
    border-bottom: 2px solid rgba(212,175,55,0.3);
}
.envios-tab-btn {
    background: rgba(20,20,35,0.8);
    border: 1px solid rgba(212,175,55,0.2);
    border-bottom: none;
    color: var(--text-gray, #94a3b8);
    padding: 12px 24px;
    font-family: 'Cinzel', serif;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}
.envios-tab-btn:hover {
    background: rgba(212,175,55,0.1);
    color: #d4af37;
}
.envios-tab-btn.active {
    background: rgba(236,72,153,0.15);
    border-color: rgba(236,72,153,0.5);
    color: #ec4899;
    border-bottom: 2px solid #ec4899;
    margin-bottom: -2px;
}
.envios-tab-btn.active.tab-medidas {
    background: rgba(99,102,241,0.15);
    border-color: rgba(99,102,241,0.5);
    color: #818cf8;
    border-bottom: 2px solid #818cf8;
}
/* Contenido de pestañas */
.envios-tab-content {
    display: none;
    background: rgba(15,15,25,0.95);
    border: 1px solid rgba(212,175,55,0.2);
    border-top: none;
    border-radius: 0 0 12px 12px;
    padding: 24px;
    animation: fadeIn 0.3s ease;
}
.envios-tab-content.active { display: block; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Layout principal de envíos ── */
.envios-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 20px;
}
@media (max-width: 900px) { .envios-grid { grid-template-columns: 1fr; } }

/* Panel lateral pedidos */
.panel-pedidos {
    background: rgba(10,10,20,0.9);
    border: 1px solid rgba(236,72,153,0.25);
    border-radius: 10px;
    overflow: hidden;
}
.panel-pedidos-header {
    background: linear-gradient(135deg, rgba(236,72,153,0.2), rgba(168,85,247,0.1));
    padding: 14px 18px;
    font-family: 'Cinzel', serif;
    font-size: 0.85rem;
    font-weight: 700;
    color: #ec4899;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(236,72,153,0.2);
}
.pedidos-list {
    max-height: 500px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(236,72,153,0.4) transparent;
}
.pedido-item {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.pedido-item:hover { background: rgba(236,72,153,0.08); }
.pedido-item.selected { background: rgba(236,72,153,0.15); border-left: 3px solid #ec4899; }
.pedido-item .ped-cliente { font-weight: 700; color: #e2e8f0; font-size: 0.9rem; }
.pedido-item .ped-info { font-size: 0.78rem; color: #64748b; display: flex; gap: 10px; flex-wrap: wrap; }
.pedido-item .ped-estado {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 600;
    background: rgba(16,185,129,0.2);
    color: #10b981;
    align-self: flex-start;
}
.pedido-item .ped-estado.naranja { background: rgba(245,158,11,0.2); color: #f59e0b; }

/* Panel de configuración */
.config-panel {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.config-card {
    background: rgba(10,10,20,0.9);
    border: 1px solid rgba(212,175,55,0.2);
    border-radius: 10px;
    overflow: hidden;
}
.config-card-header {
    background: linear-gradient(135deg, rgba(212,175,55,0.15), rgba(168,85,247,0.05));
    padding: 12px 16px;
    font-family: 'Cinzel', serif;
    font-size: 0.82rem;
    font-weight: 700;
    color: #d4af37;
    border-bottom: 1px solid rgba(212,175,55,0.15);
    display: flex;
    align-items: center;
    gap: 8px;
}
.config-card-body { padding: 16px; }

/* Formulario inputs */
.form-row {
    display: grid;
    gap: 12px;
    margin-bottom: 12px;
}
.form-row.cols-2 { grid-template-columns: 1fr 1fr; }
.form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-row.cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
.form-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}
.input-wow {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(212,175,55,0.2);
    border-radius: 6px;
    color: #e2e8f0;
    padding: 9px 12px;
    font-size: 0.88rem;
    font-family: 'Cinzel', serif;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.input-wow:focus {
    outline: none;
    border-color: #ec4899;
    box-shadow: 0 0 0 3px rgba(236,72,153,0.15);
}
.input-wow option { background: #1a1a2e; color: #e2e8f0; }

/* Botones */
.btn-envios {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-family: 'Cinzel', serif;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-envios:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-pink   { background: linear-gradient(135deg, #ec4899, #be185d); color: white; }
.btn-pink:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(236,72,153,0.4); }
.btn-green  { background: linear-gradient(135deg, #10b981, #047857); color: white; }
.btn-green:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }
.btn-indigo { background: linear-gradient(135deg, #6366f1, #4338ca); color: white; }
.btn-indigo:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.btn-gold   { background: linear-gradient(135deg, #d4af37, #92400e); color: white; }
.btn-gold:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(212,175,55,0.4); }
.btn-sm     { padding: 6px 14px; font-size: 0.78rem; }

/* Tarifas */
.tarifas-container { display: flex; flex-direction: column; gap: 8px; }
.tarifa-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(212,175,55,0.15);
    border-radius: 8px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.tarifa-card:hover { border-color: rgba(236,72,153,0.4); background: rgba(236,72,153,0.05); }
.tarifa-card.selected {
    border-color: #10b981;
    background: rgba(16,185,129,0.08);
    box-shadow: 0 0 0 2px rgba(16,185,129,0.3);
}
.tarifa-empresa { font-weight: 800; color: #a78bfa; font-size: 0.9rem; font-family: 'Cinzel', serif; flex: 1; }
.tarifa-servicio { font-size: 0.78rem; color: #64748b; flex: 2; }
.tarifa-entrega { font-size: 0.75rem; color: #94a3b8; font-style: italic; }
.tarifa-precio {
    background: linear-gradient(135deg, #10b981, #047857);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 800;
    font-size: 1rem;
    font-family: 'Cinzel', serif;
    white-space: nowrap;
}
.demo-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(245,158,11,0.2);
    border: 1px solid rgba(245,158,11,0.4);
    color: #f59e0b;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 12px;
}

/* ═══════ PESTAÑA PESOS Y MEDIDAS ═══════ */
.medidas-search-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    align-items: center;
}
.medidas-table-wrap {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid rgba(212,175,55,0.2);
    max-height: 65vh;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(99,102,241,0.4) transparent;
}
.medidas-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.86rem;
}
.medidas-table thead th {
    background: rgba(99,102,241,0.15);
    color: #818cf8;
    font-family: 'Cinzel', serif;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(99,102,241,0.3);
    position: sticky;
    top: 0;
    z-index: 5;
    white-space: nowrap;
}
.medidas-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.15s;
}
.medidas-table tbody tr:hover { background: rgba(99,102,241,0.06); }
.medidas-table td {
    padding: 8px 14px;
    color: #cbd5e1;
    vertical-align: middle;
}
.medidas-table td:first-child { color: #d4af37; font-weight: 700; font-size: 0.8rem; }
.medidas-table td:nth-child(2) { color: #e2e8f0; max-width: 200px; }
.input-dim {
    width: 75px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(99,102,241,0.25);
    border-radius: 5px;
    color: #e2e8f0;
    padding: 5px 8px;
    font-size: 0.85rem;
    text-align: center;
    transition: border-color 0.2s;
}
.input-dim:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 2px rgba(129,140,248,0.2); }
.save-row-btn {
    background: rgba(16,185,129,0.2);
    border: 1px solid rgba(16,185,129,0.4);
    color: #10b981;
    padding: 5px 12px;
    border-radius: 5px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Cinzel', serif;
    white-space: nowrap;
}
.save-row-btn:hover { background: rgba(16,185,129,0.3); transform: scale(1.02); }
.save-row-btn.saved { background: rgba(16,185,129,0.4); color: #6ee7b7; }

/* Alert */
.envios-alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 0.88rem;
    display: none;
    align-items: center;
    gap: 10px;
}
.envios-alert.show { display: flex; }
.envios-alert.success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4); color: #10b981; }
.envios-alert.error   { background: rgba(239,68,68,0.15);  border: 1px solid rgba(239,68,68,0.4);  color: #ef4444; }
.envios-alert.loading { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.3); color: #f59e0b; }

/* Estado vacío */
.empty-state {
    padding: 40px;
    text-align: center;
    color: #475569;
}
.empty-state i { font-size: 3rem; margin-bottom: 12px; opacity: 0.3; display: block; }

/* Loading spinner */
.spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255,255,255,0.2);
    border-top-color: #ec4899;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Miniaturas de producto en tabla medidas */
.prod-thumb-wrap {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid rgba(129,140,248,0.25);
    background: rgba(255,255,255,0.03);
    flex-shrink: 0;
}
.prod-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s;
    cursor: zoom-in;
}
.prod-thumb:hover { transform: scale(3.5); z-index: 50; position: relative; }
.prod-thumb-empty {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.15);
    font-size: 1.1rem;
}
</style>

<div class="envios-wrapper">
    <!-- Cabecera -->
    <div class="panel-header-wow" style="margin-bottom: 0; border-radius: 12px 12px 0 0;">
        <h1 style="margin:0; padding: 1rem 1.5rem;">
            <i class="fas fa-truck"></i> Gestión de Envíos
        </h1>
    </div>

    <!-- Pestañas -->
    <div class="envios-tabs">
        <button class="envios-tab-btn active" onclick="switchEnviosTab('tab-envios', this)" id="btn-tab-envios">
            <i class="fas fa-shipping-fast"></i> Envíos Packlink
        </button>
        <button class="envios-tab-btn tab-medidas" onclick="switchEnviosTab('tab-medidas', this)" id="btn-tab-medidas">
            <i class="fas fa-ruler-combined"></i> Pesos y Medidas
        </button>
    </div>

    <!-- ════════════════════════════════════════
         PESTAÑA 1: GESTIÓN DE ENVÍOS
    ════════════════════════════════════════ -->
    <div id="tab-envios" class="envios-tab-content active">

        <div id="alert-envios" class="envios-alert"></div>

        <div class="envios-grid">
            <!-- Panel izquierdo: lista de pedidos -->
            <div class="panel-pedidos">
                <div class="panel-pedidos-header">
                    <span><i class="fas fa-box-open"></i> Pedidos Pendientes</span>
                    <button class="btn-envios btn-sm btn-pink" onclick="cargarPedidos()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>

                <!-- Filtro de estado -->
                <div style="padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.04);">
                    <select class="input-wow" id="filtro-estado" onchange="cargarPedidos()" style="font-size:0.8rem; padding: 6px 10px;">
                        <option value="Listo para entrega">Listos para entrega</option>
                        <option value="todos">Todos (excl. Entregados)</option>
                        <option value="En proceso">En proceso</option>
                        <option value="Por empezar">Por empezar</option>
                    </select>
                </div>

                <div class="pedidos-list" id="pedidos-list">
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>Cargando pedidos...</p>
                    </div>
                </div>
            </div>

            <!-- Panel derecho: configuración y cotización -->
            <div class="config-panel">

                <!-- Selección manual cliente/artículo -->
                <div class="config-card">
                    <div class="config-card-header">
                        <i class="fas fa-user-tag"></i> Configuración Manual
                    </div>
                    <div class="config-card-body">
                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label>Cliente</label>
                                <select class="input-wow" id="sel-cliente" onchange="onClienteChange()">
                                    <option value="">— Seleccionar cliente —</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Artículo (SKU)</label>
                                <select class="input-wow" id="sel-articulo" onchange="onArticuloChange()">
                                    <option value="">— Seleccionar artículo —</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row cols-1">
                            <div class="form-group">
                                <label>Estado del pedido seleccionado</label>
                                <div id="estado-label" style="font-size:0.85rem; color:#94a3b8; padding: 8px 0;">
                                    — Sin pedido seleccionado —
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos del paquete y destino -->
                <div class="config-card">
                    <div class="config-card-header">
                        <i class="fas fa-map-marker-alt"></i> Datos del Paquete y Destino
                    </div>
                    <div class="config-card-body">
                        <div class="form-row cols-1">
                            <div class="form-group">
                                <label>Dirección de Destino</label>
                                <input type="text" class="input-wow" id="ent-direccion" placeholder="Calle, número, piso...">
                            </div>
                        </div>
                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label>CP Origen</label>
                                <input type="text" class="input-wow" id="ent-cp-origen" value="28001" placeholder="28001">
                            </div>
                            <div class="form-group">
                                <label>CP Destino</label>
                                <input type="text" class="input-wow" id="ent-cp-destino" placeholder="08001">
                            </div>
                        </div>
                        <div class="form-row cols-4" style="align-items: end;">
                            <div class="form-group">
                                <label>Peso (Kg)</label>
                                <input type="number" class="input-wow" id="ent-peso" value="0.5" min="0.01" step="0.01">
                            </div>
                            <div class="form-group">
                                <label>Largo (cm)</label>
                                <input type="number" class="input-wow" id="ent-largo" value="20" min="1">
                            </div>
                            <div class="form-group">
                                <label>Ancho (cm)</label>
                                <input type="number" class="input-wow" id="ent-ancho" value="15" min="1">
                            </div>
                            <div class="form-group">
                                <label>Alto (cm)</label>
                                <input type="number" class="input-wow" id="ent-alto" value="10" min="1">
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="btn-envios btn-pink" onclick="cotizarPacklink()">
                                <i class="fas fa-search-dollar"></i> Comparar Tarifas
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Zona tarifas -->
                <div class="config-card" id="card-tarifas" style="display:none;">
                    <div class="config-card-header">
                        <i class="fas fa-tags"></i> Tarifas Disponibles
                        <span id="demo-badge" class="demo-badge" style="display:none;">
                            <i class="fas fa-flask"></i> Modo Demo
                        </span>
                    </div>
                    <div class="config-card-body">
                        <div id="tarifas-container" class="tarifas-container"></div>
                        <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="btn-envios btn-green" id="btn-procesar" onclick="procesarEnvio()" disabled>
                                <i class="fas fa-paper-plane"></i> Procesar Envío
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════
         PESTAÑA 2: PESOS Y MEDIDAS
    ════════════════════════════════════════ -->
    <div id="tab-medidas" class="envios-tab-content">

        <div id="alert-medidas" class="envios-alert"></div>

        <!-- Barra de filtros -->
        <div class="medidas-search-bar" style="flex-wrap: wrap;">

            <!-- Filtro por categoría -->
            <div style="display:flex; align-items:center; gap:8px;">
                <i class="fas fa-layer-group" style="color:#818cf8;"></i>
                <select class="input-wow" id="sel-cat-medidas" onchange="cargarProductosPorCategoria()"
                        style="min-width:200px; padding:8px 12px; font-size:0.85rem;">
                    <option value="">— Elige una categoría —</option>
                    <option value="__TODAS__">📦 Todas las categorías</option>
                </select>
            </div>

            <!-- Búsqueda textual -->
            <input type="text" class="input-wow" id="buscar-producto"
                   placeholder="🔍 Buscar por SKU o nombre..."
                   oninput="filtrarProductosLocal()"
                   style="flex:1; min-width:180px;" disabled
                   title="Primero selecciona una categoría">

            <button class="btn-envios btn-indigo btn-sm" onclick="guardarTodosLosCambios()">
                <i class="fas fa-save"></i> Guardar Todos
            </button>
        </div>

        <!-- Estado vacío inicial -->
        <div id="medidas-vacio" style="text-align:center; padding:4rem 2rem; color:rgba(255,255,255,0.25);">
            <i class="fas fa-ruler-combined" style="font-size:3rem; margin-bottom:1rem; display:block;"></i>
            <p style="font-size:1rem;">Selecciona una categoría para ver y editar los pesos y medidas</p>
        </div>

        <!-- Spinner -->
        <div id="medidas-spinner" style="display:none; text-align:center; padding:3rem;">
            <div class="spinner" style="margin:0 auto 12px; width:30px; height:30px; border-width:3px; border-top-color:#818cf8;"></div>
            <p style="color:#64748b; font-size:0.9rem;">Cargando productos...</p>
        </div>

        <!-- Contador -->
        <div id="medidas-info" style="display:none; font-size:0.82rem; color:#64748b; margin-bottom: 10px;"></div>

        <!-- Tabla (oculta hasta seleccionar categoría) -->
        <div id="medidas-table-wrap" class="medidas-table-wrap" style="display:none;">
            <table class="medidas-table">
                <thead>
                    <tr>
                        <th style="width:52px;">Foto</th>
                        <th style="width:110px;">SKU</th>
                        <th>Nombre</th>
                        <th style="width:90px;">Categoría</th>
                        <th style="width:90px;"><i class="fas fa-weight-hanging"></i> Peso (Kg)</th>
                        <th style="width:85px;"><i class="fas fa-arrows-alt-h"></i> Largo</th>
                        <th style="width:85px;"><i class="fas fa-arrows-alt-h"></i> Ancho</th>
                        <th style="width:85px;"><i class="fas fa-arrows-alt-v"></i> Alto</th>
                        <th style="width:80px;">Acción</th>
                    </tr>
                </thead>
                <tbody id="medidas-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════
// CONFIGURACIÓN
// ═══════════════════════════════════════════
const API = '<?= rtrim($base_path, '/') ?>/api/envios.php';
let pedidoSeleccionado = null;
let tarifaSeleccionada = null;
let clientesMap        = {};
let productosData      = [];
let cambiosPendientes  = {};  // { sku: { peso, largo, ancho, alto } }

// ═══════════════════════════════════════════
// PESTAÑAS
// ═══════════════════════════════════════════
function switchEnviosTab(tabId, btn) {
    document.querySelectorAll('.envios-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.envios-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
    // Al entrar en medidas por primera vez, cargar categorías
    if (tabId === 'tab-medidas' && !window._categoriasLoaded) {
        window._categoriasLoaded = true;
        cargarCategoriasMedidas();
    }
}

// ═══════════════════════════════════════════
// ALERTS
// ═══════════════════════════════════════════
function showAlert(id, msg, type) {
    const el = document.getElementById(id);
    el.className = 'envios-alert show ' + type;
    el.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':type==='error'?'fa-exclamation-circle':'fa-spinner fa-spin'}"></i> ${msg}`;
    if (type !== 'loading') setTimeout(() => el.classList.remove('show'), 4000);
}
function hideAlert(id) { document.getElementById(id).classList.remove('show'); }

// ═══════════════════════════════════════════
// PESTAÑA 1: ENVÍOS
// ═══════════════════════════════════════════
async function cargarPedidos() {
    const estado = document.getElementById('filtro-estado').value;
    const url    = estado === 'todos'
        ? `${API}?action=get_all_pedidos`
        : `${API}?action=get_pedidos&estado=${encodeURIComponent(estado)}`;
    try {
        const res  = await fetch(url);
        const data = await res.json();
        renderPedidos(data);
    } catch(e) {
        document.getElementById('pedidos-list').innerHTML =
            '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error cargando pedidos</p></div>';
    }
}

function renderPedidos(pedidos) {
    const cont = document.getElementById('pedidos-list');
    if (!pedidos || pedidos.length === 0) {
        cont.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle" style="color:#10b981;opacity:1;"></i><p>No hay pedidos en este estado</p></div>';
        return;
    }
    cont.innerHTML = pedidos.map(p => {
        const fecha  = p.fecha_pedido ? p.fecha_pedido.split(' ')[0] : '—';
        const cliente = p.cliente_nombre || 'Sin cliente';
        const sku    = p.sku_articulo   || '—';
        const prod   = p.producto_nombre || sku;
        const esListo = p.estado === 'Listo para entrega';
        return `<div class="pedido-item" onclick="seleccionarPedido(${p.id}, this)" data-id="${p.id}">
            <div class="ped-estado ${esListo ? '' : 'naranja'}">${p.estado || 'Sin estado'}</div>
            <div class="ped-cliente">${cliente}</div>
            <div class="ped-info">
                <span><i class="fas fa-calendar-alt"></i> ${fecha}</span>
                <span><i class="fas fa-tag"></i> ${prod.length > 28 ? prod.substring(0,28)+'…' : prod}</span>
                ${p.costo_envio > 0 ? `<span style="color:#10b981;"><i class="fas fa-check"></i> ${parseFloat(p.costo_envio).toFixed(2)}€</span>` : ''}
            </div>
        </div>`;
    }).join('');
}

async function seleccionarPedido(id, el) {
    document.querySelectorAll('.pedido-item').forEach(e => e.classList.remove('selected'));
    el.classList.add('selected');

    try {
        const res  = await fetch(`${API}?action=get_all_pedidos`);
        const data = await res.json();
        const p    = data.find(x => x.id == id);
        if (!p) return;

        pedidoSeleccionado = p;

        // Rellenar selector de cliente
        if (p.cliente_nombre) {
            const opts = document.getElementById('sel-cliente').options;
            for (let i = 0; i < opts.length; i++) {
                if (opts[i].text === p.cliente_nombre) {
                    document.getElementById('sel-cliente').selectedIndex = i;
                    break;
                }
            }
        }
        // Rellenar campos
        document.getElementById('ent-direccion').value = p.direccion  || '';
        document.getElementById('ent-cp-destino').value = p.codigo_postal || '';
        document.getElementById('ent-peso').value  = p.peso_envio  || '0.5';
        document.getElementById('ent-largo').value = p.largo_envio || '20';
        document.getElementById('ent-ancho').value = p.ancho_envio || '15';
        document.getElementById('ent-alto').value  = p.alto_envio  || '10';

        const estadoLabel = document.getElementById('estado-label');
        estadoLabel.style.color = p.estado === 'Listo para entrega' ? '#10b981' : '#f59e0b';
        estadoLabel.textContent = `📦 ${p.estado} — Pedido #${p.id}`;
    } catch(e) { console.error(e); }
}

async function cargarClientes() {
    try {
        const res  = await fetch(`${API}?action=get_clientes`);
        const data = await res.json();
        const sel  = document.getElementById('sel-cliente');
        data.forEach(c => {
            clientesMap[c.id] = c;
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.nombre;
            sel.appendChild(opt);
        });
    } catch(e) { console.error(e); }
}

async function cargarArticulosCombo() {
    try {
        const res  = await fetch(`${API}?action=get_productos`);
        const data = await res.json();
        const sel  = document.getElementById('sel-articulo');
        sel.innerHTML = '<option value="">— Seleccionar artículo —</option>';
        data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.SKU_REF;
            opt.textContent = `${p.SKU_REF} — ${p.NOMBRE ? p.NOMBRE.substring(0,40) : ''}`;
            opt.dataset.peso  = p.peso_envio  || 0.5;
            opt.dataset.largo = p.largo_envio || 20;
            opt.dataset.ancho = p.ancho_envio || 15;
            opt.dataset.alto  = p.alto_envio  || 10;
            sel.appendChild(opt);
        });
    } catch(e) { console.error(e); }
}

function onClienteChange() {
    const id = document.getElementById('sel-cliente').value;
    if (!id || !clientesMap[id]) return;
    const c = clientesMap[id];
    document.getElementById('ent-direccion').value  = c.direccion    || '';
    document.getElementById('ent-cp-destino').value = c.codigo_postal || '';
    pedidoSeleccionado = null;
    document.getElementById('estado-label').textContent = '— Selección manual —';
    document.getElementById('estado-label').style.color = '#94a3b8';
}

function onArticuloChange() {
    const sel = document.getElementById('sel-articulo');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('ent-peso').value  = opt.dataset.peso;
    document.getElementById('ent-largo').value = opt.dataset.largo;
    document.getElementById('ent-ancho').value = opt.dataset.ancho;
    document.getElementById('ent-alto').value  = opt.dataset.alto;
}

async function cotizarPacklink() {
    const cp_o  = document.getElementById('ent-cp-origen').value.trim();
    const cp_d  = document.getElementById('ent-cp-destino').value.trim();
    const peso  = document.getElementById('ent-peso').value;
    const largo = document.getElementById('ent-largo').value;
    const ancho = document.getElementById('ent-ancho').value;
    const alto  = document.getElementById('ent-alto').value;

    if (!cp_d || !peso) {
        showAlert('alert-envios', 'Faltan datos: CP destino y peso son obligatorios.', 'error');
        return;
    }

    showAlert('alert-envios', 'Consultando tarifas Packlink PRO...', 'loading');
    document.getElementById('card-tarifas').style.display = 'block';
    document.getElementById('tarifas-container').innerHTML =
        '<div style="text-align:center; padding:20px; color:#94a3b8;"><div class="spinner" style="margin:0 auto 10px;"></div><br>Contactando API de Packlink...</div>';

    tarifaSeleccionada = null;
    document.getElementById('btn-procesar').disabled = true;

    const url = `${API}?action=cotizar_packlink&cp_origen=${cp_o}&cp_destino=${cp_d}&peso=${peso}&largo=${largo}&ancho=${ancho}&alto=${alto}`;
    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (data.error) { showAlert('alert-envios', `Error: ${data.error}`, 'error'); return; }
        hideAlert('alert-envios');

        const demoBadge = document.getElementById('demo-badge');
        demoBadge.style.display = data.demo ? 'inline-flex' : 'none';

        renderTarifas(data.tarifas || []);
    } catch(e) {
        showAlert('alert-envios', 'Error de red al contactar Packlink.', 'error');
    }
}

function renderTarifas(tarifas) {
    const cont = document.getElementById('tarifas-container');
    if (!tarifas.length) {
        cont.innerHTML = '<div class="empty-state"><i class="fas fa-times-circle"></i><p>Sin tarifas disponibles para este destino/peso.</p></div>';
        return;
    }
    cont.innerHTML = tarifas.map((t, i) => `
        <div class="tarifa-card ${i === 0 ? 'selected' : ''}" onclick="selectTarifa(this, ${JSON.stringify(JSON.stringify(t))})"
             data-tarifa='${JSON.stringify(t)}'>
            <div>
                <div class="tarifa-empresa">${t.empresa}</div>
                <div class="tarifa-servicio">${t.servicio}</div>
            </div>
            <div class="tarifa-entrega"><i class="fas fa-clock"></i> ${t.entrega}</div>
            <div class="tarifa-precio">${parseFloat(t.precio).toFixed(2)} €</div>
        </div>
    `).join('');

    // Seleccionar la primera por defecto
    const first = cont.querySelector('.tarifa-card');
    if (first) {
        tarifaSeleccionada = JSON.parse(first.dataset.tarifa);
        document.getElementById('btn-procesar').disabled = false;
    }
}

function selectTarifa(el, _raw) {
    document.querySelectorAll('.tarifa-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    tarifaSeleccionada = JSON.parse(el.dataset.tarifa);
    document.getElementById('btn-procesar').disabled = false;
}

async function procesarEnvio() {
    if (!tarifaSeleccionada) { showAlert('alert-envios', 'Selecciona una tarifa primero.', 'error'); return; }

    const id = pedidoSeleccionado ? pedidoSeleccionado.id : null;
    const tracking = `PK-${new Date().toISOString().slice(0,10).replace(/-/g,'')}-${id || Date.now()}`;

    showAlert('alert-envios', 'Procesando envío...', 'loading');

    try {
        if (id) {
            const res = await fetch(`${API}?action=marcar_entregado`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id,
                    costo_envio:  tarifaSeleccionada.precio,
                    metodo_envio: tarifaSeleccionada.empresa + ' — ' + tarifaSeleccionada.servicio,
                    tracking_id:  tracking,
                })
            });
            const data = await res.json();
            if (data.error) { showAlert('alert-envios', `Error: ${data.error}`, 'error'); return; }
        }
        showAlert('alert-envios',
            `✅ Envío procesado correctamente. Empresa: <strong>${tarifaSeleccionada.empresa}</strong> — ${tarifaSeleccionada.precio.toFixed(2)}€ — Tracking: <strong>${tracking}</strong>`,
            'success'
        );
        document.getElementById('btn-procesar').disabled = true;
        tarifaSeleccionada = null;
        setTimeout(() => cargarPedidos(), 1000);
    } catch(e) {
        showAlert('alert-envios', 'Error de red al procesar el envío.', 'error');
    }
}

// ═══════════════════════════════════════════
// PESTAÑA 2: PESOS Y MEDIDAS
// ═══════════════════════════════════════════

async function cargarCategoriasMedidas() {
    try {
        const res  = await fetch(`${API}?action=get_categorias`);
        const cats = await res.json();
        const sel  = document.getElementById('sel-cat-medidas');
        cats.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            sel.appendChild(opt);
        });
    } catch(e) { console.error('Error cargando categorías:', e); }
}

async function cargarProductosPorCategoria() {
    const cat  = document.getElementById('sel-cat-medidas').value;
    const vacio   = document.getElementById('medidas-vacio');
    const spinner = document.getElementById('medidas-spinner');
    const wrap    = document.getElementById('medidas-table-wrap');
    const info    = document.getElementById('medidas-info');
    const busq    = document.getElementById('buscar-producto');

    if (!cat) {
        vacio.style.display   = 'block';
        spinner.style.display = 'none';
        wrap.style.display    = 'none';
        info.style.display    = 'none';
        busq.disabled = true;
        busq.value = '';
        productosData = [];
        return;
    }

    vacio.style.display   = 'none';
    wrap.style.display    = 'none';
    info.style.display    = 'none';
    spinner.style.display = 'block';
    busq.disabled = true;
    busq.value = '';
    cambiosPendientes = {};

    try {
        const url = cat === '__TODAS__'
            ? `${API}?action=get_productos_por_cat`
            : `${API}?action=get_productos_por_cat&cat=${encodeURIComponent(cat)}`;
        const res = await fetch(url);
        productosData = await res.json();

        spinner.style.display = 'none';

        if (!productosData.length) {
            vacio.innerHTML = '<i class="fas fa-search" style="font-size:2.5rem; display:block; margin-bottom:1rem;"></i><p>No hay productos en esta categoría.</p>';
            vacio.style.display = 'block';
            return;
        }

        renderProductosMedidas(productosData);
        wrap.style.display = 'block';
        info.style.display = 'block';
        info.innerHTML = `<i class="fas fa-boxes"></i> ${productosData.length} producto(s) — Categoría: <strong style="color:#818cf8;">${escHtml(cat)}</strong>`;
        busq.disabled = false;
    } catch(e) {
        spinner.style.display = 'none';
        vacio.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#ef4444; font-size:2rem; display:block; margin-bottom:1rem;"></i><p style="color:#ef4444;">Error al cargar productos.</p>';
        vacio.style.display = 'block';
    }
}

function resolverFoto(foto) {
    if (!foto) return null;
    const clean = foto.replace(/\\/g, '/');
    const BASE  = '<?= rtrim($base_path, "/") ?>/';
    if (/^[a-zA-Z]:\//.test(clean)) {
        const idx = clean.toLowerCase().indexOf('uploads/');
        if (idx !== -1) return BASE + clean.substring(idx);
        return BASE + 'uploads/articulos/imagenes/' + clean.split('/').pop();
    }
    if (clean.startsWith('uploads/')) return BASE + clean;
    return BASE + 'uploads/' + clean;
}

function renderProductosMedidas(productos) {
    const tbody = document.getElementById('medidas-tbody');
    if (!productos.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="empty-state"><i class="fas fa-box-open"></i><p>Sin resultados</p></td></tr>';
        return;
    }
    const BASE = '<?= rtrim($base_path, "/") ?>/';
    tbody.innerHTML = productos.map(p => {
        const sku  = escHtml(p.SKU_REF   || '');
        const nom  = escHtml(p.NOMBRE    || '—');
        const cat  = escHtml(p.CATEGORIA || '');
        const foto = resolverFoto(p.FOTO_PORTADA);
        const imgHtml = foto
            ? `<img src="${foto}" alt="" class="prod-thumb" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
               <div class="prod-thumb-empty" style="display:none;"><i class="fas fa-image"></i></div>`
            : `<div class="prod-thumb-empty"><i class="fas fa-image"></i></div>`;
        return `<tr data-sku="${sku}">
            <td><div class="prod-thumb-wrap">${imgHtml}</div></td>
            <td><code style="font-size:0.78rem;">${sku}</code></td>
            <td style="max-width:180px; word-break:break-word;" title="${nom}">${nom.length>38?nom.substring(0,38)+'…':nom}</td>
            <td><span style="background:rgba(129,140,248,0.15); color:#818cf8; padding:2px 8px; border-radius:20px; font-size:0.72rem; white-space:nowrap;">${cat}</span></td>
            <td><input class="input-dim" type="number" step="0.001" min="0.001"
                       data-field="peso_envio" data-sku="${sku}"
                       value="${parseFloat(p.peso_envio||0.5).toFixed(3)}"
                       oninput="marcaCambio('${sku}')"></td>
            <td><input class="input-dim" type="number" step="0.1" min="0.1"
                       data-field="largo_envio" data-sku="${sku}"
                       value="${parseFloat(p.largo_envio||20).toFixed(1)}"
                       oninput="marcaCambio('${sku}')"></td>
            <td><input class="input-dim" type="number" step="0.1" min="0.1"
                       data-field="ancho_envio" data-sku="${sku}"
                       value="${parseFloat(p.ancho_envio||15).toFixed(1)}"
                       oninput="marcaCambio('${sku}')"></td>
            <td><input class="input-dim" type="number" step="0.1" min="0.1"
                       data-field="alto_envio" data-sku="${sku}"
                       value="${parseFloat(p.alto_envio||10).toFixed(1)}"
                       oninput="marcaCambio('${sku}')"></td>
            <td><button class="save-row-btn" id="btn-save-${sku}" onclick="guardarDimensionProducto('${sku}')">
                <i class="fas fa-save"></i>
            </button></td>
        </tr>`;
    }).join('');
}

function filtrarProductosLocal() {
    const q = document.getElementById('buscar-producto').value.toLowerCase().trim();
    if (!q) { renderProductosMedidas(productosData); return; }
    renderProductosMedidas(productosData.filter(p =>
        (p.SKU_REF   ||'').toLowerCase().includes(q) ||
        (p.NOMBRE    ||'').toLowerCase().includes(q)
    ));
}

function marcaCambio(sku) {
    cambiosPendientes[sku] = true;
    const btn = document.getElementById(`btn-save-${sku}`);
    if (btn) { btn.style.borderColor = 'rgba(245,158,11,0.6)'; btn.style.color = '#f59e0b'; }
}

async function guardarDimensionProducto(sku) {
    const row = document.querySelector(`tr[data-sku="${sku}"]`);
    if (!row) return;
    const campos = {
        peso_envio:  parseFloat(row.querySelector('[data-field="peso_envio"]').value)  || 0.5,
        largo_envio: parseFloat(row.querySelector('[data-field="largo_envio"]').value) || 20,
        ancho_envio: parseFloat(row.querySelector('[data-field="ancho_envio"]').value) || 15,
        alto_envio:  parseFloat(row.querySelector('[data-field="alto_envio"]').value)  || 10,
    };
    const btn = document.getElementById(`btn-save-${sku}`);
    if (btn) { btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btn.disabled = true; }
    try {
        const res  = await fetch(`${API}?action=update_dimensiones`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sku, ...campos })
        });
        const data = await res.json();
        if (data.success) {
            delete cambiosPendientes[sku];
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.classList.add('saved'); btn.style.borderColor=''; btn.style.color='';
                btn.disabled = false;
                setTimeout(() => { btn.innerHTML='<i class="fas fa-save"></i>'; btn.classList.remove('saved'); }, 2000);
            }
            const idx = productosData.findIndex(p => p.SKU_REF === sku);
            if (idx >= 0) Object.assign(productosData[idx], campos);
        } else {
            if (btn) { btn.innerHTML='<i class="fas fa-save"></i>'; btn.disabled=false; }
            showAlert('alert-medidas', `Error al guardar ${sku}`, 'error');
        }
    } catch(e) {
        if (btn) { btn.innerHTML='<i class="fas fa-save"></i>'; btn.disabled=false; }
    }
}

async function guardarTodosLosCambios() {
    const skus = Object.keys(cambiosPendientes);
    if (!skus.length) { showAlert('alert-medidas', 'No hay cambios pendientes.', 'success'); return; }
    showAlert('alert-medidas', `Guardando ${skus.length} producto(s)...`, 'loading');
    for (const sku of skus) await guardarDimensionProducto(sku);
    showAlert('alert-medidas', `✅ ${skus.length} producto(s) guardados.`, 'success');
}

// ── Helpers ──
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ═══════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    cargarPedidos();
    cargarClientes();
    cargarArticulosCombo();
});
</script>

<?php include('../includes/footer.php'); ?>
