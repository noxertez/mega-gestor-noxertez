<?php
ob_start();
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';

// Early session validation
if (!isset($_SESSION['user_id'])) {
    header("Location: {$base_path}login");
    exit();
}

// Global DB access
global $auth_db, $site_db;

// Initialize variables
$accountInfo = [];
$message = '';
$error = '';
$activityLog = [];
$currencies = ['points' => 0, 'tokens' => 0, 'avatar' => 'user.jpg'];
$available_avatars = [];
$role = $_SESSION['role'] ?? 'user';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission";
        header("Location: {$base_path}account");
        exit();
    }

    // Handle Email Change
    if (isset($_POST['change_email'])) {
        $new_email = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);
        $stmt = $auth_db->prepare("UPDATE account SET email = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $new_email, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Email actualizado correctamente";
            }
            $stmt->close();
        }
        header("Location: {$base_path}account");
        exit();
    }

    // Handle Avatar Change
    if (isset($_POST['change_avatar'])) {
        $avatar = $_POST['avatar'];
        $stmt = $site_db->prepare("UPDATE user_currencies SET avatar = ? WHERE account_id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $avatar, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['avatar'] = $avatar;
                $_SESSION['message'] = "Avatar actualizado";
            }
            $stmt->close();
        }
        header("Location: {$base_path}account");
        exit();
    }
}

// Get Basic Account Data
$stmt = $auth_db->prepare("SELECT id, username, email FROM account WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $accountInfo = $res->fetch_assoc();
    $stmt->close();
}

// Get User Currencies & Avatar
// Using error suppression and explicit check for $site_db
if ($site_db) {
    $stmt = $site_db->prepare("SELECT points, tokens, avatar FROM user_currencies WHERE account_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $currencies = $res->fetch_assoc();
        }
        $stmt->close();
    }

    // Get available avatars
    $stmt = $site_db->prepare("SELECT filename, display_name FROM profile_avatars WHERE active = 1");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $available_avatars = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Get activity log
    $stmt = $site_db->prepare("SELECT action, timestamp, details FROM website_activity_log WHERE account_id = ? ORDER BY timestamp DESC LIMIT 5");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $activityLog = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_class = 'account';
include_once $project_root . 'includes/header.php';
?>

<div class="container mt-4" style="color: #fff; background: rgba(0,0,0,0.7); padding: 30px; border-radius: 15px; border: 1px solid #d4af37;">
    <h1 class="text-center mb-4" style="color: #d4af37; font-family: 'Cinzel', serif;">Panel de Gestión - Noxertez Artesanía</h1>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="accountTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview">Resumen</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity">Actividad</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#security">Configuración</button></li>
    </ul>

    <div class="tab-content">
        <!-- Overview -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row">
                <div class="col-md-4 text-center">
                    <?php $av_img = !empty($currencies['avatar']) ? $currencies['avatar'] : 'user.jpg'; ?>
                    <img src="<?= $base_path ?>img/accountimg/profile_pics/<?= htmlspecialchars($av_img) ?>" class="img-fluid rounded-circle mb-3" style="width: 150px; border: 3px solid #d4af37; height: 150px; object-fit: cover;">
                    <h3><?= htmlspecialchars($accountInfo['username'] ?? 'Usuario') ?></h3>
                    <p class="badge bg-warning text-dark"><?= strtoupper($role) ?></p>
                </div>
                <div class="col-md-8">
                    <div class="card bg-dark text-white border-secondary">
                        <div class="card-body">
                            <p><strong>ID Cuenta:</strong> <?= $accountInfo['id'] ?? 'N/A' ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($accountInfo['email'] ?? 'No establecido') ?></p>
                            <p><strong>Puntos Nox:</strong> <?= $currencies['points'] ?></p>
                            <p><strong>Tokens:</strong> <?= $currencies['tokens'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div class="tab-pane fade" id="activity">
            <table class="table table-dark table-striped">
                <thead><tr><th>Acción</th><th>Fecha</th><th>Detalles</th></tr></thead>
                <tbody>
                    <?php if(!empty($activityLog)): ?>
                        <?php foreach($activityLog as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= date('d/M/Y H:i', $log['timestamp']) ?></td>
                                <td><?= htmlspecialchars($log['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center">No hay actividad reciente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Security -->
        <div class="tab-pane fade" id="security">
            <div class="row">
                <div class="col-md-6">
                    <h4>Cambiar Email</h4>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="email" name="new_email" class="form-control mb-2 bg-dark text-white border-warning" value="<?= htmlspecialchars($accountInfo['email'] ?? '') ?>">
                        <button type="submit" name="change_email" class="btn btn-warning w-100">Guardar Email</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <h4>Seleccionar Avatar</h4>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="row row-cols-3 g-2 mb-3" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach($available_avatars as $av): ?>
                                <div class="col text-center">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="avatar" value="<?= $av['filename'] ?>" <?= (isset($currencies['avatar']) && $currencies['avatar']==$av['filename'])?'checked':'' ?> style="display:none">
                                        <img src="<?= $base_path ?>img/accountimg/profile_pics/<?= $av['filename'] ?>" class="img-fluid rounded <?= (isset($currencies['avatar']) && $currencies['avatar']==$av['filename'])?'border border-warning':'' ?>" style="width: 60px; height: 60px; object-fit: cover;">
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" name="change_avatar" class="btn btn-warning w-100">Guardar Avatar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once $project_root . 'includes/footer.php'; ?>
