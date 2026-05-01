<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
require_once 'linkedin_prompts.php';
$db = conectar();

$accion = $_GET['accion'] ?? '';

// --- FUNCIONES LINKEDIN ---

function renovarTokenSiEsNecesario($db) {
    $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('linkedin_access_token', 'linkedin_refresh_token', 'linkedin_token_expires', 'linkedin_client_id', 'linkedin_client_secret')");
    $cfg = [];
    foreach ($stmt->fetchAll() as $r) $cfg[$r['clave']] = $r['valor'];

    $expires = (int)($cfg['linkedin_token_expires'] ?? 0);
    // Si quedan menos de 5 dias (432000 seg)
    if ($expires > 0 && ($expires - time()) < 432000 && !empty($cfg['linkedin_refresh_token'])) {
        $post_data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $cfg['linkedin_refresh_token'],
            'client_id' => $cfg['linkedin_client_id'],
            'client_secret' => $cfg['linkedin_client_secret']
        ];
        $ch = curl_init("https://www.linkedin.com/oauth/v2/accessToken");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        $res = json_decode(curl_exec($ch), true);
        if (isset($res['access_token'])) {
            $stmt = $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
            $stmt->execute([$res['access_token'], 'linkedin_access_token']);
            $stmt->execute([time() + $res['expires_in'], 'linkedin_token_expires']);
            return $res['access_token'];
        }
    }
    return $cfg['linkedin_access_token'] ?? '';
}

function publicarPostLinkedIn($db, $post_data_db) {
    $token = renovarTokenSiEsNecesario($db);
    $urn = "";
    $stmt = $db->query("SELECT valor FROM configuracion WHERE clave = 'linkedin_person_urn'");
    $urn = $stmt->fetchColumn();

    if (!$token || !$urn) return ['ok' => false, 'error' => 'Falta token o URN de usuario'];

    $texto = $post_data_db['texto'];
    $imagen_url = $post_data_db['imagen_url'];
    $media_urn = "";

    // 1. Si hay imagen, subirla primero
    if (!empty($imagen_url)) {
        // Normalizar ruta para el servidor (si es local)
        $local_path = $imagen_url;
        if (strpos($imagen_url, 'noxertez.com/Sahtout/') !== false) {
            $local_path = '../' . explode('Sahtout/', $imagen_url)[1];
        } elseif (!preg_match('~^(?:f|ht)tps?://~i', $imagen_url)) {
            // Si es una ruta relativa, asumimos que es desde la raíz del CMS
            // Como estamos en /api/, bajamos un nivel
            $local_path = '../' . ltrim($imagen_url, '/');
        }

        // A. Initialize Upload
        $init_url = "https://api.linkedin.com/rest/images?action=initializeUpload";
        $init_body = [
            "initializeUploadRequest" => [
                "owner" => "urn:li:person:" . $urn
            ]
        ];
        
        $ch = curl_init($init_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "LinkedIn-Version: 202604",
            "X-Restli-Protocol-Version: 2.0.0",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($init_body));
        $init_res = json_decode(curl_exec($ch), true);
        
        if (isset($init_res['value']['uploadUrl'])) {
            $upload_url = $init_res['value']['uploadUrl'];
            $media_urn = $init_res['value']['image'];
            
            // B. Upload Binary
            $img_content = @file_get_contents($local_path);
            if (!$img_content && $local_path !== $imagen_url) {
                // Si fallo la ruta local, intentar con la URL original
                $img_content = @file_get_contents($imagen_url);
            }

            if ($img_content) {
                $ch_up = curl_init($upload_url);
                curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_up, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch_up, CURLOPT_POSTFIELDS, $img_content);
                curl_setopt($ch_up, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $token",
                    "Content-Type: image/jpeg" // Asumimos jpeg o detectamos
                ]);
                curl_exec($ch_up);
                curl_close($ch_up);
            } else {
                $media_urn = ""; // Fallback a solo texto si falla la imagen
            }
        }
    }

    // 2. Crear el Post
    $post_url = "https://api.linkedin.com/rest/posts";
    $post_body = [
        "author" => "urn:li:person:" . $urn,
        "lifecycleState" => "PUBLISHED",
        "visibility" => "PUBLIC", 
        "commentary" => $texto,
        "distribution" => [
            "feedDistribution" => "MAIN_FEED",
            "targetEntities" => [],
            "thirdPartyDistributionChannels" => []
        ]
    ];

    if ($media_urn) {
        $post_body["content"] = [
            "media" => ["id" => $media_urn]
        ];
    }

    $ch_p = curl_init($post_url);
    curl_setopt($ch_p, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_p, CURLOPT_HEADER, true); // Necesitamos los headers para el ID
    curl_setopt($ch_p, CURLOPT_POST, true);
    curl_setopt($ch_p, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "LinkedIn-Version: 202604",
        "X-Restli-Protocol-Version: 2.0.0",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch_p, CURLOPT_POSTFIELDS, json_encode($post_body));
    
    $response = curl_exec($ch_p);
    $header_size = curl_getinfo($ch_p, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $http_code = curl_getinfo($ch_p, CURLINFO_HTTP_CODE);
    curl_close($ch_p);

    if ($http_code == 201) {
        // Buscar x-linkedin-id en los headers
        preg_match('/x-linkedin-id:\s*(.*)/i', $headers, $matches);
        $post_id = isset($matches[1]) ? trim($matches[1]) : "published";
        return ['ok' => true, 'id' => $post_id];
    } else {
        return ['ok' => false, 'error' => "Error API ($http_code): " . $body];
    }
}

