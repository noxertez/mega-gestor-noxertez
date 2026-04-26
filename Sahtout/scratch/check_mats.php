<?php
require_once 'api/config.php';
$db = conectar();
$mats = $db->query("SELECT REF_MAT, NOMBRE, MARCA, CATEGORIA, SUBCATEGORIA FROM materiales")->fetchAll(PDO::FETCH_ASSOC);
print_r($mats);
