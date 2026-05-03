<?php
define('ALLOWED_ACCESS', true);
require_once 'config.php';
$db = conectar();

$accion = $_REQUEST['accion'] ?? '';

if ($accion === 'listar') {
    $where = ["1=1"]; $params = [];
    if (!empty($_GET['tipo'])) { $where[] = "tipo = ?"; $params[] = $_GET['tipo']; }
    if (!empty($_GET['marca'])) {
        $m = $_GET['marca'];
        if($m === 'NOXERTEZ') $where[] = "marca_noxertez = 1";
        if($m === 'CANDLEHOLDER') $where[] = "marca_candleholder = 1";
        if($m === 'ZEN') $where[] = "marca_zen = 1";
    }
    
    // FILTRO POR RED SOCIAL (Nombres exactos de tu DB)
    if (!empty($_GET['social'])) {
        $s = $_GET['social']; 
        if ($s === 'ig') $where[] = "publicado_instagram IS NOT NULL";
        if ($s === 'li') $where[] = "publicado_linkedin IS NOT NULL";
        if ($s === 'pi') $where[] = "publicado_pinterest IS NOT NULL";
    }

    if (!empty($_GET['estancia'])) { $where[] = "estancia = ?"; $params[] = $_GET['estancia']; }
    if (!empty($_GET['estilo'])) { $where[] = "estilo = ?"; $params[] = $_GET['estilo']; }
    if (!empty($_GET['decoracion'])) { $where[] = "decoracion = ?"; $params[] = $_GET['decoracion']; }
    if (!empty($_GET['vinculado'])) {
        if ($_GET['vinculado'] === 'con') {
            $where[] = "EXISTS (SELECT 1 FROM mockups_vinculaciones WHERE mockup_id = m.id)";
        } else if ($_GET['vinculado'] === 'sin') {
            $where[] = "NOT EXISTS (SELECT 1 FROM mockups_vinculaciones WHERE mockup_id = m.id)";
        }
    }
    if (!empty($_GET['buscar'])) { $where[] = "(archivo LIKE ? OR estancia LIKE ?)"; $params[] = "%".$_GET['buscar']."%"; $params[] = "%".$_GET['buscar']."%"; }

    $sql = "SELECT m.*, (SELECT GROUP_CONCAT(sku) FROM mockups_vinculaciones WHERE mockup_id = m.id) as skus 
            FROM mockups_varios m WHERE " . implode(" AND ", $where) . " ORDER BY id DESC";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($accion === 'uno') {
    $stmt = $db->prepare("SELECT * FROM mockups_varios WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

if ($accion === 'editar') {
    // Al ser datetime, si el checkbox está marcado ponemos la fecha actual, si no NULL
    $ig = !empty($_POST['publicado_instagram']) ? date('Y-m-d H:i:s') : null;
    $li = !empty($_POST['publicado_linkedin']) ? date('Y-m-d H:i:s') : null;
    $pi = !empty($_POST['publicado_pinterest']) ? date('Y-m-d H:i:s') : null;

    $sql = "UPDATE mockups_varios SET 
            estancia=?, estilo=?, luz=?, decoracion=?, formato=?, color_dominante=?, temporada=?, calidad=?, notas=?,
            marca_noxertez=?, marca_candleholder=?, marca_zen=?,
            publicado_instagram=?, publicado_linkedin=?, publicado_pinterest=?
            WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $_POST['estancia'], $_POST['estilo'], $_POST['luz'], $_POST['decoracion'], $_POST['formato'], $_POST['color_dominante'], $_POST['temporada'], $_POST['calidad'], $_POST['notas'],
        $_POST['marca_noxertez'], $_POST['marca_candleholder'], $_POST['marca_zen'],
        $ig, $li, $pi,
        $_POST['id']
    ]);
    echo json_encode(['status'=>'ok']);
}

if ($accion === 'vincular_multiple') {
    $db->prepare("UPDATE mockups_varios SET veces_usado = veces_usado + 1, ultima_vez_usado = NOW() WHERE id = ?")->execute([$_POST['id']]);
    $stmt = $db->prepare("INSERT IGNORE INTO mockups_vinculaciones (mockup_id, sku) VALUES (?, ?)");
    $stmt->execute([$_POST['id'], $_POST['sku']]);
    echo json_encode(['status'=>'ok']);
}

