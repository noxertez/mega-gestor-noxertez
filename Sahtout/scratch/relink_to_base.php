<?php
require_once 'api/config.php';
$db = conectar();

echo "Re-vinculando mockups a SKUs base...\n";

$mockups = $db->query("SELECT id, asignado_a_sku FROM mockups_varios WHERE asignado_a_sku IS NOT NULL AND asignado_a_sku != ''")->fetchAll(PDO::FETCH_ASSOC);

foreach ($mockups as $m) {
    $sku = $m['asignado_a_sku'];
    // Buscar si este SKU tiene un base
    $stmt = $db->prepare("SELECT sku_base FROM articulos WHERE referencia = ?");
    $stmt->execute([$sku]);
    $base = $stmt->fetchColumn();
    
    if ($base && $base != $sku) {
        $upd = $db->prepare("UPDATE mockups_varios SET asignado_a_sku = ? WHERE id = ?");
        $upd->execute([$base, $m['id']]);
        echo "Vinculado ID {$m['id']} de $sku a BASE $base\n";
    }
}

// También actualizar la columna 'mockup' de la tabla artículos por si acaso
$db->exec("UPDATE articulos a JOIN mockups_varios m ON a.referencia = m.asignado_a_sku SET a.mockup = m.ruta WHERE a.mockup IS NULL");

echo "\nProceso finalizado.";
?>
