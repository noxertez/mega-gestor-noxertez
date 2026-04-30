<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

// Buscar NXTCUAMAN0042 en BD
$r = $db->query("SELECT referencia, nombre, es_variante FROM articulos WHERE referencia LIKE '%0042%'")->fetchAll(PDO::FETCH_ASSOC);
echo "Artículos con 0042:\n";
foreach ($r as $a) echo "  [{$a['es_variante']}] {$a['referencia']} - {$a['nombre']}\n";
?>
