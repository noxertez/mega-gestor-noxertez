<?php
// Configuracion de conexion a MySQL
// EDITA estos valores con los que pusiste al crear el usuario

define('DB_HOST', 'localhost');
define('DB_NAME', 'noxertez');
define('DB_USER', 'noxertez_user');
define('DB_PASS', 'Noxertez2024!'); // NOTA: Esto se debio configurar en fase 0
define('DB_CHARSET', 'utf8mb4');

// Configuracion general
define('APP_VERSION', '3.0');
define('API_KEY', 'noxertez_api_2024');  // Cambia esto

// ============================================================
// CLAUDE AI — Clave de API de Anthropic
// Obtén tu clave en: https://console.anthropic.com/
// ============================================================
define('CLAUDE_API_KEY', 'TU_CLAUDE_API_KEY_AQUI');
define('GEMINI_API_KEY', 'TU_GEMINI_API_KEY_AQUI');
define('GROQ_API_KEY', 'TU_GROQ_API_KEY_AQUI');
define('PACKLINK_API_KEY', 'TU_PACKLINK_API_KEY_AQUI');

// Funcion de conexion (reutilizable en todos los endpoints)
function conectar() {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $opciones);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Error de conexion: ' . $e->getMessage()]));
    }
}

// Cabeceras para que n8n pueda llamar a la API
// Solo se envían si NO estamos en un contexto de página CMS (identificado por ALLOWED_ACCESS)
if (!defined('ALLOWED_ACCESS') && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function jsonSalida($data) {
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}
?>