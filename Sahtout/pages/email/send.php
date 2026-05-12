<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../../includes/session.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

// Verificar sesión activa
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once '../../api/config.php';
require_once '../../vendor/autoload.php';
require_once __DIR__ . '/imap_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Recoger y validar parámetros ---
$alias_from    = trim($_POST['alias_from']    ?? '');
$destinatario  = trim($_POST['destinatario']  ?? '');
$asunto        = trim($_POST['asunto']        ?? '');
$cuerpo        = trim($_POST['cuerpo']        ?? '');
$en_respuesta  = trim($_POST['en_respuesta_a'] ?? '');

// Validar alias
$aliases_permitidos = array_values(ALIASES_VALIDOS);
if (!in_array($alias_from, $aliases_permitidos)) {
    echo json_encode(['ok' => false, 'error' => 'Alias de origen no válido.']);
    exit;
}

// Validar campos obligatorios
if (empty($destinatario) || empty($asunto) || empty($cuerpo)) {
    echo json_encode(['ok' => false, 'error' => 'Faltan campos obligatorios (destinatario, asunto, cuerpo).']);
    exit;
}

if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'El destinatario no es un email válido.']);
    exit;
}

// --- Envío con PHPMailer ---
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = IMAP_USER;                   // noxertez@gmail.com
    $mail->Password   = IMAP_PASS;                   // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // Remitente: el alias seleccionado
    $mail->setFrom($alias_from, 'Noxertez');
    $mail->addReplyTo($alias_from, 'Noxertez');
    $mail->addAddress($destinatario);

    $mail->Subject = $asunto;
    $mail->isHTML(true);
    $mail->Body    = nl2br(htmlspecialchars($cuerpo));
    $mail->AltBody = $cuerpo;

    $mail->send();

    // --- Guardar en BD ---
    $db   = conectar();
    $stmt = $db->prepare("
        INSERT INTO emails_enviados (alias_from, destinatario, asunto, cuerpo, en_respuesta_a, estado)
        VALUES (?, ?, ?, ?, ?, 'enviado')
    ");
    $stmt->execute([
        $alias_from,
        $destinatario,
        $asunto,
        $cuerpo,
        $en_respuesta ?: null,
    ]);

    echo json_encode(['ok' => true, 'mensaje' => 'Email enviado correctamente.']);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Error PHPMailer: ' . $mail->ErrorInfo]);
} catch (\Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
}
