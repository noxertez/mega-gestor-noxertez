<?php
// Quitar el die para ver todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Comprobando extensión IMAP... ";
echo function_exists('imap_open') ? "✅ OK<br>" : "❌ NO INSTALADA<br>";

echo "2. Intentando conectar...<br>";

$conn = imap_open(
    '{imap.gmail.com:993/imap/ssl}INBOX',
    'noxertez@gmail.com',
    'gfpwdyrsqlczbrxt',  // sin espacios
    0,
    1
);

if ($conn) {
    echo "✅ Conexión exitosa!<br>";
    imap_close($conn);
} else {
    echo "❌ Error: <br>";
    print_r(imap_errors());
    print_r(imap_alerts());
}
?>
