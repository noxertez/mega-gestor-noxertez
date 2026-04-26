<?php
/**
 * api/articulos.php
 * Gestión maestra de artículos con soporte extendido de multimedia y sincronización total.
 */
require_once 'config.php';
$db = conectar();

// Asegurar que columnas extendidas existen
try { $db->query("ALTER TABLE articulos ADD COLUMN stock_minimo INT DEFAULT 0"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE articulos ADD COLUMN galeria TEXT NULL"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE articulos ADD COLUMN mockup VARCHAR(255) NULL"); } catch(Exception $e) {}

if ($metodo === 'GET') {
    $accion = $_GET['accion'] ?? '';
    
    if ($accion === 'categorias') {
        $cats = $db->query("SELECT DISTINCT categoria FROM articulos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($cats);
        exit;
    }

    $ref       = $_GET['referencia'] ?? $_GET['ref'] ?? null;
    $cat       = $_GET['categoria']  ?? null;
    $solo_base = isset($_GET['solo_base']) && ($_GET['solo_base'] === 'true' || $_GET['solo_base'] == 1);

    $sql_base = "
        SELECT a.*, 
               CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) as stock_final,
               p.STOCK_FISICO as stock_semi,
               p.GALERIA as p_galeria,
               p.MOCKUP as p_mockup
        FROM articulos a 
        LEFT JOIN productos p ON a.referencia = p.SKU_REF 
        WHERE a.activo = 1
    ";
    $where = []; $params = [];

    if ($solo_base) $where[] = "a.es_variante = 'BASE'";
    if ($ref) { $where[] = "a.referencia = ?"; $params[] = $ref; }
    if ($cat) { $where[] = "a.categoria = ?"; $params[] = $cat; }

    if (!empty($where)) $sql_base .= " AND " . implode(" AND ", $where);
    $sql_base .= " ORDER BY a.categoria ASC, a.nombre ASC";
    
    $stmt = $db->prepare($sql_base);
    $stmt->execute($params);
    $res = ($ref) ? $stmt->fetch() : $stmt->fetchAll();

    if ($res) {
        if ($ref) {
            $res['galeria'] = $res['galeria'] ?: $res['p_galeria'];
            $res['mockup'] = $res['mockup'] ?: $res['p_mockup'];
            $res['stock'] = $res['stock_final'];
            
            // Si es base, sumar stock de variantes
            if ($res['es_variante'] === 'BASE') {
                $stmtV = $db->prepare("
                    SELECT a.referencia, a.nombre, a.color, a.foto_portada, a.mockup,
                           CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) as v_stock_final,
                           p.STOCK_FISICO as v_stock_semi
                    FROM articulos a
                    LEFT JOIN productos p ON a.referencia = p.SKU_REF
                    WHERE a.sku_base = ? AND a.es_variante = 'VARIANTE' AND a.activo = 1
                ");
                $stmtV->execute([$res['sku_base']]);
                $variantes = $stmtV->fetchAll();
                foreach($variantes as $v) {
                    $res['stock_final'] += $v['v_stock_final'];
                    $res['stock_semi'] += $v['v_stock_semi'];
                }
                $res['stock'] = $res['stock_final'];
                $res['variantes'] = $variantes;
            }
        } else {
            foreach ($res as &$r) {
                $r['galeria'] = $r['galeria'] ?: $r['p_galeria'];
                $r['mockup'] = $r['mockup'] ?: $r['p_mockup'];
                
                // Si es base, traer resumen de variantes y SUMAR stock
                if ($r['es_variante'] === 'BASE') {
                    $stmtV = $db->prepare("
                        SELECT a.referencia, a.nombre, a.color, a.foto_portada, a.mockup,
                               CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) as v_stock_final,
                               p.STOCK_FISICO as v_stock_semi
                        FROM articulos a
                        LEFT JOIN productos p ON a.referencia = p.SKU_REF
                        WHERE a.sku_base = ? AND a.es_variante = 'VARIANTE' AND a.activo = 1
                    ");
                    $stmtV->execute([$r['sku_base']]);
                    $variantes = $stmtV->fetchAll();
                    foreach($variantes as $v) {
                        $r['stock_final'] += $v['v_stock_final'];
                        $r['stock_semi'] += $v['v_stock_semi'];
                    }
                    $r['variantes'] = $variantes;
                } else {
                    $r['variantes'] = [];
                }
                $r['stock'] = $r['stock_final'];
            }
        }
    }
    echo json_encode($res ?: []);
}

