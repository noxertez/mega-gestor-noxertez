<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../../includes/session.php';

// Verificar sesión activa
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once '../../api/config.php';
require_once __DIR__ . '/imap_helper.php';

$action = $_GET['action'] ?? 'emails';
$alias  = $_GET['alias']  ?? 'info';

// Validar alias
$aliases_validos = array_keys(ALIASES_VALIDOS);
if (!in_array($alias, $aliases_validos)) {
    echo json_encode(['ok' => false, 'error' => 'Alias inválido.']);
    exit;
}

$db = conectar();

switch ($action) {

    // ---- Listado de emails IMAP + estadísticas ----
    case 'emails':
        $conn   = conectar_imap();
        $emails = obtener_emails_alias($conn, $alias, 30);
        $no_leidos = contar_no_leidos($conn, $alias);
        cerrar_imap($conn);

        // Estadísticas de enviados hoy
        $alias_email = ALIASES_VALIDOS[$alias];
        $hoy         = date('Y-m-d');

        $stmt = $db->prepare("SELECT COUNT(*) FROM emails_enviados WHERE alias_from = ? AND DATE(fecha_envio) = ?");
        $stmt->execute([$alias_email, $hoy]);
        $enviados_hoy = (int)$stmt->fetchColumn();

        // Sin responder = recibidos con asunto que no tiene respuesta en emails_enviados
        $sin_responder = 0;
        foreach ($emails as $em) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM emails_enviados WHERE en_respuesta_a LIKE ? AND alias_from = ?");
            $stmt->execute(['%' . $em['asunto'] . '%', $alias_email]);
            if ((int)$stmt->fetchColumn() === 0) {
                $sin_responder++;
            }
        }

        // Recibidos hoy
        $recibidos_hoy = 0;
        foreach ($emails as $em) {
            if (substr($em['fecha'], 0, 10) === $hoy) {
                $recibidos_hoy++;
            }
        }

        $errores_imap = $conn === false ? ['No se pudo conectar a IMAP. Verifica la App Password y que IMAP esté habilitado en Gmail.'] : (imap_errors() ?: []);

        echo json_encode([
            'ok'     => true,
            'emails' => $emails,
            'stats'  => [
                'recibidos_hoy' => $recibidos_hoy,
                'enviados_hoy'  => $enviados_hoy,
                'sin_responder' => $sin_responder,
                'no_leidos'     => $no_leidos,
            ],
            'errores_imap' => $errores_imap,
        ]);
        break;

    // ---- Detalle de un email por UID ----
    case 'detalle':
        $uid  = (int)($_GET['uid'] ?? 0);
        if ($uid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'UID no válido.']);
            break;
        }
        $conn   = conectar_imap();
        $detalle = obtener_email_detalle($conn, $uid);
        cerrar_imap($conn);
        echo json_encode(['ok' => true, 'email' => $detalle]);
        break;

    // ---- Emails enviados desde alias ----
    case 'enviados':
        $alias_email = ALIASES_VALIDOS[$alias];
        $stmt = $db->prepare("
            SELECT id, destinatario, asunto, fecha_envio, en_respuesta_a, estado
            FROM emails_enviados
            WHERE alias_from = ?
            ORDER BY fecha_envio DESC
            LIMIT 20
        ");
        $stmt->execute([$alias_email]);
        $enviados = $stmt->fetchAll();
        echo json_encode(['ok' => true, 'enviados' => $enviados]);
        break;

    // ---- Badges (no leídos por alias) ----
    case 'badges':
        $conn    = conectar_imap();
        $badges  = [];
        foreach (array_keys(ALIASES_VALIDOS) as $a) {
            $badges[$a] = contar_no_leidos($conn, $a);
        }
        cerrar_imap($conn);
        echo json_encode(['ok' => true, 'badges' => $badges]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
}
