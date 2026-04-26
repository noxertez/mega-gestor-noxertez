<?php
define('ALLOWED_ACCESS', true);

require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';

$page_class = "legal-page";
$header_file = $project_root . 'includes/header.php';

if (file_exists($header_file)) {
    include $header_file;
} else {
    die('Error: Header file not found.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Cookies - <?php echo $site_title_name; ?></title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/legal.css">
</head>
<body class="legal-page">
    <main>
        <div class="legal-page-container">
            <header class="legal-header">
                <h1>Política de Cookies</h1>
                <p>Información simplificada sobre el uso de tecnologías</p>
            </header>

            <article class="legal-content">
                <h2>1. Uso de Cookies</h2>
                <p><strong><?php echo $site_title_name; ?></strong> informa a los usuarios de que este sitio web **NO utiliza cookies de seguimiento, publicidad ni marketing**.</p>
                
                <p>Nuestra web está diseñada para respetar al máximo la privacidad del usuario y no recopilamos datos de navegación para fines comerciales ni de terceros.</p>

                <h2>2. Cookies Técnicas y de Sesión</h2>
                <p>Únicamente utilizamos **cookies técnicas y estrictamente necesarias** para el funcionamiento del sitio y para garantizar el acceso privado a las áreas restringidas (como el panel de usuario o administración). Estas cookies permiten:</p>
                <ul>
                    <li>Mantener la sesión abierta del usuario registrado.</li>
                    <li>Gestionar de forma segura las funciones de la cuenta privada.</li>
                    <li>Recordar preferencias básicas de navegación (como el idioma seleccionado).</li>
                </ul>
                <p>Al ser cookies de carácter técnico y esencial, no requieren de un banner de consentimiento según la normativa vigente (AEPD), ya que son indispensables para que el servicio solicitado por el usuario funcione correctamente.</p>

                <h2>3. Cookies de Terceros</h2>
                <p>Este sitio web no integra servicios de terceros que carguen cookies de rastreo (como Google Analytics, píxeles de Facebook, etc.).</p>

                <h2>4. Configuración del Navegador</h2>
                <p>Aunque no utilizamos cookies intrusivas, usted puede configurar su navegador para bloquear cualquier tipo de cookie si así lo desea. Tenga en cuenta que, si bloquea las cookies técnicas, no podrá acceder a las áreas privadas por razones de seguridad tecnológica del servidor.</p>
            </article>
        </div>
    </main>

    <?php
    $footer_file = $project_root . 'includes/footer.php';
    if (file_exists($footer_file)) {
        include $footer_file;
    }
    ?>
</body>
</html>
