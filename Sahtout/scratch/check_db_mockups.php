<?php
require_once 'Sahtout/api/config.php';
$db = conectar();
$assignedMockups = $db->query("SELECT DISTINCT mockup FROM articulos WHERE mockup IS NOT NULL AND mockup != ''")->fetchAll(PDO::FETCH_COLUMN);
echo "Assigned Mockups in DB:\n";
print_r($assignedMockups);
?>