function marcarMockupUsado($db, $imagen_url) {
    if (empty($imagen_url)) return;
    $path = $imagen_url;
    if (strpos($path, 'Sahtout/') !== false) $path = explode('Sahtout/', $path)[1];
    $db->prepare("UPDATE mockups_varios SET publicado_linkedin = NOW(), veces_usado = veces_usado + 1, ultima_vez_usado = NOW() WHERE ruta = ? OR ruta = ?")->execute([$path, ltrim($path, '/')]);
}

// --- LOGICA DE ENDPOINTS ---

$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($accion) {
    case 'save':
        $stmt = $db->prepare("INSERT INTO linkedin_queue (tipo, sku_ref, texto, imagen_url, enlace, estado, fecha_programada, generado_por_ia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['tipo'] ?? 'manual',
            $input['sku_ref'] ?? null,
            $input['texto'] ?? '',
            $input['imagen_url'] ?? '',
            $input['enlace'] ?? '',
            $input['estado'] ?? 'pendiente',
            $input['fecha_programada'] ?: null,
            $input['ia'] ?? 0
        ]);
        echo json_encode(['ok' => true]);
        break;

    case 'publish_now':
        $res = publicarPostLinkedIn($db, $input);
        if ($res['ok']) {
            $stmt = $db->prepare("INSERT INTO linkedin_queue (tipo, sku_ref, texto, imagen_url, enlace, estado, fecha_publicado, linkedin_post_id, generado_por_ia) VALUES (?, ?, ?, ?, ?, 'publicado', NOW(), ?, ?)");
            $stmt->execute([
                $input['tipo'] ?? 'manual',
                $input['sku_ref'] ?? null,
                $input['texto'] ?? '',
                $input['imagen_url'] ?? '',
                $input['enlace'] ?? '',
                $res['id'],
                $input['ia'] ?? 0
            ]);
            marcarMockupUsado($db, $input['imagen_url']);
            echo json_encode(['ok' => true, 'linkedin_id' => $res['id']]);
        } else {
            echo json_encode(['error' => $res['error']]);
        }
        break;

    case 'list':
        $pag = (int)($_GET['pag'] ?? 1);
        $limit = 25;
        $offset = ($pag - 1) * $limit;
        $estado = $_GET['estado'] ?? '';
        $busq = $_GET['busq'] ?? '';

        $where = "WHERE 1=1";
        $params = [];
        if ($estado) { $where .= " AND estado = ?"; $params[] = $estado; }
        if ($busq) { $where .= " AND (texto LIKE ? OR sku_ref LIKE ?)"; $params[] = "%$busq%"; $params[] = "%$busq%"; }

        $total = $db->prepare("SELECT COUNT(*) FROM linkedin_queue $where");
        $total->execute($params);
        $count = $total->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM linkedin_queue $where ORDER BY fecha_creacion DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        echo json_encode([
            'ok' => true,
            'items' => $items,
            'total_paginas' => ceil($count / $limit),
            'total_items' => $count
        ]);
        break;

    case 'get':
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare("SELECT * FROM linkedin_queue WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'item' => $stmt->fetch()]);
        break;

    case 'update':
        $stmt = $db->prepare("UPDATE linkedin_queue SET texto = ?, fecha_programada = ?, imagen_url = ? WHERE id = ?");
        $stmt->execute([
            $input['texto'],
            $input['fecha_programada'] ?: null,
            $input['imagen_url'],
            $input['id']
        ]);
        echo json_encode(['ok' => true]);
        break;

    case 'regenerate_ia':
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare("SELECT * FROM linkedin_queue WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if (!$post) die(json_encode(['error' => 'Post no encontrado']));

        // Recuperar contexto
        $estancia = ''; $decoracion = ''; $info_prod = '';
        
        // 1. Info de producto
        if ($post['sku_ref']) {
            $stmtP = $db->prepare("SELECT NOMBRE, DESCRIPCION FROM productos WHERE SKU_REF = ?");
            $stmtP->execute([$post['sku_ref']]);
            $art = $stmtP->fetch();
            if ($art) $info_prod = "Producto: " . $art['NOMBRE'] . ". " . $art['DESCRIPCION'];
        }

        // 2. Info de mockup (vía ruta de imagen)
        if ($post['imagen_url']) {
            $path = $post['imagen_url'];
            // Si es URL absoluta, limpiar
            if (strpos($path, 'Sahtout/') !== false) $path = explode('Sahtout/', $path)[1];
            $stmtM = $db->prepare("SELECT estancia, estilo, luz FROM mockups_varios WHERE ruta = ? OR ruta = ?");
            $stmtM->execute([$path, ltrim($path, '/')]);
            $m = $stmtM->fetch();
            if ($m) {
                $estancia = $m['estancia'];
                $decoracion = $m['estilo'] . " con luz " . $m['luz'];
            }
        }

        $prompt = getNoxertezLinkedinPrompt([
            'estancia' => $estancia,
            'decoracion' => $decoracion,
            'info_prod' => $info_prod,
            'tono' => 'Profesional'
        ]);

        // Llamar a Gemini / Groq
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_API_KEY;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["contents" => [["parts" => [["text" => $prompt]]]]]));
        $resp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $texto = "";
        if (isset($resp['candidates'][0]['content']['parts'][0]['text'])) {
            $texto = trim($resp['candidates'][0]['content']['parts'][0]['text']);
        } else if (defined('GROQ_API_KEY')) {
            // Fallback
            $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . GROQ_API_KEY]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["model" => "llama-3.3-70b-versatile", "messages" => [["role" => "user", "content" => $prompt]]]));
            $gres = json_decode(curl_exec($ch), true);
            curl_close($ch);
            if (isset($gres['choices'][0]['message']['content'])) $texto = trim($gres['choices'][0]['message']['content']);
        }

        if ($texto) {
            $db->prepare("UPDATE linkedin_queue SET texto = ?, generado_por_ia = 1 WHERE id = ?")->execute([$texto, $id]);
            echo json_encode(['ok' => true, 'texto' => $texto]);
        } else {
            echo json_encode(['error' => 'No se pudo generar el texto']);
        }
        break;

    case 'delete':
        $id = $_GET['id'] ?? 0;
        $db->prepare("DELETE FROM linkedin_queue WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    case 'publish_single':
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare("SELECT * FROM linkedin_queue WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if (!$post) die(json_encode(['error' => 'Post no encontrado']));

        $res = publicarPostLinkedIn($db, $post);
        if ($res['ok']) {
            $db->prepare("UPDATE linkedin_queue SET estado = 'publicado', fecha_publicado = NOW(), linkedin_post_id = ? WHERE id = ?")->execute([$res['id'], $id]);
            marcarMockupUsado($db, $post['imagen_url']);
            echo json_encode(['ok' => true]);
        } else {
            $intentos = $post['intentos'] + 1;
            $nuevo_estado = ($intentos >= 3) ? 'error' : 'pendiente';
            $db->prepare("UPDATE linkedin_queue SET intentos = ?, mensaje_error = ?, estado = ? WHERE id = ?")->execute([$intentos, $res['error'], $nuevo_estado, $id]);
            echo json_encode(['error' => $res['error']]);
        }
        break;

    case 'publish_batch':
        // Publicar pendientes de hoy (o pasados)
        $stmt = $db->query("SELECT * FROM linkedin_queue WHERE estado = 'pendiente' AND fecha_programada <= NOW() ORDER BY fecha_programada ASC");
        $pendientes = $stmt->fetchAll();
        
        $publicados = 0;
        $errores = [];
        
        foreach ($pendientes as $p) {
            $res = publicarPostLinkedIn($db, $p);
            if ($res['ok']) {
                $db->prepare("UPDATE linkedin_queue SET estado = 'publicado', fecha_publicado = NOW(), linkedin_post_id = ? WHERE id = ?")->execute([$res['id'], $p['id']]);
                marcarMockupUsado($db, $p['imagen_url']);
                $publicados++;
            } else {
                $intentos = $p['intentos'] + 1;
                $nuevo_estado = ($intentos >= 3) ? 'error' : 'pendiente';
                $db->prepare("UPDATE linkedin_queue SET intentos = ?, mensaje_error = ?, estado = ? WHERE id = ?")->execute([$intentos, $res['error'], $nuevo_estado, $p['id']]);
                $errores[] = ['id' => $p['id'], 'error' => $res['error']];
            }
        }
        
        echo json_encode(['ok' => true, 'publicados' => $publicados, 'errores' => $errores]);
        break;

    case 'stats':
        $stats = $db->query("SELECT estado, COUNT(*) as total FROM linkedin_queue GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
        $pub_mes = $db->query("SELECT COUNT(*) FROM linkedin_queue WHERE estado = 'publicado' AND MONTH(fecha_publicado) = MONTH(NOW()) AND YEAR(fecha_publicado) = YEAR(NOW())")->fetchColumn();
        $stats['publicado_mes'] = $pub_mes;

        $calendario = [];
        for ($i = 0; $i < 14; $i++) {
            $fecha = date('Y-m-d', strtotime("+$i days"));
            $n = $db->query("SELECT COUNT(*) FROM linkedin_queue WHERE DATE(fecha_programada) = '$fecha' AND estado = 'pendiente'")->fetchColumn();
            $calendario[] = ['fecha' => date('d/m', strtotime($fecha)), 'total' => (int)$n];
        }

        $ultimos = $db->query("SELECT texto, linkedin_post_id FROM linkedin_queue WHERE estado = 'publicado' ORDER BY fecha_publicado DESC LIMIT 5")->fetchAll();

        echo json_encode(['ok' => true, 'stats' => $stats, 'calendario' => $calendario, 'ultimos' => $ultimos]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
?>
