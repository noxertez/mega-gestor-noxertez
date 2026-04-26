<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../api/config.php';
$db = conectar();

// 1. Definiciones del Kanban — AHORA 7 ESTADOS (Especial + 6 Estándar)
$estados = ['estado_especial', 'por_empezar', 'en_proceso', 'montado', 'tintado', 'barnizado', 'listo_para_entregar'];

$iconos = [
    'estado_especial'     => '<i class="fas fa-wand-magic-sparkles"></i>',
    'por_empezar'         => '<i class="fas fa-inbox"></i>',
    'en_proceso'          => '<i class="fas fa-hammer"></i>',
    'montado'             => '<i class="fas fa-puzzle-piece"></i>',
    'tintado'             => '<i class="fas fa-paint-roller"></i>',
    'barnizado'           => '<i class="fas fa-shield-halved"></i>',
    'listo_para_entregar' => '<i class="fas fa-box"></i>',
];

$colores = [
    'estado_especial'     => '#d4af37',
    'por_empezar'         => '#6366f1',
    'en_proceso'          => '#f59e0b',
    'montado'             => '#06b6d4',
    'tintado'             => '#ec4899',
    'barnizado'           => '#84cc16',
    'listo_para_entregar' => '#fb923c',
];

$clientes = $db->query("SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre")->fetchAll();

$page_class = 'management-page';
include('../includes/header.php');

if(!isset($_SESSION['user_id'])){
    header('Location:/noxertez/login.php'); exit;
}
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.2">
<style>
    :root {
        --kanban-col-width: 250px; /* Original: 300px */
        --kanban-gap: 10px;        /* Original: 15px */
        --kanban-img-size: 80px;    /* Original: 100px */
    }

    .kanban-board {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
        overflow-x: auto !important;
        padding-bottom: 2rem;
        align-items: flex-start;
        width: 100%;
        min-height: 750px;
    }
    .kanban-col {
        flex: 0 0 250px !important; /* Original: 300px */
        width: 250px !important;     /* Original: 300px */
        background: rgba(15, 23, 42, 0.6);
        border-radius: 8px;
        min-height: 700px;
        border: 1px solid #1e293b;
        display: flex;
        flex-direction: column;
        margin-right: 5px; 
    }
    .flow-step-badge {
        font-size: 10px;
        background: rgba(212, 175, 55, 0.2);
        color: #d4af37;
        padding: 3px 7px;
        border-radius: 4px;
        border: 1px solid rgba(212, 175, 55, 0.3);
        margin-top: 5px;
        display: inline-block;
    }

    /* Estilos para Pantalla Completa */
    .panel-management:fullscreen {
        padding: 30px;
        background: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
        overflow-y: auto;
        max-width: none !important; /* Permitir que ocupe todo el ancho */
        width: 100vw;
        height: 100vh;
        margin: 0;
    }
    .panel-management:-webkit-full-screen {
        padding: 30px;
        background: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
        overflow-y: auto;
        max-width: none !important;
        width: 100vw;
        height: 100vh;
        margin: 0;
    }
</style>

