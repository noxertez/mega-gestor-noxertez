<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';

// Access Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: {$base_path}login");
    exit;
}

// Get Stats
$stats = [
    'users' => 0,
    'orders' => 0,
    'sales' => 0,
    'items' => 0
];

$res = $site_db->query("SELECT COUNT(*) AS count FROM user_currencies");
if($res) $stats['users'] = $res->fetch_assoc()['count'];

$res = $site_db->query("SELECT COUNT(*) AS count FROM pedidos");
if($res) $stats['orders'] = $res->fetch_assoc()['count'];

$res = $site_db->query("SELECT COUNT(*) AS count FROM ventas");
if($res) $stats['sales'] = $res->fetch_assoc()['count'];

$res = $site_db->query("SELECT COUNT(*) AS count FROM articulos");
if($res) $stats['items'] = $res->fetch_assoc()['count'];

$page_class = 'management-page';
include $project_root . 'includes/header.php';
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.3">

<style>
    .stat-card-wow {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-glass);
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        transition: transform 0.3s, border-color 0.3s;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }
    .stat-card-wow:hover {
        transform: translateY(-5px);
        border-color: var(--accent-gold);
        background: rgba(212, 175, 55, 0.05);
    }
    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--text-white);
        margin: 0.5rem 0;
    }
    .stat-label {
        color: var(--accent-gold);
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
        font-family: 'Cinzel', serif;
    }
    .admin-tool-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-glass);
        border-radius: 20px;
        overflow: hidden;
    }
    .admin-tool-header {
        background: rgba(212, 175, 55, 0.1);
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-glass);
        color: var(--accent-gold);
        font-family: 'Cinzel', serif;
        font-weight: bold;
    }
    .admin-tool-body {
        padding: 1.5rem;
    }
    .tool-link-list li {
        margin-bottom: 0.8rem;
    }
    .tool-link-list a {
        color: var(--text-gray);
        text-decoration: none;
        transition: color 0.2s, padding-left 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.95rem;
    }
    .tool-link-list a:hover {
        color: var(--accent-blue);
        padding-left: 8px;
    }
    .tool-link-list i {
        width: 20px;
        text-align: center;
    }
</style>

