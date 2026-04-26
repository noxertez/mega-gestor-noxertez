<?php
/**
 * API Ventas - Gestión de Plataformas de Venta
 */

$db = conectar();
$accion = $_GET['accion'] ?? '';

switch ($accion) {
    case 'get_plataformas':
        try {
            $stmt = $db->query("SELECT * FROM plataformas_config ORDER BY orden");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'get_datos':
        try {
            // Obtener todas las plataformas configuradas
            $stmt_plats = $db->query("SELECT nombre_columna FROM plataformas_config ORDER BY orden");
            $plataformas = $stmt_plats->fetchAll(PDO::FETCH_COLUMN);

            // Construir la consulta dinámica
            $cols = "p.SKU_REF, p.NOMBRE, p.CATEGORIA, p.STOCK as STOCK_ONLINE, p.STOCK_FISICO, p.FOTO_PORTADA";
            foreach ($plataformas as $plat) {
                $cols .= ", pv.{$plat}_ESTADO, pv.{$plat}_PRECIO, pv.{$plat}_URL";
            }

            $query = "SELECT $cols FROM productos p 
                      LEFT JOIN plataformas_ventas pv ON p.SKU_REF = pv.SKU_BASE 
                      WHERE p.ES_VARIANTE = 'BASE' 
                      ORDER BY p.NOMBRE ASC";
            
            $stmt = $db->query($query);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'get_art':
        $sku = $_GET['sku'] ?? '';
        if (!$sku) {
            http_response_code(400);
            echo json_encode(['error' => 'SKU no proporcionado']);
            break;
        }
        try {
            $stmt_prod = $db->prepare("SELECT SKU_REF, NOMBRE, STOCK as STOCK_ONLINE, STOCK_FISICO, FOTO_PORTADA, PRECIO FROM productos WHERE SKU_REF = ?");
            $stmt_prod->execute([$sku]);
            $producto = $stmt_prod->fetch();

            if (!$producto) {
                http_response_code(404);
                echo json_encode(['error' => 'Producto no encontrado']);
                break;
            }

            $stmt_ventas = $db->prepare("SELECT * FROM plataformas_ventas WHERE SKU_BASE = ?");
            $stmt_ventas->execute([$sku]);
            $ventas = $stmt_ventas->fetch() ?: [];

            echo json_encode([
                'producto' => $producto,
                'ventas' => $ventas
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            break;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $sku = $body['sku_base'] ?? '';
        
        if (!$sku) {
            http_response_code(400);
            echo json_encode(['error' => 'SKU_BASE faltante']);
            break;
        }

        try {
            $db->beginTransaction();

            // 1. Actualizar tabla productos y articulos (Stock Online y Físico)
            $stock_online = isset($body['stock_online']) ? (int)$body['stock_online'] : null;
            $stock_fisico = isset($body['stock_fisico']) ? (int)$body['stock_fisico'] : null;

            if ($stock_online !== null || $stock_fisico !== null) {
                $sets = [];
                $params_p = [];
                if ($stock_online !== null) {
                    $sets[] = "STOCK = ?";
                    $params_p[] = $stock_online;
                }
                if ($stock_fisico !== null) {
                    $sets[] = "STOCK_FISICO = ?";
                    $params_p[] = $stock_fisico;
                }
                
                if (!empty($sets)) {
                    $params_p[] = $sku;
                    $stmt1 = $db->prepare("UPDATE productos SET " . implode(', ', $sets) . " WHERE SKU_REF = ?");
                    $stmt1->execute($params_p);
                    
                    if ($stock_online !== null) {
                        $stmt2 = $db->prepare("UPDATE articulos SET stock = ? WHERE referencia = ?");
                        $stmt2->execute([$stock_online, $sku]);
                    }
                }
            }

            // 2. Actualizar tabla plataformas_ventas
            unset($body['sku_base']);
            unset($body['stock_online']);
            unset($body['stock_fisico']);

            if (!empty($body)) {
                // Sanitizar valores: convertir campos vacíos que parecen numéricos a 0
                foreach ($body as $key => &$val) {
                    if ($val === '') {
                        // Si termina en _PRECIO o contiene CANTIDAD o es UNIDADES_VENTA, poner 0
                        if (strpos($key, '_PRECIO') !== false || 
                            strpos($key, 'CANTIDAD') !== false || 
                            $key === 'UNIDADES_VENTA' ||
                            strpos($key, 'vntd') !== false) { // Parche preventivo para el error reportado
                            $val = 0;
                        }
                    }
                }

                // Verificar si existe
                $stmt_check = $db->prepare("SELECT 1 FROM plataformas_ventas WHERE SKU_BASE = ?");
                $stmt_check->execute([$sku]);
                $exists = $stmt_check->fetch();

                if ($exists) {
                    $cols = [];
                    $params = [];
                    foreach ($body as $key => $val) {
                        $cols[] = "$key = ?";
                        $params[] = $val;
                    }
                    $params[] = $sku;
                    $stmt = $db->prepare("UPDATE plataformas_ventas SET " . implode(', ', $cols) . " WHERE SKU_BASE = ?");
                    $stmt->execute($params);
                } else {
                    $cols = ['SKU_BASE'];
                    $placeholders = ['?'];
                    $params = [$sku];
                    foreach ($body as $key => $val) {
                        $cols[] = $key;
                        $placeholders[] = '?';
                        $params[] = $val;
                    }
                    $stmt = $db->prepare("INSERT INTO plataformas_ventas (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")");
                    $stmt->execute($params);
                }
            }

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
        break;
}
?>