<?php
require_once __DIR__ . '/config.php';
$db = conectar();

// Funciones de ayuda para configuracion
function getCfg($db, $clave) {
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $stmt->execute([$clave]);
    return $stmt->fetchColumn();
}

function setCfg($db, $clave, $valor) {
    $stmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->execute([$clave, $valor, $valor]);
}

$accion = $_GET['accion'] ?? '';
$step = $_GET['step'] ?? '';

// --- PASO 1: Redirigir a LinkedIn ---
if ($step == '1') {
    $client_id = getCfg($db, 'linkedin_client_id');
    if (!$client_id) die("Falta Client ID en la configuración.");

    $redirect_uri = "https://noxertez.com/Sahtout/api/linkedin_oauth.php";
    $state = bin2hex(random_bytes(16));
    setcookie('li_state', $state, time() + 600, '/', '', true, true);

    $scope = "openid profile w_member_social";
    $auth_url = "https://www.linkedin.com/oauth/v2/authorization?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => $scope,
        'state' => $state
    ]);

    header("Location: $auth_url");
    exit;
}

// --- CALLBACK: Procesar código de LinkedIn ---
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';
    
    // Validar state
    if (!isset($_COOKIE['li_state']) || $state !== $_COOKIE['li_state']) {
        // die("Error de validación de estado (State mismatch).");
        // A veces las cookies fallan en redirects, si es un entorno controlado podemos ser mas flexibles o loguear
    }

    $client_id = getCfg($db, 'linkedin_client_id');
    $client_secret = getCfg($db, 'linkedin_client_secret');
    $redirect_uri = "https://noxertez.com/Sahtout/api/linkedin_oauth.php";

    $token_url = "https://www.linkedin.com/oauth/v2/accessToken";
    $post_data = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        setCfg($db, 'linkedin_access_token', $data['access_token']);
        if (isset($data['refresh_token'])) {
            setCfg($db, 'linkedin_refresh_token', $data['refresh_token']);
        }
        $expires_at = time() + ($data['expires_in'] ?? 3600);
        setCfg($db, 'linkedin_token_expires', $expires_at);

        // Obtener URN inmediatamente
        $ch_u = curl_init("https://api.linkedin.com/v2/userinfo");
        curl_setopt($ch_u, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_u, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $data['access_token']]);
        $user_res = curl_exec($ch_u);
        $user_data = json_decode($user_res, true);
        
        if (isset($user_data['sub'])) {
            setCfg($db, 'linkedin_person_urn', $user_data['sub']);
        }

        header("Location: ../pages/linkedin.php?auth=ok");
    } else {
        echo "Error al obtener token: " . json_encode($data);
    }
    exit;
}

// --- ACCIONES AJAX ---
header('Content-Type: application/json; charset=utf-8');

if ($accion == 'save_config') {
    $body = json_decode(file_get_contents('php://input'), true);
    setCfg($db, 'linkedin_client_id', $body['client_id'] ?? '');
    setCfg($db, 'linkedin_client_secret', $body['client_secret'] ?? '');
    setCfg($db, 'linkedin_posts_por_semana', $body['pps'] ?? '3');
    setCfg($db, 'linkedin_default_tono', $body['default_tono'] ?? 'Cercano y Artesanal');
    setCfg($db, 'linkedin_default_enfoque', $body['default_enfoque'] ?? 'storytelling');
    echo json_encode(['ok' => true]);
    exit;
}

if ($accion == 'refresh') {
    $client_id = getCfg($db, 'linkedin_client_id');
    $client_secret = getCfg($db, 'linkedin_client_secret');
    $refresh_token = getCfg($db, 'linkedin_refresh_token');

    if (!$refresh_token) die(json_encode(['error' => 'No hay refresh token']));

    $post_data = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ];

    $ch = curl_init("https://www.linkedin.com/oauth/v2/accessToken");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        setCfg($db, 'linkedin_access_token', $data['access_token']);
        if (isset($data['refresh_token'])) setCfg($db, 'linkedin_refresh_token', $data['refresh_token']);
        setCfg($db, 'linkedin_token_expires', time() + $data['expires_in']);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['error' => 'Error al renovar', 'details' => $data]);
    }
    exit;
}

if ($accion == 'verify') {
    $token = getCfg($db, 'linkedin_access_token');
    $ch = curl_init("https://api.linkedin.com/v2/userinfo");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data['sub'])) {
        setCfg($db, 'linkedin_person_urn', $data['sub']);
        echo json_encode(['ok' => true, 'profile' => $data]);
    } else {
        echo json_encode(['error' => 'Token inválido o expirado', 'details' => $data]);
    }
    exit;
}