<div class="wrapper">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include $project_root . 'includes/admin_sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
                
                <div class="panel-header-wow">
                    <h1>SISTEMA CENTRAL DE CONTROL</h1>
                    <div class="badge-wow btn-gold" style="font-size: 0.7rem; font-family: sans-serif;">
                        NODO ACTIVO: <?= strtoupper($_SESSION['username']) ?>
                    </div>
                </div>

                <!-- NEW Stats Grid -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card-wow">
                            <div class="stat-label">Web Masters</div>
                            <div class="stat-value"><?= $stats['users'] ?></div>
                            <i class="fas fa-users-cog" style="opacity: 0.1; position: absolute; right: 15px; bottom: 15px; font-size: 2.5rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-wow">
                            <div class="stat-label">Pedidos Activos</div>
                            <div class="stat-value"><?= $stats['orders'] ?></div>
                            <i class="fas fa-box-open" style="opacity: 0.1; position: absolute; right: 15px; bottom: 15px; font-size: 2.5rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-wow">
                            <div class="stat-label">Ventas Totales</div>
                            <div class="stat-value"><?= $stats['sales'] ?></div>
                            <i class="fas fa-coins" style="opacity: 0.1; position: absolute; right: 15px; bottom: 15px; font-size: 2.5rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-wow">
                            <div class="stat-label">Artículos Catálogo</div>
                            <div class="stat-value"><?= $stats['items'] ?></div>
                            <i class="fas fa-gem" style="opacity: 0.1; position: absolute; right: 15px; bottom: 15px; font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>

                <!-- Admin News / Recent Activity -->
                <div class="row g-4">
                    <!-- GESTIÓN DE ARTESANÍA CARD -->
                    <div class="col-md-6">
                        <div class="admin-tool-card h-100">
                            <div class="admin-tool-header">
                                <i class="fas fa-hammer"></i> Gestión de Producción
                            </div>
                            <div class="admin-tool-body">
                                <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 1.5rem;">Acceso directo a las herramientas de fabricación y flujo de pedidos.</p>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <a href="<?= $base_path ?>pages/pedidos.php" class="btn-premium-wow btn-gold w-100" style="justify-content:center; padding: 0.8rem; font-size: 0.9rem;">
                                            <i class="fas fa-list"></i> LISTADO
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?= $base_path ?>pages/kanban.php" class="btn-premium-wow btn-gold w-100" style="justify-content:center; padding: 0.8rem; font-size: 0.9rem;">
                                            <i class="fas fa-tasks"></i> KANBAN
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?= $base_path ?>pages/stock.php" class="btn-premium-wow btn-gold w-100" style="justify-content:center; padding: 0.8rem; font-size: 0.9rem;">
                                            <i class="fas fa-warehouse"></i> STOCK
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?= $base_path ?>pages/clientes.php" class="btn-premium-wow btn-gold w-100" style="justify-content:center; padding: 0.8rem; font-size: 0.9rem;">
                                            <i class="fas fa-address-book"></i> CLIENTES
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?= $base_path ?>pages/pinterest.php" class="btn-premium-wow w-100" style="justify-content:center; padding: 0.8rem; font-size: 0.9rem; background: #E60023;">
                                            <i class="fab fa-pinterest"></i> PINTEREST
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?= $base_path ?>pages/linkedin.php" class="btn-premium-wow w-100" style="justify-content:center; padding: 0.8rem; font-size: 0.9rem; background: #0A66C2;">
                                            <i class="fab fa-linkedin"></i> LINKEDIN
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- HERRAMIENTAS CMS CARD -->
                    <div class="col-md-6">
                        <div class="admin-tool-card h-100" style="border-color: var(--accent-blue);">
                            <div class="admin-tool-header" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                                <i class="fas fa-cogs"></i> Herramientas de Sistema (WoW)
                            </div>
                            <div class="admin-tool-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h6 style="color: var(--accent-gold); font-size: 0.75rem; border-bottom: 1px solid rgba(212,175,55,0.2); padding-bottom: 5px; margin-bottom: 10px;">GENERAL & CONTENIDO</h6>
                                        <ul class="list-unstyled tool-link-list mb-3">
                                            <li><a href="<?= $base_path ?>pages/how_to_play.php"><i class="fas fa-book-open text-info"></i> Cómo Jugar</a></li>
                                            <li><a href="<?= $base_path ?>pages/herramientas.php"><i class="fas fa-tools text-info"></i> Herramientas</a></li>
                                            <li><a href="<?= $base_path ?>pages/news.php"><i class="fas fa-newspaper text-info"></i> Noticias</a></li>
                                            <li><a href="<?= $base_path ?>pages/shop.php"><i class="fas fa-shopping-bag text-info"></i> Tienda</a></li>
                                            <li><a href="<?= $base_path ?>pages/producto.php"><i class="fas fa-box text-info"></i> Producto</a></li>
                                            <li><a href="<?= $base_path ?>pages/vote.php"><i class="fas fa-vote-yea text-info"></i> Votos</a></li>
                                            <li><a href="<?= $base_path ?>pages/urgentes.php"><i class="fas fa-exclamation-triangle text-info"></i> Urgentes</a></li>
                                            <li><a href="<?= $base_path ?>pages/tareas.php"><i class="fas fa-tasks text-info"></i> Tareas</a></li>
                                            <li><a href="<?= $base_path ?>pages/pinterest.php" style="color:#E60023"><i class="fab fa-pinterest"></i> Pinterest Publisher</a></li>
                                            <li><a href="<?= $base_path ?>pages/linkedin.php" style="color:#0A66C2"><i class="fab fa-linkedin"></i> LinkedIn Publisher</a></li>
                                        </ul>
                                    </div>
                                    <div class="col-6">
                                        <h6 style="color: var(--accent-blue); font-size: 0.75rem; border-bottom: 1px solid rgba(59,130,246,0.2); padding-bottom: 5px; margin-bottom: 10px;">ARMERÍA & ARENA</h6>
                                        <ul class="list-unstyled tool-link-list mb-0">
                                            <li><a href="<?= $base_path ?>pages/character.php"><i class="fas fa-user-shield text-primary"></i> Personaje</a></li>
                                            <li><a href="<?= $base_path ?>pages/armory/arenateam.php"><i class="fas fa-users text-primary"></i> Equipos Arena</a></li>
                                            <li><a href="<?= $base_path ?>pages/armory/solo_pvp.php"><i class="fas fa-swords text-primary"></i> Solo PvP</a></li>
                                            <li><a href="<?= $base_path ?>pages/armory/arena_2v2.php"><i class="fas fa-trophy text-primary"></i> Arena 2v2</a></li>
                                            <li><a href="<?= $base_path ?>pages/armory/arena_3v3.php"><i class="fas fa-trophy text-primary"></i> Arena 3v3</a></li>
                                            <li><a href="<?= $base_path ?>pages/armory/arena_5v5.php"><i class="fas fa-trophy text-primary"></i> Arena 5v5</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<?php include $project_root . 'includes/footer.php'; ?>
<?php 
$site_db->close();
$auth_db->close();
?>
