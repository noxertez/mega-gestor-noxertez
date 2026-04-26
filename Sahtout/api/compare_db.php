<?php
require_once 'config.php';
$db = conectar();
$art = $db->query("SELECT referencia, nombre, stock FROM articulos LIMIT 5")->fetchAll();
$prod = $db->query("SELECT SKU_REF, NOMBRE, STOCK, STOCK_FISICO FROM productos WHERE ES_VARIANTE = 'BASE' AND SKU_REF IN ('" . implode("','", array_column($art, 'referencia')) . "')")->fetchAll();

echo "Articulos:\n";
print_r($art);
echo "\nProductos:\n";
print_r($prod);
?>
