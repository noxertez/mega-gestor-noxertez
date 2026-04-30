<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

$skus = ['NXTCUAGEO0050','NXTCUAGEO0051','NXTCUAGEO0052','NXTCUAMAN0042','NXTCUAMAN0043','NXTCUAMAN0044','NXTCUAMAN0045','NXTLAMPAL0041'];

echo "--- Estado de los artículos con mockup ---\n";
foreach ($skus as $sku) {
    $stmt = $db->prepare("SELECT referencia, es_variante, categoria FROM articulos WHERE referencia = ?");
    $stmt->execute([$sku]);
    $art = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($art) {
        echo "$sku -> es_variante: [{$art['es_variante']}] | categoria: {$art['categoria']}\n";
    } else {
        echo "$sku -> NO EXISTE EN articulos\n";
    }
}

echo "\n--- Mockups en BD para estos SKUs ---\n";
$stmt = $db->query("SELECT asignado_a_sku, COUNT(*) as n FROM mockups_varios WHERE asignado_a_sku IN ('NXTCUAGEO0050','NXTCUAGEO0051','NXTCUAGEO0052','NXTCUAMAN0042','NXTCUAMAN0043','NXTCUAMAN0044','NXTCUAMAN0045','NXTLAMPAL0041') GROUP BY asignado_a_sku");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  {$r['asignado_a_sku']}: {$r['n']} mockups\n";
?>
