<?php
require_once 'api/config.php';
$db = conectar();

echo "--- ULTIMOS 5 POSTS EN COLA ---\n";
$stmt = $db->query("SELECT id, fecha_programada, estado, intentos, mensaje_error, texto FROM linkedin_queue ORDER BY fecha_programada DESC LIMIT 5");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']} | Fecha: {$row['fecha_programada']} | Estado: {$row['estado']} | Intentos: {$row['intentos']}\n";
    if ($row['mensaje_error']) echo "Error: {$row['mensaje_error']}\n";
    echo "Texto: " . substr($row['texto'], 0, 50) . "...\n";
    echo "-----------------------------------\n";
}

echo "\n--- PENDIENTES PASADOS ---\n";
$stmt = $db->query("SELECT COUNT(*) FROM linkedin_queue WHERE estado = 'pendiente' AND fecha_programada <= NOW()");
echo "Total pendientes pasados: " . $stmt->fetchColumn() . "\n";
?>
