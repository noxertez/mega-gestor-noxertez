<?php
/**
 * PUENTE ASISTENTE VOZ - NOXERTEZ
 * Modo corazones: consulta directamente el proxy MySQL (SIN n8n)
 * Modo general: llama a n8n webhook cerebro_gen
 */
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$texto = trim($input['texto'] ?? '');
$modo  = trim($input['modo']  ?? 'general');

if (empty($texto)) {
    echo json_encode(['respuesta' => 'No he recibido texto.']);
    exit;
}

// ─────────────────────────────────────────────────────────
// MODO CORAZONES → Consulta directa al proxy MySQL (sin n8n)
// ─────────────────────────────────────────────────────────
if ($modo === 'corazones') {
    // Paso 1: Quitar frases de búsqueda
    $articulo = preg_replace(
        '/d[oó]nde\s*(est[áa]n?|se\s+encuentra[n]?)?|busca[r]?|ubica[r]?|encontrar|dame|dime|hay|tienes|tenemos|est[áa]n?|se\s+encuentra[n]?/ui',
        '',
        $texto
    );
    // Paso 2: Quitar artículos (el, la, los, las, un, una, unos, unas)
    $articulo = preg_replace('/\b(el|la|los|las|un|una|unos|unas|me|te|se|de|del|al)\b/ui', '', $articulo);
    // Paso 3: Limpiar espacios sobrantes
    $articulo = trim(preg_replace('/\s+/', ' ', $articulo));
    // Paso 4: Generar variantes de búsqueda (corazones → corazones, corazone, corazon)
    $variantes = [$articulo];
    // Quitar 'es' del final
    if (preg_match('/es$/ui', $articulo) && strlen($articulo) > 4) {
        $variantes[] = preg_replace('/es$/ui', '', $articulo);
    }
    // Quitar 's' del final
    if (preg_match('/s$/ui', $articulo) && strlen($articulo) > 3) {
        $variantes[] = rtrim($articulo, 's');
    }
    $variantes = array_unique($variantes);

    // Buscar en el proxy — primera variante que encuentre algo gana
    $data = [];
    foreach ($variantes as $variante) {
        if (empty($variante)) continue;
        $proxy_url  = 'http://localhost/noxertez/n8n_mysql_proxy.php?articulo=' . urlencode($variante);
        $proxy_resp = @file_get_contents($proxy_url);
        if ($proxy_resp !== false) {
            $tmp = json_decode($proxy_resp, true) ?: [];
            if (!empty($tmp)) { $data = $tmp; break; }
        }
    }

    if (!empty($data) && isset($data[0]['nombre'])) {
        $item      = $data[0];
        $nombre    = $item['nombre']    ?? 'el artículo';
        $ubic_raw  = $item['ubicacion'] ?? '';
        
        // --- Formateador de Ubicación Humano ---
        $ubicacion_humana = $ubic_raw;
        if (!empty($ubic_raw)) {
            // Ejemplo: A1-Z9 -> Estantería A, Nivel 1, Posición Z9
            $partes = explode('-', $ubic_raw);
            if (count($partes) == 2) {
                $estanteria_full = $partes[0]; // A1
                $posicion_full   = $partes[1]; // Z9
                
                $est = preg_replace('/[^a-zA-Z]/', '', $estanteria_full);
                $niv = preg_replace('/[^0-9]/', '', $estanteria_full);
                $pos = $posicion_full;
                
                $ubicacion_humana = "en la Estantería letra " . (empty($est) ? "" : strtoupper($est));
                if (!empty($niv)) $ubicacion_humana .= ", Nivel " . $niv;
                $ubicacion_humana .= ", Posición " . $pos;
            } else {
                // Si no tiene guion, intentamos separar letra y número
                $ubicacion_humana = preg_replace('/([a-zA-Z]+)([0-9]+)/', 'Estantería $1, Nivel $2', $ubic_raw);
            }
        }

        $respuesta = "$nombre está en " . (empty($ubicacion_humana) ? "una ubicación desconocida" : $ubicacion_humana) . ".";
    } else {
        $respuesta = "No he encontrado \"$articulo\" en el almacén. ¿Has dicho el nombre correcto del artículo?";
    }

    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// ─────────────────────────────────────────────────────────
// MODO GENERAL → Directo a PHP sin n8n
// ─────────────────────────────────────────────────────────
$general_url = 'http://localhost/noxertez/api/asistente_general.php';
$payload     = json_encode(['texto' => $texto, 'modo' => $modo]);

$ch = curl_init($general_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $response) {
    echo $response;
} else {
    echo json_encode([
        'respuesta' => "Error $http_code al consultar el asistente general. Comprueba que XAMPP está activo.",
        'accion'    => 'error'
    ]);
}
?>
