<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();

// Corregir las estancias con descripciones largas
$stmt = $db->prepare("UPDATE mockups_varios SET estancia = 'Cafetería realista' WHERE estancia LIKE '%cafetería%' OR estancia LIKE '%cafeteria%'");
$stmt->execute();
echo "Cafeterias: " . $stmt->rowCount() . "\n";

$stmt = $db->prepare("UPDATE mockups_varios SET estancia = 'Cafetería realista' WHERE estancia LIKE '%acogedora%'");
$stmt->execute();
echo "Acogedora: " . $stmt->rowCount() . "\n";

// Mostrar resultado final
$estancias = $db->query("SELECT DISTINCT estancia FROM mockups_varios WHERE estancia != '' ORDER BY estancia")->fetchAll(PDO::FETCH_COLUMN);
echo "\nEstancias limpias:\n";
foreach ($estancias as $e) echo "  - $e\n";
?>