<div class="panel-management">
    <div class="panel-header-wow">
        <h1><i class="fas fa-tasks"></i> Kanban de Producción</h1>
        <div style="display: flex; gap: 10px;">
            <button class="btn-premium-wow btn-gold" onclick="abrirModalPedido()">
                <i class="fas fa-plus"></i> Añadir Pedido
            </button>
            <button id="btnFullscreen" class="btn-premium-wow" onclick="toggleFullScreen()" style="background: rgba(255,255,255,0.05); color: #94a3b8; border: 1px solid rgba(255,255,255,0.1);">
                <i class="fas fa-expand"></i> Pantalla Completa
            </button>
        </div>
    </div>

    <!-- Doble barra de desplazamiento: Superior sincronizada -->
    <div id="kanban-scroll-top" style="overflow-x: auto; margin-bottom: 10px; height: 18px; background: rgba(0,0,0,0.2); border-radius: 9px; cursor: pointer;">
        <div id="kanban-scroll-dummy" style="height: 1px;"></div>
    </div>

    <div class="kanban-board">
        <?php foreach($estados as $est): 
            if ($est === 'estado_especial') {
                // Estados "especiales" o pendientes que no encajan en las otras columnas
                $sql = "SELECT p.*, c.nombre as cliente_nombre, 
                                COALESCE(a.FOTO_PORTADA, fp.FOTO_REFERENCIA) as foto_portada, 
                                fnp.nombre as nodo_nombre 
                        FROM pedidos p 
                        LEFT JOIN clientes c ON p.id_cliente = c.id 
                        LEFT JOIN productos a ON p.sku_articulo = a.SKU_REF 
                        LEFT JOIN futuros_proyectos fp ON p.futuro_id = fp.id
                        LEFT JOIN pedido_nodos pn ON p.id = pn.id_pedido AND pn.estado = 'en_curso'
                        LEFT JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                        WHERE p.estado NOT IN ('por_empezar', 'en_proceso', 'montado', 'tintado', 'barnizado', 'listo_para_entregar', 'entregado', 'cancelado')
                        ORDER BY p.prioridad DESC, p.fecha_pedido ASC";
                $pedidos_stmt = $db->query($sql);
                $lista = $pedidos_stmt->fetchAll();
                $display_name = "ESTADO ESPECIAL / WEB";
            } else {
                $sql = "SELECT p.*, c.nombre as cliente_nombre, 
                                COALESCE(a.FOTO_PORTADA, fp.FOTO_REFERENCIA) as foto_portada, 
                                fnp.nombre as nodo_nombre 
                        FROM pedidos p 
                        LEFT JOIN clientes c ON p.id_cliente = c.id 
                        LEFT JOIN productos a ON p.sku_articulo = a.SKU_REF 
                        LEFT JOIN futuros_proyectos fp ON p.futuro_id = fp.id
                        LEFT JOIN pedido_nodos pn ON p.id = pn.id_pedido AND pn.estado = 'en_curso'
                        LEFT JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                        WHERE p.estado = ? 
                        ORDER BY p.prioridad DESC, p.fecha_pedido ASC";
                $pedidos_stmt = $db->prepare($sql);
                $pedidos_stmt->execute([$est]);
                $lista = $pedidos_stmt->fetchAll();
                $display_name = strtoupper(str_replace('_', ' ', $est));
            }
        ?>
            <div class="kanban-col" 
                 id="col-<?= md5($est) ?>" 
                 ondragover="allowDrop(event)" 
                 ondragenter="this.style.background='rgba(212,175,55,0.1)'; this.style.border='2px dashed #d4af37';"
                 ondragleave="this.style.background=''; this.style.border='';"
                 ondrop="this.style.background=''; this.style.border=''; drop(event, '<?= $est ?>')">
                
                <div class="kanban-col-header" style="padding: 15px; border-bottom: 2px solid <?= $colores[$est] ?>; color: <?= $colores[$est] ?>; font-weight: bold; display: flex; justify-content: space-between; background: rgba(0,0,0,0.2);">
                    <span><?= $iconos[$est] ?> <?= $display_name ?></span>
                    <span style="background: <?= $colores[$est] ?>; color: #000; padding: 0 8px; border-radius: 10px; font-size: 12px; line-height: 18px;">
                        <?= count($lista) ?>
                    </span>
                </div>

                <div class="kanban-cards" style="padding: 10px; flex: 1; overflow-y: auto;">
                    <?php foreach($lista as $p): 
                        $prio_color = ($p['prioridad'] === 'Rojo') ? '#ef4444' : (($p['prioridad'] === 'Amarillo') ? '#f59e0b' : (($p['prioridad'] === 'Azul') ? '#3b82f6' : '#10b981'));
                    ?>
                        <div class="kanban-card" 
                             draggable="true" 
                             ondragstart="drag(event)" 
                             id="pedido-<?= $p['id'] ?>" 
                             data-id="<?= $p['id'] ?>"
                             onclick='mostrarDetalles(<?= json_encode($p) ?>)'
                             style="background: #1e293b; border-left: 6px solid <?= $prio_color ?>; margin-bottom: 15px; padding: 10px; border-radius: 8px; cursor: grab; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.4); user-select: none;">
                            
                            <!-- Imagen a ancho total -->
                            <?php if($p['foto_portada']): 
                                $foto_raw = str_replace('\\', '/', $p['foto_portada']);
                                $img_src = '';
                                $pos_img = stripos($foto_raw, 'imagenes/');
                                $pos_proy = stripos($foto_raw, 'proyectos/');

                                if ($pos_img !== false) {
                                    $relativa = substr($foto_raw, $pos_img);
                                    $img_src = $base_path . 'uploads/articulos/' . $relativa;
                                } elseif ($pos_proy !== false) {
                                    $filename = basename($foto_raw);
                                    $img_src = $base_path . 'uploads/articulos/proyectos/' . $filename;
                                } elseif (strpos($foto_raw, 'uploads/') !== false) {
                                    $foto_clean = str_replace('uploads/', '', $foto_raw);
                                    $img_src = $base_path . 'uploads/' . $foto_clean;
                                } else {
                                    $img_src = $base_path . $foto_raw;
                                }
                                $img_src = str_replace('//', '/', $img_src);
                            ?>
                                <div style="width: calc(100% + 20px); height: 140px; margin: -10px -10px 10px -10px; flex-shrink: 0; overflow: hidden; border-radius: 8px 8px 0 0;">
                                    <img src="<?= htmlspecialchars($img_src) ?>" 
                                         draggable="false"
                                         onerror="this.src='<?= $base_path ?>assets/img/placeholder.png'; this.src='/noxertez/assets/img/placeholder.png'; this.onerror=null;"
                                         onclick="event.stopPropagation(); verImagen('<?= htmlspecialchars($img_src) ?>')"
                                         style="width: 100%; height: 100%; object-fit: cover; cursor: zoom-in;"
                                         title="Click para ver imagen completa">
                                </div>
                            <?php endif; ?>

                            <div style="font-size: 10px; color: #94a3b8; display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>#<?= htmlspecialchars($p['numero_pedido'] ?? $p['id']) ?></span>
                                <span><?= date('d/m', strtotime($p['fecha_pedido'])) ?></span>
                            </div>

                            <div style="margin-bottom: 8px;">
                                <div style="font-weight: bold; color: #F0E6D3; font-size: 0.9rem; margin-bottom: 2px;">
                                    <?= htmlspecialchars($p['cliente_nombre'] ?: ($p['nombre_cliente'] ?: 'Sin nombre')) ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #C89B3C;"><?= htmlspecialchars($p['sku_articulo'] ?: 'Sin SKU') ?></div>
                                
                                <?php if($p['nodo_nombre']): ?>
                                    <div class="flow-step-badge">
                                        <i class="fas fa-layer-group"></i> <?= htmlspecialchars($p['nodo_nombre']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($p['detalles_criticos']): ?>
                                <div style="font-size: 10px; background: rgba(239, 68, 68, 0.15); color: #f87171; padding: 4px 8px; border-radius: 4px; margin-top: 10px; border-left: 2px solid #ef4444;">
                                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($p['detalles_criticos']) ?>
                                </div>
                            <?php endif; ?>

                            <div style="display:flex; gap:6px; margin-top:10px; justify-content:flex-end">
                                <a href="<?= $base_path ?>pages/flujo_pedidos.php?id=<?= $p['id'] ?>"
                                   onclick="event.stopPropagation()"
                                   class="btn-flujo"
                                   style="background:rgba(99,102,241,0.2); border:1px solid #6366f1; color:#a5b4fc; border-radius:4px; padding:3px 8px; font-size:10px; text-decoration:none">
                                    <i class="fas fa-diagram-project"></i> Flujo
                                </a>
                            </div>

                            <div style="position: absolute; top: 8px; right: 8px; opacity: 0; transition: opacity 0.2s;" class="card-delete-btn">
                                <button onclick="event.stopPropagation(); eliminarPedidoConId(<?= $p['id'] ?>)" 
                                        style="background: rgba(239, 68, 68, 0.9); border: none; color: white; border-radius: 4px; cursor: pointer; padding: 4px 6px; font-size: 11px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Pedido / Detalles -->
<div id="modalPedido" class="modal-overlay-wow" onclick="if(event.target==this) cerrarModal()">
    <div class="modal-content-wow" style="max-width: 800px;">
        <div class="modal-header-wow">
            <h2 id="modalTitulo">Detalles del Pedido</h2>
            <button class="btn-premium-wow" onclick="cerrarModal()" style="background: none; color: var(--accent-gold); font-size: 1.5rem;">&times;</button>
        </div>
        <form id="formPedido" onsubmit="guardarPedido(event)" style="flex: 1; overflow-y: auto; padding: 20px;">
            <input type="hidden" name="id" id="ped_id">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div class="nox-form-group">
                        <label>Cliente</label>
                        <select name="id_cliente" id="ped_cliente" class="nox-input" style="width: 100%;">
                            <option value="">Manual / Sin Cliente</option>
                            <?php foreach($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="nox-form-group">
                        <label>SKU Artículo</label>
                        <input type="text" name="sku_articulo" id="ped_sku" class="nox-input" style="width: 100%;">
                    </div>
                    <div class="nox-form-group">
                        <label>Prioridad</label>
                        <select name="prioridad" id="ped_prio" class="nox-input" style="width: 100%;">
                            <option value="Verde">Verde (Normal)</option>
                            <option value="Amarillo">Amarillo (Pronto)</option>
                            <option value="Rojo">Rojo (URGENTE)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <div class="nox-form-group">
                        <label>Estado</label>
                        <select name="estado" id="ped_estado" class="input-wow" style="width: 100%;">
                            <option value="pendiente">Pendiente (Aprobación)</option>
                            <option value="por_empezar">Por Empezar</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="montado">Montado</option>
                            <option value="tintado">Tintado</option>
                            <option value="barnizado">Barnizado</option>
                            <option value="listo_para_entregar">Listo para Entregar</option>
                            <option value="entregado">Entregado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="nox-form-group">
                        <label>Costo Envío (€)</label>
                        <input type="number" step="0.01" name="costo_envio" id="ped_costo" class="input-wow" style="width: 100%;">
                    </div>
                    <div class="nox-form-group">
                        <label>Tracking ID</label>
                        <input type="text" name="tracking_id" id="ped_track" class="input-wow" style="width: 100%;">
                    </div>
                </div>
            </div>

            <div class="nox-form-group" style="margin-top: 10px;">
                <label>🔥 Detalles Críticos</label>
                <textarea name="detalles_criticos" id="ped_criticos" class="input-wow" style="width: 100%; height: 50px; border-color: #ef4444;"></textarea>
            </div>

            <div class="nox-form-group">
                <label>Notas / Instrucciones</label>
                <textarea name="notas" id="ped_notas" class="nox-input" style="width: 100%; height: 80px;"></textarea>
            </div>

            <div id="checkListContainer" style="margin-top: 15px; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 4px; border: 1px solid #C89B3C;">
                <h4 style="color: #C89B3C; margin-bottom: 10px;"><i class="fas fa-check-double"></i> Unboxing Checklist</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <label><input type="checkbox" name="ck_articulo" value="1"> Artículo revisado</label>
                    <label><input type="checkbox" name="ck_regalo" value="1"> Regalo extra</label>
                    <label><input type="checkbox" name="ck_tarjeta" value="1"> Tarjeta agradecimiento</label>
                    <label><input type="checkbox" name="ck_foto" value="1"> Foto enviada cliente</label>
                    <label><input type="checkbox" name="ck_limpieza" value="1"> Limpieza final</label>
                    <label><input type="checkbox" name="ck_proteccion" value="1"> Protección envío</label>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px; padding: 1rem 1.5rem;">
                <button type="submit" class="btn-premium-wow btn-gold" style="flex: 2; height: 45px; justify-content: center;">💾 Guardar Pedido</button>
                <button type="button" class="btn-premium-wow" style="background: #64748b; color: white; flex: 1; justify-content: center;" onclick="cerrarModal()">Cancelar</button>
                <button type="button" id="btnBorrar" class="btn-premium-wow btn-red" style="flex: 0.5; justify-content: center;" onclick="eliminarPedido()"><i class="fas fa-trash"></i></button>
            </div>
        </form>
    </div>
</div>

<script>
    const topBar = document.getElementById("kanban-scroll-top");
    const board  = document.querySelector(".kanban-board");
    const dummy  = document.getElementById("kanban-scroll-dummy");

    function syncScrollWidth() {
        dummy.style.width = board.scrollWidth + "px";
    }

    window.addEventListener('load', syncScrollWidth);
    window.addEventListener('resize', syncScrollWidth);
    
    topBar.onscroll = () => { board.scrollLeft = topBar.scrollLeft; };
    board.onscroll  = () => { topBar.scrollLeft = board.scrollLeft; };

let pedidoActual = null;

// ── DRAG AND DROP LOGIC (NXT-ROBUST-MODE) ────────────────────
window.nox_dragged_id = null;

function drag(ev) {
    const card = ev.target.closest('.kanban-card');
    if (card && card.dataset.id) {
        window.nox_dragged_id = card.dataset.id;
        // Algunos navegadores requieren setData para iniciar el drag aunque no se use
        ev.dataTransfer.setData("text/plain", card.dataset.id); 
        card.style.opacity = "0.4";
        card.style.transform = "scale(0.95)";
    }
}

function allowDrop(ev) { 
    ev.preventDefault(); 
}

async function drop(ev, nuevo_estado) {
    ev.preventDefault();
    
    // Recuperar ID desde variable global (más fiable que dataTransfer en algunos entornos)
    const id = window.nox_dragged_id;
    
    if (!id) {
        console.error("ID no encontrado en el drop.");
        return;
    }

    const tarjeta = document.getElementById("pedido-" + id);
    if (tarjeta) {
        tarjeta.style.opacity = "1";
        tarjeta.style.transform = "none";
    }
    
    const real_estado = (nuevo_estado === 'estado_especial') ? 'pendiente' : nuevo_estado;

    try {
        // Realizar una única petición atómica que actualiza tanto el pedido como el flujo
        const response = await fetch('../api/index.php?ruta=flujo_sync_kanban', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: id, nuevo_estado: real_estado })
        });

        if (response.ok) {
            location.reload(); 
        } else {
            const errData = await response.json();
            console.error("Error API:", errData);
            alert("Error al guardar el cambio. Por favor refresca la página.");
        }
    } catch (e) {
        console.error(e);
        alert("Error de conexión al mover el pedido.");
    } finally {
        window.nox_dragged_id = null;
    }
}

