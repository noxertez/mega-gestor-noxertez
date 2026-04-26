<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../api/config.php';
$db = conectar();

$self = 'pedidos.php'; 

// --- ACCIONES ---

// 1. Eliminar
if (isset($_GET['eliminar'])) {
    $stmt = $db->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt->execute([$_GET['eliminar']]);
    header("Location: $self");
    exit();
}

// 2. Actualizar Estado Rápido (desde la tabla) + Sincronizar Flujo
if (isset($_POST['update_status'])) {
    $nuevo_est = $_POST['nuevo_estado'];
    $id_ped    = $_POST['id_pedido'];

    $stmt = $db->prepare("UPDATE pedidos SET estado = ?, fecha_entrega = IF(? = 'entregado', NOW(), fecha_entrega) WHERE id = ?");
    $stmt->execute([$nuevo_est, $nuevo_est, $id_ped]);

    // SINCRONIZACIÓN CON FLUJO: Buscar fase mapeada y activarla
    $stmtN = $db->prepare("
        SELECT pn.id, fnp.orden
        FROM pedido_nodos pn
        JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
        WHERE pn.id_pedido = ? AND fnp.estado_pedido_mapeo = ?
        LIMIT 1
    ");
    $stmtN->execute([$id_ped, $nuevo_est]);
    $target = $stmtN->fetch();

    if ($target) {
        $db->prepare("UPDATE pedido_nodos SET estado = 'en_curso', fecha_inicio = COALESCE(fecha_inicio, NOW()), fecha_fin = NULL WHERE id = ?")->execute([$target['id']]);
        $db->prepare("UPDATE pedido_nodos pn JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id SET pn.estado = 'completado', pn.fecha_fin = COALESCE(pn.fecha_fin, NOW()) WHERE pn.id_pedido = ? AND fnp.orden < ?")->execute([$id_ped, $target['orden']]);
        $db->prepare("UPDATE pedido_nodos pn JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id SET pn.estado = 'pendiente', pn.fecha_inicio = NULL, pn.fecha_fin = NULL WHERE pn.id_pedido = ? AND fnp.orden > ?")->execute([$id_ped, $target['orden']]);
    }

    header("Location: $self");
    exit();
}

// 3. Guardar Edición Completa (Modal Premium)
if (isset($_POST['guardar_pedido'])) {
    $stmt = $db->prepare("
        UPDATE pedidos SET 
            id_cliente = ?, 
            nombre_cliente = ?, 
            telefono = ?, 
            total = ?, 
            estado = ?, 
            prioridad = ?, 
            canal = ?, 
            canal_origen = ?, 
            sku_articulo = ?, 
            detalles_criticos = ?, 
            notas = ?, 
            costo_envio = ?, 
            metodo_envio = ?, 
            transportista = ?, 
            tracking_envio = ?, 
            tracking_code = ?, 
            tracking_activo = ?,
            fecha_pedido = ?, 
            fecha_entrega_prometida = ?, 
            fecha_estimada_entrega = ?,
            numero_pedido = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['id_cliente'] ?: null,
        $_POST['nombre_cliente'] ?? '',
        $_POST['telefono'] ?? '',
        $_POST['total'] ?? 0,
        $_POST['nuevo_estado'],
        $_POST['prioridad'] ?? 'Verde',
        $_POST['canal'] ?? 'manual',
        $_POST['canal_origen'] ?? 'whatsapp',
        $_POST['sku_articulo'] ?? '',
        $_POST['detalles_criticos'] ?? '',
        $_POST['notas'] ?? '',
        $_POST['costo_envio'] ?? 0,
        $_POST['metodo_envio'] ?? '',
        $_POST['transportista'] ?? '',
        $_POST['tracking_envio'] ?? '',
        $_POST['tracking_code'] ?? '',
        isset($_POST['tracking_activo']) ? 1 : 0,
        $_POST['fecha_pedido'] ?: date('Y-m-d H:i:s'),
        $_POST['fecha_entrega_prometida'] ?: null,
        $_POST['fecha_estimada_entrega'] ?: null,
        $_POST['numero_pedido'] ?? '',
        $_GET['editar']
    ]);
    header("Location: $self");
    exit();
}

