<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);

// Include paths.php using __DIR__ to access $project_root and $base_path
require_once __DIR__ . '/../includes/paths.php';

// Use $project_root for filesystem includes
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.cap.php';
require_once $project_root . 'includes/config.mail.php';
require_once $project_root . 'includes/srp6.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$page_class = 'register';
$errors = [];
$success = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED) {
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptcha_response)) {
            $errors[] = translate('error_recaptcha_empty', 'Please complete the CAPTCHA.');
        } else {
            $verify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . RECAPTCHA_SECRET_KEY . '&response=' . $recaptcha_response);
            $captcha_result = json_decode($verify);
            if (!$captcha_result->success) {
                $errors[] = translate('error_recaptcha_failed', 'CAPTCHA verification failed.');
            }
        }
    }

    if (strlen($username) < 3 || strlen($username) > 16) {
        $errors[] = translate('error_username_invalid_length', 'Username must be between 3 and 16 characters.');
    }
    if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $errors[] = translate('error_username_invalid_chars', 'Username can only contain letters and numbers.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = translate('error_email_invalid', 'Invalid email address.');
    }
    if (strlen($password) < 6) {
        $errors[] = translate('error_password_short', 'Password must be at least 6 characters.');
    }
    if ($password !== $confirm_password) {
        $errors[] = translate('error_password_mismatch', 'Passwords do not match.');
    }

    if (empty($errors)) {
        $upper_username = strtoupper($username);
        $stmt = $site_db->prepare("SELECT username, email FROM pending_accounts WHERE username = ? OR email = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $upper_username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = translate('error_account_pending', 'An account with this username or email is already registered.');
            }
            $stmt->close();
        }
    }

    if (empty($errors)) {
        $stmt = $auth_db->prepare("SELECT id FROM account WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param('s', $upper_username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $errors[] = translate('error_username_exists', 'Username already exists.');
            $stmt->close();
        }
    }

    if (empty($errors)) {
        $salt = SRP6::GenerateSalt();
        $verifier = SRP6::CalculateVerifier($username, $password, $salt);
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            $token = bin2hex(random_bytes(32));
            $stmt = $site_db->prepare("INSERT INTO pending_accounts (username, email, salt, verifier, token) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssss", $upper_username, $email, $salt, $verifier, $token);
                if ($stmt->execute()) {
                    $activation_link = $site_url . "activate?token=$token";
                    try {
                        $mail = getMailer();
                        $mail->addAddress($email, $username);
                        $mail->Subject = translate('email_subject', 'Activate Your Account');
                        $mail->Body = "Welcome, $username! Click here to activate: $activation_link";
                        if ($mail->send()) $success = translate('success_account_created', 'Check your email to activate.');
                        else $errors[] = translate('error_email_failed', 'Failed to send email.');
                    } catch (Exception $e) { $errors[] = $mail->ErrorInfo; }
                }
                $stmt->close();
            }
        } else {
            $stmt = $auth_db->prepare("INSERT INTO account (username, salt, verifier, email, reg_mail, expansion) VALUES (?, ?, ?, ?, ?, 2)");
            if ($stmt) {
                $stmt->bind_param('sssss', $upper_username, $salt, $verifier, $email, $email);
                if ($stmt->execute()) $success = translate('success_account_created_no_email', 'Success! You can now log in.');
                $stmt->close();
            }
        }
    }
}

require_once $project_root . 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - Noxertez Artesanía</title>
    <style>body.register { background: #000; color: #fff; font-family: 'Quicksand', sans-serif; }</style>
</head>
<body class="register">
    <main class="container py-5">
        <h1 class="text-center" style="color:#C89B3C;">Crear Cuenta</h1>
        <div class="row justify-content-center mt-4">
            <div class="col-md-5 bg-dark p-4 rounded border border-warning">
                <?php if (!empty($errors)): foreach($errors as $e): echo "<p class='text-danger'>$e</p>"; endforeach; endif; ?>
                <?php if ($success): echo "<p class='text-success'>$success</p>"; else: ?>
                <form method="POST">
                    <input type="text" name="username" class="form-control mb-3" placeholder="Usuario" required>
                    <input type="email" name="email" class="form-control mb-3" placeholder="Email" required>
                    <input type="password" name="password" class="form-control mb-3" placeholder="Contraseña" required>
                    <input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirmar Contraseña" required>
                    <button type="submit" class="btn btn-warning w-100">Registrarse</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <?php include_once $project_root . 'includes/footer.php'; ?>
</body>
</html>
