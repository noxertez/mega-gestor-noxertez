<?php
/**
 * api/stock.php
 * Gestión de inventario unificada.
 */
require_once 'config.php';
$db = conectar();

// ── Asegurar que sku_base existe ───────────────────────────────────
try { $db->query("ALTER TABLE articulos ADD COLUMN sku_base VARCHAR(100) NULL"); } catch(Exception $e) {}

// ── Auto-migración módulo Almacén ────────────────────────────────────────
try { $db->query("ALTER TABLE materiales ADD COLUMN ubicacion VARCHAR(20) NULL"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE materiales ADD COLUMN estado_stock ENUM('B','S','T') NULL"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE productos ADD COLUMN UBICACION_MAP VARCHAR(20) NULL"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE productos ADD COLUMN UBICACION_MAP VARCHAR(20) NULL"); } catch(Exception $e) {}
try { $db->query("CREATE TABLE IF NOT EXISTS almacen_estanterias (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, num_baldas INT NOT NULL DEFAULT 3, num_columnas INT NOT NULL DEFAULT 4, orden INT NOT NULL DEFAULT 0, activa TINYINT(1) NOT NULL DEFAULT 1, creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}
try { $db->query("CREATE TABLE IF NOT EXISTS almacen_posiciones (id INT AUTO_INCREMENT PRIMARY KEY, estanteria_id INT NOT NULL, balda INT NOT NULL, columna INT NOT NULL, etiqueta VARCHAR(20) NOT NULL, tipo_caja ENUM('negra','verde','transparente','otra') NULL, notas TEXT NULL, UNIQUE KEY uq_pos (estanteria_id, balda, columna)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}

/**
 * Función auxiliar para servir archivos de forma segura desde fuera del root
 */
function serveFicha($path, $ref, $ext) {
    $path = str_replace('\\', '/', $path);
    if (file_exists($path)) {
        $mime = (strtolower(substr($path, -3)) === 'png') ? 'image/png' : 'image/jpeg';
        header("Content-Type: " . $mime);
        header('Content-Disposition: attachment; filename="FICHA_' . $ref . $ext . '"');
        readfile($path);
        exit();
    }
}

$metodo = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true);

if ($metodo === 'GET') {
    $accion = $_GET['accion'] ?? '';
    $ref = $_GET['ref'] ?? '';
    
    // Obtener un solo artículo con su stock real de productos
    if ($accion === 'get_art' && $ref) {
        $stmt = $db->prepare("
            SELECT a.*, 
                   CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK), 'NO'), ''), 0) AS UNSIGNED) as stock_final, 
                   p.STOCK_FISICO as stock_semi 
            FROM articulos a 
            JOIN productos p ON a.referencia = p.SKU_REF 
            WHERE a.referencia = ?
        ");
        $stmt->execute([$ref]);
        $res = $stmt->fetch();
        if ($res) {
            $res['stock'] = $res['stock_final']; 
            
            // Si es base, buscar sus variantes (con protección si sku_base no existe aún)
            if ($res['es_variante'] === 'BASE') {
                try {
                    $stmtV = $db->prepare("
                        SELECT a.*, 
                               CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK), 'NO'), ''), 0) AS UNSIGNED) as stock_final, 
                               p.STOCK_FISICO as stock_semi 
                        FROM articulos a 
                        JOIN productos p ON a.referencia = p.SKU_REF 
                        WHERE a.sku_base = ? AND a.referencia != ?
                    ");
                    $stmtV->execute([$res['sku_base'] ?? $res['referencia'], $ref]);
                    $res['variantes'] = $stmtV->fetchAll();
                    foreach ($res['variantes'] as &$v) {
                        $v['stock'] = $v['stock_final'];
                    }
                } catch (Exception $e) {
                    $res['variantes'] = []; // Sin variantes si falla la query
                }
            } else {
                $res['variantes'] = [];
            }
        }
        echo json_encode($res);
        exit();
    }

    if ($accion === 'get_mat' && $ref) {
        $stmt = $db->prepare("SELECT * FROM materiales WHERE REF_MAT = ?");
        $stmt->execute([$ref]);
        echo json_encode($stmt->fetch());
        exit();
    }

    if ($accion === 'calcular' && $ref) {
        $stmtConnect = $db->prepare("SELECT * FROM despiece_articulos WHERE SKU_BASE = ?");
        $stmtConnect->execute([$ref]);
        $partes = $stmtConnect->fetchAll();
        
        if (!$partes) {
            echo json_encode(['max_posible' => 0, 'error' => 'Sin receta']);
            exit();
        }

        $maximos = [];
        foreach ($partes as $p) {
            $stmtM = $db->prepare("SELECT STOCK_ACTUAL FROM materiales WHERE REF_MAT = ?");
            $stmtM->execute([$p['REF_MAT']]);
            $mat = $stmtM->fetch();
            if ($mat && $p['CANTIDAD'] > 0) {
                $maximos[] = floor($mat['STOCK_ACTUAL'] / $p['CANTIDAD']);
            } else {
                $maximos[] = 0;
            }
        }
        echo json_encode(['max_posible' => count($maximos) > 0 ? min($maximos) : 0]);
        exit();
    }

    if ($accion === 'ver_bom' && $ref) {
        $stmt = $db->prepare("SELECT d.*, m.NOMBRE FROM despiece_articulos d JOIN materiales m ON d.REF_MAT = m.REF_MAT WHERE d.SKU_BASE = ?");
        $stmt->execute([$ref]);
        echo json_encode($stmt->fetchAll());
        exit();
    }

    if ($accion === 'descargar_ficha' && $ref) {
        $marca = $_GET['marca'] ?? 'NOXERTEZ';
        $base = $_GET['base'] ?? '';
        $color_req = trim($_GET['color'] ?? '');
        
        // Si no viene color por parámetro, intentar sacarlo del SKU (ej: SKU-COLOR)
        if (!$color_req && strpos($ref, '-') !== false) {
            $parts = explode('-', $ref);
            $color_req = end($parts);
        }
        
        $mapping = ["CANDLE HOLDER OF THE SOUL" => "CANDLEHOLDER", "THE SECRET ZEN GARDEN" => "THE_SECRET_ZEN_GARDEN", "NOXERTEZ" => "NOXERTEZ"];
        $marca_folder = $mapping[$marca] ?? str_replace(' ', '_', $marca);
        $sku_folder = str_replace(['/', '\\'], '_', preg_replace('/P\d+$/', '', $base));
        
        $desktop_base = "../uploads/articulos/repo_pc/";
        $brand_desktop_dir = $desktop_base . $marca_folder . "/";
        
        $suffixes = ["_1", "", "_" . strtoupper($color_req), "_" . $color_req, "_" . ucfirst(strtolower($color_req))];
        $suffixes = array_unique($suffixes);
        $exts = [".png", ".jpg", ".PNG", ".JPG"];
        
        // 1. INTENTO DE RUTA DIRECTA (Rápida e Insensible a Mayúsculas/Espacios)
        $cleanRef = strtolower(str_replace(' ', '', "FICHA_" . $ref));
        
        // Generar variaciones de carpeta con espacios (ej: NXTLAM AGU0039)
        $possible_folders = [$sku_folder];
        $possible_folders[] = str_replace(' ', '', $sku_folder);
        if (strlen($sku_folder) > 6) {
            $possible_folders[] = substr($sku_folder, 0, 6) . " " . substr($sku_folder, 6);
            $possible_folders[] = substr($sku_folder, 0, 5) . " " . substr($sku_folder, 5);
        }
        
        foreach (array_unique($possible_folders) as $f) {
            $dir = $brand_desktop_dir . $f . "/";
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $f_name) {
                    $cleanFName = strtolower(str_replace(' ', '', $f_name));
                    foreach ($suffixes as $suf) {
                        foreach ($exts as $ext) {
                            $target = strtolower(str_replace(' ', '', $cleanRef . $suf . $ext));
                            if ($cleanFName === $target) {
                                serveFicha($dir . $f_name, $ref, $ext);
                            }
                        }
                    }
                }
            }
        }
        
        // 2. FALLBACK: BÚSQUEDA RECURSIVA PROFUNDA (Si la ruta directa falla)
        if (is_dir($brand_desktop_dir)) {
            try {
                $ite = new RecursiveDirectoryIterator($brand_desktop_dir);
                foreach (new RecursiveIteratorIterator($ite) as $path => $cur) {
                    if ($cur->isDir()) continue;
                    $name = $cur->getFilename();
                    $cleanName = strtolower(str_replace(' ', '', $name));
                    
                    foreach ($suffixes as $suf) {
                        foreach ($exts as $ext) {
                            $target = strtolower(str_replace(' ', '', "FICHA_" . $ref . $suf . $ext));
                            if ($cleanName === $target) {
                                serveFicha($path, $ref, $ext);
                            }
                        }
                    }
                }
            } catch (Exception $e) { /* Error de acceso */ }
        }
        
        echo "<h3>Error: Ficha no encontrada</h3>";
        echo "<p>No se pudo localizar el archivo para la referencia: <b>$ref</b></p>";
        echo "<div style='background:#f1f5f9; padding:10px; border-radius:5px; font-family:monospace; font-size:12px;'>";
        echo "<b>DEBUG INFO:</b><br>";
        echo "- SKU buscado: FICHA_$ref<br>";
        echo "- Color identificado: " . ($color_req ?: '(vacío)') . "<br>";
        echo "- Sufijos probados: " . implode(', ', $suffixes) . "<br>";
        echo "- Marca: $marca ($marca_folder)<br>";
        echo "- Carpeta Base sugerida: $sku_folder<br>";
        echo "- Ruta Escaneo: $brand_desktop_dir<br>";
        echo "</div>";
        exit();
    }

    // ── GET mapa_celda_publica (sin sesión, para caja.php) ────────────────
    if ($accion === 'mapa_celda_publica') {
        $etq = strtoupper(trim($_GET['etiqueta'] ?? ''));
        if (!$etq) { jsonSalida(['error' => 'Etiqueta requerida']); }

        $stmtPos = $db->prepare("SELECT * FROM almacen_posiciones WHERE etiqueta = ? LIMIT 1");
        $stmtPos->execute([$etq]);
        $pos = $stmtPos->fetch();
        if (!$pos) { jsonSalida(['error' => 'Celda no encontrada']); }

        $stmtM = $db->prepare("SELECT REF_MAT, NOMBRE, STOCK_ACTUAL, UNIDAD, estado_stock FROM materiales WHERE ubicacion = ?");
        $stmtM->execute([$etq]);
        $mats = $stmtM->fetchAll();

        $arts = [];
        try {
            $stmtA = $db->prepare("
                SELECT a.referencia, a.nombre,
                       CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK),'NO'),''),0) AS UNSIGNED) as stock_final,
                       p.STOCK_FISICO as stock_semi
                FROM articulos a JOIN productos p ON a.referencia = p.SKU_REF
                WHERE p.UBICACION_MAP = ?");
            $stmtA->execute([$etq]);
            $arts = $stmtA->fetchAll();
        } catch(Exception $ex) {}

        $stmtE = $db->prepare("SELECT nombre FROM almacen_estanterias WHERE id = ?");
        $stmtE->execute([$pos['estanteria_id']]);
        $est = $stmtE->fetch();

        jsonSalida([
            'posicion'    => $pos,
            'estanteria'  => $est['nombre'] ?? '',
            'materiales'  => $mats,
            'articulos'   => $arts
        ]);
        exit();
    }

    // ── GET mapa_estanterias ──────────────────────────────────────────────
    if ($accion === 'mapa_estanterias') {
        $estanterias = $db->query("
            SELECT e.*, 
                   (SELECT COUNT(*) FROM almacen_posiciones WHERE estanteria_id = e.id) as total_posiciones
            FROM almacen_estanterias e WHERE e.activa = 1 ORDER BY e.orden ASC, e.id ASC
        ")->fetchAll();

        // Obtener TODAS las etiquetas que tienen algún artículo con stock
        $etiquetasConArt = $db->query("
            SELECT DISTINCT UBICACION_MAP 
            FROM productos 
            WHERE UBICACION_MAP IS NOT NULL AND UBICACION_MAP != ''
              AND (CAST(COALESCE(NULLIF(NULLIF(TRIM(STOCK),'NO'),''),0) AS UNSIGNED) > 0 OR COALESCE(STOCK_FISICO,0) > 0)
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($estanterias as &$e) {
            $stmtP = $db->prepare("SELECT * FROM almacen_posiciones WHERE estanteria_id = ? ORDER BY balda ASC, columna ASC");
            $stmtP->execute([$e['id']]);
            $posiciones = $stmtP->fetchAll();
            
            // Marcar localmente si tiene artículo
            foreach ($posiciones as &$p) {
                $p['has_art'] = in_array($p['etiqueta'], $etiquetasConArt);
            }
            $e['posiciones'] = $posiciones;
        }
        echo json_encode($estanterias); exit();
    }

    // ── GET mapa_celda ────────────────────────────────────────────────────
    if ($accion === 'mapa_celda') {
        $posId = intval($_GET['id'] ?? 0);
        $stmtPos = $db->prepare("SELECT * FROM almacen_posiciones WHERE id = ?");
        $stmtPos->execute([$posId]);
        $pos = $stmtPos->fetch();
        if (!$pos) { jsonSalida(['error' => 'Posición no encontrada']); }

        // Materiales en esa posición
        $stmtM = $db->prepare("SELECT REF_MAT, NOMBRE, STOCK_ACTUAL, UNIDAD, estado_stock, FOTO FROM materiales WHERE ubicacion = ?");
        $stmtM->execute([$pos['etiqueta']]);
        $materiales_pos = $stmtM->fetchAll();

        // Artículos (terminados + semi) usando UBICACION_MAP en productos
        $articulos_pos = [];
        try {
            $stmtA = $db->prepare("
                SELECT a.referencia, a.nombre, a.foto_portada,
                       CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK),'NO'),''),0) AS UNSIGNED) as stock_final,
                       p.STOCK_FISICO as stock_semi
                FROM articulos a
                JOIN productos p ON a.referencia = p.SKU_REF
                WHERE p.UBICACION_MAP = ?
            ");
            $stmtA->execute([$pos['etiqueta']]);
            $articulos_pos = $stmtA->fetchAll();
        } catch(Exception $ex) { /* columna aún no existe */ }

        // Lista de TODOS los materiales (para el selector de asignación)
        $todosM = $db->query("SELECT REF_MAT, NOMBRE FROM materiales ORDER BY NOMBRE ASC")->fetchAll();

        // Lista de TODOS los artículos con stock > 0 (para asignación)
        $todosA = [];
        try {
            $todosA = $db->query("
                SELECT a.referencia, a.nombre,
                       CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK),'NO'),''),0) AS UNSIGNED) as stock_final,
                       p.STOCK_FISICO as stock_semi
                FROM articulos a JOIN productos p ON a.referencia = p.SKU_REF
                WHERE a.activo = 1 
                  AND (CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK),'NO'),''),0) AS UNSIGNED) > 0 OR COALESCE(p.STOCK_FISICO,0) > 0)
                ORDER BY a.nombre ASC
            ")->fetchAll();
        } catch(Exception $ex) {}

        jsonSalida([
            'posicion'   => $pos,
            'materiales' => $materiales_pos,
            'articulos'  => $articulos_pos,
            'todos_mat'  => $todosM,
            'todos_art'  => $todosA
        ]);
        exit();
    }

    // ── GET mapa_buscar ───────────────────────────────────────────────────
    if ($accion === 'mapa_buscar') {
        $q = trim($_GET['q'] ?? '');
        $resultados = [];
        try {
            // Buscar en materiales
            $stmtM = $db->prepare("
                SELECT 'material' as tipo, m.REF_MAT as ref, m.NOMBRE as nombre, m.ubicacion,
                       ae.nombre as estanteria, ap.balda, ap.columna
                FROM materiales m
                LEFT JOIN almacen_posiciones ap ON ap.etiqueta = m.ubicacion
                LEFT JOIN almacen_estanterias ae ON ae.id = ap.estanteria_id
                WHERE (m.REF_MAT LIKE ? OR m.NOMBRE LIKE ?) 
                  AND m.ubicacion IS NOT NULL AND m.ubicacion != ''
                LIMIT 10
            ");
            $stmtM->execute(["%$q%", "%$q%"]);
            $resultados = array_merge($resultados, $stmtM->fetchAll());

            // Buscar en artículos
            $stmtA = $db->prepare("
                SELECT 'articulo' as tipo, a.referencia as ref, a.nombre,
                       p.UBICACION_MAP as ubicacion,
                       ae.nombre as estanteria, ap.balda, ap.columna
                FROM articulos a
                JOIN productos p ON a.referencia = p.SKU_REF
                LEFT JOIN almacen_posiciones ap ON ap.etiqueta = p.UBICACION_MAP
                LEFT JOIN almacen_estanterias ae ON ae.id = ap.estanteria_id
                WHERE (a.referencia LIKE ? OR a.nombre LIKE ?) 
                  AND p.UBICACION_MAP IS NOT NULL AND p.UBICACION_MAP != ''
                LIMIT 10
            ");
            $stmtA->execute(["%$q%", "%$q%"]);
            $resultados = array_merge($resultados, $stmtA->fetchAll());
        } catch(Exception $ex) {}
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resultados);
        exit();
    }

    // Listar alertas (stock bajo) cruzando con productos.STOCK
    if ($accion === 'get_next_ref_mat') {
        $marca = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $_GET['marca'] ?? ''), 0, 3));
        $cat   = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $_GET['cat'] ?? ''), 0, 3));
        $sub   = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $_GET['sub'] ?? ''), 0, 3));
        $prefix = "M-" . $marca . $cat . $sub;
        
        // Buscar el máximo número global al final de las referencias
        $stmt = $db->query("SELECT REF_MAT FROM materiales");
        $refs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $max_num = 0;
        foreach ($refs as $r) {
            // Intentar extraer los últimos 4 dígitos
            if (preg_match('/(\d{4})$/', $r, $matches)) {
                $num = intval($matches[1]);
                if ($num > $max_num) $max_num = $num;
            }
        }
        $next_ref = $prefix . str_pad($max_num + 1, 4, '0', STR_PAD_LEFT);
        echo json_encode(['next_ref' => $next_ref]);
        exit();
    }
    echo json_encode($stmt->fetchAll());
}

