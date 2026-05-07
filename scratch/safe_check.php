<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'noxertez';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    
    $res_prod = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
    $res_mat  = $pdo->query("SELECT COUNT(*) FROM materiales")->fetchColumn();
    
    echo "Resumen de Inventario:\n";
    echo "- Productos totales: $res_prod\n";
    echo "- Materiales totales: $res_mat\n";
    echo "\nArtículos sospechosos de ser 'test':\n";
    
    $stmt = $pdo->query("SELECT NOMBRE FROM productos WHERE NOMBRE LIKE '%portafoto%' OR NOMBRE LIKE '%trapecio%' OR NOMBRE LIKE '%corazon%'");
    while($row = $stmt->fetch()) echo "[Producto] " . $row['NOMBRE'] . "\n";

    $stmt = $pdo->query("SELECT nombre FROM materiales WHERE nombre LIKE '%portafoto%' OR nombre LIKE '%trapecio%' OR nombre LIKE '%corazon%'");
    while($row = $stmt->fetch()) echo "[Material] " . $row['nombre'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
