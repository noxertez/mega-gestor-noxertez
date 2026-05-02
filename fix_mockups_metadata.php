<?php
$db = new mysqli("localhost", "noxertez_user", "Noxertez2024!", "noxertez");

// 1. Intercambiar valores para los registros de hoy
$sql = "UPDATE mockups_varios 
        SET decoracion = (@tmp:=decoracion), 
            decoracion = formato, 
            formato = @tmp 
        WHERE DATE(fecha_subida) = CURDATE()";

if ($db->query($sql)) {
    echo "¡Éxito! Se han intercambiado los metadatos de los mockups subidos hoy.\n";
} else {
    echo "Error: " . $db->error . "\n";
}

// 2. Mostrar un ejemplo para confirmar
$res = $db->query("SELECT id, archivo, estancia, luz, decoracion, formato FROM mockups_varios WHERE DATE(fecha_subida) = CURDATE() LIMIT 1");
print_r($res->fetch_assoc());