elseif ($metodo === 'PUT') {
    // Soporte para actualizaciones múltiples (batch) o sencilla
    $updates = isset($body['articulos']) ? $body['articulos'] : [$body];

    foreach ($updates as $item) {
        $ref = $item['referencia'] ?? '';
        if (!$ref) continue;

        // Ahora recibimos el TOTAL directamente
        $total_final = (int)($item['total_final'] ?? $item['plus_final'] ?? 0);
        $total_semi  = (int)($item['total_semi']  ?? $item['plus_semi']  ?? 0);
        
        // Actualizar STOCK en productos (Total Final)
        $db->prepare("UPDATE productos SET STOCK = ? WHERE SKU_REF = ?")->execute([$total_final, $ref]);
        
        // Actualizar STOCK_FISICO en productos (Total Semi)
        $db->prepare("UPDATE productos SET STOCK_FISICO = ? WHERE SKU_REF = ?")->execute([$total_semi, $ref]);
        
        // Espejo en articulos
        $db->prepare("UPDATE articulos SET stock = ? WHERE referencia = ?")->execute([$total_final, $ref]);
    }
    
    echo json_encode(['ok' => true]);
}

elseif ($metodo === 'POST') {
    $accion = $_GET['accion'] ?? '';
    if ($accion === 'add_bom') {
        $ref = $body['ref'];
        $mat = $body['ref_mat'];
        $qty = $body['qty'];

        // Insertar para el artículo actual
        $stmt = $db->prepare("INSERT INTO despiece_articulos (SKU_BASE, REF_MAT, CANTIDAD) VALUES (?, ?, ?)");
        $stmt->execute([$ref, $mat, $qty]);

        // PROPAGACIÓN: Si es un artículo BASE, propagar a todas sus variantes/patrones
        $stmtArt = $db->prepare("SELECT es_variante, sku_base FROM articulos WHERE referencia = ?");
        $stmtArt->execute([$ref]);
        $art = $stmtArt->fetch();

        if ($art && $art['es_variante'] === 'BASE') {
            $stmtV = $db->prepare("SELECT referencia FROM articulos WHERE sku_base = ? AND referencia != ?");
            $stmtV->execute([$art['sku_base'], $ref]);
            $variantes = $stmtV->fetchAll(PDO::FETCH_COLUMN);

            foreach ($variantes as $vRef) {
                // Evitar duplicados antes de insertar
                $check = $db->prepare("SELECT 1 FROM despiece_articulos WHERE SKU_BASE = ? AND REF_MAT = ?");
                $check->execute([$vRef, $mat]);
                if (!$check->fetch()) {
                    $stmt->execute([$vRef, $mat, $qty]);
                }
            }
        }

        echo json_encode(['ok' => true]); exit();
    }
    if ($accion === 'edit_mat') {
        $stmt = $db->prepare("UPDATE materiales SET NOMBRE = ?, STOCK_ACTUAL = ?, PUNTO_PEDIDO = ?, CATEGORIA = ?, SUBCATEGORIA = ?, MARCA = ?, COLOR = ?, DIMENSIONES = ?, FESTIVIDAD = ?, UNIDAD = ? WHERE REF_MAT = ?");
        $stmt->execute([
            $body['nombre'], 
            $body['stock'], 
            $body['punto_pedido'] ?? 0, 
            $body['categoria'] ?? '',
            $body['subcategoria'] ?? '',
            $body['marca'] ?? '',
            $body['color'] ?? '',
            $body['dimensiones'] ?? '',
            $body['festividad'] ?? '',
            $body['unidad'] ?? '',
            $body['ref']
        ]);
        echo json_encode(['ok' => true]); exit();
    }

    // ── POST mapa_guardar_estanteria ──────────────────────────────────────
    if ($accion === 'mapa_guardar_estanteria') {
        $id        = intval($body['id'] ?? 0);
        $nombre    = trim($body['nombre'] ?? '');
        $baldas    = max(1, intval($body['num_baldas'] ?? 3));
        $columnas  = max(1, intval($body['num_columnas'] ?? 4));
        $orden     = intval($body['orden'] ?? 0);
        if (!$nombre) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit(); }

        if ($id > 0) {
            $db->prepare("UPDATE almacen_estanterias SET nombre=?, num_baldas=?, num_columnas=?, orden=? WHERE id=?")
               ->execute([$nombre, $baldas, $columnas, $orden, $id]);
        } else {
            $db->prepare("INSERT INTO almacen_estanterias (nombre,num_baldas,num_columnas,orden) VALUES (?,?,?,?)")
               ->execute([$nombre, $baldas, $columnas, $orden]);
            $id = $db->lastInsertId();
        }

        // Sincronizar posiciones: añadir las que falten, actualizar etiquetas
        // Usamos el ID de la estantería como prefijo para evitar colisiones totales entre muebles
        // Pero permitimos que el usuario vea una letra si la pone al final del nombre
        $prefijo = preg_match('/([A-Z])\s*$/i', $nombre, $m) ? strtoupper($m[1]) : $id;
        
        for ($b = 1; $b <= $baldas; $b++) {
            for ($c = 1; $c <= $columnas; $c++) {
                $etiqueta = $prefijo . $b . '-C' . $c;
                $stmtCheck = $db->prepare("SELECT id FROM almacen_posiciones WHERE estanteria_id=? AND balda=? AND columna=?");
                $stmtCheck->execute([$id, $b, $c]);
                if (!$stmtCheck->fetch()) {
                    $db->prepare("INSERT INTO almacen_posiciones (estanteria_id,balda,columna,etiqueta) VALUES (?,?,?,?)")
                       ->execute([$id, $b, $c, $etiqueta]);
                }
            }
        }
        echo json_encode(['ok' => true, 'id' => $id]); exit();
    }

    // ── POST mapa_asignar ─────────────────────────────────────────────────
    if ($accion === 'mapa_asignar') {
        $posId     = intval($body['posicion_id'] ?? 0);
        $tipoCaja  = $body['tipo_caja'] ?? null;
        $notas     = $body['notas'] ?? null;
        if (!$posId) { echo json_encode(['ok'=>false,'error'=>'ID posición requerido']); exit(); }
        $db->prepare("UPDATE almacen_posiciones SET tipo_caja=?, notas=? WHERE id=?")
           ->execute([$tipoCaja, $notas, $posId]);
        echo json_encode(['ok' => true]); exit();
    }

    // ── POST mapa_actualizar_ubicacion (materiales) ───────────────────────
    if ($accion === 'mapa_actualizar_ubicacion') {
        $ref       = trim($body['ref'] ?? '');
        $ubicacion = trim($body['ubicacion'] ?? '') ?: null;
        $estado    = $body['estado_stock'] ?? null;
        if (!$ref) { jsonSalida(['ok'=>false,'error'=>'Referencia requerida']); }
        $db->prepare("UPDATE materiales SET ubicacion=?, estado_stock=? WHERE REF_MAT=?")
           ->execute([$ubicacion, $estado, $ref]);
        jsonSalida(['ok' => true]);
    }

    // ── POST mapa_asignar_articulo (artículos terminados/semi → productos) ─
    if ($accion === 'mapa_asignar_articulo') {
        $ref       = trim($body['ref'] ?? '');
        $ubicacion = trim($body['ubicacion'] ?? '') ?: null;
        if (!$ref) { jsonSalida(['ok'=>false,'error'=>'Referencia requerida']); }
        try {
            $db->prepare("UPDATE productos SET UBICACION_MAP=? WHERE SKU_REF=?")
               ->execute([$ubicacion, $ref]);
            jsonSalida(['ok' => true]);
        } catch(Exception $e) {
            jsonSalida(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }
}

elseif ($metodo === 'DELETE') {
    $accion = $_GET['accion'] ?? '';
    $id = $_GET['id'] ?? null;

    if ($accion === 'del_bom' && $id) {
        // Obtener info antes de borrar para propagar
        $stmtInfo = $db->prepare("SELECT d.SKU_BASE, d.REF_MAT, a.es_variante, a.sku_base as parent_base 
                                FROM despiece_articulos d 
                                JOIN articulos a ON d.SKU_BASE = a.referencia 
                                WHERE d.id = ?");
        $stmtInfo->execute([$id]);
        $info = $stmtInfo->fetch();

        // Borrar el original
        $db->prepare("DELETE FROM despiece_articulos WHERE id = ?")->execute([$id]);

        // PROPAGACIÓN: Si era un BASE, borrar mismo material de sus variantes
        if ($info && $info['es_variante'] === 'BASE') {
            $stmtV = $db->prepare("DELETE d FROM despiece_articulos d 
                                 JOIN articulos a ON d.SKU_BASE = a.referencia 
                                 WHERE a.sku_base = ? AND d.REF_MAT = ?");
            $stmtV->execute([$info['parent_base'], $info['REF_MAT']]);
        }

        echo json_encode(['ok' => true]); exit();
    }

    // ── DELETE mapa_borrar ────────────────────────────────────────────────
    if ($accion === 'mapa_borrar' && $id) {
        $tipo = $_GET['tipo'] ?? 'estanteria';
        if ($tipo === 'posicion') {
            $db->prepare("DELETE FROM almacen_posiciones WHERE id=?")->execute([$id]);
        } else {
            // Borrar estantería y sus posiciones en cascada
            $db->prepare("DELETE FROM almacen_estanterias WHERE id=?")->execute([$id]);
        }
        echo json_encode(['ok' => true]); exit();
    }
}
?>