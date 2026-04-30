<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();
// Eliminar duplicados manteniendo solo el ID más bajo
$db->exec("DELETE t1 FROM mockups_vinculaciones t1 INNER JOIN mockups_vinculaciones t2 WHERE t1.id > t2.id AND t1.mockup_id = t2.mockup_id AND t1.sku = t2.sku");
// Añadir índice único
$db->exec("ALTER TABLE mockups_vinculaciones ADD UNIQUE INDEX unique_mockup_sku (mockup_id, sku)");
echo "Duplicados eliminados e índice único creado.\n";
?>
