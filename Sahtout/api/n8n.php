<?php
/**
 * Proxy para el servidor de control n8n (Python Port 5000)
 * Permite que el CMS PHP en el puerto 80/443 se comunique con el controlador local.
 */
require_once 'config.php';

// Solo permitir si el usuario está autenticado (vía session o API Key)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && (!isset($_GET['api_key']) || $_GET['api_key'] !== API_KEY)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'restart') {
    // Llamar al servidor local en el puerto 5000 (Flask)
    $url = 'http://localhost:5000/api/n8n/restart';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        header('Content-Type: application/json');
        echo $response;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'El controlador local (Puerto 5000) no responde. ¿Está abierta la App de PC?',
            'details' => $error,
            'code' => $httpCode
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no especificada o no válida']);
}
?>
