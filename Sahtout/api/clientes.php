<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Ensure database connection from config is used or create a new one matching PC app
// The PC app uses 'noxertez' database.
try {
    $db = new PDO('mysql:host=localhost;dbname=noxertez;charset=utf8mb4', 'noxertez_user', 'Noxertez2024!');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            break;

        case 'save':
            $id = $_POST['id'] ?? null;
            $data = [
                'nombre' => $_POST['nombre'] ?? '',
                'telefono' => $_POST['telefono'] ?? '',
                'email' => $_POST['email'] ?? '',
                'instagram' => $_POST['instagram'] ?? '',
                'direccion' => $_POST['direccion'] ?? '',
                'ciudad' => $_POST['ciudad'] ?? '',
                'codigo_postal' => $_POST['codigo_postal'] ?? '',
                'pais' => $_POST['pais'] ?? '',
                'notas' => $_POST['notas'] ?? '',
                'activo' => 1
            ];

            if ($id) {
                unset($data['activo']); // Don't overwrite activo on update unless specified
                $sql = "UPDATE clientes SET nombre=:nombre, telefono=:telefono, email=:email, instagram=:instagram, 
                        direccion=:direccion, ciudad=:ciudad, codigo_postal=:codigo_postal, pais=:pais, notas=:notas WHERE id=:id";
                $data['id'] = $id;
            } else {
                $sql = "INSERT INTO clientes (nombre, telefono, email, instagram, direccion, ciudad, codigo_postal, pais, notas, activo) 
                        VALUES (:nombre, :telefono, :email, :instagram, :direccion, :ciudad, :codigo_postal, :pais, :notas, :activo)";
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($data);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? 0;
            $stmt = $db->prepare("UPDATE clientes SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'search_products':
            $q = $_GET['q'] ?? '';
            $stmt = $db->prepare("SELECT SKU_REF, NOMBRE, PRECIO, CATEGORIA, STOCK_FISICO FROM productos 
                                 WHERE SKU_REF LIKE ? OR NOMBRE LIKE ? OR CATEGORIA LIKE ? LIMIT 20");
            $stmt->execute(["%$q%", "%$q%", "%$q%"]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get_history':
            $id = $_GET['id'] ?? 0;
            $stmt = $db->prepare("SELECT * FROM pedidos WHERE id_cliente = ? ORDER BY id DESC");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'create_order':
            $id_cliente = $_POST['id_cliente'] ?? 0;
            $sku = $_POST['sku'] ?? '';
            $tipo = $_POST['tipo_trabajo'] ?? '';
            $fecha = date('Y-m-d H:i:s');
            $estado = ($tipo == 'Stock (Listo)') ? 'Listo para entrega' : 'Por empezar';
            $notas = "Trabajo: $tipo";

            if ($tipo == 'Stock (Listo)') {
                $db->prepare("UPDATE productos SET STOCK_FISICO = GREATEST(0, COALESCE(STOCK_FISICO, 0) - 1) WHERE SKU_REF = ?")->execute([$sku]);
            }

            $stmt = $db->prepare("INSERT INTO pedidos (id_cliente, fecha_pedido, fecha_inicio, estado, sku_articulo, notas, prioridad) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_cliente, $fecha, $fecha, $estado, $sku, $notas, 'Verde']);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action ' . $action]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>