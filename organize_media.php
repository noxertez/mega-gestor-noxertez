<?php
$db = new mysqli("localhost", "noxertez_user", "Noxertez2024!", "noxertez");

// 1. Corregir tipos mal clasificados
$db->query("UPDATE mockups_varios SET tipo = 'video' WHERE archivo LIKE '%.mp4%' OR archivo LIKE '%.mov%' OR archivo LIKE '%.avi%'");
$db->query("UPDATE mockups_varios SET tipo = 'imagen' WHERE tipo != 'video'");

// 2. Para los vídeos actuales, limpiar metadatos basura si los tienen
$sql = "UPDATE mockups_varios SET 
        estancia = 'Mix / Varias', 
        decoracion = 'Estilos mezclados', 
        estilo = 'Video IA',
        formato = 'Reel / Vertical'
        WHERE tipo = 'video'";
$db->query($sql);

echo "¡Base de datos organizada! He separado todos los vídeos de las imágenes y les he puesto etiquetas genéricas para que no ensucien tus filtros de fotos.\n";
