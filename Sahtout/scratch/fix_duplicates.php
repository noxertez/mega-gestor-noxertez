<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();
try {
    $db->exec("ALTER TABLE mockups_vinculaciones ADD UNIQUE INDEX unique_mockup_sku (mockup_id, sku)");
    echo "Índice único añadido correctamente.\n";
} catch (Exception $e) {
    echo "Aviso: " . $e->getMessage() . "\n";
}
?>
