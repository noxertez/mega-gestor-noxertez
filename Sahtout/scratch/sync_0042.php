<?php
require 'api/config.php';
$db = conectar();

$dir = "C:/xampp/htdocs/noxertez/uploads/articulos/imagenes/NOXERTEZ/NXTCUAMAN0042";
if (is_dir($dir)) {
    $files = scandir($dir);
    $importados = 0;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (stripos($file, '_mockup_') !== false) {
            $rutaRel = "uploads/articulos/imagenes/NOXERTEZ/NXTCUAMAN0042/" . $file;
            $sku = "NXTCUAMAN0042"; // Forzamos el SKU base
            
            // Registrar si no existe
            $check = $db->prepare("SELECT id FROM mockups_varios WHERE LOWER(archivo) = LOWER(?)");
            $check->execute([$file]);
            if (!$check->fetch()) {
                $ins = $db->prepare("INSERT INTO mockups_varios 
                    (archivo, ruta, tipo, estancia, estilo, luz, calidad, marca_noxertez, asignado_a_sku) 
                    VALUES (?, ?, 'imagen', 'Catálogo', 'Estándar', 'Día', 'publicar', 1, ?)");
                $ins->execute([$file, $rutaRel, $sku]);
                $importados++;
            }
        }
    }
    echo "Importados $importados mockups de la carpeta 0042.";
} else {
    echo "La carpeta no existe en la ruta especificada.";
}
