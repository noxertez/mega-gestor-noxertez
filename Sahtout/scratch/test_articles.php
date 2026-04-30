<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

echo "=== TEST get_articles ===\n\n";

// Simular la consulta exacta que hace el PHP
$where = ["1=1"];
$params = [];
$where[] = "(es_variante = 'BASE' OR referencia REGEXP 'P01$' OR referencia REGEXP 'P01-')";
$sql = "SELECT referencia, nombre, foto_portada, mockup FROM articulos WHERE " . implode(" AND ", $where) . " ORDER BY referencia ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total artículos encontrados (BASE + P01): " . count($articulos) . "\n\n";

$conMockup = 0;
foreach ($articulos as $art) {
    $sku = $art['referencia'];
    $skuBase = preg_replace('/P\d+.*$/', '', $sku);
    
    $stmtM = $db->prepare("SELECT ruta FROM mockups_varios WHERE asignado_a_sku = ? OR asignado_a_sku LIKE ? OR archivo LIKE ?");
    $stmtM->execute([$sku, $skuBase . '%', '%' . $skuBase . '%']);
    $mockups = $stmtM->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($mockups) > 0) {
        $conMockup++;
        echo "  CON MOCKUP: {$art['referencia']} - {$art['nombre']} (" . count($mockups) . " mockups)\n";
        echo "    Primer ruta: {$mockups[0]}\n";
    }
}
echo "\nTotal artículos con mockup: $conMockup\n";
?>
