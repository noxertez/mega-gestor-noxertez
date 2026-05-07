<?php
header('Content-Type: application/json');

// Recibir el texto del frontend
$input = json_decode(file_get_contents('php://input'), true);
$texto = $input['texto'] ?? '';

if (empty($texto)) {
    echo json_encode(['respuesta' => 'No he recibido texto para procesar.']);
    exit;
}

// URL de n8n Producción
$n8n_url = 'http://127.0.0.1:5678/webhook/asistente';

// Payload redundante en el cuerpo también
$payload = [
    'texto' => $texto,
    'body' => ['texto' => $texto]
];

// Log del envío
file_put_contents('asistente_debug.log', date('[Y-m-d H:i:s] ') . "SENDING to n8n: " . json_encode($payload) . " (URL: $n8n_url)\n", FILE_APPEND);

// Llamada vía CURL
$ch = curl_init($n8n_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$date = date('Y-m-d H:i:s');

if ($curl_error) {
    file_put_contents('asistente_debug.log', "[$date] CURL ERROR: $curl_error\n", FILE_APPEND);
    echo json_encode(["respuesta" => "Error de conexión cURL: $curl_error", "accion" => "error"]);
} elseif ($http_code === 200) {
    file_put_contents('asistente_debug.log', "[$date] SUCCESS: $response\n", FILE_APPEND);
    echo $response;
} else {
    // AQUÍ ESTÁ EL TRUCO: Guardamos la respuesta completa de n8n aunque sea error
    file_put_contents('asistente_debug.log', "[$date] ERROR $http_code: $response\n", FILE_APPEND);
    
    // Intentamos decodificar si n8n envió un JSON con el error
    $error_data = json_decode($response, true);
    $msg = $error_data['message'] ?? $response ?? "Error desconocido en n8n";
    
    echo json_encode([
        "respuesta" => "n8n devolvió error $http_code: $msg",
        "accion" => "error",
        "debug" => $response
    ]);
}
curl_close($ch);
?>
