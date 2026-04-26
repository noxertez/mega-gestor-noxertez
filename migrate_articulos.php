<?php
require_once(__DIR__ . '/Sahtout/api/config.php');
$db = conectar();

try {
    // 1. Add column
    $db->exec("ALTER TABLE articulos ADD COLUMN es_variante VARCHAR(10) DEFAULT 'BASE'");
    echo "Columna 'es_variante' añadida a 'articulos'.\n";

    // 2. Initialize existing records (assuming all are BASE if not specified)
    // We could try to detect if it's a version by SKU, but DEFAULT 'BASE' is safer for now.
    
    echo "Migración completada con éxito.\n";
} catch (Exception $e) {
    echo "Error en la migración: " . $e->getMessage() . "\n";
}
?>
