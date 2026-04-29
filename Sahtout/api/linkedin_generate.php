<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

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

// 2. Construir Prompt Base
$prompt = "Eres un experto en marketing de contenidos para LinkedIn. Escribe un post para LinkedIn sobre Noxertez, un taller artesanal español que crea mosaicos geométricos de madera, figuras decorativas y esculturas volumétricas hechas a mano. El post debe ser en español, tono $tono, máximo 1500 caracteres, con emojis apropiados, y terminar con 3-5 hashtags relevantes. No uses comillas al inicio ni al final.";

// 3. Añadir Prompt específico por tipo
switch ($tipo) {
    case 'producto':
        $prompt .= "\n\n" . ($producto_info ?: "Presenta uno de nuestros productos estrella, destacando la calidad de la madera y el trabajo artesanal.");
        break;
    case 'behind_scenes':
        $prompt .= "\n\nDescribe el proceso artesanal de creación de mosaicos de madera: selección de materiales, corte de piezas, montaje geométrico, barnizado y control de calidad.";
        break;
    case 'marca':
        $prompt .= "\n\nHabla sobre la historia y valores de Noxertez: artesanía española, personalización por encargo, piezas únicas hechas a mano con madera natural.";
        break;
    case 'promocion':
        $prompt .= "\n\nCrea un post promocional destacando que las piezas se personalizan en color y tamaño bajo pedido, sin stock muerto, producción a demanda.";
        break;
    default:
        $prompt .= "\n\nCrea un post inspirador sobre decoración artesanal y diseño geométrico.";
}

if (!empty($contexto)) {
    $prompt .= "\n\nContexto adicional: $contexto";
}

// 4. Llamar a Gemini API
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_API_KEY;

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
if (curl_errno($ch)) {
    die(json_encode(['error' => 'Error CURL: ' . curl_error($ch)]));
}
curl_close($ch);

$res = json_decode($response, true);
if (isset($res['candidates'][0]['content']['parts'][0]['text'])) {
    $texto = trim($res['candidates'][0]['content']['parts'][0]['text']);
    echo json_encode(['ok' => true, 'texto' => $texto]);
} else {
    echo json_encode(['error' => 'Error de Gemini: ' . ($res['error']['message'] ?? 'Respuesta inesperada'), 'debug' => $res]);
}
