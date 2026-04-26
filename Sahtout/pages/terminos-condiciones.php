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
    <title>Términos y Condiciones - <?php echo $site_title_name; ?></title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/legal.css">
</head>
<body class="legal-page">
    <main>
        <div class="legal-page-container">
            <header class="legal-header">
                <h1>Términos y Condiciones</h1>
                <p>Condiciones generales de uso y acceso privado</p>
            </header>

            <div class="placeholder-warning">
                <strong>Nota Importante:</strong> Esta página contiene campos pendientes de completar por el titular del sitio web. Por favor, rellene los datos marcados como [PENDIENTE].
            </div>

            <article class="legal-content">
                <h2>1. Objeto y Ámbito</h2>
                <p>Las presentes condiciones regulan el uso de este sitio web, el cual tiene una **naturaleza predominantemente privada y de gestión interna**. El sitio ofrece una sección pública informativa sobre artesanías y servicios proporcionados por <strong><?php echo $site_title_name; ?></strong>.</p>

                <h2>2. Uso del Sitio</h2>
                <p>Los usuarios pueden navegar por la sección pública del sitio conforme a la buena fe y las leyes vigentes. No obstante:</p>
                <ul>
                    <li>El acceso a las áreas del CMS y gestión es estrictamente **privado**.</li>
                    <li>Cualquier intento de acceso no autorizado a las funciones de administración será registrado por seguridad.</li>
                </ul>

                <h2>3. Registro y Privacidad</h2>
                <p>Para acceder a las funciones privadas, el usuario debe poseer una cuenta autorizada. <strong><?php echo $site_title_name; ?></strong> no utiliza cookies de rastreo ni recolecta datos de navegación con fines comerciales.</p>
                <p>Para la gestión técnica de las sesiones privadas, solo se utilizan cookies de carácter técnico e indispensable.</p>

                <h2>4. Precios e Información Pública</h2>
                <p>En caso de que en la sección pública se muestren precios, estos serán de carácter informativo, pudiendo variar según la personalización del trabajo artesanal.</p>

                <h2>5. Propiedad Intelectual</h2>
                <p>Todos los diseños, fotografías de artesanías y contenidos exhibidos son propiedad de <strong><?php echo $site_title_name; ?></strong>. No se permite su copia o uso sin consentimiento previo.</p>

                <h2>6. Ley Aplicable y Jurisdicción</h2>
                <p>Estas condiciones se rigen por la legislación española. Para cualquier controversia, las partes se someten a los juzgados y tribunales de la ciudad de [PENDIENTE - Ciudad de Jurisdicción].</p>

                <h2>7. Contacto</h2>
                <p>Para cualquier duda sobre el uso de la plataforma, puede contactar a través del email: <strong>[PENDIENTE - Email de contacto]</strong>.</p>
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
