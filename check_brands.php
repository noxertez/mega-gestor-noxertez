<?php
require 'Sahtout/api/config.php';
$db = conectar();
$rows = $db->query("SELECT DISTINCT MARCA FROM productos")->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('res_brands_debug.txt', implode("\n", $rows));
?>
