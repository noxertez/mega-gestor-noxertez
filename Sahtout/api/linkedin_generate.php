<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/linkedin_prompts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Método no permitido']));
}

$body = json_decode(file_get_contents('php://input'), true);
$tipo = $body['tipo'] ?? 'manual';
$sku_ref = $body['sku_ref'] ?? '';
$contexto = $body['contexto'] ?? '';
$tono = $body['tono'] ?? 'Profesional';

$db = conectar();

// 1. Obtener datos del producto si hay SKU
$producto_info = "";
if ($sku_ref) {
    $stmt = $db->prepare("SELECT NOMBRE, DESCRIPCION, CATEGORIA, PRECIO, COLOR FROM productos WHERE SKU_REF = ?");
    $stmt->execute([$sku_ref]);
    $p = $stmt->fetch();
    if ($p) {
        $producto_info = "Presenta este producto: " . $p['NOMBRE'] . ", " . $p['DESCRIPCION'] . ", categoría " . $p['CATEGORIA'] . ", precio " . $p['PRECIO'] . "€, color " . $p['COLOR'] . ". Destaca la artesanía y la personalización.";
    }
}

// 2. Obtener datos del mockup si existe en el body (opcional para manual)
$estancia = $body['estancia'] ?? '';
$decoracion = $body['decoracion'] ?? '';

// 3. Construir Prompt con la nueva guía
$prompt = getNoxertezLinkedinPrompt([
    'estancia'   => $estancia,
    'decoracion' => $decoracion,
    'info_prod'  => $producto_info,
    'contexto'   => $contexto,
    'tono'       => $tono
]);

// 4. Intentar llamar a la IA (Gemini o Groq)
$texto = "";
$error_msg = "";

// Intentar Gemini primero
$url_gemini = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . GEMINI_API_KEY;
$data_gemini = ["contents" => [["parts" => [["text" => $prompt]]]]];

$ch = curl_init($url_gemini);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_gemini));
$res_gemini = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($res_gemini['candidates'][0]['content']['parts'][0]['text'])) {
    $texto = trim($res_gemini['candidates'][0]['content']['parts'][0]['text']);
} else {
    // Si Gemini falla, probar Groq
    $url_groq = "https://api.groq.com/openai/v1/chat/completions";
    $data_groq = [
        "model" => "llama-3.3-70b-versatile",
        "messages" => [["role" => "user", "content" => $prompt]],
        "temperature" => 0.7
    ];
    
    $ch = curl_init($url_groq);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_groq));
    $res_groq = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (isset($res_groq['choices'][0]['message']['content'])) {
        $texto = trim($res_groq['choices'][0]['message']['content']);
    } else {
        $error_msg = "Ambas IAs fallaron. Gemini: " . ($res_gemini['error']['message'] ?? 'Error desconocido') . ". Groq: " . ($res_groq['error']['message'] ?? 'Error desconocido');
    }
}

if ($texto) {
    echo json_encode(['ok' => true, 'texto' => $texto]);
} else {
    echo json_encode(['error' => $error_msg, 'debug' => ['gemini' => $res_gemini, 'groq' => $res_groq]]);
}
