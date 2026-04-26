<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);

// Include paths.php to access $project_root and $base_path
require_once __DIR__ . '/../includes/paths.php';

// Use $project_root for filesystem includes
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.cap.php';
require_once $project_root . 'includes/srp6.php';

// Brute force prevention settings
define('MAX_LOGIN_ATTEMPTS', 5); // Maximum allowed attempts
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds
define('ATTEMPT_WINDOW', 3600); // 1 hour window for attempt counting

// Redirect to account if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: {$base_path}account");
    exit();
}

$page_class = 'login';
$errors = [];
$username = '';
$show_resend_button = false;
$remaining_attempts = MAX_LOGIN_ATTEMPTS; // Default to max attempts

// Function to get client IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get current attempt count
function getAttemptCount($site_db, $ip_address, $username) {
    if (!$site_db) return 0;
    $stmt = $site_db->prepare("SELECT attempts, last_attempt FROM failed_logins WHERE ip_address = ? AND username = ?");
    if (!$stmt) return 0;
    $upper_username = strtoupper($username);
    $stmt->bind_param('ss', $ip_address, $upper_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && (int)$row['last_attempt'] >= time() - ATTEMPT_WINDOW) {
        return $row['attempts'];
    }
    return 0;
}

// Function to check and update login attempts
function checkBruteForce($site_db, $ip_address, $username) {
    global $errors, $remaining_attempts;
    if (!$site_db) return true;
    
    $stmt = $site_db->prepare("SELECT attempts, last_attempt, block_until FROM failed_logins WHERE ip_address = ? AND username = ?");
    if (!$stmt) return true;
    $upper_username = strtoupper($username);
    $stmt->bind_param('ss', $ip_address, $upper_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['block_until'] && (int)$row['block_until'] <= time()) {
        $stmt = $site_db->prepare("DELETE FROM failed_logins WHERE ip_address = ? AND username = ? AND block_until <= ?");
        $current_time = time();
        $stmt->bind_param('ssi', $ip_address, $upper_username, $current_time);
        $stmt->execute();
        $stmt->close();
        $row = null;
    }
    
    if ($row && (int)$row['last_attempt'] < time() - ATTEMPT_WINDOW) {
        $stmt = $site_db->prepare("UPDATE failed_logins SET attempts = 0, block_until = NULL WHERE ip_address = ? AND username = ?");
        $stmt->bind_param('ss', $ip_address, $upper_username);
        $stmt->execute();
        $stmt->close();
        $row['attempts'] = 0;
        $row['block_until'] = null;
    }
    
    $remaining_attempts = MAX_LOGIN_ATTEMPTS - ($row['attempts'] ?? 0);
    
    if ($row && $row['block_until'] && (int)$row['block_until'] > time()) {
        $remaining_time = ceil(((int)$row['block_until'] - time()) / 60);
        $errors[] = translate('error_too_many_attempts', 'Demasiados intentos de inicio de sesión. Por favor, inténtelo de nuevo en %d minutos.', $row['attempts'], $remaining_time);
        return false;
    }
    
    if ($row && $row['attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $block_until = time() + LOCKOUT_DURATION;
        $stmt = $site_db->prepare("UPDATE failed_logins SET block_until = ? WHERE ip_address = ? AND username = ?");
        $stmt->bind_param('iss', $block_until, $ip_address, $upper_username);
        $stmt->execute();
        $stmt->close();
        $remaining_time = ceil(LOCKOUT_DURATION / 60);
        $errors[] = translate('error_too_many_attempts', 'Demasiados intentos. Por favor, espere %d minutos.', $row['attempts'], $remaining_time);
        return false;
    }
    
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_address = getUserIP();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!checkBruteForce($site_db, $ip_address, $username)) {
        // Locked out
    } else {
        if (empty($username) || empty($password)) {
            $errors[] = translate('error_fields_required', 'Todos los campos son obligatorios');
        }

        if (empty($errors)) {
            $upper_username = strtoupper($username);
            $stmt = $auth_db->prepare("SELECT id, username, salt, verifier FROM account WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param('s', $upper_username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $errors[] = translate('error_invalid_credentials', 'Usuario o contraseña incorrectos');
                } else {
                    $account = $result->fetch_assoc();
                    if (SRP6::VerifyPassword($username, $password, $account['salt'], $account['verifier'])) {
                        session_regenerate_id(true);    
                        $_SESSION['user_id'] = $account['id'];
                        $_SESSION['username'] = $account['username'];
                        $_SESSION['last_regeneration'] = time();

                        $update = $auth_db->prepare("UPDATE account SET last_login = NOW() WHERE id = ?");
                        $update->bind_param('i', $account['id']);
                        $update->execute();
                        $update->close();

                        header("Location: {$base_path}account");
                        exit();
                    } else {
                        $errors[] = translate('error_invalid_credentials', 'Usuario o contraseña incorrectos');
                    }
                }
                $stmt->close();
            }
        }
    }
}

$remaining_attempts = MAX_LOGIN_ATTEMPTS - getAttemptCount($site_db, getUserIP(), $username);
include_once $project_root . 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="Accede a tu cuenta de Noxertez Artesanía para gestionar tus pedidos y catálogo.">
    <title>Acceso - Noxertez Artesanía</title>
    <style>
        :root{
            --bg-login: url('<?= $base_path ?>img/backgrounds/bg-login.jpg');
        }
        body.login {
            background: var(--bg-login) no-repeat center center fixed;
            background-size: cover;
            font-family: 'Quicksand', sans-serif;
        }
        .form-container {
            background: rgba(0,0,0,0.8);
            border: 1px solid #d4af37;
            border-radius: 15px;
            padding: 40px;
            max-width: 400px;
            margin: 100px auto;
            color: #fff;
        }
        h2 { font-family: 'Cinzel', serif; color: #d4af37; text-align: center; }
        .btn-warning { background-color: #d4af37; border: none; color: #000; font-weight: bold; }
        .btn-warning:hover { background-color: #bfa02d; }
    </style>
</head>
<body class="login">
<div class="container">
    <div class="form-container shadow-lg">
        <h2>Acceso Clientes</h2>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-4">
            <div class="mb-3">
                <input type="text" name="username" class="form-control bg-dark text-white border-warning" placeholder="Usuario" required value="<?= htmlspecialchars($username) ?>">
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control bg-dark text-white border-warning" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn btn-warning w-100 py-2">Entrar</button>
            <div class="text-center mt-3">
                <small>¿No tienes cuenta? <a href="<?= $base_path ?>register" class="text-warning">Regístrate</a></small>
            </div>
        </form>
    </div>
</div>
<?php include_once $project_root . 'includes/footer.php'; ?>
</body>
</html>
