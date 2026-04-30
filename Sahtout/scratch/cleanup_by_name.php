<?php
require 'api/config.php';
$db = conectar();

// Limpiar duplicados dejando solo uno por cada NOMBRE DE ARCHIVO
$db->query("DELETE t1 FROM mockups_varios t1 
            INNER JOIN mockups_varios t2 
            WHERE t1.id > t2.id 
            AND LOWER(TRIM(t1.archivo)) = LOWER(TRIM(t2.archivo))");

echo "Limpieza final por nombre de archivo completada.";
