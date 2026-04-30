<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

$rows = $db->query("SELECT referencia, es_variante, nombre FROM articulos WHERE referencia LIKE 'NXTCUA%' OR referencia LIKE 'NXTLAM%' OR referencia LIKE 'NXT%' ORDER BY referencia LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
echo "Artículos NXT en BD:\n";
foreach ($rows as $r) {
    echo "  [{$r['es_variante']}] {$r['referencia']} - {$r['nombre']}\n";
}
echo "\nTotal: " . count($rows) . "\n";
?>
