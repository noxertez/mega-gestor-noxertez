<?php
require_once 'api/config.php';
$db = conectar();

echo "Iniciando vinculación inteligente...\n";

$mockups = $db->query("SELECT id, archivo FROM mockups_varios WHERE asignado_a_sku IS NULL OR asignado_a_sku = ''")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;

foreach ($mockups as $m) {
    $archivo = $m['archivo'];
    // Extraer la primera parte del archivo (ej: NXTCUAMAN0042)
    $parts = explode('_', $archivo);
    $prefix = $parts[0];
    
    // Buscar cualquier artículo que EMPIECE por ese prefijo
    $stmt = $db->prepare("SELECT referencia FROM articulos WHERE referencia LIKE CONCAT(?, '%') LIMIT 1");
    $stmt->execute([$prefix]);
    $sku = $stmt->fetchColumn();
    
    if ($sku) {
        $upd = $db->prepare("UPDATE mockups_varios SET asignado_a_sku = ? WHERE id = ?");
        $upd->execute([$sku, $m['id']]);
        $count++;
        echo "Vinculado: $archivo -> $sku\n";
    }
}

// Asegurar que decoracion tenga valor para el filtro
$db->exec("UPDATE mockups_varios SET decoracion = 'Estándar' WHERE decoracion IS NULL OR decoracion = ''");

echo "\nReparación finalizada. $count mockups recuperados.";
?>