function abrirModalPedido() {
    pedidoActual = null;
    document.getElementById("modalTitulo").innerText = "Añadir Nuevo Pedido";
    document.getElementById("formPedido").reset();
    document.getElementById("ped_id").value = "";
    document.getElementById("btnBorrar").style.display = "none";
    document.getElementById("modalPedido").style.display = "block";
}

function mostrarDetalles(p) {
    pedidoActual = p;
    document.getElementById("modalTitulo").innerText = "Editar Pedido #" + p.id;
    document.getElementById("ped_id").value = p.id;
    document.getElementById("ped_cliente").value = p.id_cliente || "";
    document.getElementById("ped_sku").value = p.sku_articulo || "";
    document.getElementById("ped_prio").value = p.prioridad || "Verde";
    document.getElementById("ped_estado").value = p.estado || "por_empezar";
    document.getElementById("ped_costo").value = p.costo_envio || 0;
    document.getElementById("ped_track").value = p.tracking_id || "";
    document.getElementById("ped_criticos").value = p.detalles_criticos || "";
    document.getElementById("ped_notas").value = p.notas || "";
    
    const ck = p.unboxing_checklist ? JSON.parse(p.unboxing_checklist) : {};
    document.querySelectorAll('#checkListContainer input').forEach(box => {
        box.checked = !!ck[box.name];
    });

    document.getElementById("btnBorrar").style.display = "block";
    document.getElementById("modalPedido").style.display = "block";
}

