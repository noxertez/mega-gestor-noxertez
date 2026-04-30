<?php
require_once 'api/config.php';
$db = conectar();
$res = $db->query("SELECT DISTINCT decoracion FROM mockups_varios")->fetchAll(PDO::FETCH_COLUMN);
echo "Valores de decoracion encontrados: " . count($res) . "\n";
print_r($res);

$total = $db->query("SELECT COUNT(*) FROM mockups_varios")->fetchColumn();
echo "Total mockups en tabla: " . $total . "\n";

$mapped = $db->query("SELECT COUNT(*) FROM mockups_varios WHERE asignado_a_sku IS NOT NULL AND asignado_a_sku != ''")->fetchColumn();
echo "Mockups con SKU asignado: " . $mapped . "\n";
?>
