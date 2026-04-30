<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

// Fijar NXTCUAMAN0042 -> primer P01 que exista
$r = $db->query("SELECT referencia FROM articulos WHERE referencia LIKE 'NXTCUAMAN0042P01%' LIMIT 1")->fetchColumn();
if ($r) {
    $stmt = $db->prepare("UPDATE mockups_varios SET asignado_a_sku = ? WHERE asignado_a_sku = 'NXTCUAMAN0042'");
    $stmt->execute([$r]);
    echo "NXTCUAMAN0042 -> $r (" . $stmt->rowCount() . " mockups)\n";
} else {
    echo "No encontrado NXTCUAMAN0042P01\n";
}

// Verificación final completa
echo "\nVinculaciones finales:\n";
$rows = $db->query("SELECT asignado_a_sku, COUNT(*) as n FROM mockups_varios WHERE asignado_a_sku IS NOT NULL GROUP BY asignado_a_sku ORDER BY asignado_a_sku")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  {$r['asignado_a_sku']}: {$r['n']} mockups\n";
?>
