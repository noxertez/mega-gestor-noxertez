<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
$page_class = 'management-page';
require_once('../includes/header.php');
?>

<!-- Estilos Específicos -->
<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.3">
<link rel="stylesheet" href="<?= $base_path ?>assets/css/clientes.css?v=1.4">

<?php
// Database connection for the list
$db = new PDO('mysql:host=localhost;dbname=noxertez;charset=utf8mb4', 'noxertez_user', 'Noxertez2024!');
$clientes = $db->query(
    'SELECT c.*, COUNT(p.id) as total_pedidos, COALESCE(SUM(p.total),0) as total_gastado
     FROM clientes c LEFT JOIN pedidos p ON p.id_cliente = c.id
     WHERE c.activo = 1 GROUP BY c.id ORDER BY c.nombre'
)->fetchAll();
?>

<div class="panel-clientes">
    <div class="panel-header">
        <h1>Gestión de Clientes</h1>
        <button onclick="nuevoCliente()" class="btn-premium btn-nuevo">
            <i class="fas fa-plus"></i> Nuevo Cliente
        </button>
    </div>

    <div class="search-container">
        <input type="text" id="buscador" placeholder="Buscar por nombre, teléfono, email..." oninput="filtrarClientes()" class="buscador-wow">
    </div>

    <div class="tabla-container">
        <table class="tabla-wow" id="tablaClientes">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Ciudad</th>
                    <th>Pedidos</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($clientes as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['ciudad'] ?? '—') ?></td>
                    <td><?= $c['total_pedidos'] ?></td>
                    <td><?= number_format($c['total_gastado'], 2) ?> €</td>
                    <td>
                        <button onclick="verCliente(<?= $c['id'] ?>)" class="btn-action btn-ver" title="Ver Detalles">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="eliminarCliente(<?= $c['id'] ?>)" class="btn-action btn-eliminar" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CLIENT MODAL -->
<div id="clientModal" class="modal-overlay" onclick="if(event.target==this) closeModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Detalles del Cliente</h2>
            <button class="btn-action" onclick="closeModal()" style="font-size: 1.5rem;">&times;</button>
        </div>
        
        <div class="modal-tabs">
            <button class="tab-btn active" onclick="switchTab('tab-perfil')">1: Datos Personales</button>
            <button class="tab-btn" id="btn-tab-asignacion" onclick="switchTab('tab-asignacion')">2: Nueva Asignación</button>
            <button class="tab-btn" id="btn-tab-historial" onclick="switchTab('tab-historial')">3: Historial / Estado</button>
        </div>

        <div class="modal-body">
            <!-- TAB PERFIL -->
            <div id="tab-perfil" class="tab-content active">
                <form id="clientForm" onsubmit="event.preventDefault(); guardarCliente();">
                    <input type="hidden" name="id" id="clientId">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre Completo</label>
                            <input type="text" name="nombre" id="nombre" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" id="telefono">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="email">
                        </div>
                        <div class="form-group">
                            <label>Instagram</label>
                            <input type="text" name="instagram" id="instagram">
                        </div>
                        <div class="form-group full-width">
                            <label>Dirección</label>
                            <input type="text" name="direccion" id="direccion">
                        </div>
                        <div class="form-group">
                            <label>Ciudad</label>
                            <input type="text" name="ciudad" id="ciudad">
                        </div>
                        <div class="form-group">
                            <label>Código Postal</label>
                            <input type="text" name="codigo_postal" id="codigo_postal">
                        </div>
                        <div class="form-group">
                            <label>País</label>
                            <input type="text" name="pais" id="pais" value="España">
                        </div>
                        <div class="form-group full-width">
                            <label>Notas</label>
                            <textarea name="notas" id="notas" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB ASIGNACION -->
            <div id="tab-asignacion" class="tab-content">
                <div class="search-container">
                    <input type="text" id="searchProductInput" placeholder="Buscar producto por SKU o nombre..." class="buscador-wow" style="background: rgba(0,0,0,0.5);" oninput="buscarProductos()">
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="modal-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Nombre</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="productSearchResults">
                            <!-- JS populated -->
                        </tbody>
                    </table>
                </div>

                <div id="assignmentAction" style="display: none; margin-top: 1.5rem; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 12px; border: 1px solid var(--accent-green);">
                    <h4 id="selectedProductName" style="margin-bottom: 1rem; color: var(--accent-green);"></h4>
                    <input type="hidden" id="selectedProductSku">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <label style="flex-shrink: 0;">Tipo de Trabajo:</label>
                        <select id="tipoTrabajo" style="flex: 1;">
                            <option value="Stock (Listo)">Stock (Listo)</option>
                            <option value="Solo Barnizar">Solo Barnizar</option>
                            <option value="Para Montaje">Para Montaje</option>
                            <option value="Fabricar Total" selected>Fabricar Total</option>
                        </select>
                        <button onclick="crearPedido()" class="btn-premium" style="background: var(--accent-green);">EMPEZAR PEDIDO</button>
                    </div>
                </div>
            </div>

            <!-- TAB HISTORIAL -->
            <div id="tab-historial" class="tab-content">
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>ID Pedido</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>SKU Articulo</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="orderHistoryList">
                        <!-- JS populated -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-premium" style="background: #4b5563;" onclick="closeModal()">Cancelar</button>
            <button type="button" id="btnGuardar" class="btn-premium btn-nuevo" onclick="guardarCliente()">Guardar Cambios</button>
        </div>
    </div>
</div>

<script src="assets/js/clientes.js"></script>

<?php require_once('../includes/footer.php'); ?>
