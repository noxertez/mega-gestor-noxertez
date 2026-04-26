<?php
require 'Sahtout/api/config.php';
$db = conectar();
$rows = $db->query('SELECT FOTO_PORTADA FROM productos WHERE FOTO_PORTADA IS NOT NULL LIMIT 10')->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('res_fotos.txt', implode("\n", $rows));
?>
