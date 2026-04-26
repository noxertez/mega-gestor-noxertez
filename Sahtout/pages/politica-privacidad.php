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
    <title>Política de Privacidad - <?php echo $site_title_name; ?></title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/legal.css">
</head>
<body class="legal-page">
    <main>
        <div class="legal-page-container">
            <header class="legal-header">
                <h1>Política de Privacidad</h1>
                <p>Información detallada sobre el tratamiento de tus datos personales</p>
            </header>

            <div class="placeholder-warning">
                <strong>Nota Importante:</strong> Esta página contiene campos pendientes de completar por el titular del sitio web. Por favor, rellene los datos marcados como [PENDIENTE].
            </div>

            <article class="legal-content">
                <h2>1. Introducción</h2>
                <p>En cumplimiento de lo establecido en el Reglamento (UE) 2016/679 (RGPD) y la Ley Orgánica 3/2018 (LOPDGDD), <strong><?php echo $site_title_name; ?></strong> informa al USUARIO de la existencia de un tratamiento de datos de carácter personal.</p>

                <h2>2. Identificación del Responsable del Tratamiento</h2>
                <ul>
                    <li><strong>Nombre del Responsable:</strong> [PENDIENTE - Nombre Completo o Razón Social]</li>
                    <li><strong>CIF/NIF:</strong> [PENDIENTE - Número de Identificación Fiscal]</li>
                    <li><strong>Domicilio Social:</strong> [PENDIENTE - Dirección completa]</li>
                    <li><strong>Email de contacto para temas de privacidad:</strong> [PENDIENTE - Email de contacto]</li>
                </ul>

                <h2>3. Finalidades del Tratamiento</h2>
                <p>Los datos que el usuario nos facilite a través del sitio web serán tratados para las siguientes finalidades:</p>
                <ul>
                    <li>Gestionar el registro de usuarios y el acceso a los servicios de la plataforma.</li>
                    <li>Gestionar los pedidos realizados a través de la tienda online (si aplica).</li>
                    <li>Atender consultas, quejas o solicitudes de información técnica o comercial.</li>
                    <li>Enviar comunicaciones comerciales (newsletter) si el usuario lo ha consentido expresamente.</li>
                    <li>Cumplir con las obligaciones legales aplicables.</li>
                </ul>

                <h2>4. Base Legal</h2>
                <p>La base legal para el tratamiento de los datos es:</p>
                <ul>
                    <li><strong>Contrato:</strong> La ejecución del contrato de servicios o la compra de productos.</li>
                    <li><strong>Consentimiento:</strong> Para el envío de publicidad o el uso de cookies no técnicas.</li>
                    <li><strong>Interés legítimo:</strong> Para la gestión administrativa interna del sitio.</li>
                    <li><strong>Obligación legal:</strong> Cumplimiento de normativas tributarias y de comercio.</li>
                </ul>

                <h2>5. Plazo de Conservación de los Datos</h2>
                <p>Los datos se conservarán mientras exista un interés mutuo para mantener el fin del tratamiento y cuando ya no sea necesario para tal fin, se suprimirán con medidas de seguridad adecuadas para garantizar la seudonimización de los datos o la destrucción total de los mismos, o bien se conservarán bloqueados durante los plazos legales requeridos.</p>

                <h2>6. Destinatarios de los Datos</h2>
                <p>No se comunicarán los datos a terceros, salvo obligación legal o en los casos necesarios para la prestación del servicio (por ejemplo, empresas de mensajería para envíos de pedidos).</p>

                <h2>7. Derechos del Usuario</h2>
                <p>El USUARIO puede ejercer sus derechos en cualquier momento:</p>
                <ul>
                    <li><strong>Acceso:</strong> Saber qué datos estamos tratando sobre usted.</li>
                    <li><strong>Rectificación:</strong> Corregir datos inexactos o incompletos.</li>
                    <li><strong>Supresión:</strong> Solicitar que eliminemos sus datos.</li>
                    <li><strong>Oposición:</strong> Oponerse a que tratemos sus datos para fines específicos.</li>
                    <li><strong>Limitación:</strong> Limitar temporalmente el tratamiento bajo ciertas condiciones.</li>
                    <li><strong>Portabilidad:</strong> Recibir sus datos en un formato estructurado y legible.</li>
                </ul>
                <p>Para ejercer estos derechos, debe dirigir un escrito acompañando una copia de su DNI al email: <strong>[PENDIENTE - Email de contacto]</strong>.</p>
                <p>Asimismo, el usuario tiene derecho a presentar una reclamación ante la Agencia Española de Protección de Datos (www.aepd.es) si considera que se han vulnerado sus derechos.</p>

                <h2>8. Seguridad</h2>
                <p><strong><?php echo $site_title_name; ?></strong> ha adoptado las medidas técnicas y organizativas necesarias para garantizar la seguridad de los datos de carácter personal y evitar su alteración, pérdida, tratamiento o acceso no autorizado, habida cuenta del estado de la tecnología, la naturaleza de los datos almacenados y los riesgos a que están expuestos.</p>
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
