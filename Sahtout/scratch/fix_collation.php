<?php
require 'api/config.php';
$db = conectar();

try {
    // Unificar tabla mockups_varios
    $db->query("ALTER TABLE mockups_varios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    // Unificar tabla articulos
    $db->query("ALTER TABLE articulos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    
    // Asegurar columnas específicas de comparación
    $db->query("ALTER TABLE articulos MODIFY referencia VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $db->query("ALTER TABLE mockups_varios MODIFY asignado_a_sku VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    echo "Collation unificada correctamente a utf8mb4_general_ci.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
