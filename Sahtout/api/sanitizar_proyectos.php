<?php
require_once 'config.php';
$db = conectar();

header('Content-Type: text/plain');
echo "🚀 Iniciando Saneamiento de Imágenes de Proyectos...\n\n";

$dir = __DIR__ . '/../uploads/articulos/proyectos/';
if (!is_dir($dir)) {
    die("❌ Error: Directorio no encontrado en $dir\n");
}

// 1. Obtener todos los proyectos de la DB
$stmt = $db->query("SELECT id, FOTO_REFERENCIA FROM futuros_proyectos");
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count_files = 0;
$count_db = 0;

foreach ($proyectos as $p) {
    if (empty($p['FOTO_REFERENCIA'])) continue;
    
    $old_full_path = $p['FOTO_REFERENCIA'];
    $filename = basename(str_replace('\\', '/', $old_full_path));
    
    // SANITIZAR EL NOMBRE: Reemplazar espacios y puntos raros por guiones bajos
    // Mantener solo la última extensión
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name_part = pathinfo($filename, PATHINFO_FILENAME);
    
    $new_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name_part);
    $new_name = preg_replace('/_+/', '_', $new_name); // Evitar múltiples guiones bajos
    $new_filename = $new_name . '.' . strtolower($ext);
    
    if ($filename === $new_filename) continue; // Ya está limpio
    
    $old_file_path = $dir . $filename;
    $new_file_path = $dir . $new_filename;
    
    echo "Processing ID {$p['id']}: $filename -> $new_filename\n";
    
    // Renombrar el archivo físico si existe
    if (file_exists($old_file_path)) {
        if (rename($old_file_path, $new_file_path)) {
            echo "   ✅ Archivo físico renombrado.\n";
            $count_files++;
        } else {
            echo "   ❌ Error renombrando archivo físico.\n";
        }
    } else {
        echo "   ⚠️ Archivo físico no encontrado (ya podría estar renombrado).\n";
    }
    
    // Actualizar la base de datos
    // Mantenemos la estructura de la ruta original (con C:\... si es necesario) para la PC App,
    // o simplemente actualizamos el nombre del archivo al final.
    $new_db_value = str_replace($filename, $new_filename, $old_full_path);
    
    $upd = $db->prepare("UPDATE futuros_proyectos SET FOTO_REFERENCIA = ? WHERE id = ?");
    if ($upd->execute([$new_db_value, $p['id']])) {
        echo "   💾 Base de datos actualizada.\n";
        $count_db++;
    }
}

echo "\n✨ Saneamiento completado.\n";
echo "Archivos procesados: $count_files\n";
echo "Registros DB actualizados: $count_db\n";
?>
