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
<?php endif; ?>