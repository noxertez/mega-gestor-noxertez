<?php
$db = new mysqli("localhost", "noxertez_user", "Noxertez2024!", "noxertez");

// 1. Obtener las rutas de los archivos a borrar
$res = $db->query("SELECT id, ruta FROM mockups_varios WHERE DATE(fecha_subida) = CURDATE()");
$total = 0;
while($row = $res->fetch_assoc()) {
    $filePath = '../' . $row['ruta'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    // También borrar vinculaciones
    $db->query("DELETE FROM mockups_vinculaciones WHERE mockup_id = " . $row['id']);
    $total++;
}

// 2. Borrar de la tabla principal
$db->query("DELETE FROM mockups_varios WHERE DATE(fecha_subida) = CURDATE()");

echo "¡Limpieza completada! Se han eliminado $total mockups (archivos y registros) subidos hoy.\n";
