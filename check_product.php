<?php
require_once 'Sahtout/api/config.php';
$db = conectar();

$sku = 'NXTCUAEST0019';
$sku_search = '%' . $sku . '%';

echo "Searching for pattern: $sku_search\n\n";

echo "--- Table: articulos ---\n";
$stmt = $db->prepare("SELECT referencia, nombre, activo FROM articulos WHERE referencia LIKE ?");
$stmt->execute([$sku_search]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($results) {
    print_r($results);
} else {
    echo "No similar SKU found in articulos\n";
}

echo "\n--- Sample BASE products ---\n";
$stmt = $db->query("SELECT SKU_REF, NOMBRE, ES_VARIANTE FROM productos WHERE ES_VARIANTE = 'BASE' LIMIT 5");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);

if ($results) {
    $first_base = $results[0]['SKU_REF'];
    echo "\n--- Variants of $first_base ---\n";
    $stmt = $db->prepare("SELECT SKU_REF, NOMBRE, ES_VARIANTE FROM productos WHERE SKU_REF LIKE ? AND ES_VARIANTE = 'VARIANTE'");
    $stmt->execute([$first_base . '-%']);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
