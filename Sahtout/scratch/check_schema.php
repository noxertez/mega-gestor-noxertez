<?php
require 'api/config.php';
$db = conectar();
$res = $db->query("DESCRIBE mockups_varios")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
