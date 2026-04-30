<?php
require 'api/config.php';
$db = conectar();
$res = $db->query("SELECT id, archivo, ruta, asignado_a_sku FROM mockups_varios WHERE archivo LIKE '%NXTCUAMAN0045_mockup_4%'")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
