<?php
/**
 * api/sync_productos.php
 * Sincroniza la tabla `productos` (app PC) hacia `articulos` (CMS web).
 * Actualizado para incluir foto_portada, galeria y descripcion en ON DUPLICATE KEY UPDATE.
 */

require_once 'config.php';
$db = conectar();

// [ASEGURAR SCHEMA] Añadir columnas si no existen
try {
    $db->query("ALTER TABLE articulos ADD COLUMN sku_base VARCHAR(100) NULL AFTER referencia");
} catch (Exception $e) {}
try {
    $db->query("ALTER TABLE articulos ADD COLUMN mockup TEXT NULL AFTER galeria");
} catch (Exception $e) {}

/**
 * Convierte una ruta local de Windows (C:\Users\...\imagenes\...\archivo.jpg)
 * en una ruta web relativa (uploads/articulos/imagenes/.../archivo.jpg)
 * para que sea accesible desde el navegador.
 */
function normalizar_ruta_imagen(string $ruta): string {
    if (empty($ruta)) return '';
    // Normalizar separadores
    $clean = str_replace('\\', '/', $ruta);

    // Si ya es relativa (no empieza con letra de unidad), devolver limpia
    if (!preg_match('/^[a-zA-Z]:\//', $clean)) {
        // Asegurar que no tiene dobles barras
        return ltrim($clean, '/');
    }

    // Si contiene repo_pc, lo quitamos para unificar todas las imágenes en la carpeta principal
    $clean = str_ireplace('/repo_pc/', '/', $clean);

    // Buscar el marcador 'uploads/' dentro de la ruta
    $pos = stripos($clean, '/uploads/');
    if ($pos !== false) {
        return substr($clean, $pos + 1); // Devuelve: uploads/articulos/imagenes/...
    }

    // Buscar el marcador 'imagenes/' como fallback
    $pos = stripos($clean, '/imagenes/');
    if ($pos !== false) {
        return 'uploads/articulos' . substr($clean, $pos);
    }

    // Último recurso: devolver solo el nombre del archivo
    return 'uploads/articulos/imagenes/' . basename($clean);
}

