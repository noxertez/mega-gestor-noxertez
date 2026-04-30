<?php
require_once 'config.php';
$db = conectar();
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// LISTAR todos los mockups con filtros opcionales
if ($accion === 'listar') {
    $where = ["1=1"]; $params = [];
    if (!empty($_GET['estancia'])) { $where[] = "estancia = ?"; $params[] = $_GET['estancia']; }
    if (!empty($_GET['estilo']))   { $where[] = "estilo = ?";   $params[] = $_GET['estilo'];   }
    if (!empty($_GET['calidad']))  { $where[] = "calidad = ?";  $params[] = $_GET['calidad'];  }
    if (!empty($_GET['tipo']))     { $where[] = "tipo = ?";     $params[] = $_GET['tipo'];     }
    if (!empty($_GET['decoracion'])){ $where[] = "decoracion = ?"; $params[] = $_GET['decoracion']; }
    if (!empty($_GET['marca'])) {
        $m = strtoupper($_GET['marca']);
        if ($m === 'NOXERTEZ')     $where[] = "marca_noxertez = 1";
        elseif ($m === 'CANDLEHOLDER') $where[] = "marca_candleholder = 1";
        elseif ($m === 'ZEN')      $where[] = "marca_zen = 1";
    }
    if (!empty($_GET['buscar'])) {
        $where[] = "(archivo LIKE ? OR estancia LIKE ? OR estilo LIKE ? OR notas LIKE ?)";
        $b = "%" . $_GET['buscar'] . "%";
        $params = array_merge($params, [$b,$b,$b,$b]);
    }
    $sql = "SELECT * FROM mockups_varios WHERE " . implode(" AND ", $where) . " ORDER BY id DESC";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    jsonSalida($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// OBTENER UNO
if ($accion === 'uno') {
    $stmt = $db->prepare("SELECT * FROM mockups_varios WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    jsonSalida($stmt->fetch(PDO::FETCH_ASSOC));
}

// EDITAR
if ($accion === 'editar') {
    $sql = "UPDATE mockups_varios SET estancia=?, estilo=?, luz=?, decoracion=?, formato=?, color_dominante=?, temporada=?, calidad=?, notas=?, favorito=?, marca_noxertez=?, marca_candleholder=?, marca_zen=? WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $_POST['estancia']??'', $_POST['estilo']??'', $_POST['luz']??'',
        $_POST['decoracion']??'', $_POST['formato']??'', $_POST['color_dominante']??'',
        $_POST['temporada']??'', $_POST['calidad']??'publicar', $_POST['notas']??'',
        $_POST['favorito']??0, $_POST['marca_noxertez']??0,
        $_POST['marca_candleholder']??0, $_POST['marca_zen']??0, $_POST['id']
    ]);
    jsonSalida(['ok' => true]);
}

// ELIMINAR
if ($accion === 'eliminar') {
    $stmt = $db->prepare("SELECT ruta FROM mockups_varios WHERE id = ?");
    $stmt->execute([$_POST['id']]); $ruta = $stmt->fetchColumn();
    if ($ruta) { $f = __DIR__ . '/../' . $ruta; if (file_exists($f)) unlink($f); }
    $db->prepare("DELETE FROM mockups_varios WHERE id = ?")->execute([$_POST['id']]);
    jsonSalida(['ok' => true]);
}

// VINCULAR a SKU
if ($accion === 'vincular') {
    $stmt = $db->prepare("UPDATE mockups_varios SET asignado_a_sku = ? WHERE id = ?");
    $stmt->execute([$_POST['sku'], $_POST['id']]);
    $stmt2 = $db->prepare("UPDATE articulos SET mockup = (SELECT ruta FROM mockups_varios WHERE id = ?) WHERE referencia = ?");
    $stmt2->execute([$_POST['id'], $_POST['sku']]);
    jsonSalida(['ok' => true]);
}

// SINCRONIZAR catálogo (dos fuentes: por artículo y banco general)
if ($accion === 'sync_catalog') {
    $count = 0;

    // Parsear metadatos del nombre: {estancia}_{luz}_{formato}_{decoracion}_mockup-N.ext
    function parseMeta($file) {
        $base = pathinfo($file, PATHINFO_FILENAME);
        $base = preg_replace('/_mockup[-_]\d+$/i', '', $base);
        $p = explode('_', $base);
        $est = isset($p[0]) ? str_replace('-', ' ', $p[0]) : '';
        
        // Limpieza de nombres largos
        if (stripos($est, 'cafet') !== false || stripos($est, 'acogedora') !== false) $est = 'Cafetería realista';
        if (stripos($est, 'boutique') !== false) $est = 'Boutique artesanía';

        return [
            'estancia'   => substr(ucfirst($est), 0, 100),
            'luz'        => isset($p[1]) ? substr(ucfirst(str_replace('-',' ',$p[1])), 0, 100) : '',
            'formato'    => isset($p[2]) ? substr(ucfirst(str_replace('-',' ',$p[2])), 0, 100) : '',
            'decoracion' => isset($p[3]) ? substr(ucfirst(str_replace('-',' ',$p[3])), 0, 100) : '',
        ];
    }


    // --- 1. Mockups por artículo (uploads/articulos/imagenes/NOXERTEZ/{SKU}/) ---
    $artDir = __DIR__ . '/../uploads/articulos/imagenes/NOXERTEZ';
    if (is_dir($artDir)) {
        foreach (scandir($artDir) as $sku) {
            if ($sku === '.' || $sku === '..') continue;
            $skuDir = $artDir . '/' . $sku;
            if (!is_dir($skuDir)) continue;
            foreach (scandir($skuDir) as $file) {
                if ($file === '.' || $file === '..') continue;
                if (stripos($file, '_mockup_') === false && stripos($file, '_mockup-') === false) continue;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','mp4','mov'])) continue;
                $rutaRel = 'uploads/articulos/imagenes/NOXERTEZ/' . $sku . '/' . $file;
                $check = $db->prepare("SELECT id FROM mockups_varios WHERE ruta = ?");
                $check->execute([$rutaRel]);
                if ($check->fetch()) continue;
                $tipo = in_array($ext, ['mp4','mov']) ? 'video' : 'imagen';
                $ins = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, asignado_a_sku, marca_noxertez, estancia, estilo, decoracion, calidad) VALUES (?,?,?,?,1,'Catálogo','Estándar','Estándar','publicar')");
                $ins->execute([$file, $rutaRel, $tipo, $sku]);
                $count++;
            }
        }
    }

    // --- 2. Banco General (uploads/mockups_varios/ con subcarpetas por marca) ---
    $genBase = __DIR__ . '/../uploads/mockups_varios';
    function syncGenDir($dir, $db, &$count, $marca = '') {
        if (!is_dir($dir)) return;
        $rootNoxertez = realpath(__DIR__ . '/..');
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) { syncGenDir($path, $db, $count, strtoupper($item)); continue; }
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','mp4','mov'])) continue;
            $rutaRel = ltrim(str_replace('\\', '/', str_replace($rootNoxertez, '', realpath($path))), '/');
            $check = $db->prepare("SELECT id FROM mockups_varios WHERE ruta = ?");
            $check->execute([$rutaRel]);
            if ($check->fetch()) continue;
            $meta  = parseMeta($item);
            $tipo  = in_array($ext, ['mp4','mov']) ? 'video' : 'imagen';
            $mNox  = ($marca === 'NOXERTEZ') ? 1 : 0;
            $mCand = in_array($marca, ['CANDLEHOLDER','CANDLE']) ? 1 : 0;
            $mZen  = ($marca === 'ZEN') ? 1 : 0;
            $ins = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, asignado_a_sku, marca_noxertez, marca_candleholder, marca_zen, estancia, luz, formato, decoracion, calidad) VALUES (?,?,?,NULL,?,?,?,?,?,?,?,'publicar')");
            $ins->execute([$item, $rutaRel, $tipo, $mNox, $mCand, $mZen, $meta['estancia'], $meta['luz'], $meta['formato'], $meta['decoracion']]);
            $count++;
        }
    }
    syncGenDir($genBase, $db, $count, '');

    jsonSalida(['mensaje' => "Sincronización completada. $count nuevos mockups encontrados."]);
}

// GET FILTERS
if ($accion === 'get_filters') {
    $estancias   = $db->query("SELECT DISTINCT estancia   FROM mockups_varios WHERE estancia   != '' ORDER BY estancia")->fetchAll(PDO::FETCH_COLUMN);
    $estilos     = $db->query("SELECT DISTINCT estilo     FROM mockups_varios WHERE estilo     != '' ORDER BY estilo")->fetchAll(PDO::FETCH_COLUMN);
    $decoraciones= $db->query("SELECT DISTINCT decoracion FROM mockups_varios WHERE decoracion != '' ORDER BY decoracion")->fetchAll(PDO::FETCH_COLUMN);
    jsonSalida(['estancias'=>$estancias,'estilos'=>$estilos,'decoraciones'=>$decoraciones]);
}
