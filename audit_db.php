<?php
require_once(__DIR__ . '/Sahtout/api/config.php');
$db = conectar();

$fp = fopen('db_audit.txt', 'w');
fwrite($fp, "AUDIT LOG\n");

// 1. Check distinct marcas in productos
$res = $db->query("SELECT DISTINCT MARCA FROM productos");
fwrite($fp, "\nMarcas en PRODUCTOS:\n");
while($row = $res->fetch(PDO::FETCH_ASSOC)) {
    fwrite($fp, "- " . $row['MARCA'] . "\n");
}

// 2. Check ES_VARIANTE counts in productos
$res = $db->query("SELECT ES_VARIANTE, COUNT(*) as c FROM productos GROUP BY ES_VARIANTE");
fwrite($fp, "\nES_VARIANTE en PRODUCTOS:\n");
while($row = $res->fetch(PDO::FETCH_ASSOC)) {
    fwrite($fp, "- '" . $row['ES_VARIANTE'] . "': " . $row['c'] . "\n");
}

// 3. Check some samples for CANDLEHOLDER
$stmt = $db->prepare("SELECT NOMBRE, ES_VARIANTE FROM productos WHERE MARCA = 'CANDLEHOLDER' LIMIT 5");
$stmt->execute();
fwrite($fp, "\Samples for CANDLEHOLDER:\n");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fwrite($fp, "- " . $row['NOMBRE'] . " | " . $row['ES_VARIANTE'] . "\n");
}

fclose($fp);
echo "Audit complete. Check db_audit.txt\n";
?>
