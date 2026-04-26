<?php
$host = 'localhost';
$db   = 'noxertez';
$user = 'noxertez_user';
$pass = 'Noxertez2024!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // Get total count and max ID
     $stmt = $pdo->query("SELECT COUNT(*) as total, MAX(id) as max_id FROM pedidos");
     $row = $stmt->fetch();
     echo "Total orders: " . $row['total'] . "\n";
     echo "Max Order ID: " . $row['max_id'] . "\n";
     
     // Get all orders to see if they are real or trash
     echo "\nOrders Data:\n";
     $stmt = $pdo->query("SELECT * FROM pedidos ORDER BY id ASC");
     while ($row = $stmt->fetch()) {
         echo "ID: " . $row['id'] . " | Cliente: " . ($row['id_cliente'] ?? 'N/A') . " | Fecha: " . $row['fecha'] . " | Total: " . $row['total'] . "\n";
     }

} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
