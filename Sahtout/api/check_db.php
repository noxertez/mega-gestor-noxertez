<?php
require_once 'config.php';
$db = conectar();
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(", ", $tables) . "\n";

foreach ($tables as $table) {
    if (strpos($table, 'plataformas') !== false || $table == 'productos' || $table == 'articulos') {
        echo "\nStructure of $table:\n";
        $cols = $db->query("DESCRIBE $table")->fetchAll();
        foreach ($cols as $col) {
            echo " - {$col['Field']} ({$col['Type']})\n";
        }
    }
}
?>
