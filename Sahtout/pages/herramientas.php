<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
// pages/herramientas.php - Panel con estadisticas del dia
include('../includes/header.php');
$db = new PDO('mysql:host=localhost;dbname=noxertez;charset=utf8mb4','noxertez_user','Noxertez2024!');

$stats = $db->query(
    'SELECT
       COUNT(*) as total_pedidos,
       COALESCE(SUM(total),0) as facturado,
       COUNT(DISTINCT nombre_cliente) as clientes
     FROM pedidos
     WHERE DATE(fecha_pedido) = CURDATE()
     AND estado NOT IN ("cancelado","borrador")'
)->fetch();

$stock_bajo = $db->query(
    'SELECT COUNT(*) as n FROM articulos WHERE stock <= stock_minimo AND activo=1'
)->fetch()['n'];

$tareas = $db->query(
    'SELECT COUNT(*) as n FROM tareas WHERE completada=0'
)->fetch()['n'];
?>

<div class='panel-titulo'><h1>Panel Principal</h1></div>

<!-- Tarjetas estilo WoW con las estadisticas del dia -->
<div class='grid-tarjetas'>
    <div class='tarjeta-wow'>
        <div class='tarjeta-icono'>📦</div>
        <div class='tarjeta-numero'><?= $stats['total_pedidos'] ?></div>
        <div class='tarjeta-label'>Pedidos hoy</div>
    </div>
    <div class='tarjeta-wow'>
        <div class='tarjeta-icono'>💰</div>
        <div class='tarjeta-numero'><?= number_format($stats['facturado'],2) ?> EUR</div>
        <div class='tarjeta-label'>Facturado hoy</div>
    </div>
    <div class='tarjeta-wow <?= $stock_bajo > 0 ? "tarjeta-alerta" : "" ?>'>
        <div class='tarjeta-icono'>⚠️</div>
        <div class='tarjeta-numero'><?= $stock_bajo ?></div>
        <div class='tarjeta-label'>Stock bajo</div>
    </div>
    <div class='tarjeta-wow'>
        <div class='tarjeta-icono'>✅</div>
        <div class='tarjeta-numero'><?= $tareas ?></div>
        <div class='tarjeta-label'>Tareas pendientes</div>
    </div>
</div>

<?php
include('../includes/footer.php'); ?>




