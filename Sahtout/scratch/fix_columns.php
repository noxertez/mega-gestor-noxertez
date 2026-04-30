<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();
// Ampliar columnas que pueden ser cortas
$db->exec("ALTER TABLE mockups_varios MODIFY COLUMN estancia VARCHAR(500), MODIFY COLUMN luz VARCHAR(255), MODIFY COLUMN formato VARCHAR(255), MODIFY COLUMN decoracion VARCHAR(255)");
echo "Columnas ampliadas OK\n";
?>
