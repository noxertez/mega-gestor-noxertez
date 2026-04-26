<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
include('../includes/header.php');
?>

<div class="panel-titulo">
    <h1><i class="fas fa-project-diagram"></i> Futuros Proyectos</h1>
    <p>Gestión de ideas y próximos lanzamientos de artesanía.</p>
</div>

<div class="grid-tarjetas">
    <div class="tarjeta-wow">
        <div class="tarjeta-icono">💡</div>
        <div class="tarjeta-numero">Próximamente</div>
        <div class="tarjeta-label">Nuevos Diseños</div>
    </div>
</div>

<div style="padding: 20px; color: #fff; background: rgba(0,0,0,0.5); border-radius: 8px; margin-top: 20px;">
    <h3>Notas del Artesano</h3>
    <textarea style="width: 100%; height: 200px; background: #1a1a1a; color: #00ff00; border: 1px solid #444; padding: 10px; font-family: monospace;" placeholder="Escribe aquí tus ideas de proyectos..."></textarea>
</div>

<?php
// include('../includes/footer.php'); ?>
</body>
</html>



