<?php
require 'api/config.php';
$db = conectar();
$db->query("ALTER TABLE mockups_varios MODIFY COLUMN calidad VARCHAR(50) DEFAULT 'revisar'");
$db->query("ALTER TABLE mockups_varios MODIFY COLUMN tipo VARCHAR(50) DEFAULT 'imagen'");
$db->query("ALTER TABLE mockups_varios MODIFY COLUMN formato VARCHAR(50) NULL");
echo "Tabla modificada correctamente.";
