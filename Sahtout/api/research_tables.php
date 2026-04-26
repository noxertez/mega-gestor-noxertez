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

describeTable($db, 'pedidos');
describeTable($db, 'flujo_nodos');
describeTable($db, 'flujo_plantillas');
describeTable($db, 'pedido_nodos'); // I saw this in previous turn summary (check_triggers output)

echo "--- Sample: flujo_nodos ---\n";
$stmt = $db->query("SELECT * FROM flujo_nodos LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- Sample: pedido_nodos ---\n";
try {
    $stmt = $db->query("SELECT * FROM pedido_nodos LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) { echo "Table pedido_nodos not found\n"; }
