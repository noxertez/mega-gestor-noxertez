<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../api/config.php';
$db = conectar();

// 1. Fetch categorías distintas para el desplegable (ligero)
$categorias_rows = $db->query("
    SELECT DISTINCT categoria FROM articulos 
    WHERE categoria IS NOT NULL AND categoria != '' 
    ORDER BY categoria ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Inventario ya NO se carga aquí (se carga vía AJAX por categoría)
$todas_las_categorias = $db->query("SELECT DISTINCT categoria FROM articulos WHERE activo=1 ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);

// 2. Fetch Materiales (5.2)
$materiales = $db->query("SELECT * FROM materiales ORDER BY NOMBRE ASC")->fetchAll();

// 3. Procesar "Agregar Material" (5.1)
if (isset($_POST['agregar_material'])) {
    $ref = $_POST['ref_mat'];
    $foto_path = "";
    
    if (isset($_FILES['foto_mat']) && $_FILES['foto_mat']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto_mat']['name'], PATHINFO_EXTENSION);
        $target_dir = "../uploads/articulos/materiales/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $ref) . "." . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['foto_mat']['tmp_name'], $target_file)) {
            $foto_path = str_replace('../', '', $target_file);
        }
    }

    $stmt = $db->prepare("INSERT INTO materiales (REF_MAT, NOMBRE, STOCK_ACTUAL, PUNTO_PEDIDO, CATEGORIA, UNIDAD, FOTO, SUBCATEGORIA, MARCA, COLOR, DIMENSIONES, FESTIVIDAD) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $ref, 
        $_POST['nombre_mat'], 
        $_POST['stock_actual'], 
        $_POST['stock_minimo'], 
        $_POST['categoria_mat'],
        $_POST['unidad_mat'],
        $foto_path,
        $_POST['subcategoria_mat'] ?? '',
        $_POST['marca_mat'] ?? '',
        $_POST['color_mat'] ?? '',
        $_POST['dimensiones_mat'] ?? '',
        $_POST['festividad_mat'] ?? ''
    ]);
    header("Location: stock.php?tab=5.1&success=1");
    exit();
}

// 4. Stock bajo: ya no se filtra aquí (los artículos se cargan vía AJAX)
$stock_bajo = []; // placeholder – el conteo real vendrá por AJAX

$page_class = 'management-page';
include('../includes/header.php');

$current_tab = $_GET['tab'] ?? '5.1';
?>

<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/management_style.css?v=1.1">
<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/clientes.css?v=1.1">

