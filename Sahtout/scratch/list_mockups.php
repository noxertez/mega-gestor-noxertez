<?php
require_once 'Sahtout/api/config.php';
$db = conectar();
$res = $db->query("SELECT referencia, mockup FROM articulos WHERE mockup IS NOT NULL AND mockup != ''")->fetchAll();
echo "Articles with mockups:\n";
print_r($res);
?>
