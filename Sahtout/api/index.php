<?php
// Suprimir errores para que no rompan el JSON
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Buffer de salida: captura cualquier output accidental

require_once 'config.php';

// Leer la ruta solicitada: /api/articulos, /api/pedidos, etc.
$ruta = $_GET['ruta'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];


// Enrutar a cada modulo
switch($ruta) {
    case 'articulos':   require 'articulos.php';   break;
    case 'pedidos':     require 'pedidos.php';     break;
    case 'clientes':    require 'clientes.php';    break;
    case 'stock':       require 'stock.php';       break;
    case 'ventas':      require 'ventas.php';      break;
    case 'tareas':      require 'tareas.php';      break;
    case 'notificaciones': require 'notificaciones.php'; break;
    case 'n8n':              require 'n8n.php';              break;
    case 'futuros':          require 'futuros.php';          break;
    case 'influencers':      require 'influencers.php';      break;
    case 'sync_productos':   require 'sync_productos.php';   break;
    case 'asistente':        require 'asistente.php';        break;
    // ─── Módulo Flujo de Pedidos ───────────────────────
    case 'flujo':
    case 'flujo_plantillas':
    case 'flujo_nodo':
    case 'flujo_incidencia':
    case 'flujo_analytics':
    case 'flujo_dashboard_stats':
    case 'flujo_plantilla_save_all':
    case 'flujo_sync_kanban': require 'flujo.php';           break;
    case 'pinterest':         require 'pinterest_api.php';  break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada: ' . $ruta]);
}
?>