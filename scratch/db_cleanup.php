<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'noxertez';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Limpiar tablas de productos y materiales para empezar de nuevo
    // (Buscamos los términos específicos que mencionó el usuario)
    $pdo->exec("DELETE FROM productos WHERE NOMBRE LIKE '%portafoto%' OR NOMBRE LIKE '%trapecio%' OR NOMBRE LIKE '%corazon%'");
    $pdo->exec("DELETE FROM materiales WHERE nombre LIKE '%portafoto%' OR nombre LIKE '%trapecio%' OR nombre LIKE '%corazon%'");
    
    echo "Limpieza de base de datos realizada con éxito. Ahora puedes añadir los artículos reales.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
