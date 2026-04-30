<?php
require 'api/config.php';
$db = conectar();
$res = $db->query("DESCRIBE articulos")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