// 4. Crear Nuevo Pedido
if (isset($_POST['crear_pedido'])) {
    // Generar número correlativo automático
    $anio = date('Y');
    $stmtMax = $db->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(numero_pedido, '-', -1) AS UNSIGNED)) AS ultimo
        FROM pedidos
        WHERE numero_pedido REGEXP '^NEX-[0-9]{4}-[0-9]+$'
          AND SUBSTRING(numero_pedido, 5, 4) = ?
    ");
    $stmtMax->execute([$anio]);
    $ultimo    = (int)($stmtMax->fetchColumn() ?? 0);
    $num       = 'NEX-' . $anio . '-' . str_pad($ultimo + 1, 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("INSERT INTO pedidos (id_cliente, numero_pedido, fecha_pedido, total, estado, notas) VALUES (?, ?, NOW(), ?, ?, ?)");
    $stmt->execute([$_POST['id_cliente'], $num, $_POST['total'], $_POST['estado'], $_POST['notas']]);
    header("Location: $self");
    exit();
}

// --- DATOS ---

$pedido_detalle = null;
if (isset($_GET['ver'])) {
    $stmt = $db->prepare("SELECT p.*, COALESCE(c.nombre, p.nombre_cliente, 'Manual') as nombre_cliente FROM pedidos p LEFT JOIN clientes c ON p.id_cliente = c.id WHERE p.id = ?");
    $stmt->execute([$_GET['ver']]);
    $pedido_detalle = $stmt->fetch();
}

$pedido_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $pedido_editar = $stmt->fetch();
}

// Lista de estados para los selectores
$estados_map = [
    'pendiente'           => 'Pendiente de Aprobación',
    'por_empezar'         => 'Por Empezar',
    'en_proceso'          => 'En Proceso',
    'montado'             => 'Montado',
    'tintado'             => 'Tintado',
    'barnizado'           => 'Barnizado',
    'listo_para_entregar' => 'Listo para Entregar',
    'entregado'           => 'Entregado',
    'borrador'            => 'Borrador',
    'cancelado'           => 'Cancelado'
];

// 1. Pedidos Pendientes (Aprobación)
$stmt = $db->query("SELECT p.*, COALESCE(c.nombre, p.nombre_cliente, 'Web / Manual') as nombre_cliente FROM pedidos p LEFT JOIN clientes c ON p.id_cliente = c.id WHERE p.estado = 'pendiente' ORDER BY p.id DESC");
$pendientes = $stmt->fetchAll();

// 2. Pedidos Activos (Excluye pendiente, entregado, cancelado)
$stmt = $db->query("SELECT p.*, COALESCE(c.nombre, p.nombre_cliente, 'Manual') as nombre_cliente FROM pedidos p LEFT JOIN clientes c ON p.id_cliente = c.id WHERE p.estado NOT IN ('pendiente', 'entregado', 'cancelado') ORDER BY p.id DESC");
$activos = $stmt->fetchAll();

// 3. Historial (Solo entregados)
$stmt = $db->query("SELECT p.*, COALESCE(c.nombre, p.nombre_cliente, 'Manual') as nombre_cliente FROM pedidos p LEFT JOIN clientes c ON p.id_cliente = c.id WHERE p.estado = 'entregado' ORDER BY p.fecha_entrega DESC LIMIT 50");
$historial = $stmt->fetchAll();

$clientes_lista = $db->query("SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre")->fetchAll();

