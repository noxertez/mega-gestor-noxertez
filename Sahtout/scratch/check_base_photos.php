<?php
define('ALLOWED_ACCESS', true);
require_once 'api/config.php';
$db = conectar();
$stmt = $db->query("SELECT SKU_REF, FOTO_PORTADA FROM productos WHERE ES_VARIANTE = 'BASE' LIMIT 10");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "SKU: " . $row['SKU_REF'] . " | FOTO: " . $row['FOTO_PORTADA'] . "\n";
}
