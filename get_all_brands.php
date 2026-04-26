<?php
require 'Sahtout/api/config.php';
$db = conectar();
$brands = $db->query('SELECT DISTINCT MARCA FROM productos')->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('res_all_brands.txt', implode("\n", $brands));
?>
