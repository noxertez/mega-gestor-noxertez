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
    <title>Aviso Legal - <?php echo $site_title_name; ?></title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/legal.css">
</head>
<body class="legal-page">
    <main>
        <div class="legal-page-container">
            <header class="legal-header">
                <h1>Aviso Legal</h1>
                <p>Información General y Condiciones de Uso Privado</p>
            </header>

            <div class="placeholder-warning">
                <strong>Nota Importante:</strong> Esta página contiene campos pendientes de completar por el titular del sitio web. Por favor, rellene los datos marcados como [PENDIENTE].
            </div>

            <article class="legal-content">
                <h2>1. Datos Identificativos</h2>
                <p>En cumplimiento con el deber de información recogido en el artículo 10 de la Ley 34/2002, de 11 de julio, de Servicios de la Sociedad de la Información y del Comercio Electrónico (LSSI-CE), se facilitan los siguientes datos:</p>
                <ul>
                    <li><strong>Titular:</strong> [PENDIENTE - Nombre Completo o Razón Social]</li>
                    <li><strong>NIF/CIF:</strong> [PENDIENTE - Número de Identificación Fiscal]</li>
                    <li><strong>Domicilio Social:</strong> [PENDIENTE - Dirección completa]</li>
                    <li><strong>Correo electrónico de contacto:</strong> [PENDIENTE - Email de contacto]</li>
                </ul>

                <h2>2. Naturaleza del Sitio Web</h2>
                <p><strong><?php echo $site_title_name; ?></strong> es una plataforma diseñada principalmente para **uso privado y gestión interna**, que incluye una pequeña sección pública destinada a la exhibición de artesanías y servicios.</p>
                <p>El acceso a la zona pública es libre, mientras que el acceso a las funciones de gestión y áreas privadas está restringido a usuarios autorizados mediante credenciales de acceso seguras.</p>

                <h2>3. Usuarios y Aceptación</h2>
                <p>El acceso y/o uso de este portal de <strong><?php echo $site_title_name; ?></strong> atribuye la condición de USUARIO, que acepta, desde dicho acceso y/o uso, las Condiciones Generales de Uso aquí reflejadas.</p>

                <h2>4. Uso del Portal</h2>
                <p>El USUARIO se compromete a hacer un uso adecuado de los contenidos y servicios (zona pública) que <strong><?php echo $site_title_name; ?></strong> ofrece. Queda estrictamente prohibido:</p>
                <ul>
                    <li>Intentar acceder a las áreas privadas del CMS sin la debida autorización.</li>
                    <li>Realizar actividades ilícitas, ilegales o contrarias a la buena fe y al orden público.</li>
                    <li>Interferir en el correcto funcionamiento técnico de la plataforma.</li>
                </ul>

                <h2>5. Propiedad Intelectual e Industrial</h2>
                <p><strong><?php echo $site_title_name; ?></strong> es titular de todos los derechos de propiedad intelectual e industrial de su página web, así como de los elementos contenidos en la misma (imágenes de artesanías, textos, logotipos, etc.).</p>
                <p>Queda expresamente prohibida la reproducción, distribución y comunicación pública de cualquier contenido de este sitio con fines comerciales sin la autorización expresa de <strong><?php echo $site_title_name; ?></strong>.</p>

                <h2>6. Exclusión de Garantías y Responsabilidad</h2>
                <p><strong><?php echo $site_title_name; ?></strong> no se hace responsable de daños de cualquier naturaleza que pudieran ocasionar: errores en los contenidos, falta de disponibilidad del portal o transmisión de virus informáticos a pesar de haber adoptado las medidas técnicas necesarias.</p>

                <h2>7. Cookies</h2>
                <p>Este sitio web **no recolecta cookies de rastreo ni publicidad**. Solo utiliza cookies técnicas estrictamente necesarias para la gestión de la sesión privada. Para más información, consulte nuestra Política de Cookies.</p>

                <h2>8. Legislación Aplicable</h2>
                <p>La relación entre <strong><?php echo $site_title_name; ?></strong> y el USUARIO se regirá por la normativa española vigente y cualquier controversia se someterá a los Juzgados y tribunales de la ciudad de [PENDIENTE - Ciudad de Jurisdicción].</p>
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
