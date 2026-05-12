<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}
require_once __DIR__ . '/paths.php';
?>
<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/admin/admin_sidebar.css?v=2.2">
<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    exit;
}
$page_class = $page_class ?? '';
?>

<aside class="col-md-2 admin-sidebar">
    <div class="card admin-sidebar-card">
        <div class="card-header admin-sidebar-header">
            <h5 class="mb-0">ADMINISTRACIÓN</h5>
        </div>
        
        <div class="card-body p-0">
            
            <!-- SECCIÓN: PRINCIPAL -->
            <div class="sidebar-section-title">Inicio</div>
            <div class="sidebar-btn-grid">
                <a class="sidebar-btn <?php echo $page_class === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'users' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/users">
                    <i class="fas fa-user-shield"></i>
                    <span>Usuarios</span>
                </a>
            </div>

            <!-- SECCIÓN: COMERCIAL -->
            <div class="sidebar-section-title">Comercial</div>
            <div class="sidebar-btn-grid">
                <a class="sidebar-btn <?php echo $page_class === 'pedidos' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/pedidos.php">
                    <i class="fas fa-box"></i>
                    <span>Pedidos</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'ventas' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/ventas.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Ventas</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'analisis' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/admin/dashboard_stats.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Análisis</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'anews' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/anews">
                    <i class="fas fa-newspaper"></i>
                    <span>Noticias</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'clientes' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/clientes.php">
                    <i class="fas fa-address-book"></i>
                    <span>Clientes</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'envios' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/envios.php">
                    <i class="fas fa-truck"></i>
                    <span>Envíos</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'email' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/email/index.php" style="border-color:#7c3aed;">
                    <i class="fas fa-envelope" style="color:#a78bfa;"></i>
                    <span>Email</span>
                </a>
            </div>

            <!-- SECCIÓN: CATÁLOGO Y DISEÑO -->
            <div class="sidebar-section-title">Catálogo / Diseño</div>
            <div class="sidebar-btn-grid">
                <a class="sidebar-btn <?php echo $page_class === 'items' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/stock.php?tab=5.3">
                    <i class="fas fa-th"></i>
                    <span>Artículos</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'fichas' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/fichas_tecnicas.php">
                    <i class="fas fa-certificate"></i>
                    <span>Fichas T.</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'mockups' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/mockups.php">
                    <i class="fas fa-palette"></i>
                    <span>Mockups</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'plantillas' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/admin/flujo_plantillas.php">
                    <i class="fas fa-project-diagram"></i>
                    <span>Plantillas</span>
                </a>
            </div>

            <!-- SECCIÓN: MARKETING / REDES -->
            <div class="sidebar-section-title">Redes Sociales</div>
            <div class="sidebar-btn-grid">
                <a class="sidebar-btn" href="<?php echo $base_path; ?>pages/pinterest.php" style="border-color: #E60023;">
                    <i class="fab fa-pinterest" style="color: #E60023;"></i>
                    <span>Pinterest</span>
                </a>
                <a class="sidebar-btn" href="<?php echo $base_path; ?>pages/linkedin.php" style="border-color: #0A66C2;">
                    <i class="fab fa-linkedin" style="color: #0A66C2;"></i>
                    <span>LinkedIn</span>
                </a>
                <a class="sidebar-btn <?php echo $page_class === 'influencers' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>pages/influencers.php">
                    <i class="fab fa-instagram"></i>
                    <span>Influencers</span>
                </a>
            </div>

            <!-- SECCIÓN: SISTEMA -->
            <div class="sidebar-section-title">Configuración</div>
            <div class="sidebar-btn-grid">
                <a class="sidebar-btn <?php echo $page_class === 'settings' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/settings/general">
                    <i class="fas fa-cogs"></i>
                    <span>Ajustes</span>
                </a>
                <a class="sidebar-btn" href="javascript:void(0)" onclick="restartN8NAdmin()">
                    <i class="fas fa-sync-alt"></i>
                    <span>N8N Sync</span>
                </a>
            </div>

            <!-- SECCIÓN CORE -->
            <div class="sidebar-section-title">Core</div>
            <div class="sidebar-btn-grid">
                <a class="sidebar-btn <?php echo $page_class === 'shop' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/ashop">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Shop Admin</span>
                </a>
                <a class="sidebar-btn btn-logout" href="<?php echo $base_path; ?>logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Salir</span>
                </a>
            </div>

        </div>
    </div>
</aside>

<script>
function restartN8NAdmin() {
    if(!confirm("¿Deseas reiniciar el servidor n8n?")) return;
    fetch('<?php echo $base_path; ?>api/n8n?action=restart')
    .then(r => r.json())
    .then(d => {
        if(d.success) alert("✅ Reinicio enviado correctamente.");
        else alert("❌ Error: " + (d.error || "Desconocido"));
    })
    .catch(e => alert("❌ Error de conexión."));
}
</script>
<script src="<?php echo $base_path; ?>assets/js/includes/admin_sidebar.js"></script>