function asegurar_archivo_fisico(string $ruta_origen, string $ruta_relativa_destino): void {
    if (empty($ruta_origen) || empty($ruta_relativa_destino)) return;
    
    // Ruta física de destino en XAMPP
    $base_server = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    $target_path = $base_server . str_replace('/', DIRECTORY_SEPARATOR, $ruta_relativa_destino);
    
    // Normalizar ruta origen (por si tiene /)
    $source_path = str_replace('/', DIRECTORY_SEPARATOR, $ruta_origen);
    
    // Si el origen existe y es accesible desde PHP (en la misma máquina)
    if (file_exists($source_path) && is_readable($source_path)) {
        
        // Si ya existe en el servidor, comprobamos si el de origen es más nuevo
        if (file_exists($target_path)) {
            // Solo copiamos si el origen es más reciente (o si el tamaño es distinto para asegurar)
            if (filemtime($source_path) <= filemtime($target_path) && filesize($source_path) === filesize($target_path)) {
                return; // Ya está actualizado
            }
        }

        // Asegurar que la subcarpeta de destino existe
        $dir = dirname($target_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Copiar el archivo al servidor (sobrescribir si ya existe)
        @copy($source_path, $target_path);
        // Tocar el archivo de destino para igualar la fecha de modificación si es posible
        @touch($target_path, filemtime($source_path));
    }
}

if ($metodo === 'GET') {
    $sql_nuevos = "
        SELECT COUNT(*) AS nuevos
        FROM productos p
        WHERE p.SKU_REF IS NOT NULL
          AND p.SKU_REF <> ''
          AND NOT EXISTS (SELECT 1 FROM articulos a WHERE a.referencia = p.SKU_REF)
    ";
    $stmtN = $db->query($sql_nuevos);
    $nuevos = (int)$stmtN->fetch()['nuevos'];

    echo json_encode(['ok' => true, 'pendientes' => $nuevos]);

} elseif ($metodo === 'POST') {
    $sql = "
        SELECT
            p.SKU_REF                         AS referencia,
            p.SKU_BASE                        AS sku_base,
            p.NOMBRE                          AS nombre,
            p.COLOR                           AS color,
            p.DIMENSIONES                     AS dimensiones,
            p.PRECIO                          AS precio,
            p.MARCA                           AS marca,
            p.CATEGORIA                       AS categoria,
            p.SUBCATEGORIA                    AS subcategoria,
            p.STOCK                           AS stock_str,
            p.STOCK_FISICO                    AS stock_fisico,
            p.FOTO_PORTADA                    AS foto_portada,
            p.GALERIA                         AS galeria,
            p.MOCKUP                          AS mockup_origen,
            p.DESCRIPCION                     AS descripcion,
            p.ES_VARIANTE                     AS es_variante
        FROM productos p
        WHERE p.SKU_REF IS NOT NULL AND p.SKU_REF <> ''
    ";

    $stmt = $db->query($sql);
    $productos = $stmt->fetchAll();
    
    $upsert = $db->prepare("
        INSERT INTO articulos (
            referencia, sku_base, nombre, descripcion, precio, stock, 
            categoria, subcategoria, marca, color, dimensiones, 
            foto_portada, galeria, mockup, activo, es_variante
        )
        VALUES (
            :ref, :sku_base, :nombre, :desc, :precio, :stock, 
            :cat, :subcat, :marca, :color, :dims, 
            :foto, :gal, :mockup, 1, :es_variante
        )
        ON DUPLICATE KEY UPDATE
            sku_base      = VALUES(sku_base),
            nombre        = VALUES(nombre),
            descripcion   = VALUES(descripcion),
            precio        = VALUES(precio),
            stock         = VALUES(stock),
            categoria     = VALUES(categoria),
            subcategoria  = VALUES(subcategoria),
            marca         = VALUES(marca),
            color         = VALUES(color),
            dimensiones   = VALUES(dimensiones),
            foto_portada  = VALUES(foto_portada),
            galeria       = VALUES(galeria),
            mockup        = VALUES(mockup),
            es_variante   = VALUES(es_variante)
    ");

    $ok = 0;
    $errores = [];
    foreach ($productos as $p) {
        // Limpiar stock (si es 'NO' o vacío -> 0)
        $stock_final = (int)$p['stock_str'];
        if ($p['stock_str'] === 'NO') $stock_final = 0;

        // 1. Normalizar rutas para DB
        $foto_relativa = normalizar_ruta_imagen($p['foto_portada'] ?? '');
        $gal_relativa  = normalizar_ruta_imagen($p['galeria'] ?? '');
        $mock_relativa = normalizar_ruta_imagen($p['mockup_origen'] ?? '');

        // 2. Intentar copiar archivos físicos al servidor (XAMPP)
        if (!empty($p['foto_portada'])) {
            asegurar_archivo_fisico($p['foto_portada'], $foto_relativa);
        }

        if (!empty($p['mockup_origen'])) {
            // El campo mockup puede tener varias rutas separadas por comas
            $mockups = explode(',', $p['mockup_origen']);
            foreach ($mockups as $m) {
                $m = trim($m);
                if (empty($m)) continue;
                $m_relativa = normalizar_ruta_imagen($m);
                asegurar_archivo_fisico($m, $m_relativa);
            }
        }

        if (!empty($p['galeria'])) {
            // El campo galeria puede tener varias rutas separadas por comas
            $galerias = explode(',', $p['galeria']);
            foreach ($galerias as $g) {
                $g = trim($g);
                if (empty($g)) continue;
                $g_relativa = normalizar_ruta_imagen($g);
                asegurar_archivo_fisico($g, $g_relativa);
            }
        }

        try {
            // 3. Ejecutar Upsert en DB
            $upsert->execute([
                'ref'         => $p['referencia'],
                'sku_base'    => $p['sku_base'] ?: $p['referencia'], // Si es NULL, el base es él mismo
                'nombre'      => trim($p['nombre'] ?? 'Sin nombre'),
                'desc'        => $p['descripcion'] ?? '',
                'precio'      => (float)$p['precio'],
                'stock'       => $stock_final,
                'cat'         => $p['categoria'] ?? '',
                'subcat'      => $p['subcategoria'] ?? '',
                'marca'       => $p['marca'] ?? '',
                'color'       => $p['color'] ?? '',
                'dims'        => $p['dimensiones'] ?? '',
                'foto'        => $foto_relativa,
                'gal'         => $gal_relativa,
                'mockup'      => $mock_relativa,
                'es_variante' => empty($p['es_variante']) ? 'BASE' : strtoupper($p['es_variante'])
            ]);
            $ok++;
        } catch (Exception $e) {
            $errores[] = "Error en SKU {$p['referencia']}: " . $e->getMessage();
        }
    }

    // 4. Eliminar artículos que ya no existen en la tabla productos (Limpieza de huérfanos)
    $sql_del = "DELETE FROM articulos WHERE referencia NOT IN (SELECT SKU_REF FROM productos WHERE SKU_REF IS NOT NULL AND SKU_REF <> '')";
    $stmtDel = $db->query($sql_del);
    $eliminados = $stmtDel->rowCount();

    echo json_encode([
        'ok'          => true, 
        'importados'  => $ok, 
        'eliminados'  => $eliminados,
        'errores'     => $errores,
        'mensaje'     => "Sincronizados $ok artículos. Se eliminaron $eliminados artículos obsoletos."
    ]);
}
?>
