<?php
require_once 'api/config.php';
$db = conectar();

echo "Iniciando ESCANEO DE RESCATE total...\n";

// 1. Opcional: Limpiar tabla para evitar duplicados con rutas viejas
// $db->exec("DELETE FROM mockups_varios"); 

$baseDir = realpath(__DIR__ . '/uploads/articulos');
$count = 0;

function rescateTotal($dir, $db, &$count) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            rescateTotal($path, $db, $count);
        } else {
            // Buscamos cualquier archivo que sea un mockup
            if (stripos($file, '_mockup_') !== false) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'])) {
                    
                    // Calcular ruta relativa limpia
                    $rutaRel = str_replace(realpath(__DIR__), '', realpath($path));
                    $rutaRel = str_replace('\\', '/', $rutaRel);
                    if (strpos($rutaRel, '/') === 0) $rutaRel = substr($rutaRel, 1);

                    // Detectar SKU (es el nombre de la carpeta que lo contiene)
                    $sku = basename(dirname($path));

                    // Insertar o actualizar
                    $check = $db->prepare("SELECT id FROM mockups_varios WHERE ruta = ?");
                    $check->execute([$rutaRel]);
                    if (!$check->fetch()) {
                        $tipo = in_array($ext, ['mp4', 'mov']) ? 'video' : 'imagen';
                        $stmt = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, asignado_a_sku, estancia, estilo, decoracion, calidad) VALUES (?, ?, ?, ?, 'Catálogo', 'Estándar', 'Estándar', 'publicar')");
                        $stmt->execute([$file, $rutaRel, $tipo, $sku]);
                        $count++;
                        echo "Registrado: $file vinculado a $sku\n";
                    }
                }
            }
        }
    }
}

rescateTotal($baseDir, $db, $count);
echo "\nRescate finalizado. $count nuevos mockups encontrados y vinculados.";
?>
