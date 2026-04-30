<?php
require 'api/config.php';
$db = conectar();
echo "Articulos: " . $db->query("SELECT COUNT(*) FROM articulos")->fetchColumn() . "\n";
echo "Productos: " . $db->query("SELECT COUNT(*) FROM productos")->fetchColumn() . "\n";
echo "Mockups Varios: " . $db->query("SELECT COUNT(*) FROM mockups_varios")->fetchColumn() . "\n";
