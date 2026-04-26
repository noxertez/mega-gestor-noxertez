<?php
require_once 'config.php';
$db = conectar();

function describeTable($db, $table) {
    echo "--- Table: $table ---\n";
    try {
        $stmt = $db->query("DESCRIBE $table");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

describeTable($db, 'flujo_nodos_plantilla');
describeTable($db, 'pedido_nodos');

echo "--- Sample: flujo_nodos_plantilla ---\n";
try {
    $stmt = $db->query("SELECT * FROM flujo_nodos_plantilla LIMIT 20");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) { echo "Table flujo_nodos_plantilla not found\n"; }
?>
