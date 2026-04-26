<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'api/config.php';
require_once $project_root . 'includes/config.settings.php';

$brand_name = $_GET['name'] ?? '';

// Configuración de marcas (cargada desde el archivo global)
$brands = $brand_settings;

if (!isset($brands[$brand_name])) {
    header("Location: {$base_path}");
    exit;
}

$brand_data = $brands[$brand_name];

// Sobrescribir redes sociales globales con las de la marca si existen
if (isset($brand_data['social_links']) && is_array($brand_data['social_links'])) {
    foreach ($brand_data['social_links'] as $key => $val) {
        if (!empty($val)) {
            $social_links[$key] = $val;
        }
    }
}

$page_class = 'brand-page';
include $project_root . 'includes/header.php';

// Obtener los últimos 5 productos de la marca (soportando variaciones de nombre en DB)
$db = conectar();
$db_values = $brand_data['db_values'] ?? [$brand_data['db_name'] ?? $brand_name];
$placeholders = implode(',', array_fill(0, count($db_values), '?'));

$stmt = $db->prepare("SELECT * FROM productos WHERE MARCA IN ($placeholders) AND (ES_VARIANTE = 'BASE' OR ES_VARIANTE = '' OR ES_VARIANTE IS NULL) ORDER BY FECHA DESC LIMIT 5");
$stmt->execute($db_values);
$productos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? 'es'); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($brand_name); ?> - Noxertez</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/brand.css">
    <style>
        :root {
            --brand-primary: <?php echo $brand_data['primary']; ?>;
            --brand-accent: <?php echo $brand_data['accent']; ?>;
        }
    </style>
