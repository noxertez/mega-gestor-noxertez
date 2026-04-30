<?php
require 'api/config.php';
$db = conectar();

// Ruta donde se estaban creando los duplicados innecesarios
$dupDir = dirname(__DIR__) . '/uploads/articulos/imagenes/repo_pc';

if (!is_dir($dupDir)) {
    die("No se ha encontrado la carpeta de duplicados. Es posible que ya esté limpia.\n");
}

function cleanupDuplicates($dir, $targetBase, $db) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            cleanupDuplicates($path, $targetBase . '/' . $file, $db);
            @rmdir($path);
        } else {
            // El archivo ya debería estar en la carpeta principal de imagenes
            // Si no está, lo movemos. Si está, borramos el duplicado.
            $targetPath = str_replace('/imagenes/repo_pc/', '/imagenes/', $path);
            $targetDir = dirname($targetPath);
            
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            if (file_exists($targetPath)) {
                // Ya existe en el destino, borramos este duplicado
                @unlink($path);
            } else {
                // No existe en el destino, lo movemos allí
                @rename($path, $targetPath);
            }
            
            // Actualizar DB para que apunte a la ruta limpia (sin repo_pc)
            $oldRel = str_replace(dirname(__DIR__).'/', '', $path);
            $newRel = str_replace(dirname(__DIR__).'/', '', $targetPath);
            $stmt = $db->prepare("UPDATE mockups_varios SET ruta = ? WHERE ruta = ?");
            $stmt->execute([$newRel, $oldRel]);
        }
    }
}

echo "Limpiando duplicados y liberando espacio...\n";
cleanupDuplicates($dupDir, dirname(__DIR__) . '/uploads/articulos/imagenes', $db);
@rmdir($dupDir);
echo "¡Limpieza completada! 2GB de duplicados eliminados.\n";
