<?php
require_once __DIR__ . '/../api/config.php';
$db = conectar();

$output = "--- STATUS DE LA COLA LINKEDIN ---\n";
try {
    $stmt = $db->query("SELECT id, fecha_programada, estado, intentos, mensaje_error, texto FROM linkedin_queue ORDER BY fecha_programada DESC LIMIT 10");
    while ($row = $stmt->fetch()) {
        $output .= "ID: {$row['id']} | Fecha: {$row['fecha_programada']} | Estado: {$row['estado']} | Intentos: {$row['intentos']}\n";
        if ($row['mensaje_error']) $output .= "Error: {$row['mensaje_error']}\n";
        $output .= "Texto: " . substr($row['texto'], 0, 50) . "...\n";
        $output .= "-----------------------------------\n";
    }
} catch (Exception $e) {
    $output .= "Error querying database: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/linkedin_status.txt', $output);
echo "Status written to scratch/linkedin_status.txt";
?>
