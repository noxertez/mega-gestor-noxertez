<?php
$db = new mysqli("localhost", "noxertez_user", "Noxertez2024!", "noxertez");
if ($db->connect_error) die("Connection failed: " . $db->connect_error);

echo "--- NOTIFICACIONES DE HOY ---\n";
$res = $db->query("SELECT tipo, mensaje, fecha, leida FROM notificaciones WHERE fecha >= CURDATE() ORDER BY fecha DESC");
while($row = $res->fetch_assoc()) {
    echo "{$row['fecha']} | {$row['tipo']} | {$row['mensaje']} | Leida: {$row['leida']}\n";
}

echo "\n--- PEDIDOS DE HOY ---\n";
$res = $db->query("SELECT numero_pedido, nombre_cliente, canal, fecha_pedido FROM pedidos WHERE fecha_pedido >= CURDATE() ORDER BY id DESC");
while($row = $res->fetch_assoc()) {
    echo "{$row['fecha_pedido']} | {$row['numero_pedido']} | {$row['nombre_cliente']} | Canal: {$row['canal']}\n";
}
