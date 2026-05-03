<?php
require_once 'Sahtout/api/config.php';
$db = conectar();

$sku = 'NXTCUAMAN0043P01';

echo "Testing NEW JOIN LOGIC for SKU: $sku\n";

$sql = "
    SELECT 
        p_base.SKU_REF as base_sku,
        p_base.SKU_BASE as familia,
        COUNT(DISTINCT p_all.SKU_REF) as total_variantes,
        COUNT(DISTINCT mv.mockup_id) as total_mockups
    FROM productos p_base
    LEFT JOIN productos p_all ON p_all.SKU_BASE = p_base.SKU_BASE
    LEFT JOIN mockups_vinculaciones mv ON mv.sku = p_all.SKU_REF
    WHERE p_base.SKU_REF = ?
    GROUP BY p_base.SKU_REF
";

$stmt = $db->prepare($sql);
$stmt->execute([$sku]);
print_r($stmt->fetch());

echo "\nTesting OLD JOIN LOGIC for comparison:\n";
$sql_old = "
    SELECT 
        p_base.SKU_REF as base_sku,
        COUNT(DISTINCT p_all.SKU_REF) as total_variantes,
        COUNT(DISTINCT mv.mockup_id) as total_mockups
    FROM productos p_base
    LEFT JOIN productos p_all ON p_all.SKU_BASE = p_base.SKU_REF
    LEFT JOIN mockups_vinculaciones mv ON mv.sku = p_all.SKU_REF
    WHERE p_base.SKU_REF = ?
    GROUP BY p_base.SKU_REF
";
$stmt = $db->prepare($sql_old);
$stmt->execute([$sku]);
print_r($stmt->fetch());
?>
