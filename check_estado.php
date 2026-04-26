<?php
require_once 'Sahtout/api/config.php';
try {
    $db = conectar();
    $stmt = $db->query("SHOW COLUMNS FROM pedidos LIKE 'estado'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Column: " . $row['Field'] . "\n";
        echo "Type: " . $row['Type'] . "\n";
        echo "Null: " . $row['Null'] . "\n";
        echo "Default: " . $row['Default'] . "\n";
    } else {
        echo "Column 'estado' not found in table 'pedidos'.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
