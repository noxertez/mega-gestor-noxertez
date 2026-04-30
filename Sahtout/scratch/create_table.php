<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

$sql = "CREATE TABLE IF NOT EXISTS mockups_vinculaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mockup_id INT,
    sku VARCHAR(100),
    INDEX(mockup_id),
    INDEX(sku)
)";

try {
    $db->exec($sql);
    echo "Tabla mockups_vinculaciones creada o ya existente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
