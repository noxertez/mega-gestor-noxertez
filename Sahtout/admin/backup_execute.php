<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'includes/config.php';
require_once $project_root . 'includes/config.settings.php';

// Verificar permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Solo administradores.']);
    exit;
}

// Configuración
$backup_dir = $project_root . 'backups/';

// Usar ruta personalizada si existe y es válida
if (!empty($backup_path) && is_dir($backup_path)) {
    $backup_dir = rtrim($backup_path, '/\\') . DIRECTORY_SEPARATOR;
}

if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$timestamp = date('Y-m-d_H-i-s');
$db_backup_file = $backup_dir . "db_backup_{$timestamp}.sql";
$zip_file = $backup_dir . "noxertez_backup_{$timestamp}.zip";

// 1. Exportar Base de Datos
$mysqldump_path = "C:\\xampp\\mysql\\bin\\mysqldump.exe";
$command = sprintf(
    '"%s" --user=%s --password=%s --host=%s %s > "%s"',
    $mysqldump_path,
    $db_user,
    $db_pass,
    $db_host,
    $db_auth,
    $db_backup_file
);

exec($command, $output, $return_var);

if ($return_var !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al exportar la base de datos. Verifique la ruta de mysqldump.']);
    exit;
}

// 2. Comprimir Archivos (Web + DB Dump)
$zip = new ZipArchive();
if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($project_root),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        // Omitir directorios (se añaden automáticamente)
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($project_root));

            // EXCLUIR carpeta de backups para evitar recursión infinita y backups pesados
            if (strpos($relativePath, 'backups' . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }
            // EXCLUIR logs y cache si es necesario
            if (strpos($relativePath, 'logs' . DIRECTORY_SEPARATOR) === 0 || strpos($relativePath, 'vendor' . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }

            $zip->addFile($filePath, $relativePath);
        }
    }
    
    // Añadir el dump de la DB explícitamente si no se incluyó por estar en backups
    $zip->addFile($db_backup_file, 'database_dump.sql');
    
    $zip->close();
    
    // Eliminar el archivo SQL temporal después de añadirlo al ZIP
    unlink($db_backup_file);

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Copia de seguridad creada correctamente.',
        'download_url' => $base_path . 'backups/' . basename($zip_file),
        'filename' => basename($zip_file)
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al crear el archivo ZIP.']);
}
?>
