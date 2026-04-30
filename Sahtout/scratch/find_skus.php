<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

// Buscar cualquier artículo que contenga los números del SKU
$buscar = ['0042','0043','0044','0045','0050','0051','0052','0041'];
foreach ($buscar as $num) {
    $rows = $db->prepare("SELECT referencia, nombre, es_variante FROM articulos WHERE referencia LIKE ? AND es_variante = 'BASE'");
    $rows->execute(["%$num%"]);
    $arts = $rows->fetchAll(PDO::FETCH_ASSOC);
    echo "Número $num:\n";
    foreach ($arts as $a) echo "  {$a['referencia']} - {$a['nombre']}\n";
    if (!$arts) echo "  (ninguno)\n";
}
?>
