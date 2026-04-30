<?php
require_once 'api/config.php';
$db = conectar();
$count = $db->query("SELECT COUNT(*) FROM mockups_varios")->fetchColumn();
echo "Total en mockups_varios: " . $count . "\n";
$samples = $db->query("SELECT * FROM mockups_varios LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($samples);
?>
