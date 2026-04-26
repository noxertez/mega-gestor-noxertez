<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
include('../includes/header.php');

if(!isset($_SESSION['user_id'])){
    header('Location:/noxertez/login.php');exit;
}
require_once('../api/config.php');
$db = conectar();

$id = $_GET['id'] ?? null;
if(!$id) die("ID no proporcionado");

$c = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$c->execute([$id]);
$cliente = $c->fetch();

if(!$cliente) die("Cliente no encontrado");

// Historial de pedidos
$p = $db->prepare("SELECT * FROM pedidos WHERE id_cliente = ? ORDER BY fecha_pedido DESC");
$p->execute([$id]);
$pedidos = $p->fetchAll();
?>

<div class="nox-content">
    <div style="display: flex; gap: 20px; align-items: flex-start;">
        <!-- Ficha técnica -->
        <div class="nox-tarjeta" style="flex: 1; min-width: 300px;">
            <h2 style="border:none; margin-top:0;"><i class="fas fa-id-card"></i> <?= htmlspecialchars($cliente['nombre']) ?></h2>
            <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 4px; border-left: 3px solid #C89B3C;">
                <p><b><i class="fas fa-phone"></i> Tel:</b> <?= htmlspecialchars($cliente['telefono'] ?: '—') ?></p>
                <p><b><i class="fas fa-envelope"></i> Email:</b> <?= htmlspecialchars($cliente['email'] ?: '—') ?></p>
                <p><b><i class="fab fa-instagram"></i> Insta:</b> <?= htmlspecialchars($cliente['instagram'] ?: '—') ?></p>
                <p><b><i class="fas fa-map-marker-alt"></i> Ciudad:</b> <?= htmlspecialchars($cliente['ciudad'] ?: '—') ?></p>
                <p><b><i class="fas fa-globe"></i> País:</b> <?= htmlspecialchars($cliente['pais'] ?: '—') ?></p>
            </div>
            <div style="margin-top: 15px;">
                <label style="font-size: 11px; color: #C89B3C;">Dirección Completa</label>
                <p style="font-size: 13px; color: #94a3b8;"><?= nl2br(htmlspecialchars($cliente['direccion'] ?: 'Sin dirección registrada')) ?></p>
            </div>
            <div style="margin-top: 15px; background: rgba(200, 155, 60, 0.1); padding: 10px; border-radius: 4px;">
                <label style="font-size: 11px; color: #C89B3C;">Notas del Cliente</label>
                <p style="font-size: 12px;"><?= nl2br(htmlspecialchars($cliente['notas'] ?: 'Sin notas')) ?></p>
            </div>
            <button class="nox-btn" style="width: 100%; margin-top: 20px;" onclick="window.history.back()">Volver al Listado</button>
        </div>

        <!-- Historial -->
        <div style="flex: 2;">
            <h2><i class="fas fa-history"></i> Historial de Pedidos</h2>
            <?php
if(empty($pedidos)): ?>
                <div class="nox-tarjeta" style="text-align: center; color: #94a3b8;">
                    Este cliente aún no tiene pedidos registrados.
                </div>
            <?php
else: ?>
                <table class="nox-tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>SKU / Art.</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Tracking</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
foreach($pedidos as $ped): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($ped['fecha_pedido'])) ?></td>
                            <td><code style="color: #C89B3C;"><?= htmlspecialchars($ped['sku_articulo'] ?: 'Manual') ?></code></td>
                            <td>
                                <span style="font-size: 11px; background: rgba(200, 155, 60, 0.2); padding: 2px 6px; border-radius: 3px;">
                                    <?= $ped['estado'] ?>
                                </span>
                            </td>
                            <td style="font-weight: bold;"><?= number_format($ped['total']??0, 2) ?> €</td>
                            <td style="font-size: 11px;"><?= $ped['tracking_id'] ?: '—' ?></td>
                        </tr>
                    <?php
endforeach; ?>
                    </tbody>
                </table>
            <?php
endif; ?>
        </div>
    </div>
</div>

<?php
include('../includes/footer.php'); ?>