$page_class = 'management-page';
include('../includes/header.php');
$self = $base_path . 'pages/pedidos.php';
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.3">
<style>
    .section-approval-wow {
        background: rgba(212, 175, 55, 0.05);
        border: 2px solid #d4af37;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 2rem;
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.15);
        animation: border-glow 3s infinite;
    }
    @keyframes border-glow {
        0% { border-color: #d4af37; box-shadow: 0 0 10px rgba(212, 175, 55, 0.1); }
        50% { border-color: #fff; box-shadow: 0 0 25px rgba(212, 175, 55, 0.4); }
        100% { border-color: #d4af37; box-shadow: 0 0 10px rgba(212, 175, 55, 0.1); }
    }
    .select-status-wow {
        background: #0f172a;
        color: #fff;
        border: 1px solid #334155;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.85rem;
    }
</style>

<div class="panel-management">
    <div class="panel-header-wow">
        <h1>Gestión de Pedidos</h1>
        <div class="header-actions-wow">
            <button onclick="generateMassTracking()" class="btn-premium-wow btn-amber" style="margin-right: 10px;">
                <i class="fas fa-magic"></i> Generar códigos pendientes
            </button>
            <a href="<?= $self ?>?nuevo=1" class="btn-premium-wow btn-gold">
                <i class="fas fa-plus"></i> Nuevo Pedido
            </a>
        </div>
    </div>

    <!-- 1. SECCIÓN DE APROBACIÓN (PENDIENTES) -->
    <?php if (!empty($pendientes)): ?>
    <div class="section-approval-wow">
        <h2 style="color:#d4af37; margin-bottom: 1.5rem;"><i class="fas fa-clock"></i> Pendientes de Aprobación (Pedidos Web)</h2>
        <div class="table-container-wow">
            <table class="table-wow">
                <thead>
                    <tr>
                        <th>Nº Pedido</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pendientes as $p): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($p['numero_pedido'] ?? '#'.$p['id']) ?></b></td>
                        <td><?= htmlspecialchars($p['nombre_cliente']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></td>
                        <td><?= number_format($p['total'], 2) ?> €</td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id_pedido" value="<?= $p['id'] ?>">
                                <input type="hidden" name="nuevo_estado" value="por_empezar">
                                <button type="submit" name="update_status" class="btn-premium-wow btn-gold" style="padding: 0.4rem 1rem;">
                                    <i class="fas fa-check"></i> Aprobar
                                </button>
                            </form>
                            <a href="<?= $self ?>?ver=<?= $p['id'] ?>" class="btn-premium-wow btn-blue" style="padding: 0.4rem 0.8rem;"><i class="fas fa-eye"></i></a>
                            <a href="<?= $self ?>?eliminar=<?= $p['id'] ?>" class="btn-premium-wow btn-red" style="padding: 0.4rem 0.8rem;" onclick="return confirm('¿Rechazar este pedido?')"><i class="fas fa-times"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- 2. TABLA DE PEDIDOS ACTIVOS -->
    <h2 style="margin: 2rem 0 1rem; font-size: 1.5rem;"><i class="fas fa-hammer"></i> Pedidos en curso</h2>
    <div class="table-container-wow">
        <table class="table-wow">
            <thead>
                <tr>
                    <th>Nº Pedido</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Tracking</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($activos)): ?>
                <?php foreach($activos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['numero_pedido'] ?? '#'.$p['id']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_cliente']) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?></td>
                    <td><?= number_format($p['total'], 2) ?> €</td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="id_pedido" value="<?= $p['id'] ?>">
                            <select name="nuevo_estado" class="select-status-wow" onchange="this.form.submit()">
                                <?php foreach($estados_map as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $p['estado']==$val?'selected':'' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                    </td>
                    <td>
                        <?php if (!empty($p['tracking_code'])): ?>
                            <span style="font-family: monospace; color: var(--gold); font-weight: bold;"><?= $p['tracking_code'] ?></span>
                        <?php else: ?>
                            <span style="color: #64748b; font-size: 0.8rem;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= $self ?>?ver=<?= $p['id'] ?>" class="btn-premium-wow btn-blue" title="Ver Detalles" style="padding: 0.4rem 0.6rem;"><i class="fas fa-eye"></i></a>
                        <a href="<?= $self ?>?editar=<?= $p['id'] ?>" class="btn-premium-wow btn-gold" title="Editar" style="padding: 0.4rem 0.6rem;"><i class="fas fa-edit"></i></a>
                        <a href="<?= $base_path ?>pages/flujo_pedidos.php?id=<?= $p['id'] ?>" class="btn-premium-wow btn-amber" title="Flujo de Trabajo" style="padding: 0.4rem 0.6rem;"><i class="fas fa-diagram-project"></i></a>
                        <?php if (!empty($p['tracking_code'])): ?>
                            <a href="<?= $base_path ?>seguimiento?code=<?= $p['tracking_code'] ?>" target="_blank" class="btn-premium-wow btn-green" title="Ver Seguimiento Público" style="padding: 0.4rem 0.6rem;"><i class="fas fa-external-link-alt"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-gray);">No hay pedidos activos actualmente.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 3. TABLA DE HISTORIAL (ENTREGADOS) -->
    <h2 style="margin: 4rem 0 1rem; font-size: 1.5rem; color: #10b981;"><i class="fas fa-history"></i> Historial de Entregas</h2>
    <div class="table-container-wow" style="opacity: 0.8;">
        <table class="table-wow">
            <thead>
                <tr>
                    <th>Nº Pedido</th>
                    <th>Cliente</th>
                    <th>Fecha Entrega</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($historial)): ?>
                <?php foreach($historial as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['numero_pedido'] ?? '#'.$p['id']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_cliente']) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></td>
                    <td><?= number_format($p['total'], 2) ?> €</td>
                    <td>
                        <a href="<?= $self ?>?ver=<?= $p['id'] ?>" class="btn-premium-wow btn-blue" style="padding: 0.4rem 0.6rem;"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="padding: 2rem; text-align: center;">No hay registros en el historial.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: DETALLES -->
<?php if ($pedido_detalle): ?>
<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;">
    <div style="background:#1a1a1a;border:1px solid #d4af37;border-radius:12px;padding:30px;max-width:550px;width:90%;color:#fff;">
        <h3 style="color:#d4af37;">Pedido <?= htmlspecialchars($pedido_detalle['numero_pedido'] ?? '#'.$pedido_detalle['id']) ?></h3>
        <p><b>Cliente:</b> <?= htmlspecialchars($pedido_detalle['nombre_cliente']) ?></p>
        <p><b>Fecha Pedido:</b> <?= date('d/m/Y H:i', strtotime($pedido_detalle['fecha_pedido'])) ?></p>
        <p><b>Estado:</b> <span class="badge bg-warning text-dark"><?= $pedido_detalle['estado'] ?></span></p>
        <p><b>Total:</b> <?= number_format($pedido_detalle['total'], 2) ?> €</p>
        <?php if (!empty($pedido_detalle['notas'])): ?>
        <p><b>Notas:</b><br><?= nl2br(htmlspecialchars($pedido_detalle['notas'])) ?></p>
        <?php endif; ?>

        <!-- SECCIÓN: SEGUIMIENTO PÚBLICO -->
        <div style="margin-top:20px; padding-top:20px; border-top: 1px solid #334155;">
            <h4 style="color:#d4af37; font-size: 1.1rem;"><i class="fas fa-map-location-dot"></i> Seguimiento Público</h4>
            
            <?php if (empty($pedido_detalle['tracking_code'])): ?>
                <div style="background: rgba(212, 175, 55, 0.1); border: 1px dashed #d4af37; padding: 15px; border-radius: 8px; text-align: center; margin-top: 10px;">
                    <p style="margin-bottom: 10px; font-size: 0.9rem;">Este pedido aún no tiene código de seguimiento.</p>
                    <button onclick="generateTrackingCode(<?= $pedido_detalle['id'] ?>)" class="btn-premium-wow btn-gold">
                        <i class="fas fa-plus-circle"></i> Generar enlace de seguimiento
                    </button>
                </div>
            <?php else: ?>
                <div style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">
                    <span style="background: #334155; padding: 5px 12px; border-radius: 4px; font-family: monospace; font-size: 1.1rem; color: #fbbf24; border: 1px solid #d4af37;">
                        <?= $pedido_detalle['tracking_code'] ?>
                    </span>
                    <button onclick="copyTrackingLink('<?= $pedido_detalle['tracking_code'] ?>')" class="btn-premium-wow btn-blue" style="width: auto; padding: 6px 12px;">
                        <i class="fas fa-copy"></i> Copiar enlace
                    </button>
                </div>
                
                <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                    <div>
                        <label style="font-size: 0.8rem; display: block; margin-bottom: 4px; color: #94a3b8;">Entrega Estimada</label>
                        <input type="date" id="tra_fecha" value="<?= $pedido_detalle['fecha_estimada_entrega'] ?>" class="form-control bg-dark text-white border-secondary">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; display: block; margin-bottom: 4px; color: #94a3b8;">Transportista</label>
                        <select id="tra_carrier" class="form-control bg-dark text-white border-secondary">
                            <option value="">Seleccionar...</option>
                            <?php foreach(['Correos', 'SEUR', 'MRW', 'GLS', 'DHL', 'Otro'] as $c): ?>
                                <option value="<?= $c ?>" <?= $pedido_detalle['transportista']==$c?'selected':'' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="font-size: 0.8rem; display: block; margin-bottom: 4px; color: #94a3b8;">Nº Seguimiento Transportista</label>
                        <input type="text" id="tra_id" value="<?= htmlspecialchars($pedido_detalle['tracking_envio']) ?>" placeholder="Ej: CP123456789ES" class="form-control bg-dark text-white border-secondary">
                    </div>
                </div>

                <div style="margin-top: 15px; display: flex; align-items: center; justify-content: space-between; background: #0f172a; padding: 10px; border-radius: 6px;">
                    <span style="font-size: 0.9rem;">Compartir seguimiento con el cliente</span>
                    <label class="switch-wow">
                        <input type="checkbox" id="tra_active" <?= $pedido_detalle['tracking_activo']?'checked':'' ?>>
                        <span class="slider-wow round"></span>
                    </label>
                </div>

                <button onclick="saveTracking(<?= $pedido_detalle['id'] ?>)" class="btn-premium-wow btn-green" style="margin-top: 15px; width: 100%;">
                    <i class="fas fa-save"></i> Guardar Cambios Seguimiento
                </button>
            <?php endif; ?>
        </div>
        <div class="mt-4">
            <a href="<?= $self ?>" class="btn btn-secondary">Cerrar</a>
            <a href="<?= $self ?>?editar=<?= $pedido_detalle['id'] ?>" class="btn btn-warning">✏ Editar</a>
            <a href="<?= $base_path ?>pages/flujo_pedidos.php?id=<?= $pedido_detalle['id'] ?>" class="btn btn-info">⚙ Ver Flujo</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: NUEVO PEDIDO -->
<?php if (isset($_GET['nuevo'])): ?>
<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;">
    <div style="background:#1a1a1a;border:1px solid #d4af37;border-radius:12px;padding:30px;max-width:500px;width:90%;color:#fff;">
        <h3 style="color:#d4af37;">Nuevo Pedido Manual</h3>
        <form method="POST">
            <div class="mb-3">
                <label>Cliente</label>
                <select name="id_cliente" class="form-control bg-dark text-white border-warning mt-1" required>
                    <option value="">Seleccionar Cliente...</option>
                    <?php foreach($clientes_lista as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label>Total (€)</label>
                <input type="number" step="0.01" name="total" class="form-control bg-dark text-white border-warning mt-1" required>
            </div>
            <div class="mb-3">
                <label>Estado Inicial</label>
                <select name="estado" class="form-control bg-dark text-white border-warning mt-1">
                    <option value="pendiente">Pendiente (Web/Aprobación)</option>
                    <option value="por_empezar" selected>Por Empezar</option>
                    <option value="en_proceso">En Proceso</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Notas</label>
                <textarea name="notas" class="form-control bg-dark text-white border-warning mt-1" rows="2"></textarea>
            </div>
            <div class="text-end">
                <a href="<?= $self ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" name="crear_pedido" class="btn btn-warning">💾 Crear Pedido</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: EDITAR -->
<?php if ($pedido_editar): ?>
<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center; padding: 20px;">
    <div style="background:#1a1a1a;border:2px solid #d4af37;border-radius:16px;padding:35px;max-width:900px;width:100%;max-height:90vh;overflow-y:auto;color:#fff;box-shadow: 0 0 50px rgba(212,175,55,0.2);">
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid rgba(212,175,55,0.3); padding-bottom: 15px;">
            <h2 style="color:#d4af37; margin:0;"><i class="fas fa-edit"></i> Edición Premium: <?= htmlspecialchars($pedido_editar['numero_pedido'] ?? '#'.$pedido_editar['id']) ?></h2>
            <a href="<?= $self ?>" style="color:#94a3b8; font-size: 1.5rem; text-decoration:none;">&times;</a>
        </div>
        
        <form method="POST">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                
                <!-- COLUMNA 1: INFO Y CLIENTE -->
                <div>
                    <h4 style="color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px;">Información Principal</h4>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Nº Pedido</label>
                        <input type="text" name="numero_pedido" value="<?= htmlspecialchars($pedido_editar['numero_pedido']) ?>" class="form-control bg-dark text-white border-secondary">
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Cliente</label>
                        <select name="id_cliente" class="form-control bg-dark text-white border-secondary">
                            <option value="">Manual / Sin Cliente</option>
                            <?php foreach($clientes_lista as $cl): ?>
                                <option value="<?= $cl['id'] ?>" <?= $pedido_editar['id_cliente']==$cl['id']?'selected':'' ?>><?= htmlspecialchars($cl['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Nombre Cliente (Manual)</label>
                        <input type="text" name="nombre_cliente" value="<?= htmlspecialchars($pedido_editar['nombre_cliente'] ?? '') ?>" class="form-control bg-dark text-white border-secondary">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Teléfono</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($pedido_editar['telefono'] ?? '') ?>" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Total (€)</label>
                            <input type="number" step="0.01" name="total" value="<?= $pedido_editar['total'] ?>" class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Estado</label>
                            <select name="nuevo_estado" class="form-control bg-dark text-white border-warning">
                                <?php foreach($estados_map as $es => $label): ?>
                                    <option value="<?= $es ?>" <?= $pedido_editar['estado']===$es?'selected':'' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Prioridad</label>
                            <select name="prioridad" class="form-control bg-dark text-white border-secondary">
                                <option value="Verde" <?= $pedido_editar['prioridad']=='Verde'?'selected':'' ?>>Verde</option>
                                <option value="Amarillo" <?= $pedido_editar['prioridad']=='Amarillo'?'selected':'' ?>>Amarillo</option>
                                <option value="Azul" <?= $pedido_editar['prioridad']=='Azul'?'selected':'' ?>>Azul</option>
                                <option value="Rojo" <?= $pedido_editar['prioridad']=='Rojo'?'selected':'' ?>>Rojo (URGENTE)</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Canal Origen</label>
                        <select name="canal_origen" class="form-control bg-dark text-white border-secondary">
                            <option value="whatsapp" <?= $pedido_editar['canal_origen']=='whatsapp'?'selected':'' ?>>WhatsApp</option>
                            <option value="trendioff" <?= $pedido_editar['canal_origen']=='trendioff'?'selected':'' ?>>Trendioff</option>
                            <option value="directo" <?= $pedido_editar['canal_origen']=='directo'?'selected':'' ?>>Venta Directa</option>
                            <option value="otro" <?= $pedido_editar['canal_origen']=='otro'?'selected':'' ?>>Otro</option>
                        </select>
                    </div>
                </div>

                <!-- COLUMNA 2: DETALLES Y TRACKING -->
                <div>
                    <h4 style="color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px;">Producción y Envío</h4>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">SKU / Artículo</label>
                        <input type="text" name="sku_articulo" value="<?= htmlspecialchars($pedido_editar['sku_articulo'] ?? '') ?>" class="form-control bg-dark text-white border-secondary" placeholder="Ej: TAB-M-01">
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Detalles Críticos (Visible en Kanban)</label>
                        <textarea name="detalles_criticos" class="form-control bg-dark text-white border-danger" rows="1"><?= htmlspecialchars($pedido_editar['detalles_criticos'] ?? '') ?></textarea>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Notas / Instrucciones</label>
                        <textarea name="notas" class="form-control bg-dark text-white border-secondary" rows="2"><?= htmlspecialchars($pedido_editar['notas'] ?? '') ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Transportista</label>
                            <select name="transportista" class="form-control bg-dark text-white border-secondary">
                                <option value="">No definido</option>
                                <option value="Correos" <?= $pedido_editar['transportista']=='Correos'?'selected':'' ?>>Correos</option>
                                <option value="SEUR" <?= $pedido_editar['transportista']=='SEUR'?'selected':'' ?>>SEUR</option>
                                <option value="MRW" <?= $pedido_editar['transportista']=='MRW'?'selected':'' ?>>MRW</option>
                                <option value="GLS" <?= $pedido_editar['transportista']=='GLS'?'selected':'' ?>>GLS</option>
                                <option value="DHL" <?= $pedido_editar['transportista']=='DHL'?'selected':'' ?>>DHL</option>
                                <option value="Otro" <?= $pedido_editar['transportista']=='Otro'?'selected':'' ?>>Otro</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Nº Seguimiento Envío</label>
                            <input type="text" name="tracking_envio" value="<?= htmlspecialchars($pedido_editar['tracking_envio'] ?? '') ?>" class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Código NXT (Tracking Público)</label>
                            <input type="text" name="tracking_code" value="<?= htmlspecialchars($pedido_editar['tracking_code'] ?? '') ?>" class="form-control bg-dark text-white border-warning" style="font-family:monospace;">
                        </div>
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">Compartir Tracking</label>
                            <label class="switch-wow" style="margin-top: 5px;">
                                <input type="checkbox" name="tracking_activo" <?= ($pedido_editar['tracking_activo'] ?? 0)?'checked':'' ?>>
                                <span class="slider-wow round"></span>
                            </label>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:10px;">
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">F. Pedido</label>
                            <input type="datetime-local" name="fecha_pedido" value="<?= date('Y-m-d\TH:i', strtotime($pedido_editar['fecha_pedido'])) ?>" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div>
                            <label style="display:block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px;">F. Prometida Entrega</label>
                            <input type="date" name="fecha_entrega_prometida" value="<?= $pedido_editar['fecha_entrega_prometida'] ?>" class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>
                </div>

            </div>

            <div style="margin-top: 35px; padding-top: 20px; border-top: 1px solid rgba(212,175,55,0.3); display: flex; gap: 15px; justify-content: flex-end;">
                <a href="<?= $self ?>" class="btn-premium-wow btn-blue" style="background:#475569; width: auto; padding: 10px 30px;">Cancelar</a>
                <button type="submit" name="guardar_pedido" class="btn-premium-wow btn-gold" style="width: auto; padding: 10px 40px; font-size: 1.1rem;">
                    <i class="fas fa-save"></i> GUARDAR CAMBIOS
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<style>
    /* Estilos para el Switch / Toggle */
    .switch-wow { position: relative; display: inline-block; width: 44px; height: 22px; }
    .switch-wow input { opacity: 0; width: 0; height: 0; }
    .slider-wow { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #334155; transition: .4s; border-radius: 34px; }
    .slider-wow:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider-wow { background-color: #d4af37; }
    input:checked + .slider-wow:before { transform: translateX(22px); }
</style>

<script>
async function generateTrackingCode(id) {
    if (!confirm('¿Generar código de seguimiento para este pedido?')) return;
    const res = await fetch('../api/index.php?ruta=pedidos&action=generate_code', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    });
    const data = await res.json();
    if (data.ok) location.reload();
}

async function generateMassTracking() {
    if (!confirm('¿Generar códigos para TODOS los pedidos que no lo tengan?')) return;
    const res = await fetch('../api/index.php?ruta=pedidos&action=generate_mass_tracking', {
        method: 'POST'
    });
    const data = await res.json();
    if (data.ok) {
        alert('Se han generado ' + data.generated + ' códigos nuevos.');
        location.reload();
    }
}

async function saveTracking(id) {
    const body = {
        id: id,
        fecha_estimada_entrega: document.getElementById('tra_fecha').value,
        transportista: document.getElementById('tra_carrier').value,
        tracking_envio: document.getElementById('tra_id').value,
        tracking_activo: document.getElementById('tra_active').checked ? 1 : 0
    };
    const res = await fetch('../api/index.php?ruta=pedidos&action=update_tracking', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    });
    const data = await res.json();
    if (data.ok) {
        alert('Seguimiento actualizado');
        location.reload();
    }
}

function copyTrackingLink(code) {
    const url = 'https://noxertez.com/seguimiento?code=' + code;
    navigator.clipboard.writeText(url).then(() => {
        alert('Enlace copiado al portapapeles');
    });
}
</script>

<?php include('../includes/footer.php'); ?>
