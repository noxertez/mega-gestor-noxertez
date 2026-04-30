<?php
require 'api/config.php';
$db = conectar();

// 1. Limpiar duplicados por nombre de archivo y SKU (dejando solo el ID más bajo)
$db->query("DELETE t1 FROM mockups_varios t1 
            INNER JOIN mockups_varios t2 
            WHERE t1.id > t2.id 
            AND TRIM(t1.archivo) = TRIM(t2.archivo) 
            AND TRIM(t1.asignado_a_sku) = TRIM(t2.asignado_a_sku)");

// 2. Limpiar duplicados por ruta (por si acaso)
$db->query("DELETE t1 FROM mockups_varios t1 
            INNER JOIN mockups_varios t2 
            WHERE t1.id > t2.id 
            AND TRIM(t1.ruta) = TRIM(t2.ruta)");

echo "Limpieza de base de datos completada.";
