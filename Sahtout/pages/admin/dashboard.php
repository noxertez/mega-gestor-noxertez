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

// 1. OBTENER ESTADÍSTICAS BÁSICAS
$stats = ['users' => 0, 'orders' => 0, 'sales' => 0, 'items' => 0];
$res = $site_db->query("SELECT COUNT(*) AS count FROM user_currencies");
if($res) $stats['users'] = $res->fetch_assoc()['count'];
$res = $site_db->query("SELECT COUNT(*) AS count FROM pedidos");
if($res) $stats['orders'] = $res->fetch_assoc()['count'];
$res = $site_db->query("SELECT COUNT(*) AS count FROM ventas");
if($res) $stats['sales'] = $res->fetch_assoc()['count'];
$res = $site_db->query("SELECT COUNT(*) AS count FROM articulos");
if($res) $stats['items'] = $res->fetch_assoc()['count'];

// 2. CONTROL DE SISTEMA
$ia_calls_today = 0;
$res_ia = $site_db->query("SELECT COUNT(*) AS count FROM linkedin_queue WHERE generado_por_ia = 1 AND DATE(fecha_programada) >= CURDATE()");
if($res_ia) $ia_calls_today = $res_ia->fetch_assoc()['count'];

$n8n_status = false;
$fp = @fsockopen("localhost", 5678, $errno, $errstr, 1);
if ($fp) { $n8n_status = true; fclose($fp); }

$page_class = 'management-page';
include $project_root . 'includes/header.php';
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.5">

