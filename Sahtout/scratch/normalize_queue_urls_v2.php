<?php
require_once __DIR__ . '/../api/config.php';
$db = conectar();

echo "Deep cleaning imagen_url in linkedin_queue...\n";

$res = $db->query("SELECT id, imagen_url FROM linkedin_queue")->fetchAll();
foreach ($res as $r) {
    $url = $r['imagen_url'];
    if (!$url) continue;
    
    $new_url = $url;
    // Quitar dominio
    $new_url = str_replace('https://noxertez.com/Sahtout/', '', $new_url);
    
    // Si es solo el nombre de archivo (no tiene / y no es http)
    if (strpos($new_url, '/') === false && strpos($new_url, 'http') === false) {
        // Asumimos que es un mockup general
        $new_url = 'uploads/mockups_varios/' . $new_url;
    }
    
    // Limpiar prefijos ../
    while (strpos($new_url, '../') === 0) {
        $new_url = substr($new_url, 3);
    }
    
    if ($new_url !== $url) {
        echo "Updating ID {$r['id']}: $url -> $new_url\n";
        $stmt = $db->prepare("UPDATE linkedin_queue SET imagen_url = ? WHERE id = ?");
        $stmt->execute([$new_url, $r['id']]);
    }
}
echo "Done!\n";
