<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Include paths.php to access $project_root and $base_path
require_once __DIR__ . '/paths.php';

if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

// CRITICAL: Include config.php BEFORE using $site_db, $auth_db, etc.
require_once $project_root . 'includes/config.php'; 
require_once $project_root . 'includes/config.settings.php'; // load logo + socials
// Include language detection
require_once $project_root . 'languages/language.php';

// Check if session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    echo "<!-- Session not active -->\n";
}

// Ensure $page_class is defined
$page_class = isset($page_class) ? $page_class : 'default';

// Get current URL
$currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentUrl = rtrim($currentUrl, '/');

// Language URLs helper
function getLanguageUrl($lang) {
    global $currentUrl;
    $query = $_GET;
    $query['lang'] = $lang;
    return $currentUrl . '?' . http_build_query($query);
}

// User Data Fetching
$points = 0; $tokens = 0; $email = 'user@example.com';
$avatar = $base_path . 'img/accountimg/profile_pics/user.jpg';
$gmlevel = 0; $role = 'player';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['avatar'])) {
        $avatar_filename = $_SESSION['avatar'] !== '' ? $_SESSION['avatar'] : 'user.jpg';
        $avatar = $base_path . 'img/accountimg/profile_pics/' . $avatar_filename;
    }
    
    // Queries from original backup
    $stmt_site = $site_db->prepare("SELECT points, tokens, avatar, role FROM user_currencies WHERE account_id = ?");
    $stmt_auth = $auth_db->prepare("SELECT email FROM account WHERE id = ?");
    
    if ($stmt_site && $stmt_auth) {
        $stmt_site->bind_param('i', $_SESSION['user_id']);
        $stmt_site->execute();
        $result_site = $stmt_site->get_result();
        
        $stmt_auth->bind_param('i', $_SESSION['user_id']);
        $stmt_auth->execute();
        $result_auth = $stmt_auth->get_result();
        
        if ($result_site && $result_site->num_rows > 0 && $result_auth && $result_auth->num_rows > 0) {
            $row_site = $result_site->fetch_assoc();
            $row_auth = $result_auth->fetch_assoc();
            $points = (int)$row_site['points'];
            $tokens = (int)$row_site['tokens'];
            $email = htmlspecialchars($row_auth['email'] ?? 'user@example.com', ENT_QUOTES, 'UTF-8');
            $role = $row_site['role'] ?? 'player';
            
            if (!empty($row_site['avatar'])) {
                $avatar = $base_path . 'img/accountimg/profile_pics/' . htmlspecialchars($row_site['avatar'], ENT_QUOTES, 'UTF-8');
            }
        }
        $stmt_site->close();
        $stmt_auth->close();
    }

    $stmt = $auth_db->prepare("SELECT gmlevel FROM account_access WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $gmData = $result->fetch_assoc();
            $gmlevel = (int)$gmData['gmlevel'];
        }
        $stmt->close();
    }
}