<style>
    body, h1, h2, h3, h4, h5, h6, .stat-label, .admin-tool-header, .tool-link-list a {
        font-family: 'Inter', sans-serif !important;
    }

    /* BARRA DE CONTROL COMPACTA */
    .system-control-bar {
        background: rgba(10, 10, 10, 0.4);
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 12px;
        padding: 10px 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        backdrop-filter: blur(10px);
    }
    .control-item { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .dot-green { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
    .dot-red { background: #ef4444; box-shadow: 0 0 8px #ef4444; }
    
    .stat-card-wow {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-glass);
        border-radius: 16px;
        padding: 1.2rem;
        text-align: center;
    }
    .stat-value { font-size: 2rem; font-weight: 800; color: #fff; }
    .stat-label { color: var(--accent-gold); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; }
    
    .admin-tool-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-glass);
        border-radius: 20px;
        overflow: hidden;
    }
    .admin-tool-header {
        background: rgba(212, 175, 55, 0.1);
        padding: 1rem 1.5rem;
        color: var(--accent-gold);
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.9rem;
    }
    .admin-tool-body { padding: 1.5rem; }
    .tool-link-list a {
        color: #ccc;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        font-size: 0.95rem;
        transition: 0.2s;
    }
    .tool-link-list a:hover { color: var(--accent-gold); padding-left: 8px; }
    .tool-link-list i { width: 20px; text-align: center; }
</style>

<div class="wrapper">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include $project_root . 'includes/admin_sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3" style="font-weight: 800; letter-spacing: -0.5px;">Sistema Central de Control</h1>
                    <div class="badge-wow btn-gold" style="font-size: 0.7rem;">ADMIN DASHBOARD</div>
                </div>

                <!-- BARRA DE CONTROL DE SISTEMA -->
                <div class="system-control-bar">
                    <div class="control-item">
                        <span style="color: #888;">n8n:</span>
                        <span class="status-dot <?= $n8n_status ? 'dot-green' : 'dot-red' ?>"></span>
                        <strong style="color: <?= $n8n_status ? '#22c55e' : '#ef4444' ?>"><?= $n8n_status ? 'ACTIVO' : 'OFFLINE' ?></strong>
                    </div>
                    <div class="control-item">
                        <i class="fas fa-robot" style="color: var(--accent-gold);"></i>
                        <span style="color: #888;">IA Llamadas:</span>
                        <strong style="color: #fff;"><?= $ia_calls_today ?></strong>
                    </div>
                    <div class="control-item">
                        <i class="fas fa-database" style="color: #3b82f6;"></i>
                        <span style="color: #888;">Database:</span>
                        <strong style="color: #22c55e;">OK</strong>
                    </div>
                    <div class="control-item ms-auto">
                        <button onclick="location.reload()" class="btn btn-sm btn-outline-warning" style="font-size: 0.7rem; border-radius: 20px;">
                            <i class="fas fa-sync-alt"></i> REFRESH
                        </button>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3"><div class="stat-card-wow"><div class="stat-label">Web Masters</div><div class="stat-value"><?= $stats['users'] ?></div></div></div>
                    <div class="col-md-3"><div class="stat-card-wow"><div class="stat-label">Pedidos</div><div class="stat-value"><?= $stats['orders'] ?></div></div></div>
                    <div class="col-md-3"><div class="stat-card-wow"><div class="stat-label">Ventas</div><div class="stat-value"><?= $stats['sales'] ?></div></div></div>
                    <div class="col-md-3"><div class="stat-card-wow"><div class="stat-label">Artículos</div><div class="stat-value"><?= $stats['items'] ?></div></div></div>
                </div>

                <!-- SECCIÓN INTOCABLE RESTAURADA -->
                <div class="row g-4">
                    <div class="col-md-12">
                        <div class="admin-tool-card" style="border-color: #ef4444; border-width: 2px;">
                            <div class="admin-tool-header" style="background: rgba(239, 68, 68, 0.2); color: #fff; display: flex; justify-content: space-between; align-items: center;">
                                <span><i class="fas fa-exclamation-triangle"></i> HERRAMIENTAS DE SISTEMA (WOW)</span>
                                <span style="background: #ef4444; color: #fff; padding: 2px 10px; border-radius: 4px; font-size: 0.7rem; animation: pulse 2s infinite;">⚠️ SECCIÓN CRÍTICA - NO TOCAR / NO CAMBIAR</span>
                            </div>
                            <div class="admin-tool-body">
                                <div class="row">
                                    <div class="col-md-4 tool-link-list">
                                        <h6 style="color: var(--accent-gold); font-size: 0.8rem; margin-bottom: 15px;">GENERAL & CONTENIDO</h6>
                                        <a href="<?= $base_path ?>pages/how_to_play.php"><i class="fas fa-book-open text-info"></i> Cómo Jugar</a>
                                        <a href="<?= $base_path ?>pages/herramientas.php"><i class="fas fa-tools text-info"></i> Herramientas</a>
                                        <a href="<?= $base_path ?>pages/news.php"><i class="fas fa-newspaper text-info"></i> Noticias</a>
                                        <a href="<?= $base_path ?>pages/shop.php"><i class="fas fa-shopping-bag text-info"></i> Tienda</a>
                                        <a href="<?= $base_path ?>pages/producto.php"><i class="fas fa-box text-info"></i> Producto</a>
                                        <a href="<?= $base_path ?>pages/vote.php"><i class="fas fa-vote-yea text-info"></i> Votos</a>
                                    </div>
                                    <div class="col-md-4 tool-link-list">
                                        <h6 style="color: var(--accent-gold); font-size: 0.8rem; margin-bottom: 15px;">GESTIÓN & REDES</h6>
                                        <a href="<?= $base_path ?>pages/urgentes.php"><i class="fas fa-exclamation-triangle text-info"></i> Urgentes</a>
                                        <a href="<?= $base_path ?>pages/tareas.php"><i class="fas fa-tasks text-info"></i> Tareas</a>
                                        <a href="<?= $base_path ?>pages/pinterest.php" style="color:#E60023"><i class="fab fa-pinterest"></i> Pinterest Publisher</a>
                                        <a href="<?= $base_path ?>pages/linkedin.php" style="color:#0A66C2"><i class="fab fa-linkedin"></i> LinkedIn Publisher</a>
                                    </div>
                                    <div class="col-md-4 tool-link-list">
                                        <h6 style="color: #3b82f6; font-size: 0.8rem; margin-bottom: 15px;">ARMERÍA & ARENA</h6>
                                        <a href="<?= $base_path ?>pages/character.php"><i class="fas fa-user-shield text-primary"></i> Personaje</a>
                                        <a href="<?= $base_path ?>pages/armory/arenateam.php"><i class="fas fa-users text-primary"></i> Equipos Arena</a>
                                        <a href="<?= $base_path ?>pages/armory/solo_pvp.php"><i class="fas fa-swords text-primary"></i> Solo PvP</a>
                                        <a href="<?= $base_path ?>pages/armory/arena_2v2.php"><i class="fas fa-trophy text-primary"></i> Arena 2v2</a>
                                        <a href="<?= $base_path ?>pages/armory/arena_3v3.php"><i class="fas fa-trophy text-primary"></i> Arena 3v3</a>
                                        <a href="<?= $base_path ?>pages/armory/arena_5v5.php"><i class="fas fa-trophy text-primary"></i> Arena 5v5</a>
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