</head>
<body class="brand-page">
    <div class="brand-hero" style="background: linear-gradient(135deg, var(--brand-primary), #000);">
        <div class="container">
            <h1 class="brand-title"><?php echo htmlspecialchars($brand_name); ?></h1>
            <p class="brand-slogan-large"><?php echo htmlspecialchars($brand_data['slogan']); ?></p>
        </div>
    </div>

    <main class="container mt-5">
        <!-- ⚡ Disponible ahora de la Marca -->
        <?php
        $stmt_inm_brand = $db->prepare("
            SELECT a.*, CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) as stock_final, a.foto_portada, p.FOTO_PORTADA
            FROM articulos a
            LEFT JOIN productos p ON a.referencia = p.SKU_REF
            WHERE a.activo = 1 
              AND p.MARCA IN ($placeholders)
              AND (a.entrega_inmediata = 1 OR CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) > 0)
            ORDER BY a.entrega_inmediata DESC, stock_final DESC 
            LIMIT 4
        ");
        $stmt_inm_brand->execute($db_values);
        $inm_brand = $stmt_inm_brand->fetchAll();

        if ($inm_brand): ?>
            <section class="mb-5">
                <h2 class="section-title" style="color: var(--brand-accent); border-color: var(--brand-accent);">⚡ Disponible ahora (<?php echo htmlspecialchars($brand_name); ?>)</h2>
                <div class="product-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                    <?php foreach ($inm_brand as $art): 
                    $foto = $art['foto_portada'] ?: $art['FOTO_PORTADA'] ?: 'img/logo.png';
                    if (strpos($foto, 'C:\\') !== false || strpos($foto, ':/') !== false) {
                        $clean = str_replace('\\', '/', $foto);
                        $parts = explode('/', $clean);
                        $foto = $base_path . 'uploads/articulos/imagenes/' . end($parts);
                    } else {
                            $foto = $base_path . (strpos($foto, 'uploads/') === 0 ? $foto : 'uploads/'.$foto);
                        }
                    ?>
                        <div class="product-card" onclick="window.location='<?php echo $base_path; ?>pages/disponible_ahora.php'" style="cursor:pointer; border: 1px solid rgba(212,175,55,0.2);">
                            <div class="product-image">
                                <img src="<?php echo $foto; ?>" alt="<?php echo htmlspecialchars($art['nombre']); ?>" onerror="this.src='<?php echo $base_path; ?>img/logo.png'">
                            </div>
                            <div class="product-info">
                                <div style="color: var(--brand-accent); font-weight: bold; font-size: 0.75rem; margin-bottom:5px;">⚡ ENTREGA INMEDIATA</div>
                                <h3 class="product-name"><?php echo htmlspecialchars($art['nombre']); ?></h3>
                                <div class="product-footer">
                                    <span class="product-price"><?php echo number_format((float)$art['precio'], 2); ?>€</span>
                                    <span style="color:#22c55e; font-size:0.8rem; font-weight:bold;">¡Lo quiero!</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin: 3rem 0;">
        <?php endif; ?>

        <h2 class="section-title">Últimos Diseños</h2>
        
        <?php if (empty($productos)): ?>
            <p class="text-center">Próximamente nuevos artículos...</p>
        <?php else: ?>
            <!-- 🛒 Product Grid -->
            <div class="product-grid">
                <?php 
                $shown_ids = [];
                $current_category = '';
                foreach ($productos as $producto): 
                    $shown_ids[] = $producto['SKU_REF'];
                    $current_category = $producto['CATEGORIA'];
                    
                    // Image handling
                    $img_src = $producto['FOTO_PORTADA'] ?? '';
                    if ($img_src) {
                        $clean = str_replace('\\', '/', $img_src);
                        $imgIndex = strpos($clean, '/imagenes/');
                        if ($imgIndex !== false) {
                            $img_src = $base_path . "uploads/articulos" . substr($clean, $imgIndex);
                        } elseif (strpos($clean, ':/') !== false) {
                            $parts = explode('/', $clean);
                            $img_src = $base_path . "uploads/articulos/imagenes/" . end($parts);
                        } else {
                            $img_src = (strpos($clean, 'uploads/') === 0) ? $base_path . $clean : $base_path . "uploads/" . $clean;
                        }
                    } else {
                        $img_src = '';
                    }
                ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($img_src): ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($producto['NOMBRE']); ?>">
                            <?php else: ?>
                                <div class="no-image">🏺</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($producto['NOMBRE']); ?></h3>
                            <p class="product-category"><?php echo htmlspecialchars($producto['CATEGORIA'] ?? ''); ?></p>
                            <div class="product-footer">
                                <span class="product-price"><?php echo number_format((float)$producto['PRECIO'], 2); ?>€</span>
                                <a href="<?php echo $base_path; ?>pages/producto.php?ref=<?php echo urlencode($producto['SKU_REF'] ?? ''); ?>" class="btn-detail">Ver Detalle</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            // Suggested Products Logic
            if (!empty($shown_ids) && !empty($current_category)) {
                $placeholders_shown = implode(',', array_fill(0, count($shown_ids), '?'));
                $placeholders_marca = implode(',', array_fill(0, count($db_values), '?'));
                
                $suggest_stmt = $db->prepare("SELECT * FROM productos WHERE MARCA IN ($placeholders_marca) AND CATEGORIA = ? AND SKU_REF NOT IN ($placeholders_shown) AND (ES_VARIANTE = 'BASE' OR ES_VARIANTE = '' OR ES_VARIANTE IS NULL) LIMIT 4");
                $params = array_merge($db_values, [$current_category], $shown_ids);
                $suggest_stmt->execute($params);
                $sugeridos = $suggest_stmt->fetchAll();

                if (!empty($sugeridos)): ?>
                    <!-- 💡 Suggested Products -->
                    <section class="mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,0.1);">
                        <h3 class="section-title" style="font-size: 1.4rem;">También te puede interesar</h3>
                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; padding: 10px 20px;">
                            <?php foreach ($sugeridos as $sug):
                                $sug_img = $sug['FOTO_PORTADA'] ?? '';
                                if ($sug_img) {
                                    $clean = str_replace('\\', '/', $sug_img);
                                    $imgIndex = strpos($clean, '/imagenes/');
                                    if ($imgIndex !== false) {
                                        $sug_img = $base_path . "uploads/articulos" . substr($clean, $imgIndex);
                                    } else {
                                        $sug_img = (strpos($clean, 'uploads/') === 0) ? $base_path . $clean : $base_path . "uploads/" . $clean;
                                    }
                                } else {
                                    $sug_img = '';
                                }
                            ?>
                                <div class="product-card" style="cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>pages/catalogo_publico.php?busq=<?php echo urlencode($sug['SKU_REF']); ?>'">
                                    <div class="product-image" style="height: 160px;">
                                        <?php if ($sug_img): ?>
                                            <img src="<?php echo htmlspecialchars($sug_img); ?>" alt="<?php echo htmlspecialchars($sug['NOMBRE']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">🏺</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info" style="padding: 12px;">
                                        <h4 class="product-name" style="font-size: 0.9rem;"><?php echo htmlspecialchars($sug['NOMBRE']); ?></h4>
                                        <span class="product-price" style="font-size: 1rem;"><?php echo number_format((float)$sug['PRECIO'], 2); ?>€</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif;
            } ?>
        <?php endif; ?>

        <div class="text-center mt-5 mb-5">
            <a href="<?php echo $base_path; ?>pages/catalogo_publico.php" class="btn-back">Volver al Catálogo Completo</a>
        </div>

        <?php if (!empty($brand_data['ig_feed_code'])): ?>
            <section style="margin-top: 3rem; padding-bottom: 3rem;">
                <h2 class="section-title"><i class="fab fa-instagram" style="margin-right: 10px;"></i> Síguenos en Instagram</h2>
                <div style="max-width: 900px; margin: 0 auto; background: rgba(255,255,255,0.05); border-radius: 12px; padding: 20px; overflow: hidden;">
                    <?php echo $brand_data['ig_feed_code']; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php include $project_root . 'includes/footer.php'; ?>
</body>
</html>
