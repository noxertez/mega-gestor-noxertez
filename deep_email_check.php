<?php
$db = new mysqli("localhost", "noxertez_user", "Noxertez2024!", "noxertez");

echo "--- BUSQUEDA DE NOTIFICACIONES DE CORREO ---\n";
$res = $db->query("SELECT * FROM notificaciones WHERE (tipo LIKE '%correo%' OR tipo LIKE '%email%' OR mensaje LIKE '%Correo%') ORDER BY id DESC LIMIT 20");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Fecha: {$row['fecha']} | Tipo: {$row['tipo']} | Mensaje: {$row['mensaje']}\n";
}

echo "\n--- BUSQUEDA DE PEDIDOS DE EMAIL ---\n";
$res = $db->query("SELECT * FROM pedidos WHERE canal = 'email' ORDER BY id DESC LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Fecha: {$row['fecha_pedido']} | Cliente: {$row['nombre_cliente']} | Canal: {$row['canal']}\n";
}
