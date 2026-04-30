<?php
require 'api/config.php';
$db = conectar();

// 1. Forzar vinculación de NXTCUAMAN0042
$db->query("UPDATE mockups_varios SET asignado_a_sku = 'NXTCUAMAN0042' WHERE archivo LIKE 'NXTCUAMAN0042%' AND (asignado_a_sku IS NULL OR asignado_a_sku = '')");

// 2. Vincular cualquier otro mockup huérfano que empiece por un SKU conocido
$articulos = $db->query("SELECT referencia FROM articulos WHERE es_variante = 'BASE'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($articulos as $sku) {
    $db->query("UPDATE mockups_varios SET asignado_a_sku = '$sku' WHERE archivo LIKE '$sku%' AND (asignado_a_sku IS NULL OR asignado_a_sku = '')");
}

echo "Vinculación de SKUs completada.";