function cerrarModal() {
    document.getElementById("modalPedido").style.display = "none";
}

async function guardarPedido(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    const checklist = {};
    document.querySelectorAll('#checkListContainer input').forEach(box => {
        checklist[box.name] = box.checked;
    });
    data.unboxing_checklist = JSON.stringify(checklist);
    const metodo = data.id ? 'PUT' : 'POST';
    try {
        const response = await fetch('../api/index.php?ruta=pedidos', {
            method: metodo,
            body: JSON.stringify(data)
        });
        if ((await response.json()).ok) location.reload();
    } catch (e) { alert("Error de red"); }
}

async function eliminarPedidoConId(id) {
    if(!confirm("¿Eliminar?")) return;
    try {
        await fetch(`../api/index.php?ruta=pedidos&id=${id}`, { method: 'DELETE' });
        location.reload();
    } catch (e) { alert("Error"); }
}

function toggleFullScreen() {
    const elem = document.querySelector('.panel-management');
    
    if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

// Escuchar cambios de pantalla completa para actualizar el botón
const updateFullscreenButton = () => {
    const btn = document.getElementById('btnFullscreen');
    if (document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
        btn.innerHTML = '<i class="fas fa-compress"></i> Salir Fullscreen';
        btn.style.color = '#f87171';
        btn.style.borderColor = 'rgba(248, 113, 113, 0.3)';
        btn.style.background = 'rgba(248, 113, 113, 0.05)';
    } else {
        btn.innerHTML = '<i class="fas fa-expand"></i> Pantalla Completa';
        btn.style.color = '#94a3b8';
        btn.style.borderColor = 'rgba(255, 255, 255, 0.1)';
        btn.style.background = 'rgba(255, 255, 255, 0.05)';
    }
};

document.addEventListener('fullscreenchange', updateFullscreenButton);
document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
document.addEventListener('mozfullscreenchange', updateFullscreenButton);
document.addEventListener('MSFullscreenChange', updateFullscreenButton);
</script>

<style>
.kanban-card:hover { filter: brightness(1.1); outline: 1px solid #d4af37; }
.kanban-card:hover .card-delete-btn { opacity: 1 !important; }
</style>

<!-- Lightbox: visualizador pantalla completa -->
<div id="lightboxOverlay" onclick="cerrarLightbox()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:99999; align-items:center; justify-content:center; flex-direction:column; cursor:zoom-out;">
    <button onclick="cerrarLightbox()" style="position:absolute; top:20px; right:28px; background:none; border:none; color:#d4af37; font-size:2.5rem; cursor:pointer; line-height:1;">&times;</button>
    <img id="lightboxImg" src="" style="max-width:92vw; max-height:88vh; object-fit:contain; border-radius:8px; box-shadow:0 8px 48px rgba(0,0,0,0.8);" onclick="event.stopPropagation()">
    <p style="color:rgba(255,255,255,0.4); margin-top:12px; font-size:12px;">Click fuera de la imagen para cerrar · Click en la ficha para ver los detalles del pedido</p>
</div>

<script>
function verImagen(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightboxOverlay').style.display = 'flex';
}
function cerrarLightbox() {
    document.getElementById('lightboxOverlay').style.display = 'none';
    document.getElementById('lightboxImg').src = '';
}
</script>

<?php include('../includes/footer.php'); ?>
