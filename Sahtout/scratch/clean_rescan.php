<?php
require_once 'api/config.php';
$db = conectar();

echo "LIMPIEZA TOTAL INICIADA...\n";
$db->exec("DELETE FROM mockups_varios");
echo "Tabla vaciada.\n";

$baseDir = realpath(__DIR__ . '/uploads/articulos');
$count = 0;

function escaneoLimpio($dir, $db, &$count) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            escaneoLimpio($path, $db, $count);
        } else {
            if (stripos($file, '_mockup_') !== false) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'])) {
                    
                    $rutaRel = str_replace(realpath(__DIR__), '', realpath($path));
                    $rutaRel = str_replace('\\', '/', $rutaRel);
                    if (strpos($rutaRel, '/') === 0) $rutaRel = substr($rutaRel, 1);

                    // Buscamos el SKU en la carpeta padre
                    $sku = basename(dirname($path));
                    
                    // Si el nombre de la carpeta es 'imagenes' o similar, subimos un nivel
                    if (in_array(strtoupper($sku), ['IMAGENES', 'VIDEOS', 'NOXERTEZ', 'CANDLEHOLDER', 'ZEN'])) {
                         $sku = basename(dirname(dirname($path)));
                    }

                    $stmt = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, asignado_a_sku, estancia, estilo, decoracion, calidad) VALUES (?, ?, ?, ?, 'Catálogo', 'Estándar', 'Estándar', 'publicar')");
                    $stmt->execute([$file, $rutaRel, (in_array($ext, ['mp4','mov']) ? 'video' : 'imagen'), $sku]);
                    $count++;
                    echo "[$count] Detectado: $file -> SKU: $sku\n";
                }
            }
        }
    }
}

escaneoLimpio($baseDir, $db, $count);
echo "\nÉXITO: Se han recuperado $count mockups y se han vinculado a sus artículos.";
?>
