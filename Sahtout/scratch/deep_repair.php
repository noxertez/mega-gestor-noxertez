<?php
require_once 'api/config.php';
$db = conectar();

echo "Iniciando reparación de datos profunda...\n";

// 1. Rellenar decoracion, estancia y estilo si están vacíos
$db->exec("UPDATE mockups_varios SET decoracion = 'Estándar' WHERE decoracion IS NULL OR decoracion = ''");
$db->exec("UPDATE mockups_varios SET estancia = 'Catálogo' WHERE estancia IS NULL OR estancia = ''");
$db->exec("UPDATE mockups_varios SET estilo = 'Estándar' WHERE estilo IS NOT NULL AND estilo = ''");

// 2. Intentar auto-vincular por nombre de archivo (el rescatador definitivo)
$mockups = $db->query("SELECT id, archivo FROM mockups_varios WHERE asignado_a_sku IS NULL OR asignado_a_sku = ''")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;

foreach ($mockups as $m) {
    $archivo = $m['archivo'];
    // Extraer SKU (asumimos que es la primera parte antes del primer guión o guión bajo largo)
    // O simplemente buscar si alguna referencia de la tabla articulos está contenida en el nombre
    $stmt = $db->prepare("SELECT referencia FROM articulos WHERE ? LIKE CONCAT('%', referencia, '%') LIMIT 1");
    $stmt->execute([$archivo]);
    $sku = $stmt->fetchColumn();
    
    if ($sku) {
        $upd = $db->prepare("UPDATE mockups_varios SET asignado_a_sku = ? WHERE id = ?");
        $upd->execute([$sku, $m['id']]);
        $count++;
    }
}

echo "Reparación completada. $count mockups auto-vinculados por nombre.\n";
?>
