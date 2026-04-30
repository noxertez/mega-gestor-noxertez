<?php
require_once 'api/config.php';
$db = conectar();

echo "Iniciando rescate de mockups...\n";

// 1. Escanear carpeta de articulos buscando archivos que contengan '_mockup_'
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'articulos';
$count = 0;

function rescatarRecursivo($dir, $db, &$count) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            rescatarRecursivo($path, $db, $count);
        } else {
            if (stripos($file, '_mockup_') !== false) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'])) {
                    // Ruta relativa
                    $cleanPath = str_replace('\\', '/', $path);
                    $pos = stripos($cleanPath, '/uploads/');
                    $rutaRel = ($pos !== false) ? substr($cleanPath, $pos + 1) : $cleanPath;
                    
                    // Detectar SKU (nombre de la carpeta padre)
                    $sku = basename(dirname($path));
                    
                    // Detectar Marca
                    $marca = '';
                    if (stripos($path, 'noxertez') !== false) $marca = 'NOXERTEZ';
                    elseif (stripos($path, 'candle') !== false) $marca = 'CANDLEHOLDER';
                    elseif (stripos($path, 'zen') !== false) $marca = 'ZEN';

                    // Insertar si no existe
                    $check = $db->prepare("SELECT id FROM mockups_varios WHERE ruta = ?");
                    $check->execute([$rutaRel]);
                    if (!$check->fetch()) {
                        $tipo = in_array($ext, ['mp4', 'mov']) ? 'video' : 'imagen';
                        $ins = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, estancia, estilo, decoracion, calidad, marca_noxertez, marca_candleholder, marca_zen, asignado_a_sku) VALUES (?, ?, ?, 'Catálogo', 'Estándar', 'Estándar', 'publicar', ?, ?, ?, ?)");
                        $ins->execute([
                            $file, $rutaRel, $tipo, 
                            ($marca == 'NOXERTEZ' ? 1 : 0),
                            ($marca == 'CANDLEHOLDER' ? 1 : 0),
                            ($marca == 'ZEN' ? 1 : 0),
                            $sku
                        ]);
                        $count++;
                        echo "Rescatado: $file vinculado a $sku\n";
                    }
                }
            }
        }
    }
}

rescatarRecursivo($baseDir, $db, $count);
echo "\nTotal rescatados: $count";
?>
