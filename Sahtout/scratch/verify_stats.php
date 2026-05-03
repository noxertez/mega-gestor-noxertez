<?php
require_once 'Sahtout/api/config.php';
$db = conectar();

$sku = 'NXTCUAMAN0043P01';

echo "Final Verification of Statistics Query for SKU: $sku\n";

$sql = "
    SELECT 
        p_base.SKU_REF as base_sku,
        p_base.FOTO_PORTADA as foto,
        COUNT(DISTINCT p_all.SKU_REF) as total_variantes,
        COUNT(DISTINCT mv.mockup_id) as total_mockups,
        IF(COUNT(DISTINCT mv.mockup_id) > 0, 1, 0) as tiene_mockup
    FROM productos p_base
    LEFT JOIN productos p_all ON p_all.SKU_BASE = p_base.SKU_BASE
    LEFT JOIN mockups_vinculaciones mv ON mv.sku = p_all.SKU_REF
    WHERE (p_base.ES_VARIANTE = 'BASE' OR p_base.SKU_REF REGEXP '[Pp]01$')
      AND p_base.SKU_REF = ?
    GROUP BY p_base.SKU_REF
";

$stmt = $db->prepare($sql);
$stmt->execute([$sku]);
$res = $stmt->fetch();

if ($res) {
    echo "SUCCESS! Data found:\n";
    print_r($res);
} else {
    echo "FAILURE! SKU not found in result set.\n";
}
?>
