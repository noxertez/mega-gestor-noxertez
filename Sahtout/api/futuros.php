<?php
require_once 'config.php';
$db = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true);

if ($metodo === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'getCategories') {
        $stmt = $db->query("SELECT DISTINCT CATEGORIA FROM futuros_proyectos WHERE CATEGORIA IS NOT NULL AND CATEGORIA != '' ORDER BY CATEGORIA ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        exit();
    }

    if ($action === 'getRealizados') {
        // Proyectos que ya han sido terminados (estado entregado en pedidos)
        $sql = "SELECT fp.*, p.numero_pedido, p.fecha_entrega, p.total as precio_final
                FROM futuros_proyectos fp
                JOIN pedidos p ON p.futuro_id = fp.id
                WHERE p.estado = 'entregado'
                ORDER BY p.fecha_entrega DESC";
        $stmt = $db->query($sql);
        echo json_encode($stmt->fetchAll());
        exit();
    }

    $filtro = $_GET['filtro'] ?? '';
    $categoria = $_GET['categoria'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

    // Solo mostrar proyectos que NO estén entregados
    $sql = "SELECT fp.* FROM futuros_proyectos fp 
            WHERE NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.futuro_id = fp.id AND p.estado = 'entregado')";
    
    $params = [];
    
    if ($filtro) {
        $sql .= " AND (fp.NOMBRE LIKE ? OR fp.CATEGORIA LIKE ?)";
        $params[] = "%$filtro%";
        $params[] = "%$filtro%";
    }
    if ($categoria) {
        $sql .= " AND fp.CATEGORIA = ?";
        $params[] = $categoria;
    }
    
    // Si no hay filtros, solo devolvemos algo si se pide full o limit
    if (!$filtro && !$categoria && !isset($_GET['full']) && !$limit && $action !== 'all') {
        echo json_encode([]);
        exit();
    }

    $sql .= " ORDER BY fp.id DESC";
    if ($limit) {
        $sql .= " LIMIT $limit";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit();
}



elseif ($metodo === 'POST' || $metodo === 'PUT') {
    // Para multipart/form-data, usamos $_POST directamente
    $data = !empty($_POST) ? $_POST : $body;
    
    $id = $data['id'] ?? null;
    $nombre = $data['NOMBRE'] ?? '';
    $cat = $data['CATEGORIA'] ?? '';
    $sub = $data['SUBCATEGORIA'] ?? '';
    $marca = $data['MARCA'] ?? '';
    $estado = $data['ESTADO'] ?? 'PENDIENTE';
    $desc = $data['DESCRIPCION'] ?? '';
    $precio = $data['PRECIO'] ?? '';
    $color = $data['COLOR'] ?? '';
    $fest = $data['FESTIVIDAD'] ?? '';
    $sku = $data['SKU'] ?? '';
    $real = $data['UNIDADES_REALIZADAS'] ?? 0;
    
    // Gestión de Imagen
    $foto_referencia = $data['FOTO_REFERENCIA'] ?? '';
    
    if (isset($_FILES['IMAGEN_UPLOAD']) && $_FILES['IMAGEN_UPLOAD']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/articulos/proyectos/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['IMAGEN_UPLOAD']['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['IMAGEN_UPLOAD']['tmp_name'], $target_file)) {
            $foto_referencia = 'uploads/articulos/proyectos/' . $filename;
        }
    }

    if ($id) {
        // ACTUALIZAR
        $stmt = $db->prepare("UPDATE futuros_proyectos SET 
            NOMBRE=:nombre, CATEGORIA=:cat, SUBCATEGORIA=:sub, MARCA=:marca, 
            ESTADO=:estado, FOTO_REFERENCIA=:foto, DESCRIPCION=:desc, 
            PRECIO=:precio, COLOR=:color, FESTIVIDAD=:fest, SKU=:sku, 
            UNIDADES_REALIZADAS=:real 
            WHERE id=:id");
        $stmt->execute([
            'id'     => $id,
            'nombre' => $nombre,
            'cat'    => $cat,
            'sub'    => $sub,
            'marca'  => $marca,
            'estado' => $estado,
            'foto'   => $foto_referencia,
            'desc'   => $desc,
            'precio' => $precio,
            'color'  => $color,
            'fest'   => $fest,
            'sku'    => $sku,
            'real'   => $real
        ]);
        echo json_encode(['ok' => true]);
    } else {
        // INSERTAR
        $stmt = $db->prepare("INSERT INTO futuros_proyectos 
            (NOMBRE, CATEGORIA, SUBCATEGORIA, MARCA, ESTADO, FOTO_REFERENCIA, DESCRIPCION, PRECIO, COLOR, FESTIVIDAD, SKU, UNIDADES_REALIZADAS) 
            VALUES (:nombre, :cat, :sub, :marca, :estado, :foto, :desc, :precio, :color, :fest, :sku, :real)");
        $stmt->execute([
            'nombre' => $nombre,
            'cat'    => $cat,
            'sub'    => $sub,
            'marca'  => $marca,
            'estado' => $estado,
            'foto'   => $foto_referencia,
            'desc'   => $desc,
            'precio' => $precio,
            'color'  => $color,
            'fest'   => $fest,
            'sku'    => $sku,
            'real'   => $real
        ]);
        echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
    }
}

elseif ($metodo === 'DELETE') {
    $id = $_GET['id'] ?? $body['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare('DELETE FROM futuros_proyectos WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
    }
}
?>