<style>
    .tab-container { display: none; }
    .tab-container.active { display: block; }
    .nav-tabs-wow { display: flex; gap: 10px; margin-bottom: 2rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 10px; }
    .tab-link-wow { padding: 10px 20px; color: var(--text-gray); cursor: pointer; border-radius: 8px; transition: all 0.3s; font-weight: bold; }
    .tab-link-wow.active { background: var(--accent-gold); color: #000; }
    .tab-link-wow:hover:not(.active) { background: rgba(255,255,255,0.05); color: var(--text-white); }
    
    .material-card-wow { background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass); border-radius: 12px; overflow: hidden; transition: transform 0.3s; }
    .material-card-wow:hover { transform: translateY(-5px); border-color: var(--accent-gold); }
    .material-img-wow { width: 100%; height: 150px; object-fit: cover; background: #000; }

    /* Estilos para miniaturas de productos en tabla */
    .img-mini-wow {
        width: 40px;
        height: 40px;
        border-radius: 6px;
        object-fit: cover;
        border: 1px solid var(--border-glass);
        background: #000;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .img-mini-wow:hover {
        transform: scale(2.5);
        z-index: 10;
        box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        position: relative;
    }

    /* Toggle Inmediata */
    .switch-nox { position: relative; display: inline-block; width: 42px; height: 22px; }
    .switch-nox input { opacity: 0; width: 0; height: 0; }
    .slider-nox { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #4b5563; transition: .4s; border-radius: 34px; }
    .slider-nox:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider-nox { background-color: var(--accent-gold); }
    input:checked + .slider-nox:before { transform: translateX(20px); }
    
    .badge-inmediata { background: rgba(212,175,55,0.2); color: var(--accent-gold); border: 1px solid var(--accent-gold); padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
    .badge-stock-si { background: rgba(34,197,94,0.2); color: #22c55e; border: 1px solid #22c55e; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }

    /* Sistema de Colores por Valor */
    .st-zero { color: #ef4444 !important; font-weight: bold; }
    .st-final { color: var(--accent-gold) !important; font-weight: bold; }
    .st-semi { color: var(--accent-green) !important; font-weight: bold; }

    /* Scroll Horizontal Pro */
    .scroll-x-wow { 
        overflow-x: auto; 
        width: 100%; 
        padding-bottom: 20px; 
    }
    .scroll-x-wow::-webkit-scrollbar { height: 12px; }
    .scroll-x-wow::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 10px; }
    .scroll-x-wow::-webkit-scrollbar-thumb { background: var(--accent-gold); border-radius: 10px; border: 3px solid #0f172a; }
    
    .dropdown-multimedia { position: relative; display: inline-block; }
    .dropdown-content-multimedia { 
        display: none; position: absolute; right: 0; background-color: #1e293b; 
        min-width: 160px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.5); z-index: 1000; 
        border-radius: 8px; border: 1px solid var(--accent-gold); overflow: hidden;
    }
    .dropdown-multimedia:hover .dropdown-content-multimedia { display: block; }
    .dropdown-content-multimedia a { 
        color: white; padding: 10px 16px; text-decoration: none; display: flex; 
        align-items: center; gap: 8px; font-size: 0.85rem; transition: background 0.3s;
    }
    .dropdown-content-multimedia a:hover { background-color: var(--accent-gold); color: black; }
    
    .dl-row { display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .dl-row:last-child { border-bottom: none; }
    .dl-label { flex: 1; font-size: 0.75rem; color: rgba(255,255,255,0.6); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dl-icons { display: flex; gap: 10px; }
    .dl-icon { color: var(--accent-gold); font-size: 1rem; cursor: pointer; transition: transform 0.2s; }
    .dl-icon:hover { transform: scale(1.2); color: white; }
    .dl-icon.disabled { opacity: 0.2; cursor: not-allowed; pointer-events: none; }
    .dl-header { background: rgba(255,255,255,0.03); padding: 5px 16px; font-size: 0.65rem; font-weight: bold; color: var(--accent-gold); text-transform: uppercase; letter-spacing: 1px; }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<div class="panel-management">
    <div class="panel-header-wow">
        <h1>Control de Inventario</h1>
        <div style="display:flex; align-items:center; gap:15px;">
             <span class="badge-wow btn-red"><?php echo count($stock_bajo ?? []); ?> alertas</span>
        </div>
    </div>

    <!-- Navegación de Pestañas -->
    <div class="nav-tabs-wow">
        <div class="tab-link-wow <?php echo ($current_tab == '5.1') ? 'active' : ''; ?>" onclick="switchTab('tab-5-1', this)">5.1 Agregar Materiales</div>
        <div class="tab-link-wow <?php echo ($current_tab == '5.2') ? 'active' : ''; ?>" onclick="switchTab('tab-5-2', this)">5.2 Galería Materiales</div>
        <div class="tab-link-wow <?php echo ($current_tab == '5.3') ? 'active' : ''; ?>" onclick="switchTab('tab-5-3', this)">5.3 Tabla Artículos</div>
        <div class="tab-link-wow <?php echo ($current_tab == '5.4') ? 'active' : ''; ?>" onclick="switchTab('tab-5-4', this); mapaInit();">🗺️ 5.4 Mapa de Almacén</div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid var(--accent-green); color: var(--accent-green); padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <i class="fas fa-check-circle"></i> Acción realizada correctamente.
        </div>
    <?php endif; ?>

    <!-- Pestaña 5.1: Agregar Materiales -->
    <div id="tab-5-1" class="tab-container <?php echo ($current_tab == '5.1') ? 'active' : ''; ?>">
        <div class="table-container-wow" style="max-width: 600px; margin: 0 auto; padding: 2rem;">
            <h3 style="color: var(--accent-gold); margin-bottom: 1.5rem;">Añadir Nueva Materia Prima</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="nox-form-group">
                    <label>Referencia (REF_MAT)</label>
                    <input type="text" name="ref_mat" id="new_ref_mat" class="input-wow" required style="width:100%;">
                </div>
                <div class="nox-form-group">
                    <label>Nombre del Material</label>
                    <input type="text" name="nombre_mat" class="input-wow" required style="width:100%;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="nox-form-group">
                        <label>Stock Inicial</label>
                        <input type="number" step="0.01" name="stock_actual" class="input-wow" value="0" style="width:100%;">
                    </div>
                    <div class="nox-form-group">
                        <label>Stock Mínimo (Alerta)</label>
                        <input type="number" step="0.01" name="stock_minimo" class="input-wow" value="5" style="width:100%;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="nox-form-group">
                        <label>Categoría</label>
                        <input type="text" name="categoria_mat" id="new_cat_mat" oninput="autoGenRef()" class="input-wow" placeholder="Madera, Pintura..." style="width:100%;">
                    </div>
                    <div class="nox-form-group">
                        <label>Subcategoría</label>
                        <input type="text" name="subcategoria_mat" id="new_sub_mat" oninput="autoGenRef()" class="input-wow" placeholder="Pino, Acrílica..." style="width:100%;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="nox-form-group">
                        <label>Marca</label>
                        <input type="text" name="marca_mat" id="new_marca_mat" oninput="autoGenRef()" class="input-wow" style="width:100%;">
                    </div>
                    <div class="nox-form-group">
                        <label>Color</label>
                        <input type="text" name="color_mat" class="input-wow" style="width:100%;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="nox-form-group">
                        <label>Dimensiones / Tamaño</label>
                        <input type="text" name="dimensiones_mat" class="input-wow" style="width:100%;">
                    </div>
                    <div class="nox-form-group">
                        <label>Festividad / Temporada</label>
                        <input type="text" name="festividad_mat" class="input-wow" placeholder="Navidad, Halloween..." style="width:100%;">
                    </div>
                </div>
                <div class="nox-form-group">
                    <label>Unidad</label>
                    <input type="text" name="unidad_mat" class="input-wow" placeholder="Uds, m, kg..." style="width:100%;">
                </div>
                <div class="nox-form-group" style="margin-top:10px;">
                    <label>Foto del Material</label>
                    <input type="file" name="foto_mat" accept="image/*" class="input-wow" style="width:100%;">
                </div>
                <button type="submit" name="agregar_material" class="btn-premium-wow btn-gold" style="width: 100%; margin-top: 1rem; justify-content:center;">
                    💾 Guardar Material
                </button>
            </form>
        </div>
    </div>

    <!-- Pestaña 5.2: Galería de Materiales -->
    <div id="tab-5-2" class="tab-container <?php echo ($current_tab == '5.2') ? 'active' : ''; ?>">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
            <?php foreach($materiales as $m): 
                $foto = $m['FOTO'] ?: 'img/logo.png';
                if (strpos($foto, 'C:\\') !== false) {
                    $parts = explode('\\', $foto);
                    $foto = 'uploads/articulos/materiales/' . end($parts);
                }
            ?>
                <div class="material-card-wow">
                    <img src="<?php echo $foto; ?>" class="material-img-wow" onerror="this.src='img/logo.png'">
                    <div style="padding: 1rem;">
                        <h4 style="color: var(--text-white); margin-bottom: 5px;"><?php echo htmlspecialchars($m['NOMBRE']); ?></h4>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="color: <?php echo ($m['STOCK_ACTUAL'] <= $m['PUNTO_PEDIDO']) ? '#ef4444' : 'var(--accent-gold)'; ?>; font-weight: bold;">
                                <?php echo number_format($m['STOCK_ACTUAL'], 2); ?> <?php echo $m['UNIDAD']; ?>
                            </span>
                            <small style="opacity: 0.5; font-size: 0.7rem;"><?php echo $m['REF_MAT']; ?></small>
                        </div>
                        <button onclick="abrirModalMat(this)" data-ref="<?php echo $m['REF_MAT']; ?>" class="btn-premium-wow" style="width:100%; font-size:0.75rem; background: rgba(255,255,255,0.05);">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Pestaña 5.3: Tabla de Artículos -->
    <div id="tab-5-3" class="tab-container <?php echo ($current_tab == '5.3') ? 'active' : ''; ?>">

        <!-- ── Barra superior: categoría + buscador + botón sync ── -->
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap;">

            <!-- Desplegable de categoría -->
            <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                <i class="fas fa-layer-group" style="color:var(--accent-gold);"></i>
                <select id="selCategoria" class="input-wow"
                        style="min-width:180px; padding:0.4rem 0.8rem; cursor:pointer;"
                        onchange="cargarArticulosPorCategoria()">
                    <option value="">— Elige una categoría —</option>
                    <option value="__TODAS__" <?php echo (isset($_GET['categoria']) && $_GET['categoria'] == '__TODAS__') ? 'selected' : ''; ?>>📦 Todas las categorías</option>
                    <?php foreach($categorias_rows as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($_GET['categoria']) && $_GET['categoria'] == $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; background:rgba(255,255,255,0.03); padding:0.4rem 0.8rem; border-radius:8px; border:1px solid var(--border-glass); font-size:0.85rem;">
                <input type="checkbox" id="chkSoloBase" checked onchange="cargarArticulosPorCategoria()" style="accent-color:var(--accent-gold); width:16px; height:16px;">
                <span>Solo Base</span>
            </label>

            <!-- FILTRO SOLO INMEDIATOS (Nuevo) -->
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; background:rgba(255,255,255,0.03); padding:0.4rem 0.8rem; border-radius:8px; border:1px solid var(--border-glass); font-size:0.85rem;">
                <input type="checkbox" id="chkSoloInmediatos" onchange="filtrarArticulos()" style="accent-color:var(--accent-gold); width:16px; height:16px;">
                <span title="Stock > 0 o marcado manualmente">⚡ Solo inmediatos</span>
            </label>

            <!-- Buscador local -->
            <input type="text" id="busqArticulos" class="input-wow" placeholder="Buscar por referencia o nombre..."
                   style="flex:1; min-width:160px;" oninput="filtrarArticulos()" disabled
                   title="Primero selecciona una categoría">

            <!-- Botón sincronización (solo manual) -->
            <button id="btnSyncProductos" onclick="sincronizarProductos()"
                    class="btn-premium-wow"
                    style="background: linear-gradient(135deg,#7c3aed,#a855f7); color:#fff; white-space:nowrap; display:flex; align-items:center; gap:8px; padding:0.5rem 1.2rem;">
                <i class="fas fa-sync-alt" id="iconSync"></i>
                <span id="lblSync">Sincronizar</span>
            </button>

            <!-- NUEVO ARTICULO -->
            <button onclick="abrirModalMaestro()" class="btn-premium-wow btn-gold" style="white-space:nowrap;">
                <i class="fas fa-plus"></i> Nuevo
            </button>
        </div>

        <!-- Aviso de resultado de sincronización -->
        <div id="syncResultado" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:1rem; font-weight:bold;"></div>

        <!-- Estado vacío inicial -->
        <div id="estadoVacio53" style="text-align:center; padding:4rem 2rem; color:rgba(255,255,255,0.3);">
            <i class="fas fa-box-open" style="font-size:3rem; margin-bottom:1rem; display:block;"></i>
            <p style="font-size:1.1rem;">Selecciona una categoría para cargar los artículos</p>
        </div>

        <!-- Spinner de carga -->
        <div id="spinner53" style="display:none; text-align:center; padding:3rem;">
            <i class="fas fa-circle-notch" style="font-size:2.5rem; color:var(--accent-gold); animation: spin 1s linear infinite;"></i>
            <p style="margin-top:1rem; opacity:0.6;">Cargando artículos...</p>
        </div>

        <!-- Tabla (oculta hasta que se cargue una categoría) -->
        <div class="table-container-wow scroll-x-wow" id="contenedorTabla53" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.8rem;">
                <span id="infoConteo53" style="font-size:0.85rem; opacity:0.6;"></span>
            </div>
            <table class="table-wow" id="tablaArticulos" style="min-width: 1200px;">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Referencia</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Stock (Final)</th>
                        <th>Stock (Semi)</th>
                        <th>Calculado</th>
                        <th style="min-width:110px;">INMEDIATA</th>
                        <th>Descargas</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="tbody53">
                    <!-- Filas cargadas por AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Pestaña 5.4: Mapa de Almacén ── -->
    <div id="tab-5-4" class="tab-container <?php echo ($current_tab == '5.4') ? 'active' : ''; ?>">

        <!-- Barra Superior -->
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:1.5rem;">
            <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:220px;"
                 title="Escribe SKU o nombre para localizar en el mapa">
                <i class="fas fa-search" style="color:var(--accent-gold);"></i>
                <input type="text" id="mapaBuscador" class="input-wow"
                       placeholder="Buscar SKU o nombre…" style="flex:1; font-size:0.9rem;"
                       oninput="mapaBuscar(this.value)">
            </div>
            <div id="mapaBuscadorResultado"
                 style="font-size:0.85rem; color:var(--accent-gold); min-width:200px;"></div>
            <button onclick="abrirModalEstanteria()" class="btn-premium-wow btn-gold"
                    style="white-space:nowrap;">
                <i class="fas fa-plus"></i> Nueva Estantería
            </button>
        </div>

        <!-- Spinner / vacío -->
        <div id="mapaSpinner" style="text-align:center; padding:3rem;">
            <i class="fas fa-circle-notch"
               style="font-size:2.5rem; color:var(--accent-gold); animation:spin 1s linear infinite;"></i>
            <p style="margin-top:1rem; opacity:0.6;">Cargando mapa…</p>
        </div>
        <div id="mapaVacio" style="display:none; text-align:center; padding:4rem 2rem; color:rgba(255,255,255,0.3);">
            <i class="fas fa-warehouse" style="font-size:3rem; margin-bottom:1rem; display:block;"></i>
            <p style="font-size:1.1rem;">No hay estanterías. Crea la primera con el botón de arriba.</p>
        </div>

        <!-- Contenedor del Mapa -->
        <div id="mapaContenedor" style="display:none; display:flex; gap:20px; flex-wrap:nowrap; overflow-x:auto; padding-bottom:12px;">
            <!-- Estanterías generadas por JS -->
        </div>

        <!-- Panel lateral deslizante -->
        <div id="mapaPanelLateral" style="
                position:fixed; top:0; right:-420px; width:400px; max-width:95vw;
                height:100vh; overflow-y:auto;
                background:#1a1c2c; border-left:1px solid var(--border-glass);
                box-shadow:-8px 0 30px rgba(0,0,0,0.5);
                transition: right 0.35s cubic-bezier(.4,0,.2,1);
                z-index:3000; padding:0;
            ">
            <div class="modal-header-wow" style="position:sticky; top:0; z-index:1;">
                <div>
                    <h2 style="font-size:1rem; margin:0;">📦 Detalle de Celda</h2>
                    <div id="panelCeldaEtiqueta"
                         style="color:var(--accent-gold); font-size:0.85rem; margin-top:2px;"></div>
                </div>
                <button onclick="cerrarPanelLateral()"
                        style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">×</button>
            </div>
            <div id="panelCeldaContenido" style="padding:20px;"></div>
        </div>
        <!-- Overlay para cerrar panel -->
        <div id="mapaOverlay" onclick="cerrarPanelLateral()"
             style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:2999;"></div>
    </div>
</div><!-- /panel-management -->

<div id="modalMat" class="modal-overlay-wow" style="display: none;" onclick="if(event.target==this) this.style.display='none'">
    <div class="modal-content-wow" style="max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header-wow">
            <h2>Editar Material</h2>
            <button onclick="document.getElementById('modalMat').style.display='none'" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <form id="formEditMat" style="padding: 20px;">
            <input type="hidden" name="ref_mat_old" id="edit_ref_old">
            <div class="nox-form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" id="edit_mat_nombre" class="input-wow" style="width:100%;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="nox-form-group">
                    <label>Stock Actual</label>
                    <input type="number" step="0.01" name="stock" id="edit_mat_stock" class="input-wow" style="width:100%;">
                </div>
                <div class="nox-form-group">
                    <label>Stock Mínimo (Alerta)</label>
                    <input type="number" step="0.01" name="punto_pedido" id="edit_mat_punto" class="input-wow" style="width:100%;">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="nox-form-group">
                    <label>Categoría</label>
                    <input type="text" id="edit_mat_categoria" class="input-wow" style="width:100%;">
                </div>
                <div class="nox-form-group">
                    <label>Subcategoría</label>
                    <input type="text" id="edit_mat_subcategoria" class="input-wow" style="width:100%;">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="nox-form-group">
                    <label>Marca</label>
                    <input type="text" id="edit_mat_marca" class="input-wow" style="width:100%;">
                </div>
                <div class="nox-form-group">
                    <label>Color</label>
                    <input type="text" id="edit_mat_color" class="input-wow" style="width:100%;">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="nox-form-group">
                    <label>Dimensiones</label>
                    <input type="text" id="edit_mat_dimensiones" class="input-wow" style="width:100%;">
                </div>
                <div class="nox-form-group">
                    <label>Festividad</label>
                    <input type="text" id="edit_mat_festividad" class="input-wow" style="width:100%;">
                </div>
            </div>
            <div class="nox-form-group">
                <label>Unidad</label>
                <input type="text" id="edit_mat_unidad" class="input-wow" style="width:100%;">
            </div>
            <button type="button" onclick="guardarMaterial()" class="btn-premium-wow btn-gold" style="width:100%; margin-top:20px; justify-content:center;">💾 Actualizar</button>
        </form>
    </div>
</div>

<!-- Modal Despiece (BOM) -->
<div id="modalBOM" class="modal-overlay-wow" style="display: none;" onclick="if(event.target==this) this.style.display='none'">
    <div class="modal-content-wow" style="max-width: 600px;">
        <div class="modal-header-wow">
            <h2>Despiece: <span id="bom_nombre" style="color: var(--accent-gold);"></span></h2>
            <div id="bom_notice" style="font-size:0.7rem; color:rgba(255,255,255,0.4); margin-top:5px; font-weight:normal;"></div>
            <button onclick="document.getElementById('modalBOM').style.display='none'" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <div id="bom_lista" style="margin-bottom: 20px;"></div>
            <hr style="border:0; border-top:1px solid var(--border-glass); margin: 20px 0;">
            <h4 style="color: var(--accent-gold); margin-bottom: 10px;">Vincular Material</h4>
            <div style="display: flex; gap: 10px;">
                <select id="sel_mat" class="input-wow" style="flex: 2;">
                    <?php foreach($materiales as $m): ?>
                        <option value="<?php echo $m['REF_MAT']; ?>"><?php echo htmlspecialchars($m['NOMBRE']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" id="bom_qty" class="input-wow" value="1" step="0.01" style="flex: 1;" placeholder="Cant.">
                <button onclick="vincularMaterial()" class="btn-premium-wow btn-gold">Link</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reponer Avanzado -->
<div id="modalReponer" class="modal-overlay-wow" onclick="if(event.target==this) cerrarModalReponer()">
    <div class="modal-content-wow" style="max-width: 520px;">
        <div class="modal-header-wow">
            <div>
                <h2 style="margin:0;">Reponer Stock</h2>
                <div id="rep_nombre" style="color:var(--accent-gold); font-size:0.85rem; margin-top:3px; font-family:sans-serif; font-weight:normal;"></div>
            </div>
            <button onclick="cerrarModalReponer()" style="background:none;border:none;color:white;font-size:2rem;cursor:pointer;line-height:1;padding:0;">&times;</button>
        </div>
        <!-- Zona scrollable -->
        <div style="overflow-y:auto; flex:1; padding:16px 20px;">
            <div id="reponer_lista_variantes"></div>
            <!-- El calculador se mantiene como sugerencia visual -->
            <div id="calc_container" style="margin-top:14px; padding:12px; background:rgba(200,155,60,0.08); border:1px dashed var(--accent-gold); border-radius:8px;">
                <h4 style="color:var(--accent-gold); margin:0 0 5px; font-size:0.85rem;"><i class="fas fa-calculator"></i> Sugerencia por Materiales</h4>
                <div id="calc_detalles" style="font-size:0.85rem;">Cargando...</div>
                <button onclick="aplicarCalculo()" class="btn-premium-wow" style="margin-top:8px; font-size:0.75rem; background:var(--accent-gold); color:#000; padding:0.3rem 0.8rem;">Usar cantidad</button>
            </div>
        </div>
        <!-- Botones fijos al fondo -->
        <div style="padding:12px 20px; display:flex; gap:10px; border-top:1px solid var(--border-glass); flex-shrink:0;">
            <button onclick="guardarStock()" class="btn-premium-wow btn-gold" style="flex:2; justify-content:center;">💾 Confirmar Reposición</button>
            <button onclick="cerrarModalReponer()" class="btn-premium-wow" style="flex:1; justify-content:center; background:#4b5563;">Cancelar</button>
        </div>
    </div>
</div>

<script>
let artActual = null;

async function autoGenRef() {
    const marca = document.getElementById('new_marca_mat').value;
    const cat = document.getElementById('new_cat_mat').value;
    const sub = document.getElementById('new_sub_mat').value;
    
    if (marca.length >= 1 || cat.length >= 1 || sub.length >= 1) {
        const res = await fetch(`api/index.php?ruta=stock&accion=get_next_ref_mat&marca=${encodeURIComponent(marca)}&cat=${encodeURIComponent(cat)}&sub=${encodeURIComponent(sub)}`);
        const data = await res.json();
        if (data.next_ref) {
            document.getElementById('new_ref_mat').value = data.next_ref;
        }
    }
}

function cerrarModalReponer() { 
    document.getElementById('modalReponer').style.display = 'none'; 
}

function abrirReponer_mostrar() {
    document.getElementById('modalReponer').style.display = 'flex';
}

function switchTab(tabId, el) {
    document.querySelectorAll('.tab-container').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-link-wow').forEach(l => l.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    el.classList.add('active');

    // Update URL without reload
    const tabNum = tabId.replace('tab-', '').replace('-', '.');
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabNum);
    window.history.replaceState({}, '', url);
}

function filtrarArticulos() {
    const txt = document.getElementById('busqArticulos').value.toLowerCase();
    const soloInmediatos = document.getElementById('chkSoloInmediatos').checked;
    let countInmediatos = 0;
    let totalCargados = 0;

    document.querySelectorAll('.articulo-row').forEach(row => {
        const isInmediato = row.dataset.inmediato === '1';
        const searchMatch = row.innerText.toLowerCase().includes(txt);
        const filterMatch = !soloInmediatos || isInmediato;
        
        const visible = searchMatch && filterMatch;
        row.style.display = visible ? '' : 'none';

        if (visible) {
            totalCargados++;
            if (isInmediato) countInmediatos++;
        }
    });

    document.getElementById('infoConteo53').innerHTML = `${totalCargados} artículo(s) cargado(s) · <span style="color:var(--accent-gold)">⚡ ${countInmediatos} disponibles para entrega inmediata</span>`;
}

// ── Carga de artículos por categoría (AJAX) ──────────────────────────────
const BASE_PATH = '<?php echo $base_path; ?>';

async function cargarArticulosPorCategoria() {
    const cat = document.getElementById('selCategoria').value;
    const tbody = document.getElementById('tbody53');
    const contenedor = document.getElementById('contenedorTabla53');
    const vacio = document.getElementById('estadoVacio53');
    const spinner = document.getElementById('spinner53');
    const busq = document.getElementById('busqArticulos');

    if (!cat) {
        contenedor.style.display = 'none';
        vacio.style.display = 'block';
        spinner.style.display = 'none';
        busq.disabled = true;
        return;
    }

    // Mostrar spinner
    vacio.style.display = 'none';
    contenedor.style.display = 'none';
    spinner.style.display = 'block';
    busq.disabled = true;
    tbody.innerHTML = '';

    const soloBase = document.getElementById('chkSoloBase').checked;

    try {
        let url = cat === '__TODAS__'
            ? `api/index.php?ruta=articulos&solo_base=${soloBase}`
            : `api/index.php?ruta=articulos&categoria=${encodeURIComponent(cat)}&solo_base=${soloBase}`;

        const res = await fetch(url);
        const arts = await res.json();

        if (!Array.isArray(arts) || arts.length === 0) {
            spinner.style.display = 'none';
            vacio.innerHTML = '<i class="fas fa-search" style="font-size:2.5rem; display:block; margin-bottom:1rem;"></i><p>No hay artículos en esta categoría.</p>';
            vacio.style.display = 'block';
            return;
        }

        arts.forEach(a => {
            let img = resolverRuta(a.foto_portada);
            const tr = document.createElement('tr');
            tr.className = 'articulo-row';
            tr.innerHTML = `
                <td><img src="${img}" class="img-mini-wow" onerror="this.src='${BASE_PATH}img/logo.png'"></td>
                <td>${escH(a.referencia)}</td>
                <td>${escH(a.nombre)}</td>
                <td><span style="background:rgba(212,175,55,0.15); color:var(--accent-gold); padding:2px 8px; border-radius:20px; font-size:0.75rem;">${escH(a.categoria || '—')}</span></td>
                <td class="${(a.stock_final == 0) ? 'st-zero' : 'st-final'}">${a.stock_final ?? 0} uds</td>
                <td class="${(a.stock_semi == 0) ? 'st-zero' : 'st-semi'}">${a.stock_semi ?? 0} uds</td>
                <td class="calc-stock" data-ref="${escH(a.referencia)}"><i class="fas fa-circle-notch" style="animation:spin 1s linear infinite; opacity:0.5;"></i></td>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label class="switch-nox">
                            <input type="checkbox" ${a.entrega_inmediata == 1 ? 'checked' : ''} onchange="toggleInmediata('${escH(a.referencia)}', this)">
                            <span class="slider-nox"></span>
                        </label>
                        ${a.entrega_inmediata == 1 ? '<span class="badge-inmediata" title="Marcado manualmente">⚡</span>' : ''}
                        ${(a.stock_final > 0) ? '<span class="badge-stock-si" title="Stock real > 0">✅ Stock</span>' : ''}
                    </div>
                </td>
                <td>
                    <div class="dropdown-multimedia">
                        <button class="btn-mini" style="border-color:var(--accent-gold); color:var(--accent-gold);">
                            <i class="fas fa-download"></i> Descargas
                        </button>
                        <div class="dropdown-content-multimedia" style="min-width: 220px;">
                            <div class="dl-header">Modelo Base</div>
                            <div class="dl-row">
                                <span class="dl-label">Principal</span>
                                <div class="dl-icons">
                                    <a href="api/index.php?ruta=stock&accion=descargar_ficha&ref=${a.referencia}&marca=${encodeURIComponent(a.marca)}&base=${encodeURIComponent(a.sku_base)}" target="_blank" class="dl-icon" title="Descargar Ficha">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    <a href="${img}" download="PORTADA_${a.referencia}.jpg" class="dl-icon" title="Descargar Foto">
                                        <i class="fas fa-image"></i>
                                    </a>
                                    <a href="${a.mockup ? resolverRuta(a.mockup) : '#'}" download="MOCKUP_${a.referencia}.jpg" class="dl-icon ${a.mockup ? '' : 'disabled'}" title="${a.mockup ? 'Descargar Mockup' : 'Sin Mockup'}">
                                        <i class="fas fa-palette"></i>
                                    </a>
                                </div>
                            </div>
                            
                            ${(a.variantes && a.variantes.length > 0) ? `
                                <div class="dl-header">Variantes (Colores)</div>
                                ${a.variantes.map(v => {
                                    const color = v.color || v.nombre.split(' ').pop();
                                    const vImg = resolverRuta(v.foto_portada);
                                    return `
                                    <div class="dl-row">
                                        <span class="dl-label">${escH(color)}</span>
                                        <div class="dl-icons">
                                            <a href="api/index.php?ruta=stock&accion=descargar_ficha&ref=${v.referencia}&marca=${encodeURIComponent(a.marca)}&base=${encodeURIComponent(a.sku_base)}&color=${encodeURIComponent(color)}" target="_blank" class="dl-icon" title="Descargar Ficha">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            <a href="${v.foto_portada ? vImg : '#'}" download="FOTO_${v.referencia}.jpg" class="dl-icon ${v.foto_portada ? '' : 'disabled'}" title="${v.foto_portada ? 'Descargar Foto' : 'Sin Foto'}">
                                                <i class="fas fa-image"></i>
                                            </a>
                                            <a href="${v.mockup ? resolverRuta(v.mockup) : '#'}" download="MOCKUP_${v.referencia}.jpg" class="dl-icon ${v.mockup ? '' : 'disabled'}" title="${v.mockup ? 'Descargar Mockup' : 'Sin Mockup'}">
                                                <i class="fas fa-palette"></i>
                                            </a>
                                        </div>
                                    </div>`;
                                }).join('')}
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td style="display:flex; gap:5px; flex-wrap:nowrap;">
                    <button onclick="abrirModalReponer(this)" data-ref="${escH(a.referencia)}"
                            class="btn-premium-wow btn-gold"
                            style="padding:0.4rem 0.6rem; font-size:0.75rem;" title="Reponer Stock">
                        <i class="fas fa-warehouse"></i>
                    </button>
                    <button onclick="abrirModalMaestro('${escH(a.referencia)}')"
                            class="btn-premium-wow"
                            style="padding:0.4rem 0.6rem; font-size:0.75rem; background:#4b5563;" title="Editar Maestro">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="abrirModalBOM(this)" data-ref="${escH(a.referencia)}"
                            class="btn-premium-wow"
                            style="padding:0.4rem 0.6rem; font-size:0.75rem; background:#3b82f6;" title="Despiece">
                        <i class="fas fa-link"></i>
                    </button>
                </td>`;
            tbody.appendChild(tr);
        });

        document.getElementById('infoConteo53').textContent = `${arts.length} artículo(s) cargado(s)`;
        spinner.style.display = 'none';
        contenedor.style.display = 'block';
        busq.disabled = false;
        busq.value = '';

        // Cargar cálculos BOM para las filas visibles
        document.querySelectorAll('.calc-stock').forEach(td => {
            fetch(`api/index.php?ruta=stock&accion=calcular&ref=${td.dataset.ref}`)
                .then(r => r.json())
                .then(data => {
                    td.innerHTML = data.max_posible !== undefined ? data.max_posible + ' uds' : 'N/A';
                })
                .catch(() => { td.innerHTML = 'N/A'; });
        });

        // Marcar filas como inmediata para el filtro JS
        document.querySelectorAll('.articulo-row').forEach((row, i) => {
            const art = arts[i];
            const isInmediato = (art.stock_final > 0 || art.entrega_inmediata == 1);
            row.dataset.inmediato = isInmediato ? "1" : "0";
        });

        filtrarArticulos();

    } catch(e) {
        spinner.style.display = 'none';
        vacio.innerHTML = `<i class="fas fa-exclamation-triangle" style="font-size:2rem; display:block; margin-bottom:1rem; color:#ef4444;"></i><p style="color:#ef4444;">Error al cargar: ${e.message}</p>`;
        vacio.style.display = 'block';
    }
}

function resolverRuta(foto) {
    if (!foto) return BASE_PATH + 'img/logo.png';
    const clean = foto.replace(/\\/g, '/');
    
    // Si es ruta absoluta de Windows
    if (/^[a-zA-Z]:\//.test(clean)) {
        // Caso 1: Ya contiene 'uploads/'
        const idx = clean.toLowerCase().indexOf('uploads/');
        if (idx !== -1) return BASE_PATH + clean.substring(idx);

        // Caso 2: Es un material (contiene 'materiales')
        if (clean.toLowerCase().includes('/materiales/')) {
            return BASE_PATH + 'uploads/articulos/materiales/' + clean.split('/').pop();
        }

        // Caso 3: Es un artículo (fallback general a imagenes)
        const idxImg = clean.toLowerCase().indexOf('/imagenes/');
        if (idxImg !== -1) return BASE_PATH + 'uploads/articulos' + clean.substring(idxImg);

        return BASE_PATH + 'uploads/articulos/imagenes/' + clean.split('/').pop();
    }
    
    // Si es ruta relativa
    if (clean.startsWith('uploads/')) return BASE_PATH + clean;
    return BASE_PATH + 'uploads/' + clean;
}

function escH(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function toggleInmediata(ref, chk) {
    try {
        const val = chk.checked ? 1 : 0;
        const res = await fetch('api/index.php?ruta=articulos&accion=toggle_inmediata', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ referencia: ref, valor: val })
        });
        const data = await res.json();
        if (data.ok) {
            // Actualizar el dataset para el filtro sin recargar
            const row = chk.closest('tr');
            // Necesitamos saber el stock_final actual
            const stockFinal = parseInt(row.querySelector('td:nth-child(5)').innerText) || 0;
            row.dataset.inmediato = (val == 1 || stockFinal > 0) ? "1" : "0";
            
            // Actualizar insignias visuales (un poco hacky pero efectivo)
            const container = chk.parentNode.parentNode;
            container.querySelectorAll('.badge-inmediata').forEach(b => b.remove());
            if (val == 1) {
                const badge = document.createElement('span');
                badge.className = 'badge-inmediata';
                badge.title = "Marcado manualmente";
                badge.innerText = "⚡";
                container.appendChild(badge);
            }
            
            filtrarArticulos();
        } else {
            alert('Error al guardar cambio');
            chk.checked = !chk.checked;
        }
    } catch (e) {
        alert('Error de conexión');
        chk.checked = !chk.checked;
    }
}

// DOMContentLoaded: inicializa el estado si viene por URL
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    const cat = urlParams.get('categoria');
    
    // Si hay categoría y estamos en la pestaña 5.3, cargar automáticamente
    if (tab === '5.3' && cat) {
        cargarArticulosPorCategoria();
    }
});

// ── Sincronización productos → articulos ──────────────────────────────────
async function sincronizarProductos() {
    const btn  = document.getElementById('btnSyncProductos');
    const icon = document.getElementById('iconSync');
    const res  = document.getElementById('syncResultado');

    // Animación de carga
    btn.disabled = true;
    icon.style.animation = 'spin 1s linear infinite';

    try {
        const r    = await fetch('api/index.php?ruta=sync_productos', { method: 'POST' });
        const data = await r.json();

        res.style.display = 'block';

        if (data.ok) {
            if (data.importados > 0 || data.eliminados > 0) {
                res.style.background  = 'rgba(16,185,129,0.15)';
                res.style.border      = '1px solid #10b981';
                res.style.color       = '#10b981';
                res.innerHTML = `✅ ${data.mensaje}`;

                // Ocultar badge y actualizar etiqueta
                document.getElementById('badgePendientes').style.display = 'none';
                document.getElementById('lblSync').textContent = 'Sincronizar desde Productos';

                // Recargar la tabla tras 1.5 s para mostrar los nuevos artículos preserving state
                setTimeout(() => {
                    const cat = document.getElementById('selCategoria').value;
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', '5.3');
                    if (cat) url.searchParams.set('categoria', cat);
                    window.location.href = url.toString();
                }, 1500);
            } else {
                res.style.background  = 'rgba(212,175,55,0.15)';
                res.style.border      = '1px solid var(--accent-gold)';
                res.style.color       = 'var(--accent-gold)';
                res.innerHTML = `ℹ️ ${data.mensaje}`;
            }

            // Mostrar errores si los hay
            if (data.errores && data.errores.length > 0) {
                res.innerHTML += `<br><small style="opacity:.7">Errores: ${data.errores.join(', ')}</small>`;
            }
        } else {
            throw new Error(data.error || 'Error desconocido');
        }
    } catch (e) {
        res.style.display     = 'block';
        res.style.background  = 'rgba(239,68,68,0.15)';
        res.style.border      = '1px solid #ef4444';
        res.style.color       = '#ef4444';
        res.innerHTML = `❌ Error al sincronizar: ${e.message}`;
    } finally {
        btn.disabled = false;
        icon.style.animation = '';
    }
}


async function abrirModalReponer(btn) {
    const ref = btn.dataset.ref;
    const res = await fetch(`api/index.php?ruta=stock&accion=get_art&ref=${ref}`);
    artActual = await res.json();
    
    document.getElementById('rep_nombre').innerText = artActual.nombre;
    const container = document.getElementById('reponer_lista_variantes');
    
    // Construir lista de entradas (Base + Variantes)
    let html = `
        <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:8px; margin-bottom:15px; border:1px solid rgba(212,175,55,0.3);">
            <div style="font-weight:bold; margin-bottom:12px; font-size:1.1rem; color: #fff;">PRINCIPAL: <span style="background: rgba(212,175,55,0.1); padding: 2px 8px; border-radius: 6px; border: 1px solid var(--accent-gold); color: #fff;">${escH(artActual.referencia)}</span></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="nox-form-group">
                    <label style="font-size:0.75rem;">Total Final (Listos)</label>
                    <input type="number" class="input-wow rep-final" data-ref="${artActual.referencia}" value="${artActual.stock_final || 0}" style="width:100%;">
                </div>
                <div class="nox-form-group">
                    <label style="font-size:0.75rem;">Total Semi (Tintar)</label>
                    <input type="number" class="input-wow rep-semi" data-ref="${artActual.referencia}" value="${artActual.stock_semi || 0}" style="width:100%;">
                </div>
            </div>
        </div>
    `;

    if (artActual.variantes && artActual.variantes.length > 0) {
        html += `<h4 style="margin: 15px 0 10px 0; color:rgba(255,255,255,0.5); font-size:0.9rem;">Variantes y Patrones vinculados:</h4>`;
        artActual.variantes.forEach(v => {
            html += `
                <div style="background:rgba(255,255,255,0.02); padding:10px; border-radius:8px; margin-bottom:8px; border:1px solid var(--border-glass);">
                    <div style="font-size:0.95rem; margin-bottom:10px; color:rgba(255,255,255,0.7);">${escH(v.nombre)} <span style="color:#fff; font-weight:bold; margin-left:5px; font-size: 1rem;">(${v.referencia})</span></div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <input type="number" class="input-wow rep-final" data-ref="${v.referencia}" placeholder="Total Final" value="${v.stock_final || 0}" style="width:100%; font-size:0.8rem;">
                        <input type="number" class="input-wow rep-semi" data-ref="${v.referencia}" placeholder="Total Semi" value="${v.stock_semi || 0}" style="width:100%; font-size:0.8rem;">
                    </div>
                </div>
            `;
        });
    }

    container.innerHTML = html;
    document.getElementById('modalReponer').style.display = 'flex';
    
    // Cálculo sugerido (solo para el base por ahora como referencia)
    document.getElementById('calc_detalles').innerHTML = "Calculando...";
    const resB = await fetch(`api/index.php?ruta=stock&accion=calcular&ref=${ref}`);
    const dataB = await resB.json();
    document.getElementById('calc_detalles').innerHTML = dataB.max_posible !== undefined ? 
        `Materia prima disponible para <strong>${dataB.max_posible}</strong> uds.` : "Sin receta.";
    document.getElementById('calc_detalles').dataset.max = dataB.max_posible || 0;
}

function aplicarCalculo() { 
    const max = document.getElementById('calc_detalles').dataset.max || 0;
    // Aplicar al primer input (el base)
    const firstInput = document.querySelector('.rep-final');
    if (firstInput) firstInput.value = max;
}

async function guardarStock() {
    const articulos = [];
    document.querySelectorAll('.rep-final').forEach(input => {
        const ref = input.dataset.ref;
        const total_final = parseInt(input.value) || 0;
        // Buscar el semi correspondiente
        const total_semi = parseInt(document.querySelector(`.rep-semi[data-ref="${ref}"]`).value) || 0;
        
        articulos.push({ referencia: ref, total_final, total_semi });
    });

    if (articulos.length === 0) {
        cerrarModalReponer();
        return;
    }

    await fetch(`api/index.php?ruta=stock`, {
        method: 'PUT',
        body: JSON.stringify({ articulos })
    });
    
    // Redirigir manteniendo el estado
    const cat = document.getElementById('selCategoria').value;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', '5.3');
    if (cat) url.searchParams.set('categoria', cat);
    url.searchParams.set('success', '1');
    window.location.href = url.toString();
}

async function abrirModalMat(btn) {
    const ref = btn.dataset.ref;
    const res = await fetch(`api/index.php?ruta=stock&accion=get_mat&ref=${ref}`);
    const m = await res.json();
    document.getElementById('edit_ref_old').value = m.REF_MAT;
    document.getElementById('edit_mat_nombre').value = m.NOMBRE;
    document.getElementById('edit_mat_stock').value = m.STOCK_ACTUAL;
    document.getElementById('edit_mat_punto').value = m.PUNTO_PEDIDO || 0;
    document.getElementById('edit_mat_categoria').value = m.CATEGORIA || '';
    document.getElementById('edit_mat_subcategoria').value = m.SUBCATEGORIA || '';
    document.getElementById('edit_mat_marca').value = m.MARCA || '';
    document.getElementById('edit_mat_color').value = m.COLOR || '';
    document.getElementById('edit_mat_dimensiones').value = m.DIMENSIONES || '';
    document.getElementById('edit_mat_festividad').value = m.FESTIVIDAD || '';
    document.getElementById('edit_mat_unidad').value = m.UNIDAD || '';
    document.getElementById('modalMat').style.display = 'flex';
}

async function guardarMaterial() {
    await fetch('api/index.php?ruta=stock&accion=edit_mat', {
        method: 'POST',
        body: JSON.stringify({
            ref: document.getElementById('edit_ref_old').value,
            nombre: document.getElementById('edit_mat_nombre').value,
            stock: document.getElementById('edit_mat_stock').value,
            punto_pedido: document.getElementById('edit_mat_punto').value,
            categoria: document.getElementById('edit_mat_categoria').value,
            subcategoria: document.getElementById('edit_mat_subcategoria').value,
            marca: document.getElementById('edit_mat_marca').value,
            color: document.getElementById('edit_mat_color').value,
            dimensiones: document.getElementById('edit_mat_dimensiones').value,
            festividad: document.getElementById('edit_mat_festividad').value,
            unidad: document.getElementById('edit_mat_unidad').value
        })
    });
    
    // Redirigir manteniendo el estado (pestaña 5.2)
    const url = new URL(window.location.href);
    url.searchParams.set('tab', '5.2');
    url.searchParams.set('success', '1');
    window.location.href = url.toString();
}

async function abrirModalBOM(btn) {
    const ref = btn.dataset.ref;
    const res = await fetch(`api/index.php?ruta=stock&accion=get_art&ref=${ref}`);
    artActual = await res.json();
    
    document.getElementById('bom_nombre').innerText = artActual.nombre;
    document.getElementById('bom_notice').innerText = (artActual.es_variante === 'BASE') 
        ? "⚠️ Los cambios se aplicarán automáticamente a todas las variantes." 
        : "Nota: Este es un artículo variante.";
    document.getElementById('modalBOM').style.display = 'flex';
    cargarBOM(artActual.referencia);
}

async function cargarBOM(ref) {
    const res = await fetch(`api/index.php?ruta=stock&accion=ver_bom&ref=${ref}`);
    const data = await res.json();
    let html = '<table style="width:100%; border-collapse:collapse;">';
    data.forEach(p => {
        html += `<tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
            <td style="padding:8px 0;">${p.NOMBRE}</td>
            <td style="padding:8px 10px; color:var(--accent-gold);">${p.CANTIDAD}</td>
            <td style="text-align:right;"><button onclick="quitarBOM(${p.id})" style="color:#ef4444; background:none; border:none; cursor:pointer;">&times;</button></td>
        </tr>`;
    });
    html += '</table>';
    document.getElementById('bom_lista').innerHTML = data.length ? html : '<p style="opacity:0.5;">Sin materiales.</p>';
}

async function vincularMaterial() {
    await fetch('api/index.php?ruta=stock&accion=add_bom', {
        method: 'POST',
        body: JSON.stringify({
            ref: artActual.referencia,
            ref_mat: document.getElementById('sel_mat').value,
            qty: document.getElementById('bom_qty').value
        })
    });
    cargarBOM(artActual.referencia);
}

async function quitarBOM(id) {
    if(!confirm("¿Quitar este material del despiece?")) return;
    await fetch(`api/index.php?ruta=stock&accion=del_bom&id=${id}`, { method: 'DELETE' });
    if(typeof artActual !== 'undefined') cargarBOM(artActual.referencia);
}
</script>

<!-- ── MODAL MAESTRO ARTÍCULO (CRUD) ── -->
<div id="modalMaestro" class="modal-overlay-wow" style="display: none;" onclick="if(event.target==this) this.style.display='none'">
    <div class="modal-content-wow" style="max-width: 800px; padding:0;">
        <div class="modal-header-wow">
            <h2><i class="fas fa-edit"></i> Gestión Maestro de Artículo</h2>
            <button onclick="document.getElementById('modalMaestro').style.display='none'" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <form id="formMaestro" style="padding: 24px; display:grid; grid-template-columns: 1fr 1fr; gap:20px; max-height: 75vh; overflow-y: auto;">
            <input type="hidden" id="m_es_edicion" name="es_edicion" value="0">
            <input type="hidden" id="m_galeria_actual" name="galeria_actual" value="">
            
            <div class="nox-form-group">
                <label>Referencia (SKU)</label>
                <input type="text" id="m_referencia" name="referencia" class="input-wow" style="width:100%;" required>
            </div>
            <div class="nox-form-group">
                <label>SKU Base (Agrupador)</label>
                <input type="text" id="m_sku_base" name="sku_base" class="input-wow" style="width:100%;" placeholder="Se rellena solo si es BASE">
            </div>
            
            <div class="nox-form-group" style="grid-column: span 2;">
                <label>Nombre Artículo</label>
                <input type="text" id="m_nombre" name="nombre" class="input-wow" style="width:100%;" required>
            </div>
            
            <div class="nox-form-group">
                <label>Categoría</label>
                <input type="text" id="m_categoria" name="categoria" list="listaCategorias" class="input-wow" style="width:100%;">
            </div>
            <div class="nox-form-group">
                <label>Marca / Colección</label>
                <select id="m_marca" name="marca" class="input-wow" style="width:100%;">
                    <option value="NOXERTEZ">NOXERTEZ</option>
                    <option value="CANDLE HOLDER OF THE SOUL">CANDLE HOLDER OF THE SOUL</option>
                    <option value="THE SECRET ZEN GARDEN">THE SECRET ZEN GARDEN</option>
                </select>
            </div>
            
            <div class="nox-form-group">
                <label>Precio (€)</label>
                <input type="number" step="0.01" id="m_precio" name="precio" class="input-wow" style="width:100%;">
            </div>
            <div class="nox-form-group">
                <label>Stock Mínimo (Alerta)</label>
                <input type="number" id="m_stock_minimo" name="stock_minimo" class="input-wow" style="width:100%;" value="0">
            </div>
            
            <div class="nox-form-group">
                <label>Tipo de Artículo</label>
                <select id="m_tipo" name="es_variante" class="input-wow" style="width:100%;" onchange="checkSKUBase()">
                    <option value="BASE">MODELO BASE (Principal)</option>
                    <option value="VARIANTE">VARIANTE (Color/Talla)</option>
                </select>
            </div>
            <div class="nox-form-group">
                <label>Entrega Inmediata</label>
                <select id="m_inmediata" name="entrega_inmediata" class="input-wow" style="width:100%;">
                    <option value="0">No (Bajo Pedido)</option>
                    <option value="1">Sí (En Stock)</option>
                </select>
            </div>
            
            <div class="nox-form-group" style="grid-column: span 2;">
                <label>Descripción</label>
                <textarea id="m_descripcion" name="descripcion" class="input-wow" style="width:100%; height:80px;"></textarea>
            </div>
            
            <div class="nox-form-group">
                <label>Imagen Portada</label>
                <div style="display:flex; gap:10px; align-items:center;">
                    <img id="m_img_preview" src="img/logo.png" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                    <input type="file" id="m_imagen" name="imagen" accept="image/*" class="input-wow" style="flex:1;" onchange="previewImg(this)">
                </div>
            </div>
            <div class="nox-form-group">
                <label>Mockup</label>
                <input type="file" id="m_mockup_file" name="mockup_file" accept="image/*" class="input-wow" style="width:100%;">
            </div>
            
            <div class="nox-form-group" style="grid-column: span 2;">
                <label>Galería (Puedes seleccionar varias)</label>
                <input type="file" id="m_galeria_files" name="galeria_files[]" accept="image/*" multiple class="input-wow" style="width:100%;">
                <small id="info_galeria" style="opacity:0.5; display:block; margin-top:5px;"></small>
            </div>
            
            <div style="grid-column: span 2; display:flex; gap:12px; margin-top:10px;">
                <button type="button" onclick="document.getElementById('modalMaestro').style.display='none'" class="btn-premium-wow" style="flex:1; background:#4b5563; justify-content:center;">Cancelar</button>
                <button type="button" onclick="guardarMaestro()" id="btnGuardarMaestro" class="btn-premium-wow btn-gold" style="flex:2; justify-content:center;">💾 Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function resolverRutaFicha(ref, marca, skuBase) {
    const mapping = {"CANDLE HOLDER OF THE SOUL": "CANDLEHOLDER", "THE SECRET ZEN GARDEN": "THE_SECRET_ZEN_GARDEN", "NOXERTEZ": "NOXERTEZ"};
    const marcaFolder = mapping[marca] || marca.replace(/\s+/g, '_');
    const skuFolder = skuBase.replace(/[\/\\]/g, '_').replace(/P\d+$/, '');
    return `uploads/articulos/${marcaFolder}/${skuFolder}/FICHA_${ref}.png`;
}

function checkSKUBase() {
    const tipo = document.getElementById('m_tipo').value;
    const ref = document.getElementById('m_referencia').value;
    const base = document.getElementById('m_sku_base');
    if (tipo === 'BASE' && ref) {
        base.value = ref;
    }
}

document.getElementById('m_referencia').addEventListener('input', checkSKUBase);

function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('m_img_preview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

async function abrirModalMaestro(ref = null) {
    const modal = document.getElementById('modalMaestro');
    const form = document.getElementById('formMaestro');
    form.reset();
    document.getElementById('m_img_preview').src = BASE_PATH + 'img/logo.png';
    document.getElementById('m_es_edicion').value = ref ? "1" : "0";
    document.getElementById('m_referencia').readOnly = !!ref;
    document.getElementById('info_galeria').innerText = "";

    if (ref) {
        try {
            const res = await fetch(`api/index.php?ruta=articulos&referencia=${encodeURIComponent(ref)}`);
            const a = await res.json();
            if (a) {
                document.getElementById('m_referencia').value = a.referencia;
                document.getElementById('m_sku_base').value = a.sku_base;
                document.getElementById('m_nombre').value = a.nombre;
                document.getElementById('m_descripcion').value = a.descripcion;
                document.getElementById('m_precio').value = a.precio;
                document.getElementById('m_stock_minimo').value = a.stock_minimo || 0;
                document.getElementById('m_categoria').value = a.categoria;
                document.getElementById('m_tipo').value = a.es_variante || 'BASE';
                document.getElementById('m_marca').value = a.marca || 'NOXERTEZ';
                document.getElementById('m_inmediata').value = a.entrega_inmediata;
                document.getElementById('m_img_preview').src = resolverRuta(a.foto_portada);
                document.getElementById('m_galeria_actual').value = a.galeria || "";
                if(a.galeria) document.getElementById('info_galeria').innerText = "Imágenes actuales: " + a.galeria.split(', ').length;
            }
        } catch (e) { alert("Error cargando datos: " + e.message); }
    }
    
    modal.style.display = 'flex';
}

async function guardarMaestro() {
    const btn = document.getElementById('btnGuardarMaestro');
    btn.disabled = true;
    btn.innerText = "⏳ Guardando...";
    
    const form = document.getElementById('formMaestro');
    const fd = new FormData(form);
    
    try {
        const res = await fetch('api/index.php?ruta=articulos', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('modalMaestro').style.display = 'none';
            if (typeof cargarArticulosPorCategoria === 'function') {
                cargarArticulosPorCategoria();
            } else {
                location.reload();
            }
        } else {
            alert("Error: " + (data.error || "No se pudo guardar"));
        }
    } catch (e) { alert("Error de conexión: " + e.message); }
    
    btn.disabled = false;
    btn.innerText = "💾 Guardar Cambios";
}
</script>


<!-- ══ MODAL: QR de Celda ══ -->
<div id="modalQR" class="modal-overlay-wow" style="display:none;" onclick="if(event.target==this)this.style.display='none'">
  <div class="modal-content-wow" style="max-width:380px;">
    <div class="modal-header-wow">
      <h2>📦 Código QR de Caja</h2>
      <button onclick="document.getElementById('modalQR').style.display='none'" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:24px;" id="modalQRcontenido">
      <!-- Relleno por JS -->
    </div>
  </div>
</div>

<!-- ══ MODAL: Nueva / Editar Estantería ══ -->
<div id="modalEstanteria" class="modal-overlay-wow" style="display:none;" onclick="if(event.target==this)this.style.display='none'">
  <div class="modal-content-wow" style="max-width:420px;">
    <div class="modal-header-wow">
      <h2 id="tituloModalEst">Nueva Estantería</h2>
      <button onclick="document.getElementById('modalEstanteria').style.display='none'" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:24px;display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" id="estId" value="0">
      <div class="nox-form-group">
        <label>Nombre (ej: Estantería A)</label>
        <input type="text" id="estNombre" class="input-wow" style="width:100%;" placeholder="Estantería A">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="nox-form-group">
          <label>Nº Baldas</label>
          <input type="number" id="estBaldas" class="input-wow" style="width:100%;" value="3" min="1" max="20">
        </div>
        <div class="nox-form-group">
          <label>Nº Columnas</label>
          <input type="number" id="estColumnas" class="input-wow" style="width:100%;" value="4" min="1" max="20">
        </div>
      </div>
      <div class="nox-form-group">
        <label>Orden (número para ordenar)</label>
        <input type="number" id="estOrden" class="input-wow" style="width:100%;" value="0">
      </div>
      <div style="display:flex;gap:10px;margin-top:6px;">
        <button onclick="guardarEstanteria()" class="btn-premium-wow btn-gold" style="flex:2;justify-content:center;">💾 Guardar</button>
        <button onclick="document.getElementById('modalEstanteria').style.display='none'" class="btn-premium-wow" style="flex:1;background:#4b5563;justify-content:center;">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: Asignar contenido a celda ══ -->
<div id="modalCelda" class="modal-overlay-wow" style="display:none;" onclick="if(event.target==this)this.style.display='none'">
  <div class="modal-content-wow" style="max-width:480px;">
    <div class="modal-header-wow">
      <div>
        <h2 style="font-size:1rem;margin:0;">Editar Celda</h2>
        <div id="modalCeldaEtiqueta" style="color:var(--accent-gold);font-size:0.85rem;"></div>
      </div>
      <button onclick="document.getElementById('modalCelda').style.display='none'" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" id="celdaPosId">
      <input type="hidden" id="celdaEtiquetaVal">
      <div class="nox-form-group">
        <label>Tipo de Caja</label>
        <select id="celdaTipoCaja" class="input-wow" style="width:100%;">
          <option value="">— Sin asignar —</option>
          <option value="negra">Negra</option>
          <option value="verde">Verde</option>
          <option value="transparente">Transparente</option>
          <option value="otra">Otra</option>
        </select>
      </div>
      <div class="nox-form-group">
        <label>Notas</label>
        <textarea id="celdaNotas" class="input-wow" style="width:100%;height:70px;"></textarea>
      </div>
      <hr style="border:0;border-top:1px solid var(--border-glass);">
      <h4 style="color:var(--accent-gold);margin:0 0 4px;">Asignar Material a esta celda</h4>
      <div style="display:flex;gap:8px;">
        <select id="celdaMatRef" class="input-wow" style="flex:2;">
          <option value="">— Selecciona material —</option>
          <?php foreach($materiales as $m): ?>
            <option value="<?php echo $m['REF_MAT']; ?>"><?php echo htmlspecialchars($m['NOMBRE']); ?></option>
          <?php endforeach; ?>
        </select>
        <select id="celdaEstado" class="input-wow" style="flex:1;">
          <option value="B">B – Base</option>
          <option value="S">S – Sin tintar</option>
          <option value="T">T – Terminado</option>
        </select>
        <button onclick="asignarMaterialCelda()" class="btn-premium-wow btn-gold" style="white-space:nowrap;">+ Asignar</button>
      </div>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button onclick="guardarCelda()" class="btn-premium-wow btn-gold" style="flex:2;justify-content:center;">💾 Guardar Notas/Caja</button>
        <button onclick="document.getElementById('modalCelda').style.display='none'" class="btn-premium-wow" style="flex:1;background:#4b5563;justify-content:center;">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
// ════════════════════════════════════════════════════════════
//  MÓDULO 5.4 — MAPA DE ALMACÉN
// ════════════════════════════════════════════════════════════

// Colores de estado (punto indicador)
const DOT_COLOR   = { T:'#00ff88', S:'#ffcc00', B:'#4488ff' };
const LABEL_ESTADO = { T:'Terminado', S:'Sin tintar', B:'Base/Bruto' };

// Paleta de cajas por tipo
const BOX_STYLE = {
  negra        : { bg:'#2a2a2a', border:'#444444' },
  verde        : { bg:'#1a3a1a', border:'#2d5a2d' },
  transparente : { bg:'rgba(180,220,255,0.07)', border:'#8899aa' },
  otra         : { bg:'#1a1a1a', border:'#555', dashed:true },
  ''           : { bg:'#111218', border:'#2a2a3a', dashed:true }
};

// Colores herencia (compatibilidad panel lateral)
const COLOR_CELDA = { T:'#16a34a', S:'#ca8a04', B:'#2563eb', '':'#334155' };

let mapaData = [];       // estanterías completas
let mapaCargado = false;
let buscarTimeout = null;
let celdaDestacada = null; // {estId, posId}

// ── Init: carga al entrar en pestaña 5.4 ────────────────────
function mapaInit() {
  if (mapaCargado) return;
  mapaCargado = true;
  cargarMapa();
}
// Auto-init si la pestaña viene activa por URL
document.addEventListener('DOMContentLoaded', () => {
  if ((new URLSearchParams(window.location.search).get('tab')) === '5.4') mapaInit();
});

// ── Carga datos del servidor ─────────────────────────────────
async function cargarMapa() {
  document.getElementById('mapaSpinner').style.display = 'block';
  document.getElementById('mapaVacio').style.display = 'none';
  document.getElementById('mapaContenedor').style.display = 'none';
  try {
    const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_estanterias`);
    mapaData = await r.json();
    renderMapa();
  } catch(e) {
    document.getElementById('mapaSpinner').innerHTML = `<p style="color:#ef4444;">Error cargando mapa: ${e.message}</p>`;
  }
}

// ── Renderiza todas las estanterías (diseño taller) ─────────
function renderMapa() {
  const cont = document.getElementById('mapaContenedor');
  document.getElementById('mapaSpinner').style.display = 'none';
  if (!mapaData || mapaData.length === 0) {
    document.getElementById('mapaVacio').style.display = 'block';
    return;
  }
  cont.innerHTML = '';
  cont.style.display = 'flex';
  cont.style.alignItems = 'flex-start';
  cont.style.gap = '28px';
  cont.style.flexWrap = 'wrap';

  mapaData.forEach(est => {
    // ── Bloque raíz: marco de madera ────────────────────────
    const mueble = document.createElement('div');
    mueble.style.cssText = [
      'flex-shrink:0',
      'display:inline-flex',
      'flex-direction:column',
      'border-left:14px solid #5C3A1E',
      'border-right:14px solid #5C3A1E',
      'border-bottom:18px solid #3d2410',
      'border-top:6px solid #7a4f2a',
      'border-radius:6px',
      'box-shadow:4px 6px 28px rgba(0,0,0,0.7), inset 2px 0 6px rgba(0,0,0,0.4), inset -2px 0 6px rgba(0,0,0,0.4)',
      'background:#0d0d14',
      'padding:0',
      'min-width:180px'
    ].join(';');

    // ── Cabecera (nombre + botones) ──────────────────────────
    const header = document.createElement('div');
    header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:8px 10px 6px;background:linear-gradient(180deg,#1a0f05 0%,#110a03 100%);border-bottom:2px solid #5C3A1E;';
    header.innerHTML = `
      <span style="color:var(--accent-gold);font-size:0.85rem;font-weight:bold;font-family:'Cinzel',serif;letter-spacing:0.05em;">${escH(est.nombre)}</span>
      <span style="font-size:0.6rem;opacity:0.45;color:#aaa;margin:0 8px;">${est.num_baldas}×${est.num_columnas}</span>
      <div style="display:flex;gap:4px;">
        <button onclick="editarEstanteria(${est.id})" title="Editar"
          style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#aaa;border-radius:4px;padding:2px 6px;cursor:pointer;font-size:0.7rem;line-height:1.4;">✏️</button>
        <button onclick="borrarEstanteria(${est.id},'${escH(est.nombre)}')" title="Eliminar"
          style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#ef4444;border-radius:4px;padding:2px 6px;cursor:pointer;font-size:0.7rem;line-height:1.4;">🗑</button>
      </div>`;
    mueble.appendChild(header);

    // ── Filas de baldas ──────────────────────────────────────
    for (let b = 1; b <= est.num_baldas; b++) {

      // Fila contenedora (etiqueta + cajas)
      const fila = document.createElement('div');
      fila.style.cssText = 'display:flex;align-items:stretch;';

      // Etiqueta de balda (lateral izquierdo)
      const lblBalda = document.createElement('div');
      lblBalda.style.cssText = 'writing-mode:vertical-rl;text-orientation:mixed;transform:rotate(180deg);font-size:0.5rem;color:rgba(255,255,255,0.25);padding:4px 2px;letter-spacing:0.1em;white-space:nowrap;background:rgba(0,0,0,0.2);';
      lblBalda.textContent = `B${b}`;
      fila.appendChild(lblBalda);

      // Contenedor de cajas en esta balda
      const bandeja = document.createElement('div');
      bandeja.style.cssText = 'display:flex;gap:5px;padding:6px 8px;background:#0d0d14;flex:1;';

      for (let c = 1; c <= est.num_columnas; c++) {
        const pos = (est.posiciones || []).find(p => p.balda == b && p.columna == c);
        bandeja.appendChild(crearCelda(pos, est, b, c));
      }
      fila.appendChild(bandeja);
      mueble.appendChild(fila);

      // ── Balda física (franja de madera) ─────────────────────
      if (b < est.num_baldas) {
        const balda = document.createElement('div');
        balda.style.cssText = 'height:10px;background:linear-gradient(180deg,#7a4f2a 0%,#4a2e0f 100%);box-shadow:0 2px 5px rgba(0,0,0,0.5),inset 0 1px 2px rgba(255,200,120,0.15);flex-shrink:0;';
        mueble.appendChild(balda);
      }
    }

    // ── Base del mueble (suelo) ──────────────────────────────
    const base = document.createElement('div');
    base.style.cssText = 'height:6px;background:linear-gradient(180deg,#2a1505 0%,#110800 100%);border-radius:0 0 2px 2px;';
    mueble.appendChild(base);

    cont.appendChild(mueble);
  });

  if (celdaDestacada) destacarCelda(celdaDestacada);
  // Resaltar celda desde URL (?celda=X)
  checkURLCelda();
}

// ── Crea una celda-caja individual ───────────────────────────
function crearCelda(pos, est, b, c) {
  const cell = document.createElement('div');
  const tipo  = pos ? (pos.tipo_caja || '') : '';
  const style = BOX_STYLE[tipo] || BOX_STYLE[''];
  const etq   = pos ? escH(pos.etiqueta) : `${b}-${c}`;
  const posId = pos ? pos.id : `${est.id}-${b}-${c}`;

  cell.id = `celda-${posId}`;
  cell.title = pos ? `${etq}${pos.notas ? '\n'+pos.notas : ''}` : etq;

  cell.style.cssText = [
    'position:relative',
    'width:46px', 'height:64px',
    `background:${style.bg}`,
    `border:1px ${style.dashed ? 'dashed' : 'solid'} ${style.border}`,
    'border-radius:4px',
    'cursor:pointer',
    'display:flex', 'flex-direction:column',
    'align-items:center', 'justify-content:flex-end',
    'padding:0 2px 4px',
    'box-sizing:border-box',
    'transition:filter 0.15s, transform 0.15s, box-shadow 0.15s',
    'overflow:hidden',
    'flex-shrink:0'
  ].join(';');

  // Indicador de Art\u00edculos (Si tiene stock > 0)
  if (pos && pos.has_art) {
    cell.style.background = 'rgba(212, 175, 55, 0.15)'; // Tintado dorado suave
    const artIcon = document.createElement('div');
    artIcon.textContent = '\uD83D\uDCE6'; // Icono paquete
    artIcon.style.cssText = 'position:absolute;top:10px;left:50%;transform:translateX(-50%);font-size:0.8rem;opacity:0.8;pointer-events:none;z-index:2;';
    cell.appendChild(artIcon);
  }

  // Punto de estado (esquina superior derecha)
  if (pos && pos._estado) {
    const dot = document.createElement('div');
    const dc = DOT_COLOR[pos._estado] || null;
    if (dc) {
      dot.style.cssText = `position:absolute;top:4px;right:4px;width:7px;height:7px;border-radius:50%;background:${dc};box-shadow:0 0 5px ${dc};`;
      cell.appendChild(dot);
    }
  }

  // Icono QR (esquina superior izquierda) — solo si la posición existe
  if (pos) {
    const qrBtn = document.createElement('div');
    qrBtn.textContent = '\uD83D\uDDB6';
    qrBtn.title = 'Imprimir QR de esta celda';
    qrBtn.style.cssText = 'position:absolute;top:2px;left:3px;font-size:0.55rem;opacity:0.35;cursor:pointer;line-height:1;transition:opacity 0.15s;';
    qrBtn.onclick = (e) => { e.stopPropagation(); abrirModalQR(pos, est.nombre, est); };
    qrBtn.onmouseenter = () => { qrBtn.style.opacity = '1'; };
    qrBtn.onmouseleave = () => { qrBtn.style.opacity = '0.35'; };
    cell.appendChild(qrBtn);
  }

  // Etiqueta centrada
  const lbl = document.createElement('span');
  lbl.style.cssText = 'font-size:0.52rem;color:#999;text-align:center;line-height:1.2;word-break:break-all;';
  lbl.textContent = etq;
  cell.appendChild(lbl);

  // Eventos
  if (pos) {
    cell.onclick   = () => abrirPanelCelda(pos.id, etq, est.nombre);
    cell.ondblclick = () => abrirModalCelda(pos);
  }

  const glowColor = style.dashed ? 'rgba(255,255,255,0.08)' : style.border;
  cell.onmouseenter = () => {
    cell.style.filter = `brightness(1.4) drop-shadow(0 0 6px ${glowColor})`;
    cell.style.transform = 'translateY(-2px)';
    cell.style.zIndex = '10';
  };
  cell.onmouseleave = () => {
    cell.style.filter = '';
    cell.style.transform = '';
    cell.style.zIndex = '';
  };

  return cell;
}

function getColorCelda(pos) {
  // Mantener compatibilidad con panel lateral
  const map = { negra:'#1e293b', verde:'#16a34a', transparente:'#0ea5e9', otra:'#7c3aed' };
  return pos && pos.tipo_caja ? map[pos.tipo_caja] || COLOR_CELDA[''] : COLOR_CELDA[''];
}

// ── Panel lateral: contenido + formularios de asignaci\u00f3n ────
let panelCeldaData = null;

async function abrirPanelCelda(posId, etiqueta, estNombre) {
  document.getElementById('panelCeldaEtiqueta').textContent = `${estNombre} \u203a ${etiqueta}`;
  document.getElementById('panelCeldaContenido').innerHTML = '<p style="opacity:0.5;text-align:center;padding:2rem;">Cargando...</p>';
  document.getElementById('mapaPanelLateral').style.right = '0';
  document.getElementById('mapaOverlay').style.display = 'block';
  try {
    const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_celda&id=${posId}`);
    const d = await r.json();
    if (d.error) throw new Error(d.error);
    panelCeldaData = d;
    let html = '';

    // -- Materiales asignados --
    if (d.materiales && d.materiales.length) {
      html += '<p style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.1em;opacity:.4;margin:0 0 6px;">Materiales</p>';
      html += '<div style="display:flex;flex-direction:column;gap:5px;margin-bottom:14px;">';
      d.materiales.forEach(m => {
        const dc = DOT_COLOR[m.estado_stock]||'#555';
        const img = resolverRuta(m.FOTO);
        html += `<div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:9px 12px;border:1px solid var(--border-glass);display:flex;justify-content:space-between;align-items:center;">
          <div style="display:flex;align-items:center;gap:10px;">
            <img src="${img}" style="width:36px;height:36px;border-radius:6px;object-fit:cover;border:1px solid rgba(255,255,255,0.1);" onerror="this.src='${BASE_PATH}img/logo.png'">
            <div>
              <div style="font-weight:bold;font-size:0.83rem;">${escH(m.NOMBRE)}</div>
              <div style="font-size:0.7rem;opacity:.5;margin-top:1px;">${escH(m.REF_MAT)} \u00b7 ${m.STOCK_ACTUAL} ${escH(m.UNIDAD||'')}</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:5px;">
            <span style="background:${dc};width:7px;height:7px;border-radius:50%;display:inline-block;box-shadow:0 0 5px ${dc};flex-shrink:0;"></span>
            <button onclick="quitarMaterialCelda('${escH(m.REF_MAT)}',${posId},'${escH(etiqueta)}','${escH(estNombre)}')"
              style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1rem;line-height:1;padding:0 2px;" title="Quitar">\u2715</button>
          </div>
        </div>`;
      });
      html += '</div>';
    }

    // -- Artículos asignados --
    if (d.articulos && d.articulos.length) {
      html += '<p style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.1em;opacity:.4;margin:0 0 6px;">Art\u00edculos</p>';
      html += '<div style="display:flex;flex-direction:column;gap:5px;margin-bottom:14px;">';
      d.articulos.forEach(a => {
        const img = resolverRuta(a.foto_portada);
        html += `<div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:9px 12px;border:1px solid var(--border-glass);display:flex;justify-content:space-between;align-items:center;">
          <div style="display:flex;align-items:center;gap:10px;">
            <img src="${img}" style="width:36px;height:36px;border-radius:6px;object-fit:cover;border:1px solid rgba(255,255,255,0.1);" onerror="this.src='${BASE_PATH}img/logo.png'">
            <div>
              <div style="font-weight:bold;font-size:0.83rem;">${escH(a.nombre)}</div>
              <div style="font-size:0.7rem;opacity:.5;margin-top:1px;">${escH(a.referencia)} \u00b7 Final:${a.stock_final} Semi:${a.stock_semi}</div>
            </div>
          </div>
          <button onclick="quitarArticuloCelda('${escH(a.referencia)}',${posId},'${escH(etiqueta)}','${escH(estNombre)}')"
            style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1rem;line-height:1;padding:0 2px;" title="Quitar">\u2715</button>
        </div>`;
      });
      html += '</div>';
    }

    if (!d.materiales.length && !d.articulos.length) {
      html += '<p style="opacity:.3;text-align:center;padding:0.8rem 0;font-size:0.83rem;">\u2014 Celda vac\u00eda \u2014</p>';
    }

    // -- Notas --
    if (d.posicion && d.posicion.notas) {
      html += `<div style="margin-bottom:12px;padding:9px;background:rgba(255,255,255,0.03);border-radius:8px;font-size:0.78rem;opacity:.6;">\ud83d\udcdd ${escH(d.posicion.notas)}</div>`;
    }

    html += '<hr style="border:0;border-top:1px solid var(--border-glass);margin:10px 0 12px;">';

    // -- Form: A\u00f1adir Material --
    html += '<p style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.1em;color:var(--accent-gold);margin:0 0 7px;">+ A\u00f1adir Material (Base)</p>';
    html += '<div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;">';
    html += '<select id="panelSelMat" class="input-wow" style="flex:1;min-width:140px;font-size:0.78rem;padding:.4rem .6rem;"><option value="">— Material \u2014</option>';
    (d.todos_mat||[]).forEach(m => { html += `<option value="${escH(m.REF_MAT)}">${escH(m.NOMBRE)}</option>`; });
    html += '</select>';
    html += `<button onclick="panelAsignarMaterial('${escH(etiqueta)}',${posId},'${escH(estNombre)}')" class="btn-premium-wow btn-gold" style="padding:.4rem 1rem;font-size:0.78rem;">A\u00f1adir</button>`;
    html += '</div>';

    // -- Form: A\u00f1adir Art\u00edculo --
    html += '<p style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.1em;color:var(--accent-gold);margin:0 0 7px;">+ A\u00f1adir Art\u00edculo</p>';
    html += '<div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;">';
    html += '<select id="panelSelArt" class="input-wow" style="flex:3;min-width:140px;font-size:0.78rem;padding:.4rem .6rem;"><option value="">— Art\u00edculo \u2014</option>';
    (d.todos_art||[]).forEach(a => {
      html += `<option value="${escH(a.referencia)}">${escH(a.nombre)} \u00b7 F:${a.stock_final} S:${a.stock_semi}</option>`;
    });
    html += '</select>';
    html += `<button onclick="panelAsignarArticulo('${escH(etiqueta)}',${posId},'${escH(estNombre)}')" class="btn-premium-wow btn-gold" style="padding:.4rem .7rem;font-size:0.78rem;">A\u00f1adir</button>`;
    html += '</div>';

    // -- Notas (editable) --
    html += '<hr style="border:0;border-top:1px solid var(--border-glass);margin:6px 0 10px;">';
    html += '<p style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.1em;opacity:.4;margin:0 0 5px;">Notas</p>';
    html += `<textarea id="panelNotas" class="input-wow" style="width:100%;height:50px;font-size:0.78rem;box-sizing:border-box;">${escH(d.posicion.notas||'')}</textarea>`;
    html += `<button onclick="panelGuardarNotas(${posId})" class="btn-premium-wow" style="width:100%;margin-top:5px;justify-content:center;background:#2d3748;font-size:0.78rem;">\ud83d\udcbe Guardar notas</button>`;

    document.getElementById('panelCeldaContenido').innerHTML = html;
  } catch(e) {
    document.getElementById('panelCeldaContenido').innerHTML = `<p style="color:#ef4444;padding:1rem;">Error: ${e.message}</p>`;
  }
}

async function panelAsignarMaterial(etiqueta, posId, estNombre) {
  const ref = document.getElementById('panelSelMat').value;
  const estado = 'B'; // Los materiales siempre se agregan como Base
  if (!ref) { alert('Selecciona un material'); return; }
  const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_actualizar_ubicacion`, {
    method:'POST', body: JSON.stringify({ ref, ubicacion: etiqueta, estado_stock: estado })
  });
  const d = await r.json();
  if (d.ok) abrirPanelCelda(posId, etiqueta, estNombre);
  else alert('Error: ' + (d.error||'desconocido'));
}

async function panelAsignarArticulo(etiqueta, posId, estNombre) {
  const ref = document.getElementById('panelSelArt').value;
  if (!ref) { alert('Selecciona un art\u00edculo'); return; }
  const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_asignar_articulo`, {
    method:'POST', body: JSON.stringify({ ref, ubicacion: etiqueta })
  });
  const d = await r.json();
  if (d.ok) abrirPanelCelda(posId, etiqueta, estNombre);
  else alert('Error: ' + (d.error||'desconocido'));
}

async function quitarMaterialCelda(ref, posId, etiqueta, estNombre) {
  if (!confirm(`\u00bfQuitar "${ref}" de la celda ${etiqueta}?`)) return;
  await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_actualizar_ubicacion`, {
    method:'POST', body: JSON.stringify({ ref, ubicacion: '', estado_stock: null })
  });
  abrirPanelCelda(posId, etiqueta, estNombre);
}

async function quitarArticuloCelda(ref, posId, etiqueta, estNombre) {
  if (!confirm(`\u00bfQuitar "${ref}" de esta celda?`)) return;
  await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_asignar_articulo`, {
    method:'POST', body: JSON.stringify({ ref, ubicacion: '' })
  });
  abrirPanelCelda(posId, etiqueta, estNombre);
}

async function panelGuardarNotas(posId) {
  const notas = document.getElementById('panelNotas').value.trim()||null;
  const tipoCaja = panelCeldaData?.posicion?.tipo_caja||null;
  const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_asignar`, {
    method:'POST', body: JSON.stringify({ posicion_id: posId, tipo_caja: tipoCaja, notas })
  });
  const d = await r.json();
  if (d.ok) { alert('\u2705 Notas guardadas'); } else alert('Error al guardar');
}

// ── Resaltado por parámetro URL (?celda=A2-C3) ──────────────
function checkURLCelda() {
  const params = new URLSearchParams(window.location.search);
  const celdaParam = params.get('celda');
  if (!celdaParam) return;
  // Buscar la posición con esa etiqueta en mapaData
  mapaData.forEach(est => {
    (est.posiciones || []).forEach(pos => {
      if (pos.etiqueta.toUpperCase() === celdaParam.toUpperCase()) {
        celdaDestacada = pos.id;
        destacarCelda(pos.id);
        // Scroll suave a la celda
        setTimeout(() => {
          const el = document.getElementById(`celda-${pos.id}`);
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          // Abrir panel lateral automáticamente
          abrirPanelCelda(pos.id, pos.etiqueta, est.nombre);
        }, 400);
      }
    });
  });
}

// ── Modal QR ──────────────────────────────────────────────────
let qrEstActual = null;

function qrUrl(etiqueta) {
  const base = `${location.protocol}//${location.hostname}`;
  // Siempre usamos caja.php para evitar problemas de reescritura de URL (.htaccess)
  const path = (location.hostname === 'localhost' || location.hostname.includes('127.')) ?
    `${location.pathname.replace(/pages\/.*$/, '')}caja.php` :
    '/caja.php';
  return `${base}${path}?id=${encodeURIComponent(etiqueta)}`;
}

function generarQR(etiqueta, size) {
  return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(qrUrl(etiqueta))}`;
}

function abrirModalQR(pos, estNombre, estObj) {
  const etiqueta = pos.etiqueta;
  qrEstActual = estObj;
  const url = qrUrl(etiqueta);
  const qrSrc = generarQR(etiqueta, 220);
  document.getElementById('modalQRcontenido').innerHTML = `
    <div style="text-align:center;">
      <img src="${qrSrc}" width="220" height="220"
           style="border-radius:12px;border:6px solid #fff;display:block;margin:0 auto;"
           onerror="this.alt='Error generando QR'">
      <div style="margin-top:14px;font-size:1.4rem;font-weight:800;letter-spacing:.1em;color:#fff;">${escH(etiqueta)}</div>
      <div style="font-size:0.75rem;opacity:.45;margin-top:4px;">${escH(estNombre)}</div>
      <div style="font-size:0.68rem;opacity:.3;margin-top:3px;word-break:break-all;">${escH(url)}</div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px;">
      <button onclick="imprimirPegatina('${escH(etiqueta)}','${escH(estNombre)}', ${pos.balda}, ${pos.columna})"
        class="btn-premium-wow btn-gold" style="flex:2;justify-content:center;">
        \uD83D\uDDB6 Imprimir pegatina
      </button>
      <button onclick="descargarQR('${escH(etiqueta)}','${escH(estNombre)}', ${pos.balda}, ${pos.columna})"
        class="btn-premium-wow" style="flex:1;background:#2563eb;justify-content:center;" title="Descargar como imagen completa">
        \uD83D\uDCE5 PNG
      </button>
    </div>
    <button onclick="document.getElementById('modalQR').style.display='none'"
      class="btn-premium-wow" style="flex:1;background:#4b5563;justify-content:center;">
      Cerrar
    </button>
  </div>
    <button onclick="imprimirEstanteria(qrEstActual)"
      class="btn-premium-wow" style="width:100%;margin-top:8px;justify-content:center;background:#334155;">
      \uD83D\uDDC2 Imprimir toda la estantería
    </button>`;
  document.getElementById('modalQR').style.display = 'flex';
}

async function descargarQR(etiqueta, estNombre, balda, columna) {
  const width = 600;
  const height = 800; // M\u00e1s alto para texto grande
  const qrSize = 450;
  const qrSrc = generarQR(etiqueta, qrSize);
  
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  
  // Fondo blanco
  ctx.fillStyle = '#FFFFFF';
  ctx.fillRect(0, 0, width, height);
  
  // Cargar imagen QR
  const img = new Image();
  img.crossOrigin = "anonymous";
  img.onload = () => {
    // Dibujar QR (centrado arriba)
    ctx.drawImage(img, (width - qrSize) / 2, 40, qrSize, qrSize);
    
    ctx.fillStyle = '#000000';
    ctx.textAlign = 'center';
    
    // Etiqueta principal (MUY GRANDE Y NEGRITA)
    ctx.font = 'bold 70px Arial';
    ctx.fillText(etiqueta, width / 2, 580);
    
    // Info ubicaci\u00f3n (GRANDE Y NEGRITA)
    ctx.font = 'bold 36px Arial';
    ctx.fillText(estNombre, width / 2, 650);
    ctx.fillText(`BALDA: ${balda} - COL: ${columna}`, width / 2, 710);
    
    // URL peque\u00f1a al pie
    ctx.fillStyle = '#666';
    ctx.font = 'bold 18px Arial';
    ctx.fillText(qrUrl(etiqueta).replace(/^https?:\/\//, ''), width / 2, 770);
    
    // Disparar descarga
    const link = document.createElement('a');
    link.download = `ETIQUETA_${etiqueta}.png`;
    link.href = canvas.toDataURL("image/png");
    link.click();
  };
  img.onerror = () => {
    alert('Error al generar la imagen compuesta.');
    window.open(qrSrc, '_blank');
  };
  img.src = qrSrc;
}

function imprimirPegatina(etiqueta, estNombre, balda, columna) {
  const url  = qrUrl(etiqueta);
  const qrSrc = generarQR(etiqueta, 300);
  const w = window.open('', '_blank', 'width=600,height=500');
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
  <title>Pegatina ${etiqueta}</title>
  <style>
    @page { size: 60mm 60mm; margin: 0; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { width:60mm; height:60mm; display:flex; flex-direction:column;
           align-items:center; justify-content:center; background:#fff;
           font-family:Arial,sans-serif; text-align:center; }
    .qr { width:36mm; height:36mm; display:block; }
    .etq { font-size:18pt; font-weight:900; letter-spacing:.1em; margin-top:2mm; }
    .loc { font-size:11pt; font-weight:900; color:#000; margin-top:2mm; }
    .sub { font-size:8pt; font-weight:bold; color:#666; margin-top:1mm; }
    .url { font-size:6pt; font-weight:bold; color:#999; margin-top:2mm; word-break:break-all; padding:0 4mm; }
  </style></head><body>
  <img class="qr" src="${qrSrc}">
  <div class="etq">${etiqueta}</div>
  <div class="loc">${estNombre} - B:${balda} C:${columna}</div>
  <div class="url">${url}</div>
  <script>window.onload=()=>{window.print();}<\/script>
  </body></html>`);
  w.document.close();
}

function imprimirEstanteria(est) {
  if (!est || !est.posiciones || !est.posiciones.length) {
    alert('No hay posiciones en esta estantería'); return;
  }
  const rows = est.posiciones.map(pos => {
    const etq = pos.etiqueta;
    const qrSrc = generarQR(etq, 200);
    return `<div class="pegatina">
      <img class="qr" src="${qrSrc}">
      <div class="etq" style="font-size:14pt; font-weight:900;">${etq}</div>
      <div class="loc" style="font-size:10pt; font-weight:900; margin-top:1mm; color:#000;">${escH(est.nombre)}</div>
      <div class="loc" style="font-size:9pt; font-weight:900; margin-top:1mm; color:#000;">B:${pos.balda} C:${pos.columna}</div>
    </div>`;
  }).join('');

  const w = window.open('', '_blank', 'width=800,height=700');
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
  <title>Pegatinas ${est.nombre}</title>
  <style>
    @media print { @page { margin: 5mm; } }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:Arial,sans-serif; background:#fff; }
    .grid { display:flex; flex-wrap:wrap; gap:4mm; padding:4mm; }
    .pegatina { width:52mm; height:52mm; border:0.5pt solid #ccc; border-radius:3mm;
                display:flex; flex-direction:column; align-items:center;
                justify-content:center; page-break-inside:avoid; }
    .qr { width:38mm; height:38mm; }
    .etq { font-size:11pt; font-weight:900; letter-spacing:.08em; margin-top:2mm; }
    .sub { font-size:6pt; color:#888; margin-top:1mm; }
  </style></head><body>
  <div class="grid">${rows}</div>
  <script>window.onload=()=>{window.print();}<\/script>
  </body></html>`);
  w.document.close();
}

// ── Cerrar panel lateral ──────────────────────────────────────
function cerrarPanelLateral() {
  document.getElementById('mapaPanelLateral').style.right = '-420px';
  document.getElementById('mapaOverlay').style.display = 'none';
}

// ── Buscador ─────────────────────────────────────────────────
function mapaBuscar(q) {
  clearTimeout(buscarTimeout);
  const resDiv = document.getElementById('mapaBuscadorResultado');
  if (!q || q.length < 2) { resDiv.textContent = ''; limpiarDestacado(); return; }
  buscarTimeout = setTimeout(async () => {
    try {
      const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_buscar&q=${encodeURIComponent(q)}`);
      const data = await r.json();
      limpiarDestacado();
      if (!data.length) { resDiv.textContent = '❌ No encontrado en el mapa'; return; }
      const item = data[0];
      const txt = item.estanteria
        ? `📍 ${item.ref} → ${item.estanteria}, Balda ${item.balda}, Col. ${item.columna} (${item.ubicacion})`
        : `📍 ${item.ref} → ${item.ubicacion || 'Sin ubicación'}`;
      resDiv.textContent = txt;
      if (item.ubicacion) {
        // Buscar la celda por etiqueta en el DOM
        mapaData.forEach(est => {
          (est.posiciones||[]).forEach(pos => {
            if (pos.etiqueta === item.ubicacion) {
              celdaDestacada = pos.id;
              destacarCelda(pos.id);
            }
          });
        });
      }
    } catch(e) { resDiv.textContent = 'Error de búsqueda'; }
  }, 400);
}

function destacarCelda(posId) {
  const el = document.getElementById(`celda-${posId}`);
  if (el) { el.style.border = '2px solid #facc15'; el.style.boxShadow = '0 0 12px #facc15'; }
}
function limpiarDestacado() {
  if (celdaDestacada) {
    const el = document.getElementById(`celda-${celdaDestacada}`);
    if (el) { el.style.border = '2px solid transparent'; el.style.boxShadow = ''; }
    celdaDestacada = null;
  }
}

// ── Modal Estantería ─────────────────────────────────────────
function abrirModalEstanteria() {
  document.getElementById('estId').value = '0';
  document.getElementById('estNombre').value = '';
  document.getElementById('estBaldas').value = '3';
  document.getElementById('estColumnas').value = '4';
  document.getElementById('estOrden').value = '0';
  document.getElementById('tituloModalEst').textContent = 'Nueva Estantería';
  document.getElementById('modalEstanteria').style.display = 'flex';
}

function editarEstanteria(id) {
  const est = mapaData.find(e => e.id == id);
  if (!est) return;
  document.getElementById('estId').value = est.id;
  document.getElementById('estNombre').value = est.nombre;
  document.getElementById('estBaldas').value = est.num_baldas;
  document.getElementById('estColumnas').value = est.num_columnas;
  document.getElementById('estOrden').value = est.orden;
  document.getElementById('tituloModalEst').textContent = 'Editar Estantería';
  document.getElementById('modalEstanteria').style.display = 'flex';
}

async function guardarEstanteria() {
  const payload = {
    id: parseInt(document.getElementById('estId').value),
    nombre: document.getElementById('estNombre').value.trim(),
    num_baldas: parseInt(document.getElementById('estBaldas').value),
    num_columnas: parseInt(document.getElementById('estColumnas').value),
    orden: parseInt(document.getElementById('estOrden').value)
  };
  if (!payload.nombre) { alert('Escribe un nombre'); return; }
  try {
    const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_guardar_estanteria`, { method:'POST', body: JSON.stringify(payload) });
    const d = await r.json();
    if (d.ok) { document.getElementById('modalEstanteria').style.display='none'; mapaCargado=false; mapaInit(); }
    else alert('Error: ' + (d.error||'desconocido'));
  } catch(e) { alert('Error de conexión'); }
}

async function borrarEstanteria(id, nombre) {
  if (!confirm(`¿Eliminar la estantería "${nombre}" y todas sus posiciones?`)) return;
  await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_borrar&id=${id}&tipo=estanteria`, { method:'DELETE' });
  mapaCargado = false; mapaInit();
}

// ── Modal Celda ──────────────────────────────────────────────
function abrirModalCelda(pos) {
  document.getElementById('celdaPosId').value = pos.id;
  document.getElementById('celdaEtiquetaVal').value = pos.etiqueta;
  document.getElementById('modalCeldaEtiqueta').textContent = pos.etiqueta;
  document.getElementById('celdaTipoCaja').value = pos.tipo_caja || '';
  document.getElementById('celdaNotas').value = pos.notas || '';
  document.getElementById('modalCelda').style.display = 'flex';
}

async function guardarCelda() {
  const payload = {
    posicion_id: parseInt(document.getElementById('celdaPosId').value),
    tipo_caja: document.getElementById('celdaTipoCaja').value || null,
    notas: document.getElementById('celdaNotas').value.trim() || null
  };
  const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_asignar`, { method:'POST', body: JSON.stringify(payload) });
  const d = await r.json();
  if (d.ok) { document.getElementById('modalCelda').style.display='none'; mapaCargado=false; mapaInit(); }
  else alert('Error al guardar celda');
}

async function asignarMaterialCelda() {
  const ref = document.getElementById('celdaMatRef').value;
  const etiqueta = document.getElementById('celdaEtiquetaVal').value;
  const estado = document.getElementById('celdaEstado').value;
  if (!ref) { alert('Selecciona un material'); return; }
  const r = await fetch(`${BASE_PATH}api/index.php?ruta=stock&accion=mapa_actualizar_ubicacion`, {
    method:'POST', body: JSON.stringify({ ref, ubicacion: etiqueta, estado_stock: estado })
  });
  const d = await r.json();
  if (d.ok) { alert(`✅ Material asignado a ${etiqueta}`); }
  else alert('Error al asignar');
}
</script>

<?php include('../includes/footer.php'); ?>

