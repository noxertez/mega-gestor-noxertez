<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

// Mapeo: parte del nombre largo -> nombre limpio
$mapa = [
    'boutique-de-artesania'     => 'Boutique artesanía',
    'boutique de artesania'     => 'Boutique artesanía',
    'Boutique de artesania'     => 'Boutique artesanía',
    'una-adorable-cafeteria'    => 'Cafetería realista',
    'una adorable cafeteria'    => 'Cafetería realista',
    'Una adorable cafeteria'    => 'Cafetería realista',
    'Una-adorable-cafeteria'    => 'Cafetería realista',
    'cafeteria-realista'        => 'Cafetería realista',
    'sala-de-estar'             => 'Sala de estar',
    'Sala de estar'             => 'Sala de estar',
    'habitacion'                => 'Habitación',
    'cocina'                    => 'Cocina',
    'jardin'                    => 'Jardín',
    'exterior'                  => 'Exterior',
    'estudio'                   => 'Estudio',
    'oficina'                   => 'Oficina',
];

$updated = 0;
foreach ($mapa as $buscar => $reemplazar) {
    $stmt = $db->prepare("UPDATE mockups_varios SET estancia = ? WHERE LOWER(estancia) LIKE ?");
    $stmt->execute([$reemplazar, '%' . strtolower($buscar) . '%']);
    $updated += $stmt->rowCount();
}

echo "Estancias corregidas: $updated registros\n";

// Mostrar todas las estancias únicas que quedan
$estancias = $db->query("SELECT DISTINCT estancia FROM mockups_varios WHERE estancia != '' ORDER BY estancia")->fetchAll(PDO::FETCH_COLUMN);
echo "\nEstancias en BD:\n";
foreach ($estancias as $e) echo "  - $e\n";
?>
