<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

// Mapeo: carpeta/SKU viejo -> SKU real en articulos (SOLO actualizar asignado_a_sku, NO la ruta)
$mapeo = [
    'NXTCUAMAN0042' => 'NXTCUAMAN0042P01-AZUL_Y_TURQUESA',
    'NXTCUAMAN0043' => 'NXTCUAMAN0043P01',
    'NXTCUAMAN0044' => 'NXTCUAMAN0044P01',
    'NXTCUAMAN0045' => 'NXTCUAMAN0045P01',
    'NXTCUAGEO0050' => 'NXTCUAGEO0050P01',
    'NXTCUAGEO0051' => 'NXTCUAGEO0051P01',
    'NXTCUAGEO0052' => 'NXTCUAGEO0052P01',
];

foreach ($mapeo as $viejo => $nuevo) {
    $stmt = $db->prepare("UPDATE mockups_varios SET asignado_a_sku = ? WHERE asignado_a_sku = ?");
    $stmt->execute([$nuevo, $viejo]);
    echo "$viejo -> $nuevo ({$stmt->rowCount()} filas)\n";
}

// Aplicar corrección de estancias
$db->exec("UPDATE mockups_varios SET estancia = 'Cafetería realista' WHERE estancia LIKE '%cafet%' OR estancia LIKE '%acogedora%'");
$db->exec("UPDATE mockups_varios SET estancia = 'Boutique artesanía' WHERE estancia LIKE '%boutique%'");

echo "\nVinculaciones finales:\n";
$rows = $db->query("SELECT asignado_a_sku, COUNT(*) as n FROM mockups_varios WHERE asignado_a_sku IS NOT NULL GROUP BY asignado_a_sku ORDER BY asignado_a_sku")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  {$r['asignado_a_sku']}: {$r['n']} mockups\n";
?>
