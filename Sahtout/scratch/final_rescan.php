<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

echo "Vaciando tabla...\n";
$db->exec("DELETE FROM mockups_varios");
$count = 0;

// Función para parsear metadatos del nombre de archivo del banco general
// Formato: {estancia}_{luz}_{formato}_{decoracion}_mockup-{n}.{ext}
function parsearNombre($file) {
    $base = pathinfo($file, PATHINFO_FILENAME);
    // Quitar sufijo _mockup-N o _mockup_N
    $base = preg_replace('/_mockup[-_]\d+$/i', '', $base);
    $partes = explode('_', $base);
    return [
        'estancia'   => isset($partes[0]) ? ucfirst(str_replace('-', ' ', $partes[0])) : '',
        'luz'        => isset($partes[1]) ? ucfirst(str_replace('-', ' ', $partes[1])) : '',
        'formato'    => isset($partes[2]) ? ucfirst(str_replace('-', ' ', $partes[2])) : '',
        'decoracion' => isset($partes[3]) ? ucfirst(str_replace('-', ' ', $partes[3])) : '',
    ];
}

// 1. Mockups POR ARTÍCULO: uploads/articulos/imagenes/NOXERTEZ/{SKU}/
$artDir = 'C:/xampp/htdocs/noxertez/uploads/articulos/imagenes/NOXERTEZ';
echo "\n--- Artículos ($artDir) ---\n";
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
            $tipo = in_array($ext, ['mp4','mov']) ? 'video' : 'imagen';
            $ins = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, asignado_a_sku, marca_noxertez, estancia, estilo, decoracion, calidad) VALUES (?,?,?,?,1,'Catálogo','Estándar','Estándar','publicar')");
            $ins->execute([$file, $rutaRel, $tipo, $sku]);
            $count++;
            echo "  [ART] $file -> $sku\n";
        }
    }
}

// 2. Banco General: uploads/mockups_varios/ (con subcarpetas por marca)
$genBase = 'C:/xampp/htdocs/noxertez/uploads/mockups_varios';
echo "\n--- Banco General ($genBase) ---\n";

function escanearGeneral($dir, $db, &$count, $marca = '') {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            // El nombre de la carpeta = la marca
            escanearGeneral($path, $db, $count, strtoupper($item));
            continue;
        }
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','mp4','mov'])) continue;

        // Ruta relativa desde raíz del servidor
        $rutaRel = str_replace('C:/xampp/htdocs/noxertez/', '', $path);

        $meta = parsearNombre($item);
        $tipo = in_array($ext, ['mp4','mov']) ? 'video' : 'imagen';

        $marcaNox  = ($marca === 'NOXERTEZ') ? 1 : 0;
        $marcaCand = ($marca === 'CANDLEHOLDER' || $marca === 'CANDLE') ? 1 : 0;
        $marcaZen  = ($marca === 'ZEN') ? 1 : 0;

        $ins = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, asignado_a_sku, marca_noxertez, marca_candleholder, marca_zen, estancia, luz, formato, decoracion, calidad) VALUES (?,?,?,NULL,?,?,?,?,?,?,?,'publicar')");
        $ins->execute([$item, $rutaRel, $tipo, $marcaNox, $marcaCand, $marcaZen, $meta['estancia'], $meta['luz'], $meta['formato'], $meta['decoracion']]);
        $count++;
    }
}

escanearGeneral($genBase, $db, $count, '');
echo "  Banco general: " . $count . " total\n";

echo "\nTOTAL FINAL: $count mockups en la base de datos.\n";

// Mostrar muestra de decoraciones detectadas
$decos = $db->query("SELECT DISTINCT decoracion FROM mockups_varios WHERE decoracion != '' LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
echo "Decoraciones detectadas: " . implode(', ', $decos) . "\n";
?>
