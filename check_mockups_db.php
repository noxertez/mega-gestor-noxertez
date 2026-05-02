<?php
$db = new mysqli("localhost", "noxertez_user", "Noxertez2024!", "noxertez");
$res = $db->query("DESCRIBE mockups_varios");
while($row = $res->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
echo "\n--- DATOS DE PRUEBA ---\n";
$res = $db->query("SELECT * FROM mockups_varios LIMIT 1");
print_r($res->fetch_assoc());
