<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/linkedin_prompts.php';

$db = conectar();
$body = json_decode(file_get_contents('php://input'), true);
$accion = $body['accion'] ?? '';

if ($accion === 'get_mockups') {
    $categoria = $body['categoria'] ?? '';
    $estancia = $body['estancia'] ?? '';
    $decoracion = $body['decoracion'] ?? '';
    $cantidad = (int)($body['cantidad'] ?? 10);

    $where = ["1=1"];
    $params = [];
    if ($estancia) { $where[] = "m.estancia = ?"; $params[] = $estancia; }
    if ($decoracion) { $where[] = "m.decoracion = ?"; $params[] = $decoracion; }
    if ($categoria) {
        $where[] = "EXISTS (SELECT 1 FROM mockups_vinculaciones mv JOIN articulos a ON mv.sku = a.referencia WHERE mv.mockup_id = m.id AND a.categoria = ?)";
        $params[] = $categoria;
    }

    $sql = "SELECT m.id, m.ruta, m.archivo FROM mockups_varios m WHERE " . implode(" AND ", $where) . " ORDER BY RAND() LIMIT $cantidad";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $mockups = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'mockups' => $mockups]);
    exit;
}

if ($accion === 'generate_one') {
    $mockup_id = $body['mockup_id'] ?? 0;
    $contexto_general = $body['contexto'] ?? '';
    $tono = $body['tono'] ?? 'Profesional';
    $index = (int)($body['index'] ?? 0);

    // 1. Obtener datos del mockup
    $stmtM = $db->prepare("SELECT * FROM mockups_varios WHERE id = ?");
    $stmtM->execute([$mockup_id]);
    $m = $stmtM->fetch();
    if (!$m) die(json_encode(['error' => 'Mockup no encontrado']));

    // 2. Obtener info del producto vinculado
    $stmtP = $db->prepare("SELECT a.nombre, a.descripcion, a.categoria, a.referencia FROM mockups_vinculaciones mv JOIN articulos a ON mv.sku = a.referencia WHERE mv.mockup_id = ? LIMIT 1");
    $stmtP->execute([$mockup_id]);
    $art = $stmtP->fetch();
    
    $info_prod = "";
    $sku_ref = null;
    if ($art) {
        $sku_ref = $art['referencia'];
        $info_prod = "Producto: " . $art['nombre'] . " (" . $art['referencia'] . "). " . $art['descripcion'] . ". Categoría: " . $art['categoria'] . ".";
    }
    
    // 3. Prompt para Gemini con la nueva guía
    $prompt = getNoxertezLinkedinPrompt([
        'estancia'   => $m['estancia'],
        'decoracion' => $m['decoracion'],
        'info_prod'  => $info_prod,
        'contexto'   => $contexto_general,
        'tono'       => $tono
    ]);

    // 3. Intentar llamar a la IA (Gemini o Groq)
    $texto = "";
    
    // Probar Gemini
    $url_gemini = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . GEMINI_API_KEY;
    $gemini_data = ["contents" => [["parts" => [["text" => $prompt]]]]];
    
    $ch = curl_init($url_gemini);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gemini_data));
    $res_gemini = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (isset($res_gemini['candidates'][0]['content']['parts'][0]['text'])) {
        $texto = trim($res_gemini['candidates'][0]['content']['parts'][0]['text']);
    } else {
        // Fallback a Groq
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
        }
    }
    
    if (!$texto) {
        $texto = "Descubre nuestra colección de decoración artesanal para tu " . ($m['estancia'] ?: 'hogar') . ". #Noxertez #Artesania #Decoracion";
    }
    
    // 4. Calcular fecha (3 al día)
    $dias_offset = floor($index / 3);
    $slot_idx = $index % 3;
    $slots = ['09:00:00', '15:00:00', '21:00:00'];
    $fecha_prog = date('Y-m-d', strtotime("tomorrow +$dias_offset days")) . ' ' . $slots[$slot_idx];
    
    // 5. Guardar
    $ins = $db->prepare("INSERT INTO linkedin_queue (tipo, sku_ref, texto, imagen_url, estado, fecha_programada, generado_por_ia) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([
        'producto',
        $sku_ref,
        $texto,
        $m['ruta'],
        'pendiente',
        $fecha_prog,
        1
    ]);
    
    // MARCAR MOCKUP COMO USADO
    $db->prepare("UPDATE mockups_varios SET publicado_linkedin = NOW(), veces_usado = veces_usado + 1, ultima_vez_usado = NOW() WHERE id = ?")->execute([$m['id']]);
    
    echo json_encode(['ok' => true]);
    exit;
}

die(json_encode(['error' => 'Acción no permitida']));
