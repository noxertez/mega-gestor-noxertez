<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}
require_once __DIR__ . '/paths.php';
?>
<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/admin/admin_sidebar.css">
<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    exit;
}
$page_class = $page_class ?? '';
?>

<aside class="col-md-2 admin-sidebar">
    <div class="card admin-sidebar-card">
        <div class="card-header admin-sidebar-header">
            <h5 class="mb-0"><?php echo translate('admin_menu', 'Admin Menu'); ?></h5>
            <button class="mobile-toggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="card-body p-2 admin-sidebar-menu">
            <ul class="nav flex-column admin-sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/dashboard">
                        <i class="fas fa-tachometer-alt me-2"></i> <?php echo translate('admin_dashboard', 'Dashboard'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'users' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/users">
                        <i class="fas fa-users me-2"></i> <?php echo translate('admin_users', 'User Management'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'anews' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/anews">
                        <i class="fas fa-newspaper me-2"></i> <?php echo translate('admin_news', 'News Management'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'pedidos' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/pedidos.php">
                        <i class="fas fa-box me-2"></i> Pedidos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'ventas' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/ventas.php">
                        <i class="fas fa-chart-line me-2"></i> Ventas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'analisis' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/admin/dashboard_stats.php">
                        <i class="fas fa-chart-bar me-2"></i> Análisis de Ventas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'plantillas' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/admin/flujo_plantillas.php">
                        <i class="fas fa-project-diagram me-2"></i> Gestión de Plantillas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'shop' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/ashop">
                        <i class="fas fa-shopping-cart me-2"></i> <?php echo translate('admin_shop', 'Shop Management'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'items' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/stock.php?tab=5.3">
                        <i class="fas fa-th me-2"></i> Artículos (Catálogo)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'settings' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/settings/general">
                        <i class="fas fa-cogs me-2"></i> <?php echo translate('admin_settings', 'Settings'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'pinterest' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/pinterest.php" style="color: #E60023 !important;">
                        <i class="fab fa-pinterest me-2"></i> Pinterest Publisher
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'linkedin' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/linkedin.php" style="color: #0A66C2 !important;">
                        <i class="fab fa-linkedin me-2"></i> LinkedIn Publisher
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="<?php echo $base_path; ?>logout">
                        <i class="fas fa-sign-out-alt me-2"></i> <?php echo translate('logout', 'Logout'); ?>
                    </a>
                </li>
                <hr style="border-color: rgba(255,255,255,0.1);">
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0)" onclick="restartN8NAdmin()" style="background: rgba(124,58,237,0.2); color: #a78bfa !important; border-radius: 8px; margin-top: 10px; font-weight: bold;">
                        <i class="fas fa-sync-alt me-2"></i> REINICIAR n8n
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>

<script>
function restartN8NAdmin() {
    if(!confirm("¿Deseas reiniciar el servidor n8n? Se detendrán los flujos activos.")) return;
    
    // Llamar al proxy PHP que a su vez llama al puerto 5000 localmente
    fetch('<?php echo $base_path; ?>api/n8n?action=restart')
    .then(r => r.json())
    .then(d => {
        if(d.success) {
            alert("✅ Solicitud de reinicio enviada correctamente.");
        } else {
            alert("❌ Error: " + (d.error || "Error desconocido"));
        }
    })
    .catch(e => {
        console.error("Error n8n restart:", e);
        alert("❌ Error de conexión con la API del CMS.");
    });
}
</script>

<script src="<?php echo $base_path; ?>assets/js/includes/admin_sidebar.js"></script>
