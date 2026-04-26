<?php
require_once(__DIR__ . '/Sahtout/api/config.php');
$db = conectar();

echo "=== Sincronizando ES_VARIANTE: articulos <- productos (via SKU_BASE) ===\n\n";

// Update articulos.es_variante based on productos.ES_VARIANTE matching by SKU_BASE
$sql = "UPDATE articulos a 
        INNER JOIN productos p ON a.referencia = p.SKU_BASE
        SET a.es_variante = p.ES_VARIANTE
        WHERE p.ES_VARIANTE IS NOT NULL AND p.ES_VARIANTE != ''";

$count = $db->exec($sql);
echo "Actualizados por SKU_BASE: $count registros\n";

// Also try via SKU_REF for the rest  
$sql2 = "UPDATE articulos a 
         INNER JOIN productos p ON a.referencia = p.SKU_REF
         SET a.es_variante = p.ES_VARIANTE
         WHERE p.ES_VARIANTE IS NOT NULL AND p.ES_VARIANTE != '' AND a.es_variante != p.ES_VARIANTE";

$count2 = $db->exec($sql2);
echo "Actualizados por SKU_REF: $count2 registros\n";

// Verify result
$res = $db->query("SELECT es_variante, COUNT(*) as c FROM articulos GROUP BY es_variante");
echo "\nResultado de la sincronización:\n";
while($row = $res->fetch(PDO::FETCH_ASSOC)) {
    echo "  '" . ($row['es_variante'] ?: 'EMPTY') . "': " . $row['c'] . "\n";
}

echo "\n✅ Sincronización completada.\n";
?>
