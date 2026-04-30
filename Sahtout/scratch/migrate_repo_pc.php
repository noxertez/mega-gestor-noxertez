<?php
require 'api/config.php';
$db = conectar();

$baseDir = dirname(__DIR__) . '/uploads/articulos';
$repoDir = $baseDir . '/repo_pc';
$targetBase = $baseDir . '/imagenes';

if (!is_dir($repoDir)) {
    die("La carpeta repo_pc no existe. No hay nada que mover.\n");
}

function moveRecursive($src, $dst, $db) {
    if (!is_dir($src)) return;
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;

        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) mkdir($dstPath, 0777, true);
            moveRecursive($srcPath, $dstPath, $db);
            @rmdir($srcPath);
        } else {
            // Mover archivo
            if (!is_dir($dst)) mkdir($dst, 0777, true);
            if (@rename($srcPath, $dstPath)) {
                // Actualizar DB
                $oldRel = str_replace(dirname(__DIR__).'/', '', $srcPath);
                $newRel = str_replace(dirname(__DIR__).'/', '', $dstPath);
                $stmt = $db->prepare("UPDATE mockups_varios SET ruta = ? WHERE ruta = ?");
                $stmt->execute([$newRel, $oldRel]);
            }
        }
    }
}

echo "Iniciando mudanza de archivos de repo_pc a imagenes...\n";
moveRecursive($repoDir, $targetBase, $db);
@rmdir($repoDir);
echo "¡Mudanza completada! Carpeta repo_pc eliminada.\n";
