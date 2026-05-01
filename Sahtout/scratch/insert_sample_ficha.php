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
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$data = [
    'nombre_interno' => 'Madera Recuperada (Ejemplo)',
    'titulo_publico' => 'Artesanía en Madera con Historia',
    'materiales' => 'Vigas de madera de pino y roble recuperadas con más de 100 años de antigüedad.',
    'elaboracion' => 'Proceso 100% artesanal: lijado a mano, ensamblaje tradicional y acabado con aceites naturales.',
    'observaciones' => 'Debido a la naturaleza de la madera recuperada, cada pieza presenta nudos y grietas únicos que cuentan su historia.',
    'mantenimiento' => 'Limpiar con un paño seco. Evitar la exposición directa y prolongada al sol o humedad extrema.',
    'sostenibilidad' => 'Este producto utiliza materiales 100% reciclados, reduciendo la huella de carbono y preservando bosques antiguos.'
];

$sql = "INSERT INTO fichas_tecnicas (nombre_interno, titulo_publico, materiales, elaboracion, observaciones, mantenimiento, sostenibilidad) 
        VALUES (:nombre_interno, :titulo_publico, :materiales, :elaboracion, :observaciones, :mantenimiento, :sostenibilidad)";

$stmt = $pdo->prepare($sql);
if ($stmt->execute($data)) {
    echo "Ficha de ejemplo insertada correctamente.";
} else {
    echo "Error al insertar la ficha.";
}
?>
