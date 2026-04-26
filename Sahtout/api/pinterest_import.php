<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once 'config.php';
header('Content-Type: application/json');
$db = conectar();

// Leer JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$solo_base    = isset($input['solo_base'])    ? (bool)$input['solo_base']    : true;
$con_mockup   = isset($input['con_mockup'])   ? (bool)$input['con_mockup']   : false;
$filtro_cat   = isset($input['categoria'])    ? trim($input['categoria'])     : '';
$filtro_estado= isset($input['estado'])       ? trim($input['estado'])        : '';

function resolverRutaPublica($foto) {
    if (!$foto) return '';
    $clean = str_replace('\\', '/', $foto);
    $idx = strpos(strtolower($clean), '/imagenes/');
    if ($idx !== false) {
        // Construir URL pública con espacios codificados correctamente
        $path = substr($clean, $idx);
        $parts = explode('/', $path);
        $encoded = array_map('rawurlencode', $parts);
        return 'https://noxertez.com' . str_replace('%2F', '/', implode('/', $encoded));
    }
    return '';
}

try {
    // Leer configuración
    $stmtCfg = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('pinterest_pins_por_dia')");
    $config = [];
    foreach ($stmtCfg->fetchAll() as $row) $config[$row['clave']] = $row['valor'];
    $pins_por_dia = (int)($config['pinterest_pins_por_dia'] ?? 10);
    if ($pins_por_dia < 1) $pins_por_dia = 10;

    // Leer tableros configurados
    $tableros = [];
    $stmtT = $db->query("SELECT categoria, board_id FROM pinterest_tableros WHERE activo=1 AND board_id IS NOT NULL AND board_id != ''");
    foreach ($stmtT->fetchAll() as $t) $tableros[strtolower($t['categoria'])] = $t['board_id'];

    // Última fecha programada en cola
    $ultimaFecha = $db->query("SELECT MAX(fecha_programada) as uf FROM pinterest_queue WHERE estado='pendiente'")->fetchColumn();
    if (!$ultimaFecha) $ultimaFecha = date('Y-m-d');

    // Cuántos pins hay ese día
    $contDia = [];
    $rowsDia = $db->query("SELECT fecha_programada, COUNT(*) as c FROM pinterest_queue WHERE estado='pendiente' GROUP BY fecha_programada")->fetchAll();
    foreach ($rowsDia as $r) $contDia[$r['fecha_programada']] = (int)$r['c'];

    // Función para calcular siguiente fecha disponible
    function siguienteFecha(&$contDia, $pins_por_dia, $desde) {
        $fecha = $desde;
        while (true) {
            $c = $contDia[$fecha] ?? 0;
            if ($c < $pins_por_dia) {
                $contDia[$fecha] = $c + 1;
                return $fecha;
            }
            $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'));
        }
    }

    // SKUs ya en cola
    $yaEnCola = [];
    $stmtYa = $db->query("SELECT sku_ref FROM pinterest_queue WHERE estado != 'error'");
    foreach ($stmtYa->fetchAll() as $r) $yaEnCola[] = $r['sku_ref'];

    // Construir query de productos
    $where = ["p.FOTO_PORTADA IS NOT NULL", "p.FOTO_PORTADA != ''"];
    $params = [];
    if ($solo_base) { $where[] = "(p.ES_VARIANTE IS NULL OR p.ES_VARIANTE != 'SI')"; }
    if ($filtro_cat) { $where[] = "p.CATEGORIA = ?"; $params[] = $filtro_cat; }
    if ($filtro_estado) { $where[] = "p.ESTADO = ?"; $params[] = $filtro_estado; }

    $sql = "SELECT p.SKU_REF, p.NOMBRE, p.DESCRIPCION, p.CATEGORIA, p.COLOR, p.PRECIO, p.FOTO_PORTADA, p.MOCKUP
            FROM productos p WHERE " . implode(' AND ', $where) . " ORDER BY p.SKU_REF";
    $stmtP = $db->prepare($sql);
    $stmtP->execute($params);
    $productos = $stmtP->fetchAll();

    $importados = 0;
    $omitidos   = 0;
    $sin_foto   = 0;
    $errores    = [];

    $stmtIns = $db->prepare("INSERT INTO pinterest_queue 
        (sku_ref, imagen_url, titulo, descripcion, tablero_categoria, board_id_pinterest, enlace, estado, fecha_programada, tipo_contenido)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)");

    foreach ($productos as $p) {
        // Verificar si ya está en cola
        if (in_array($p['SKU_REF'], $yaEnCola)) { $omitidos++; continue; }

        // Resolver URL imagen
        $url = resolverRutaPublica($p['FOTO_PORTADA']);
        if (!$url) { $sin_foto++; continue; }

        // Datos del pin
        $titulo = mb_substr($p['NOMBRE'] ?? $p['SKU_REF'], 0, 100);
        $desc_base = ($p['DESCRIPCION'] ?? '');
        $extras = "\n\nColor: " . ($p['COLOR'] ?? '') . "\nPrecio: " . ($p['PRECIO'] ?? '') . "€\n\n#noxertez #artesania #madera #decoracion #" . strtolower($p['CATEGORIA'] ?? 'decoracion');
        $descripcion = mb_substr($desc_base . $extras, 0, 800);
        $enlace = "https://noxertez.com/pages/producto.php?ref=" . urlencode($p['SKU_REF']);
        $cat_key = strtolower($p['CATEGORIA'] ?? '');
        $board_id = $tableros[$cat_key] ?? null;
        $fecha = siguienteFecha($contDia, $pins_por_dia, $ultimaFecha);

        try {
            $stmtIns->execute([$p['SKU_REF'], $url, $titulo, $descripcion, $p['CATEGORIA'], $board_id, $enlace, $fecha, 'producto']);
            $importados++;
            $yaEnCola[] = $p['SKU_REF'];
        } catch (Exception $e) {
            $errores[] = $p['SKU_REF'] . ': ' . $e->getMessage();
        }

        // Mockup adicional
        if ($con_mockup && !empty($p['MOCKUP'])) {
            $urlMock = resolverRutaPublica($p['MOCKUP']);
            if ($urlMock) {
                $skuMock = $p['SKU_REF'] . '_MOCK';
                if (!in_array($skuMock, $yaEnCola)) {
                    $fechaMock = siguienteFecha($contDia, $pins_por_dia, $ultimaFecha);
                    try {
                        $stmtIns->execute([$p['SKU_REF'], $urlMock, $titulo . ' (Mockup)', $descripcion, $p['CATEGORIA'], $board_id, $enlace, $fechaMock, 'mockup']);
                        $importados++;
                        $yaEnCola[] = $skuMock;
                    } catch (Exception $e) {
                        $errores[] = $skuMock . ': ' . $e->getMessage();
                    }
                }
            }
        }
    }

    jsonSalida(['ok' => true, 'importados' => $importados, 'omitidos' => $omitidos, 'sin_foto' => $sin_foto, 'errores' => $errores]);

} catch (Exception $e) {
    jsonSalida(['ok' => false, 'error' => $e->getMessage()]);
}
?>
