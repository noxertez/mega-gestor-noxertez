<?php
require_once 'api/config.php';
$db = conectar();
try {
    $db->exec("ALTER TABLE mockups_varios ADD COLUMN decoracion VARCHAR(255) AFTER estilo");
    echo "Columna decoracion añadida con éxito.";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "La columna ya existe.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
