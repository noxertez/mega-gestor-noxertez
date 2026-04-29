<?php
$dir = 'C:/xampp/htdocs/noxertez/Sahtout';
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return;
    $files = array_diff(scandir($dirPath), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    return rmdir($dirPath);
}
if (deleteDir($dir)) {
    echo "LIMPIEZA_COMPLETADA: La carpeta Sahtout ha sido eliminada.";
} else {
    echo "ERROR: No se pudo eliminar la carpeta. Puede que esté abierta en otro programa.";
}
?>