if ($accion === 'desvincular_multiple') {
    $stmt = $db->prepare("DELETE FROM mockups_vinculaciones WHERE mockup_id = ? AND sku = ?");
    $stmt->execute([$_POST['id'], $_POST['sku']]);
    echo json_encode(['status'=>'ok']);
}

if ($accion === 'get_filters') {
    $res = [
        'estancias' => $db->query("SELECT DISTINCT estancia FROM mockups_varios WHERE estancia != '' ORDER BY estancia")->fetchAll(PDO::FETCH_COLUMN),
        'estilos' => $db->query("SELECT DISTINCT estilo FROM mockups_varios WHERE estilo != '' ORDER BY estilo")->fetchAll(PDO::FETCH_COLUMN),
        'decoraciones' => $db->query("SELECT DISTINCT decoracion FROM mockups_varios WHERE decoracion != '' ORDER BY decoracion")->fetchAll(PDO::FETCH_COLUMN),
    ];
    echo json_encode($res);
    exit;
}

if ($accion === 'estadisticas') {
    // 1. Totales generales
    $totalMockups   = $db->query("SELECT COUNT(*) FROM mockups_varios")->fetchColumn();
    $totalImagenes  = $db->query("SELECT COUNT(*) FROM mockups_varios WHERE tipo='imagen'")->fetchColumn();
    $totalVideos    = $db->query("SELECT COUNT(*) FROM mockups_varios WHERE tipo='video'")->fetchColumn();
    $totalVinc      = $db->query("SELECT COUNT(DISTINCT sku) FROM mockups_vinculaciones")->fetchColumn();
    $totalArticulos = $db->query("SELECT COUNT(*) FROM articulos WHERE es_variante='BASE' OR referencia REGEXP '[Pp]01$'")->fetchColumn();
    $sinMockup      = (int)$totalArticulos - (int)$totalVinc;

    // 2. Ranking de artículos por número de mockups (top 30)
    $rankingStmt = $db->query("
        SELECT a.referencia, a.nombre, a.foto_portada, a.categoria,
               COUNT(DISTINCT mv.id) as total_mockups
        FROM articulos a
        LEFT JOIN mockups_vinculaciones mv_v ON mv_v.sku = a.referencia
        LEFT JOIN mockups_varios mv ON mv.id = mv_v.mockup_id
        WHERE (a.es_variante = 'BASE' OR a.referencia REGEXP '[Pp]01$')
        GROUP BY a.referencia, a.nombre, a.foto_portada, a.categoria
        ORDER BY total_mockups DESC
        LIMIT 50
    ");
    $ranking = $rankingStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Artículos SIN ningún mockup (los que más necesitan)
    $sinMockupStmt = $db->query("
        SELECT a.referencia, a.nombre, a.foto_portada, a.categoria
        FROM articulos a
        WHERE (a.es_variante = 'BASE' OR a.referencia REGEXP '[Pp]01$')
          AND a.referencia NOT IN (SELECT DISTINCT sku FROM mockups_vinculaciones)
        ORDER BY a.categoria, a.referencia
        LIMIT 40
    ");
    $articulosSinMockup = $sinMockupStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Estadísticas por categoría
    $catStmt = $db->query("
        SELECT a.categoria,
               COUNT(DISTINCT a.referencia) as total_arts,
               COUNT(DISTINCT mv_v.sku) as arts_con_mockup,
               COUNT(DISTINCT mv.id) as total_mockups
        FROM articulos a
        LEFT JOIN mockups_vinculaciones mv_v ON mv_v.sku = a.referencia
        LEFT JOIN mockups_varios mv ON mv.id = mv_v.mockup_id
        WHERE (a.es_variante = 'BASE' OR a.referencia REGEXP '[Pp]01$')
          AND a.categoria != ''
        GROUP BY a.categoria
        ORDER BY total_mockups DESC
    ");
    $porCategoria = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Distribución por calidad
    $calidadStmt = $db->query("
        SELECT calidad, COUNT(*) as total FROM mockups_varios GROUP BY calidad ORDER BY total DESC
    ");
    $porCalidad = $calidadStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Distribución por estancia (top 10)
    $estanciaStmt = $db->query("
        SELECT estancia, COUNT(*) as total FROM mockups_varios WHERE estancia != '' GROUP BY estancia ORDER BY total DESC LIMIT 10
    ");
    $porEstancia = $estanciaStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Estadísticas por Artículo BASE (Utilizando tabla productos y ES_VARIANTE = 'BASE')
    $baseStatsStmt = $db->query("
        SELECT 
            p_base.SKU_REF as base_sku,
            p_base.FOTO_PORTADA as foto,
            COUNT(DISTINCT p_all.SKU_REF) as total_variantes,
            COUNT(DISTINCT mv.mockup_id) as total_mockups,
            IF(COUNT(DISTINCT mv.mockup_id) > 0, 1, 0) as tiene_mockup
        FROM productos p_base
        LEFT JOIN productos p_all ON p_all.SKU_BASE = p_base.SKU_BASE
        LEFT JOIN mockups_vinculaciones mv ON mv.sku = p_all.SKU_REF
        WHERE (p_base.ES_VARIANTE = 'BASE' OR p_base.SKU_REF REGEXP '[Pp]01$')
        GROUP BY p_base.SKU_REF
    ");
    $rawBase = $baseStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalBase = count($rawBase);
    $conMockupBase = 0;
    foreach($rawBase as $rb) if($rb['tiene_mockup']) $conMockupBase++;

    echo json_encode([
        'totales'            => [
            'mockups'       => (int)$totalMockups,
            'imagenes'      => (int)$totalImagenes,
            'videos'        => (int)$totalVideos,
            'articulos'     => (int)$totalArticulos,
            'con_mockup'    => (int)$totalVinc,
            'sin_mockup'    => max(0, $sinMockup),
            'total_base'    => $totalBase,
            'con_mockup_base' => $conMockupBase
        ],
        'ranking'            => $ranking,
        'sin_mockup'         => $articulosSinMockup,
        'por_categoria'      => $porCategoria,
        'por_calidad'        => $porCalidad,
        'por_estancia'       => $porEstancia,
        'articulos_base'     => array_slice($rawBase, 0, 50) // Limitamos para el ranking
    ]);
    exit;
}

if ($accion === 'delete_batch') {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) die("No hay IDs seleccionados.");
    
    foreach ($ids as $id) {
        $stmt = $db->prepare("SELECT ruta FROM mockups_varios WHERE id = ?");
        $stmt->execute([$id]);
        $ruta = $stmt->fetchColumn();
        if ($ruta && file_exists('../' . $ruta)) unlink('../' . $ruta);
        
        $db->prepare("DELETE FROM mockups_varios WHERE id = ?")->execute([$id]);
        $db->prepare("DELETE FROM mockups_vinculaciones WHERE mockup_id = ?")->execute([$id]);
    }
    echo json_encode(['status'=>'ok']);
    exit;
}

if ($accion === 'download_zip') {
    $files = $_POST['files'] ?? [];
    if (empty($files)) die("No hay archivos seleccionados.");

    $zip = new ZipArchive();
    $zipName = 'noxertez_batch_' . date('Ymd_His') . '.zip';
    $tmpFile = tempnam(sys_get_temp_dir(), 'zip');
    
    if ($zip->open($tmpFile, ZipArchive::CREATE) !== TRUE) {
        die("Error al crear el archivo ZIP.");
    }

    foreach ($files as $f) {
        $path = '../' . ltrim($f, '/');
        if (file_exists($path)) {
            $zip->addFile($path, basename($f));
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

if ($accion === 'eliminar') {
    $stmt = $db->prepare("SELECT ruta FROM mockups_varios WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $ruta = $stmt->fetchColumn();
    if ($ruta && file_exists('../' . $ruta)) unlink('../' . $ruta);
    $db->prepare("DELETE FROM mockups_varios WHERE id = ?")->execute([$_POST['id']]);
    $db->prepare("DELETE FROM mockups_vinculaciones WHERE mockup_id = ?")->execute([$_POST['id']]);
    echo json_encode(['status'=>'ok']);
}
