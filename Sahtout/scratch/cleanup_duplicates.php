<?php
require 'api/config.php';
$db = conectar();
$count = $db->query("DELETE t1 FROM mockups_varios t1 INNER JOIN mockups_varios t2 WHERE t1.id > t2.id AND t1.archivo = t2.archivo AND t1.asignado_a_sku = t2.asignado_a_sku")->rowCount();
echo "Eliminados $count duplicados.";
