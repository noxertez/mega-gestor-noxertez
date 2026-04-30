<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

// Mapeo: SKU de carpeta -> SKU real en articulos
$mapeo = [
    'NXTCUAMAN0042' => 'NXTCUAMAN0042P01',  // buscar si existe
    'NXTCUAMAN0043' => 'NXTCUAMAN0043P01',
    'NXTCUAMAN0044' => 'NXTCUAMAN0044P01',
    'NXTCUAMAN0045' => 'NXTCUAMAN0045P01',
    'NXTCUAGEO0050' => 'NXTCUAGEO0050P01',
    'NXTCUAGEO0051' => 'NXTCUAGEO0051P01',
    'NXTCUAGEO0052' => 'NXTCUAGEO0052P01',
    'NXTLAMPAL0041' => 'NXTLAMPAL0041',     // ya existe tal cual
];

echo "Verificando y actualizando SKUs:\n";
foreach ($mapeo as $viejo => $nuevo) {
    // Verificar que el nuevo SKU existe en articulos
    $check = $db->prepare("SELECT referencia FROM articulos WHERE referencia = ? LIMIT 1");
    $check->execute([$nuevo]);
    if (!$check->fetchColumn()) {
        echo "  AVISO: $nuevo no existe en articulos - buscando alternativa...\n";
        // Intentar con P01
        $alt = $db->prepare("SELECT referencia FROM articulos WHERE referencia LIKE ? AND es_variante='BASE' LIMIT 1");
        $alt->execute([$viejo . '%']);
        $encontrado = $alt->fetchColumn();
        if ($encontrado) { $nuevo = $encontrado; echo "  -> Usando: $nuevo\n"; }
        else { echo "  -> No encontrado, saltando.\n"; continue; }
    }
    
    // Actualizar mockups_varios
    $stmt = $db->prepare("UPDATE mockups_varios SET asignado_a_sku = ? WHERE asignado_a_sku = ?");
    $stmt->execute([$nuevo, $viejo]);
    $n = $stmt->rowCount();
    echo "  $viejo -> $nuevo ($n mockups actualizados)\n";
    
    // También renombrar la carpeta si es posible (actualizar ruta en BD)
    $stmtRuta = $db->prepare("UPDATE mockups_varios SET ruta = REPLACE(ruta, ?, ?) WHERE ruta LIKE ?");
    $stmtRuta->execute([$viejo, $nuevo, "%$viejo%"]);
}

echo "\nVerificación final:\n";
$rows = $db->query("SELECT asignado_a_sku, COUNT(*) as n FROM mockups_varios WHERE asignado_a_sku IS NOT NULL GROUP BY asignado_a_sku")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  {$r['asignado_a_sku']}: {$r['n']} mockups\n";
?>
