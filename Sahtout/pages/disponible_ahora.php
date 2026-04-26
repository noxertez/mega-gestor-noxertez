<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'api/config.php';
require_once $project_root . 'includes/config.settings.php';

$db = conectar();

// Consulta mejorada incluyendo enlaces de Trendioff
$query = "
    SELECT a.*, 
           CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) as stock_final,
           a.foto_portada,
           p.FOTO_PORTADA,
           pv.trendiof_URL,
           pv.trendioff_velas_URL,
           pv.trendioff_piedras_URL
    FROM articulos a
    LEFT JOIN productos p ON a.referencia = p.SKU_REF
    LEFT JOIN plataformas_ventas pv ON (pv.SKU_BASE = a.referencia OR pv.SKU_BASE = SUBSTRING(a.referencia, 1, 13))
    WHERE a.activo = 1 
      AND (a.entrega_inmediata = 1 OR CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) > 0)
    GROUP BY a.referencia
    ORDER BY a.entrega_inmediata DESC, stock_final DESC, a.nombre ASC
";
$stmt = $db->query($query);
$articulos = $stmt->fetchAll();

$page_class = 'catalogo-page';
include $project_root . 'includes/header.php';
?>

<style>
    :root {
        --accent-gold: #d4af37;
        --border-glass: rgba(255, 255, 255, 0.1);
    }
    .inmediata-header {
        background: linear-gradient(135deg, #0f172a 0%, #000 100%);
        padding: 5rem 1rem;
        text-align: center;
        border-bottom: 2px solid var(--accent-gold);
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }
    .inmediata-header::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: radial-gradient(circle at center, rgba(212,175,55,0.1) 0%, transparent 70%);
        pointer-events: none;
    }
    .inmediata-title {
        font-family: 'Cinzel', serif;
        font-size: 3.5rem;
        color: var(--accent-gold);
        text-shadow: 0 0 30px rgba(212,175,55,0.4);
        margin-bottom: 15px;
        letter-spacing: 2px;
    }
    .inmediata-subtitle {
        color: #94a3b8;
        font-size: 1.2rem;
        max-width: 700px;
        margin: 0 auto;
        font-family: 'Quicksand', sans-serif;
    }
    .grid-inmediata {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 30px;
        padding: 0 20px;
        max-width: 1300px;
        margin: 0 auto 6rem;
    }
    .card-inm {
        background: rgba(255,255,255,0.02);
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .card-inm:hover {
        transform: translateY(-10px);
        border-color: var(--accent-gold);
        box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        background: rgba(255,255,255,0.04);
    }
    .card-inm-img-wrapper {
        position: relative;
        height: 250px;
        overflow: hidden;
    }
    .card-inm-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }
    .card-inm:hover .card-inm-img { transform: scale(1.1); }
    
    .card-inm-body { padding: 25px; flex: 1; display: flex; flex-direction: column; }
    .card-inm-name { color: #fff; font-size: 1.25rem; font-weight: bold; margin-bottom: 10px; font-family: 'Cinzel', serif; color: var(--accent-gold); }
    .card-inm-price { color: #fff; font-size: 1.5rem; font-weight: bold; margin-bottom: 20px; }
    
    .badge-⚡ {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(0, 0, 0, 0.7);
        color: var(--accent-gold);
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: bold;
        border: 1px solid var(--accent-gold);
        backdrop-filter: blur(5px);
        z-index: 2;
    }
    
    .actions-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        margin-top: auto;
    }
    .btn-nox {
        padding: 12px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .btn-main {
        background: var(--accent-gold);
        color: #000;
        border: none;
    }
    .btn-main:hover {
        background: #fff;
        transform: scale(1.02);
    }
    .btn-whatsapp {
        background: #25d366;
        color: #fff;
        border: none;
    }
    .btn-whatsapp:hover {
        background: #128c7e;
        transform: scale(1.02);
    }
    .btn-outline {
        border: 1px solid var(--accent-gold);
        color: var(--accent-gold);
        background: transparent;
    }
    .btn-outline:hover {
        background: rgba(212,175,55,0.1);
        transform: scale(1.02);
        color: #fff;
    }
    
    .empty-state { text-align: center; padding: 8rem 1rem; }
    .empty-icon { font-size: 5rem; color: var(--accent-gold); margin-bottom: 30px; opacity: 0.5; }
    .empty-text { color: #94a3b8; font-size: 1.4rem; max-width: 600px; margin: 0 auto 40px; }
</style>

<div class="inmediata-header">
    <h1 class="inmediata-title">⚡ DISPONIBLE AHORA</h1>
    <p class="inmediata-subtitle">Piezas únicas terminadas y listas para su envío inmediato. Artesanía sin esperas.</p>
</div>

<div class="container">
    <?php if (empty($articulos)): ?>
        <div class="empty-state">
            <div class="empty-icon">⚒️</div>
            <p class="empty-text">Actualmente todas nuestras piezas están siendo creadas por encargo. ¡Escríbenos para reservar la tuya!</p>
            <a href="https://wa.me/34<?php echo str_replace(' ', '', $social_links['whatsapp']); ?>" class="btn-nox btn-whatsapp" style="display:inline-flex; width:auto; padding: 15px 40px;">
                <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
            </a>
        </div>
    <?php else: ?>
        <div class="grid-inmediata">
            <?php foreach ($articulos as $art): 
                // Resolver imagen
                $foto = $art['foto_portada'] ?: $art['FOTO_PORTADA'] ?: 'img/logo.png';
                if (strpos($foto, 'C:\\') !== false || strpos($foto, ':/') !== false) {
                    $clean = str_replace('\\', '/', $foto);
                    $parts = explode('/', $clean);
                    $foto = 'uploads/articulos/imagenes/' . end($parts);
                }
                if ($foto !== 'img/logo.png' && strpos($foto, 'http') === false && strpos($foto, 'uploads/') !== 0 && strpos($foto, 'img/') !== 0) {
                     $foto = 'uploads/' . $foto;
                }

                // Lógica de URL Trendiof (prioridad según marca)
                $marca_normalizada = strtoupper($art['marca'] ?? '');
                $trendi_url = $art['trendiof_URL'];
                if (in_array($marca_normalizada, ['CANDLE HOLDER OF THE SOUL', 'CANDLEHOLDER', 'CANDLE'])) {
                    $trendi_url = $art['trendioff_velas_URL'] ?: $trendi_url;
                } elseif (in_array($marca_normalizada, ['THE SECRET ZEN GARDEN', 'ZEN GARDEN', 'ZEN'])) {
                    $trendi_url = $art['trendioff_piedras_URL'] ?: $trendi_url;
                }

                $wa_msg = "Hola, me interesa el artículo " . $art['nombre'] . " (Ref: " . $art['referencia'] . ") que he visto disponible para entrega inmediata.";
                $wa_url = "https://wa.me/34" . str_replace(' ', '', $social_links['whatsapp']) . "?text=" . urlencode($wa_msg);
            ?>
                <div class="card-inm">
                    <div class="card-inm-img-wrapper">
                        <div class="badge-⚡">⚡ STOCK</div>
                        <img src="<?php echo $foto; ?>" class="card-inm-img" alt="<?php echo htmlspecialchars($art['nombre']); ?>" onerror="this.src='img/logo.png'">
                    </div>
                    <div class="card-inm-body">
                        <h3 class="card-inm-name"><?php echo htmlspecialchars($art['nombre']); ?></h3>
                        <p class="card-inm-price"><?php echo number_format((float)$art['precio'], 2); ?>€</p>
                        
                        <div class="actions-grid">
                            <?php if (!empty($trendi_url)): ?>
                                <a href="<?php echo htmlspecialchars($trendi_url); ?>" class="btn-nox btn-main" target="_blank">
                                    <i class="fas fa-shopping-bag"></i> Comprar en TrendiOff
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $wa_url; ?>" class="btn-nox btn-whatsapp" target="_blank">
                                    <i class="fab fa-whatsapp"></i> ¡Lo quiero!
                                </a>
                            <?php endif; ?>
                            
                            <a href="pages/producto.php?ref=<?php echo urlencode($art['referencia']); ?>" class="btn-nox btn-outline">
                                <i class="fas fa-search-plus"></i> Ver Detalles
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include $project_root . 'includes/footer.php'; ?>
