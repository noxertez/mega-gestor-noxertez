<?php
require_once 'config.php';
$db = conectar();

echo "--- TABLE: productos ---\n";
$stmt = $db->query("DESCRIBE productos");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']} - {$row['Default']}\n";
}

echo "\n--- TABLE: plataformas_ventas ---\n";
$stmt = $db->query("DESCRIBE plataformas_ventas");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']} - {$row['Default']}\n";
}

echo "\n--- TRIGGERS ---\n";
try {
    $stmt = $db->query("SHOW TRIGGERS");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "Trigger: {$row['Trigger']} - Table: {$row['Table']} - Event: {$row['Event']} - Timing: {$row['Timing']}\n";
        echo "Statement: {$row['Statement']}\n\n";
    }
} catch (Exception $e) {
    echo "Could not show triggers: " . $e->getMessage() . "\n";
}
?>
