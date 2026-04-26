<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once 'config.php';
$db = conectar();

function printTable($db, $tableName) {
    echo "--- TABLE: $tableName ---\n";
    try {
        $stmt = $db->query("DESCRIBE `$tableName`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']}\n";
        }
    } catch (Exception $e) {
        echo "Error describing $tableName: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

printTable($db, 'productos');
printTable($db, 'articulos');
printTable($db, 'plataformas_ventas');

echo "--- TRIGGERS for relevant tables ---\n";
try {
    $stmt = $db->query("SHOW TRIGGERS WHERE `Table` IN ('productos', 'articulos', 'plataformas_ventas')");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($triggers)) {
        echo "No triggers found for these tables.\n";
    } else {
        foreach ($triggers as $row) {
            echo "Trigger: {$row['Trigger']} | Table: {$row['Table']} | Event: {$row['Event']} | Timing: {$row['Timing']}\n";
            echo "Statement: {$row['Statement']}\n\n";
        }
    }
} catch (Exception $e) {
    echo "Could not show triggers: " . $e->getMessage() . "\n";
}
?>