elseif ($metodo === 'POST') {
    $data = !empty($_POST) ? $_POST : $body;
    $ref = trim($data['referencia'] ?? '');
    if (!$ref) { echo json_encode(['error' => 'Referencia obligatoria']); exit; }

    $marca = strtoupper(trim($data['marca'] ?? 'NOXERTEZ'));
    $sku_base = trim($data['sku_base'] ?? explode('-', $ref)[0]);
    $mapping = ["CANDLE HOLDER OF THE SOUL" => "CANDLEHOLDER", "THE SECRET ZEN GARDEN" => "THE_SECRET_ZEN_GARDEN", "NOXERTEZ" => "NOXERTEZ"];
    $marca_folder = $mapping[$marca] ?? str_replace(' ', '_', $marca);
    $sku_folder = str_replace(['/', '\\'], '_', preg_replace('/P\d+$/', '', $sku_base));
    $target_dir = "../uploads/articulos/" . $marca_folder . "/" . $sku_folder . "/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    // --- PORTADA ---
    $foto_portada = $data['foto_portada'] ?? '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $target_file = $target_dir . str_replace(['/', '\\'], '_', $ref) . "_1." . $ext;
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $target_file)) {
            $foto_portada = str_replace('../', '', $target_file);
        }
    }

    // --- MOCKUP ---
    $mockup_path = $data['mockup_path'] ?? '';
    if (isset($_FILES['mockup_file']) && $_FILES['mockup_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['mockup_file']['name'], PATHINFO_EXTENSION);
        $target_file = $target_dir . str_replace(['/', '\\'], '_', $ref) . "_mockup_1." . $ext;
        if (move_uploaded_file($_FILES['mockup_file']['tmp_name'], $target_file)) {
            $mockup_path = str_replace('../', '', $target_file);
        }
    }

    // --- GALERÍA (Múltiple) ---
    $galeria_paths = [];
    if (!empty($data['galeria_actual'])) {
        $galeria_paths = explode(', ', $data['galeria_actual']);
    }
    
    if (isset($_FILES['galeria_files'])) {
        $files = $_FILES['galeria_files'];
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $num = count($galeria_paths) + 1;
                $target_file = $target_dir . str_replace(['/', '\\'], '_', $ref) . "_gal_" . $num . "." . $ext;
                if (move_uploaded_file($files['tmp_name'][$key], $target_file)) {
                    $galeria_paths[] = str_replace('../', '', $target_file);
                }
            }
        }
    }
    $galeria_str = implode(', ', $galeria_paths);

    // --- GUARDAR ARTICULOS ---
    $stmt = $db->prepare("
        INSERT INTO articulos (referencia, sku_base, nombre, descripcion, precio, stock, stock_minimo, categoria, es_variante, entrega_inmediata, marca, foto_portada, galeria, mockup)
        VALUES (:ref, :sku_base, :nombre, :desc, :precio, :stock, :min, :cat, :es_variante, :inmediata, :marca, :foto, :gal, :mock)
        ON DUPLICATE KEY UPDATE
        sku_base=:sku_base, nombre=:nombre, descripcion=:desc, precio=:precio, stock=:stock, stock_minimo=:min, categoria=:cat, 
        es_variante=:es_variante, entrega_inmediata=:inmediata, marca=:marca, foto_portada=COALESCE(NULLIF(:foto, ''), foto_portada),
        galeria=:gal, mockup=COALESCE(NULLIF(:mock, ''), mockup)"
    );
    
    $params = [
        'ref'         => $ref, 'sku_base'    => $sku_base, 'nombre'      => $data['nombre'] ?? '',
        'desc'        => $data['descripcion'] ?? '', 'precio'      => $data['precio'] ?? 0, 'stock'       => $data['stock'] ?? 0,
        'min'         => $data['stock_minimo'] ?? 0, 'cat'         => $data['categoria'] ?? '',
        'es_variante' => $data['es_variante'] ?? 'BASE', 'inmediata'   => $data['entrega_inmediata'] ?? 0,
        'marca'       => $marca, 'foto'        => $foto_portada, 'gal'         => $galeria_str, 'mock'        => $mockup_path
    ];
    $stmt->execute($params);

    // --- SINCRONIZAR PRODUCTOS ---
    $stmtP = $db->prepare("
        INSERT INTO productos (SKU_REF, SKU_BASE, NOMBRE, PRECIO, STOCK, ES_VARIANTE, FOTO_PORTADA, GALERIA, MOCKUP, MARCA)
        VALUES (:ref, :sku_base, :nombre, :precio, :stock, :es_variante, :foto, :gal, :mock, :marca)
        ON DUPLICATE KEY UPDATE
        SKU_BASE=:sku_base, NOMBRE=:nombre, PRECIO=:precio, STOCK=:stock, ES_VARIANTE=:es_variante, 
        FOTO_PORTADA=COALESCE(NULLIF(:foto, ''), FOTO_PORTADA), GALERIA=:gal, MOCKUP=COALESCE(NULLIF(:mock, ''), MOCKUP), MARCA=:marca"
    );
    $stmtP->execute([
        'ref'         => $ref, 'sku_base'    => $sku_base, 'nombre'      => $params['nombre'],
        'precio'      => $params['precio'], 'stock'       => $params['stock'], 'es_variante' => $params['es_variante'],
        'foto'        => $foto_portada, 'gal'         => $galeria_str, 'mock'        => $mockup_path, 'marca'       => $marca
    ]);

    echo json_encode(['ok' => true]);
}
?>