// Current language
$current_lang = $_SESSION['lang'] ?? 'es';
$languages = [
    'en' => ['name' => 'English', 'flag_url' => $base_path . 'languages/flags/en.png'],
    'fr' => ['name' => 'Français', 'flag_url' => $base_path . 'languages/flags/fr.png'],
    'es' => ['name' => 'Español', 'flag_url' => $base_path . 'languages/flags/es.png'],
    'de' => ['name' => 'Deutsch', 'flag_url' => $base_path . 'languages/flags/de.png'],
    'ru' => ['name' => 'Русский', 'flag_url' => $base_path . 'languages/flags/ru.png'],
    'pt' => ['name' => 'Português', 'flag_url' => $base_path . 'languages/flags/pt.png'],
];
$current_lang_name = $languages[$current_lang]['name'];
$current_lang_flag = $languages[$current_lang]['flag_url'];
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo $base_path; ?>">
    <link rel="icon" href="<?php echo $base_path . $site_logo; ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/header.css">
    <!-- Chatbot Assistant Styles -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/chatbot_widget.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <?php if (file_exists($project_root . "assets/css/{$page_class}.css")): ?>
        <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/<?php echo $page_class; ?>.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<style>
    :root {
        --point-wow-gif: default;
        --hover-wow-gif: pointer;
    }
    .admin-nav-btn {
        margin: 2px;
        padding: 5px 10px !important;
        font-size: 0.85em !important;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    nav { gap: 10px; flex-wrap: wrap; }

    /* Estilos para el nuevo menú Administración */
    .nox-dropdown-wrapper {
        position: relative;
        display: inline-block;
    }
    .nox-dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        min-width: 200px;
        background: rgba(10, 10, 10, 0.95);
        backdrop-filter: blur(15px);
        border: 2px solid #d4af37;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.8);
        z-index: 9999;
        padding: 8px 0;
    }
    .nox-dropdown-wrapper:hover .nox-dropdown-menu {
        display: block;
        animation: fadeInDown 0.3s ease;
    }
    .nox-dropdown-menu a {
        display: flex !important;
        align-items: center;
        gap: 10px;
        color: #fff !important;
        padding: 10px 20px !important;
        text-decoration: none !important;
        background: transparent !important;
        border: none !important;
        margin: 0 !important;
        width: 100% !important;
        font-family: 'Cinzel', serif !important;
        font-size: 0.9rem !important;
        transition: all 0.2s ease;
        border-radius: 0 !important;
        justify-content: flex-start !important;
    }
    .nox-dropdown-menu a:hover {
        background: rgba(212,175,55,0.2) !important;
        color: #d4af37 !important;
        padding-left: 25px !important;
    }
    .nox-divider {
        height: 1px;
        background: rgba(212,175,55,0.3);
        margin: 5px 0;
        border: none;
    }
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ===== CAMPANA DE NOTIFICACIONES ===== */
    .nox-bell-btn {
        position: relative;
        background: rgba(212,175,55,0.15);
        border: 1px solid rgba(212,175,55,0.4);
        color: #d4af37;
        border-radius: 8px;
        width: 40px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.2s ease;
        margin-left: 8px;
        flex-shrink: 0;
    }
    .nox-bell-btn:hover {
        background: rgba(212,175,55,0.35);
        transform: scale(1.08);
    }
    .nox-bell-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: #fff;
        font-size: 0.6rem;
        font-weight: 800;
        border-radius: 50%;
        min-width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 3px;
        box-shadow: 0 0 6px rgba(239,68,68,0.7);
        animation: bellPulse 2s infinite;
    }
    .nox-bell-badge.hidden { display: none; }
    @keyframes bellPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
    /* Panel lateral */
    #nox-notif-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.45);
        z-index: 10000;
    }
    #nox-notif-overlay.open { display: block; }
    #nox-notif-panel {
        position: fixed;
        top: 0;
        right: -420px;
        width: 400px;
        height: 100vh;
        background: rgba(10,10,15,0.97);
        backdrop-filter: blur(20px);
        border-left: 2px solid rgba(212,175,55,0.4);
        z-index: 10001;
        display: flex;
        flex-direction: column;
        transition: right 0.35s cubic-bezier(0.4,0,0.2,1);
        font-family: 'Inter', sans-serif;
    }
    #nox-notif-panel.open { right: 0; }
    .nox-notif-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        border-bottom: 1px solid rgba(212,175,55,0.25);
        flex-shrink: 0;
    }
    .nox-notif-header h3 {
        color: #d4af37;
        font-size: 1rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0;
    }
    .nox-notif-header-actions { display: flex; align-items: center; gap: 8px; }
    .nox-btn-mark-all {
        background: rgba(212,175,55,0.2);
        border: 1px solid rgba(212,175,55,0.5);
        color: #d4af37;
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 20px;
        cursor: pointer;
        font-weight: 700;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .nox-btn-mark-all:hover { background: rgba(212,175,55,0.4); }
    .nox-notif-close {
        background: none;
        border: none;
        color: #888;
        font-size: 1.3rem;
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
    }
    .nox-notif-close:hover { color: #fff; }
    #nox-notif-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px 0;
    }
    #nox-notif-list::-webkit-scrollbar { width: 4px; }
    #nox-notif-list::-webkit-scrollbar-track { background: transparent; }
    #nox-notif-list::-webkit-scrollbar-thumb { background: rgba(212,175,55,0.3); border-radius: 2px; }
    .nox-notif-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        transition: background 0.2s;
        animation: fadeInDown 0.3s ease;
    }
    .nox-notif-item:hover { background: rgba(255,255,255,0.03); }
    .nox-notif-item.unread { border-left: 3px solid #d4af37; padding-left: 17px; }
    .nox-notif-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .nox-notif-body { flex: 1; min-width: 0; }
    .nox-notif-tipo {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }
    .nox-notif-msg {
        color: #ccc;
        font-size: 0.82rem;
        line-height: 1.4;
        word-break: break-word;
    }
    .nox-notif-fecha {
        color: #555;
        font-size: 0.68rem;
        margin-top: 4px;
    }
    .nox-btn-read {
        background: none;
        border: 1px solid rgba(255,255,255,0.12);
        color: #666;
        border-radius: 4px;
        padding: 3px 7px;
        font-size: 0.65rem;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
        flex-shrink: 0;
        margin-top: 4px;
    }
    .nox-btn-read:hover { border-color: #22c55e; color: #22c55e; }
    .nox-notif-empty {
        text-align: center;
        color: #555;
        padding: 40px 20px;
        font-size: 0.85rem;
    }
    .nox-notif-empty i { font-size: 2rem; display: block; margin-bottom: 10px; color: #333; }
    @media (max-width: 480px) {
        #nox-notif-panel { width: 100vw; right: -100vw; }
    }
</style>

<body class="<?php echo $page_class; ?>">
    <header>
        <a href="<?php echo $base_path; ?>"><img src="<?php echo $base_path . $site_logo; ?>" alt="Logo" height="80"></a>
        <button class="nav-toggle" aria-label="Toggle navigation"><span class="hamburger"></span></button>
        
        <nav class="<?php echo empty($_SESSION['user_id']) ? 'no-session' : ''; ?>">
            <button class="nav-close" aria-label="Close navigation">✖</button>
            
            <!-- MENU PUBLICO -->
            <a href="<?php echo $base_path; ?>"><i class="fas fa-home"></i> Inicio</a>
            <a href="<?php echo $base_path; ?>pages/catalogo_publico.php"><i class="fas fa-th"></i> Catálogo</a>
            <a href="<?php echo $base_path; ?>pages/disponible_ahora.php" style="color:var(--accent-gold) !important; font-weight:bold;">
                <i class="fas fa-bolt"></i> Disponible ahora
            </a>
            <a href="<?php echo $base_path; ?>seguimiento"><i class="fas fa-truck-fast"></i> Seguimiento</a>
            
            <?php if (empty($_SESSION['user_id'])): ?>
                <a href="<?php echo $base_path; ?>login" class="login"><i class="fas fa-sign-in-alt"></i> Entrar</a>
            <?php else: ?>
                <a href="<?php echo $base_path; ?>account"><i class="fas fa-user"></i> Mi Cuenta</a>
                
                <?php if (!($gmlevel > 0 || $role === 'admin' || $role === 'moderator')): ?>
                    <a href="<?php echo $base_path; ?>logout" class="logout-btn" style="color: #ef4444 !important; border: 1px solid rgba(239, 68, 68, 0.3); padding: 5px 10px; border-radius: 4px; margin-left: 10px;">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                <?php endif; ?>
                
                <!-- MENU PRIVADO (GESTIÓN DE ARTESANÍA) -->
                <?php if ($gmlevel > 0 || $role === 'admin' || $role === 'moderator'): ?>
                    <div class="nox-dropdown-wrapper">
                        <a href="<?php echo $base_path; ?>admin/dashboard" class="admin-nav-btn" style="background:rgba(212,175,55,0.4); border: 2px solid #d4af37; color: #fff !important; font-weight: 800; padding: 8px 15px !important; margin-right: 10px;">
                            <i class="fas fa-user-shield"></i> ADMINISTRACIÓN <i class="fas fa-chevron-down" style="font-size: 0.7em;"></i>
                        </a>
                        <div class="nox-dropdown-menu">
                            <a href="<?php echo $base_path; ?>admin/ashop"><i class="fas fa-shopping-cart"></i> Productos</a>
                            <a href="<?php echo $base_path; ?>admin/anews"><i class="fas fa-newspaper"></i> Noticias</a>
                            <a href="<?php echo $base_path; ?>admin/users"><i class="fas fa-user-lock"></i> Usuarios</a>
                            <div class="nox-divider"></div>
                            <a href="<?php echo $base_path; ?>pages/pinterest.php" style="color:#E60023 !important">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#E60023"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>
                                Pinterest Publisher
                            </a>
                            <a href="<?php echo $base_path; ?>pages/linkedin.php" style="color:#0A66C2 !important">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#0A66C2"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                LinkedIn Publisher
                            </a>
                            <a href="<?php echo $base_path; ?>pages/mockups.php">
                                <i class="fas fa-images"></i> Mockups
                            </a>
                            <div class="nox-divider"></div>
                            <a href="<?php echo $base_path; ?>logout" style="color: #ef4444 !important;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                        </div>
                    </div>

                    <a href="<?php echo $base_path; ?>pages/pedidos.php" class="admin-nav-btn" style="background:rgba(124,58,237,0.3)">
                        <i class="fas fa-box"></i> Pedidos
                    </a>
                    <a href="<?php echo $base_path; ?>pages/kanban.php" class="admin-nav-btn" style="background:rgba(34,197,94,0.2)">
                        <i class="fas fa-tasks"></i> Kanban
                    </a>
                    <a href="<?php echo $base_path; ?>pages/flujo_pedidos.php" class="admin-nav-btn" style="background:rgba(99,102,241,0.2)">
                        <i class="fas fa-diagram-project"></i> Flujo
                    </a>
                    <a href="<?php echo $base_path; ?>pages/stock.php" class="admin-nav-btn" style="background:rgba(245,158,11,0.2)">
                        <i class="fas fa-warehouse"></i> Stock
                    </a>
                    <a href="<?php echo $base_path; ?>pages/clientes.php" class="admin-nav-btn" style="background:rgba(59,130,246,0.2)">
                        <i class="fas fa-users"></i> Clientes
                    </a>
                    <a href="<?php echo $base_path; ?>pages/envios.php" class="admin-nav-btn" style="background:rgba(236,72,153,0.2)">
                        <i class="fas fa-truck"></i> Envíos
                    </a>
                    <a href="<?php echo $base_path; ?>pages/influencers.php" class="admin-nav-btn" style="background:rgba(16,185,129,0.2)">
                        <i class="fab fa-instagram"></i> Influencers
                    </a>
                    <a href="<?php echo $base_path; ?>pages/ventas.php" class="admin-nav-btn" style="background:rgba(239,68,68,0.2)">
                        <i class="fas fa-chart-line"></i> Ventas
                    </a>
                    <a href="<?php echo $base_path; ?>pages/futuros_proyectos.php" class="admin-nav-btn" style="background:rgba(139,92,246,0.2)">
                        <i class="fas fa-project-diagram"></i> Proyectos
                    </a>
                    <a href="<?php echo $base_path; ?>pages/asistente_voz.php" class="admin-nav-btn" style="background:rgba(107,114,128,0.2)">
                        <i class="fas fa-microphone"></i> Asistente
                    </a>
                    <a href="<?php echo $base_path; ?>pages/chatbot_admin.php" class="admin-nav-btn" style="background:rgba(201,168,76,0.2); border:1px solid rgba(201,168,76,0.4);">
                        <i class="fas fa-robot"></i> Chatbot
                    </a>
                    <a href="<?php echo $base_path; ?>pages/mockups.php" class="admin-nav-btn" style="background:rgba(212,175,55,0.2); border:1px solid var(--accent-gold);">
                        <i class="fas fa-palette"></i> Mockups
                    </a>
                    <!-- 🔔 Campana de Notificaciones -->
                    <button id="nox-bell-trigger" class="nox-bell-btn" title="Notificaciones" onclick="noxNotifOpen()">
                        <i class="fas fa-bell"></i>
                        <span id="nox-bell-badge" class="nox-bell-badge hidden">0</span>
                    </button>
                    <!-- Pinterest Publisher → menú ADMINISTRACIÓN -->
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </header>
    <script src="<?php echo $base_path; ?>assets/js/includes/header.js"></script>

<?php if ($gmlevel > 0 || $role === 'admin' || $role === 'moderator'): ?>
<!-- Chatbot privado del Gestor (solo admins) -->
<script>
    document.body.dataset.base = '<?php echo htmlspecialchars($base_path); ?>';
</script>
<script src="<?php echo $base_path; ?>assets/js/chatbot_privado.js"></script>

<!-- ===== PANEL DE NOTIFICACIONES ===== -->
<div id="nox-notif-overlay" onclick="noxNotifClose()"></div>
<div id="nox-notif-panel">
    <div class="nox-notif-header">
        <h3><i class="fas fa-bell" style="margin-right:8px;"></i>Notificaciones</h3>
        <div class="nox-notif-header-actions">
            <button class="nox-btn-mark-all" onclick="noxMarcarTodas()">✔ Marcar todas leídas</button>
            <button class="nox-notif-close" onclick="noxNotifClose()" title="Cerrar">✕</button>
        </div>
    </div>
    <div id="nox-notif-list">
        <div class="nox-notif-empty"><i class="fas fa-bell-slash"></i>Cargando...</div>
    </div>
</div>

<script>
(function() {
    var BASE = '<?php echo htmlspecialchars($base_path); ?>';
    var API  = BASE + 'api/notificaciones.php';

    // Mapa de tipos → icono + color
    var TIPO_MAP = {
        'stock':     { icon: 'fa-warehouse',      color: '#f59e0b', bg: 'rgba(245,158,11,0.15)' },
        'pedido':    { icon: 'fa-box',             color: '#3b82f6', bg: 'rgba(59,130,246,0.15)' },
        'error':     { icon: 'fa-exclamation-circle', color: '#ef4444', bg: 'rgba(239,68,68,0.15)' },
        'linkedin':  { icon: 'fab fa-linkedin',    color: '#0a66c2', bg: 'rgba(10,102,194,0.15)' },
        'sistema':   { icon: 'fa-cog',             color: '#8b5cf6', bg: 'rgba(139,92,246,0.15)' },
        'seguimiento':{ icon: 'fa-truck',          color: '#10b981', bg: 'rgba(16,185,129,0.15)' },
        'default':   { icon: 'fa-bell',            color: '#d4af37', bg: 'rgba(212,175,55,0.15)' }
    };

    function getTipo(tipo) {
        var key = (tipo || '').toLowerCase();
        return TIPO_MAP[key] || TIPO_MAP['default'];
    }

    // Actualizar badge
    function noxActualizarBadge() {
        fetch(API + '?action=count', { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                var badge = document.getElementById('nox-bell-badge');
                if (!badge) return;
                if (d.total > 0) {
                    badge.textContent = d.total > 99 ? '99+' : d.total;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            })
            .catch(function(){});
    }

    // Renderizar lista
    function noxRenderList(items) {
        var list = document.getElementById('nox-notif-list');
        if (!list) return;
        if (!items || items.length === 0) {
            list.innerHTML = '<div class="nox-notif-empty"><i class="fas fa-check-circle" style="color:#22c55e;"></i>¡Todo al día! No hay notificaciones.</div>';
            return;
        }
        var html = '';
        items.forEach(function(n) {
            var t = getTipo(n.tipo);
            var unread = n.leida == 0 ? ' unread' : '';
            var btnHtml = n.leida == 0
                ? '<button class="nox-btn-read" onclick="noxMarcarLeida(' + n.id + ',this)">Marcar leída</button>'
                : '<span style="color:#22c55e;font-size:0.65rem;">✔ Leída</span>';
            html += '<div class="nox-notif-item' + unread + '" id="notif-' + n.id + '">' +
                '<div class="nox-notif-icon" style="background:' + t.bg + '; color:' + t.color + '">' +
                    '<i class="fas ' + t.icon + '"></i>' +
                '</div>' +
                '<div class="nox-notif-body">' +
                    '<div class="nox-notif-tipo" style="color:' + t.color + '">' + escHTML(n.tipo) + '</div>' +
                    '<div class="nox-notif-msg">' + escHTML(n.mensaje) + '</div>' +
                    '<div class="nox-notif-fecha"><i class="fas fa-clock" style="margin-right:4px;"></i>' + escHTML(n.fecha_fmt) + '</div>' +
                '</div>' +
                '<div style="display:flex;flex-direction:column;align-items:flex-end;">' + btnHtml + '</div>' +
            '</div>';
        });
        list.innerHTML = html;
    }

    function escHTML(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Cargar notificaciones
    function noxCargarNotificaciones() {
        var list = document.getElementById('nox-notif-list');
        if (list) list.innerHTML = '<div class="nox-notif-empty"><i class="fas fa-spinner fa-spin"></i>Cargando...</div>';
        fetch(API + '?action=list', { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d){ noxRenderList(d.notificaciones || []); })
            .catch(function(){
                var list2 = document.getElementById('nox-notif-list');
                if (list2) list2.innerHTML = '<div class="nox-notif-empty"><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>Error al cargar.</div>';
            });
    }

    // Marcar una como leída
    window.noxMarcarLeida = function(id, btn) {
        var fd = new FormData();
        fd.append('action', 'marcar_leida');
        fd.append('id', id);
        fetch(API, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok) {
                    var item = document.getElementById('notif-' + id);
                    if (item) {
                        item.classList.remove('unread');
                        item.style.borderLeft = 'none';
                        var btnWrap = item.querySelector('.nox-btn-read');
                        if (btnWrap) btnWrap.outerHTML = '<span style="color:#22c55e;font-size:0.65rem;">✔ Leída</span>';
                    }
                    noxActualizarBadge();
                }
            })
            .catch(function(){});
    };

    // Marcar todas
    window.noxMarcarTodas = function() {
        var fd = new FormData();
        fd.append('action', 'marcar_todas');
        fetch(API, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok) {
                    noxCargarNotificaciones();
                    noxActualizarBadge();
                }
            })
            .catch(function(){});
    };

    // Abrir panel
    window.noxNotifOpen = function() {
        document.getElementById('nox-notif-overlay').classList.add('open');
        document.getElementById('nox-notif-panel').classList.add('open');
        noxCargarNotificaciones();
    };

    // Cerrar panel
    window.noxNotifClose = function() {
        document.getElementById('nox-notif-overlay').classList.remove('open');
        document.getElementById('nox-notif-panel').classList.remove('open');
    };

    // Init: actualizar badge cada 60s
    noxActualizarBadge();
    setInterval(noxActualizarBadge, 60000);
})();
</script>
<?php endif; ?>