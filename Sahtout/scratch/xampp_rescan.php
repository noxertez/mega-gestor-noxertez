<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

echo "Vaciando tabla...\n";
$db->exec("DELETE FROM mockups_varios");

$baseDir = 'C:/xampp/htdocs/noxertez/uploads/articulos';
$count = 0;

function escanear($dir, $db, &$count) {
    if (!is_dir($dir)) { echo "AVISO: No existe: $dir\n"; return; }
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) { escanear($path, $db, $count); continue; }
        if (stripos($file, '_mockup_') === false) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','mp4','mov'])) continue;

        // Ruta relativa desde htdocs/noxertez/
        $rutaRel = str_replace('C:/xampp/htdocs/noxertez/', '', str_replace('\\','/',$path));

        // SKU = carpeta padre
        $sku = basename(dirname($path));
        if (in_array(strtoupper($sku), ['IMAGENES','VIDEOS','NOXERTEZ','CANDLEHOLDER','ZEN','ARTICULOS'])) {
            $sku = basename(dirname(dirname($path)));
        }

        $stmt = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, asignado_a_sku, estancia, estilo, decoracion, calidad) VALUES (?,?,?,?,'Catálogo','Estándar','Estándar','publicar')");
        $tipo = in_array($ext, ['mp4','mov']) ? 'video' : 'imagen';
        $stmt->execute([$file, $rutaRel, $tipo, $sku]);
        $count++;
        echo "[$count] $file -> $sku\n";
    }
}

escanear($baseDir, $db, $count);
echo "\nTOTAL RECUPERADOS: $count mockups\n";
?>
