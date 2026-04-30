<?php
require_once 'api/config.php';
$db = conectar();
$count = $db->query("SELECT COUNT(*) FROM articulos WHERE mockup IS NOT NULL OR referencia IN (SELECT DISTINCT asignado_a_sku FROM mockups_varios)")->fetchColumn();
echo "Articulos con mockup detectados: " . $count . "\n";
?>
