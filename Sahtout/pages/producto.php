<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);

// pages/producto.php
include('../includes/header.php');
require_once('../api/config.php');

$db = conectar();

$ref = $_GET['ref'] ?? '';

if (!$ref) {
    echo "<div class='container mt-5'><p class='alert alert-warning'>Producto no encontrado.</p></div>";
    include('../includes/footer.php');
    exit;
}

// 1. Obtener detalles del producto actual
$stmt = $db->prepare("
    SELECT a.*, ft.titulo_publico, ft.materiales, ft.elaboracion, ft.observaciones, ft.mantenimiento, ft.sostenibilidad 
    FROM articulos a 
    LEFT JOIN fichas_tecnicas ft ON a.ficha_tecnica_id = ft.id
    WHERE a.referencia = ? AND a.activo = 1
");
$stmt->execute([$ref]);
$producto = $stmt->fetch();

if (!$producto) {
    echo "<div class='container mt-5'><p class='alert alert-danger'>El producto con referencia " . htmlspecialchars($ref) . " no existe o no está activo.</p></div>";
    include('../includes/footer.php');
    exit;
}

// 2. Determinar columna TrendiOff según marca
$marca_normalizada = strtoupper($producto['marca'] ?? '');
$columna_trendioff = 'trendiof_URL'; 

if (in_array($marca_normalizada, ['CANDLE HOLDER OF THE SOUL', 'CANDLEHOLDER', 'CANDLE'])) {
    $columna_trendioff = 'trendioff_velas_URL';
} elseif (in_array($marca_normalizada, ['THE SECRET ZEN GARDEN', 'ZEN GARDEN', 'ZEN'])) {
    $columna_trendioff = 'trendioff_piedras_URL';
}

// 2. Obtener enlace TrendiOff (con fallback al base si es variante)
$base_sku_13 = substr($ref, 0, 13);
$stmt_url = $db->prepare("
    SELECT $columna_trendioff as link FROM plataformas_ventas 
    WHERE (SKU_BASE = ? OR SKU_BASE = ?) 
    AND $columna_trendioff IS NOT NULL AND $columna_trendioff != ''
    ORDER BY (SKU_BASE = ?) DESC LIMIT 1
");
$stmt_url->execute([$ref, $base_sku_13, $ref]);
$url_data = $stmt_url->fetch();
$producto['trendiof_URL'] = $url_data['link'] ?? null;

// 2. Identificar Base SKU para buscar variantes (Primeros 13 caracteres para agrupar patrones y colores)
$base_sku_13 = substr($ref, 0, 13);

// 3. Buscar variantes (incluyendo el actual y patrones)
$stmt_v = $db->prepare("
    SELECT a.referencia, a.nombre, a.foto_portada, a.color, a.es_variante 
    FROM articulos a 
    WHERE a.referencia LIKE ? AND a.activo = 1
    ORDER BY a.es_variante DESC, a.referencia ASC
");
$stmt_v->execute([$base_sku_13 . '%']);
$variantes = $stmt_v->fetchAll();

// Función para normalizar rutas de imagen con soporte para cache-busting
function resolver_ruta_img($path, $base_path) {
    if (empty($path)) return $base_path . "img/logo.png";
    $clean = str_replace('\\', '/', $path);
    $rel_path = "";

    if (strpos($clean, '/imagenes/') !== false) {
        $rel_path = "uploads/articulos" . substr($clean, strpos($clean, '/imagenes/'));
    } elseif (strpos($clean, ':/') !== false) {
        $parts = explode('/', $clean);
        $rel_path = "uploads/articulos/imagenes/" . end($parts);
    } else {
        $rel_path = (strpos($clean, 'uploads/') === 0) ? $clean : "uploads/" . $clean;
    }

    // Añadir cache busting basado en fecha de modificación
    $full_path = dirname(__DIR__) . '/' . $rel_path;
    $version = (file_exists($full_path)) ? filemtime($full_path) : time();
    
    return $base_path . $rel_path . "?v=" . $version;
}

$main_img = resolver_ruta_img($producto['foto_portada'], $base_path);
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/producto_detalle.css?v=<?= time() ?>">
<style>
    body { background-color: #000 !important; margin: 0; padding: 0; }
</style>

<div class="product-detail-container" style="background-color: #0f172a; color: #fff; min-height: 100vh; padding: 30px; margin: 20px auto; border-radius: 20px;">
    <nav style="margin-bottom: 20px; font-size: 0.9em;">
        <a href="<?= $base_path ?>pages/catalogo_publico.php" style="color: var(--gold); text-decoration: none;">&lt; Volver al Catálogo</a>
    </nav>

    <div class="product-main">
        <!-- SECCIÓN IZQUIERDA: IMAGEN -->
        <div class="product-image-section">
            <div class="main-image-wrapper" id="mainImageWrapper">
                <img src="<?= htmlspecialchars($main_img) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" id="mainProductImg">
                <div class="zoom-hint"><i class="fas fa-search-plus"></i> Ver más grande</div>
            </div>

            <!-- GALERÍA DE MINIATURAS -->
            <?php 
            $gal_fotos = !empty($producto['galeria']) ? explode(',', $producto['galeria']) : [];
            // Siempre incluir la foto de portada como primera opción
            $todas_fotos = array_unique(array_filter(array_merge([$producto['foto_portada']], $gal_fotos)));
            
            if (count($todas_fotos) > 1): ?>
                <div class="product-gallery">
                    <?php foreach ($todas_fotos as $f): 
                        $f_url = resolver_ruta_img($f, $base_path);
                    ?>
                        <div class="gallery-thumb <?= ($f === $producto['foto_portada']) ? 'active' : '' ?>" onclick="changeMainImage('<?= htmlspecialchars($f_url) ?>', this)">
                            <img src="<?= htmlspecialchars($f_url) ?>" alt="Ángulo de <?= htmlspecialchars($producto['nombre']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECCIÓN DERECHA: INFO -->
        <div class="product-info-section">
            <span class="product-brand"><?= htmlspecialchars($producto['marca'] ?? 'Artesanía Noxertez') ?></span>
            <h1 class="product-title" style="color: #fff;"><?= htmlspecialchars($producto['nombre']) ?></h1>
            
            <div class="product-price">
                <?= number_format($producto['precio'], 2) ?> €
            </div>

            <div class="product-specs">
                <div class="spec-item">Referencia: <span><?= htmlspecialchars($producto['referencia']) ?></span></div>
                <div class="spec-item">Categoría: <span><?= htmlspecialchars($producto['categoria']) ?></span></div>
                <div class="spec-item">Dimensiones: <span><?= htmlspecialchars($producto['dimensiones'] ?? 'Ver descripción') ?></span></div>
                <div class="spec-item">Color: <span><?= htmlspecialchars($producto['color'] ?? 'Natural') ?></span></div>
            </div>

            <div class="product-description">
                <?= nl2br(htmlspecialchars($producto['descripcion'])) ?>
            </div>

            <?php if (!empty($producto['titulo_publico'])): ?>
                <div class="technical-sheet-trigger" style="margin-top: 20px;">
                    <button onclick="document.getElementById('modalTecnica').style.display='flex'" class="btn-premium-wow" style="background: rgba(212,175,55,0.1); border: 1px solid var(--accent-gold); color: var(--accent-gold); width: 100%; justify-content: center; padding: 12px; font-weight: bold; border-radius: 8px;">
                        <i class="fas fa-certificate"></i> Ver Detalles de Calidad y Origen
                    </button>
                </div>
            <?php endif; ?>

            <div class="product-actions">
                <?php if (!empty($producto['trendiof_URL'])): ?>
                    <a href="<?= htmlspecialchars($producto['trendiof_URL']) ?>" target="_blank" class="btn-buy-trendi">
                        <i class="fas fa-shopping-bag"></i> Comprar en TrendiOff
                    </a>
                <?php else: ?>
                    <a href="https://wa.me/<?= str_replace(' ', '', $social_links['whatsapp'] ?? '') ?>?text=Hola, estoy interesado en el producto <?= urlencode($producto['nombre']) ?> (Ref: <?= $producto['referencia'] ?>)" 
                       class="btn-buy-trendi" target="_blank">
                        <i class="fab fa-whatsapp"></i> Consultar Disponibilidad
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SECCIÓN VARIANTES -->
    <?php if (count($variantes) > 1): ?>
    <div class="variants-section">
        <h2 class="variants-title">Variantes y Colores</h2>
        <div class="variants-grid">
            <?php foreach ($variantes as $v): 
                $v_img = resolver_ruta_img($v['foto_portada'], $base_path);
                $is_active = ($v['referencia'] === $ref) ? 'active' : '';
            ?>
                <div class="variant-card <?= $is_active ?>" onclick="location.href='<?= $base_path ?>pages/producto.php?ref=<?= urlencode($v['referencia']) ?>'">
                    <img src="<?= htmlspecialchars($v_img) ?>" alt="<?= htmlspecialchars($v['nombre']) ?>">
                    <div class="variant-name"><?= htmlspecialchars($v['color'] ?: $v['referencia']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Ficha Técnica -->
<?php if (!empty($producto['titulo_publico'])): ?>
<div id="modalTecnica" class="modal-fs" style="display: none; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(15px); z-index: 9999;">
    <div style="max-width: 750px; margin: 40px auto; background: #1e293b; border: 2px solid #d4af37; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.8);">
        <!-- Cabecera del Modal -->
        <div style="padding: 25px; background: linear-gradient(90deg, rgba(212,175,55,0.2), transparent); border-bottom: 1px solid rgba(212,175,55,0.3); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; color: #d4af37; font-size: 1.8rem; font-family: 'Cinzel', serif; font-weight: 800; letter-spacing: 1px;"><?= htmlspecialchars($producto['titulo_publico']) ?></h2>
            <span onclick="document.getElementById('modalTecnica').style.display='none'" style="cursor: pointer; font-size: 2.5rem; color: #fff; line-height: 1;">&times;</span>
        </div>
        
        <!-- Cuerpo del Modal -->
        <div style="padding: 35px; color: #f1f5f9; max-height: 65vh; overflow-y: auto; font-family: 'Quicksand', sans-serif;">
            <div style="margin-bottom: 30px;">
                <h4 style="color: #d4af37; text-transform: uppercase; font-size: 0.9rem; letter-spacing: 2px; margin-bottom: 12px; font-weight: 700;"><i class="fas fa-tree" style="margin-right: 8px;"></i> Materiales Utilizados</h4>
                <p style="opacity: 1; line-height: 1.8; font-size: 1.05rem;"><?= nl2br(htmlspecialchars($producto['materiales'])) ?></p>
            </div>
            
            <div style="margin-bottom: 30px;">
                <h4 style="color: #d4af37; text-transform: uppercase; font-size: 0.9rem; letter-spacing: 2px; margin-bottom: 12px; font-weight: 700;"><i class="fas fa-hammer" style="margin-right: 8px;"></i> Proceso y Color</h4>
                <p style="opacity: 1; line-height: 1.8; font-size: 1.05rem;"><?= nl2br(htmlspecialchars($producto['elaboracion'])) ?></p>
            </div>
            
            <div style="margin-bottom: 30px; background: rgba(212,175,55,0.07); padding: 20px; border-left: 4px solid #d4af37; border-radius: 8px;">
                <h4 style="color: #d4af37; text-transform: uppercase; font-size: 0.9rem; letter-spacing: 2px; margin-bottom: 10px; font-weight: 700;"><i class="fas fa-certificate" style="margin-right: 8px;"></i> Esencia de la Pieza</h4>
                <p style="opacity: 1; line-height: 1.8; font-size: 1.05rem; margin: 0; font-style: italic;"><?= nl2br(htmlspecialchars($producto['observaciones'])) ?></p>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h4 style="color: #d4af37; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 10px; font-weight: 700;"><i class="fas fa-hand-sparkles" style="margin-right: 8px;"></i> Mantenimiento</h4>
                    <p style="opacity: 0.9; font-size: 0.95rem; line-height: 1.6;"><?= nl2br(htmlspecialchars($producto['mantenimiento'])) ?></p>
                </div>
                <div class="col-md-6 mb-4">
                    <h4 style="color: #d4af37; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 10px; font-weight: 700;"><i class="fas fa-leaf" style="margin-right: 8px;"></i> Sostenibilidad</h4>
                    <p style="opacity: 0.9; font-size: 0.95rem; line-height: 1.6;"><?= nl2br(htmlspecialchars($producto['sostenibilidad'])) ?></p>
                </div>
            </div>
        </div>
        
        <!-- Pie del Modal -->
        <div style="padding: 25px; text-align: center; background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.05);">
            <button onclick="document.getElementById('modalTecnica').style.display='none'" 
                    style="background: #d4af37; color: #000; border: none; padding: 12px 45px; font-weight: 800; border-radius: 50px; cursor: pointer; font-size: 1rem; text-transform: uppercase; transition: all 0.3s; box-shadow: 0 5px 15px rgba(212,175,55,0.3);">
                Entendido
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Fullscreen -->
<div id="imageModal" class="modal-fs">
    <span class="close-fs">&times;</span>
    <img class="modal-content-fs" id="imgFull">
</div>

<script>
// Lógica de pantalla completa
const modal = document.getElementById("imageModal");
const img = document.getElementById("mainProductImg");
const modalImg = document.getElementById("imgFull");
const wrapper = document.getElementById("mainImageWrapper");
const span = document.getElementsByClassName("close-fs")[0];

wrapper.onclick = function(){
    modal.style.display = "block";
    modalImg.src = img.src;
}

span.onclick = function() { 
    modal.style.display = "none";
}

modal.onclick = function(e) {
    if (e.target === modal) {
        modal.style.display = "none";
    }
}

// Cambiar imagen principal
function changeMainImage(newSrc, thumbEl) {
    const mainImg = document.getElementById('mainProductImg');
    mainImg.style.opacity = '0.5';
    
    setTimeout(() => {
        mainImg.src = newSrc;
        mainImg.style.opacity = '1';
    }, 150);

    // Actualizar clase active
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumbEl.classList.add('active');
}
</script>

<?php include('../includes/footer.php'); ?>
