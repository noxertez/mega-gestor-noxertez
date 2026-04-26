<?php
define('ALLOWED_ACCESS', true);

require_once __DIR__ . '/includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php'; // Include language file for translations
require_once $project_root . 'includes/config.settings.php'; // Load socials
require_once $project_root . 'includes/visitor_tracker.php'; // Track visitors
if (!isset($global_ig_feed)) $global_ig_feed = '';
$page_class = "home";
$header_file = $project_root . 'includes/header.php';

// Ensure header file exists before including
if (file_exists($header_file)) {
    include $header_file;
} else {
    die(translate('error_header_not_found', 'Error: Header file not found.'));
}

// Query to fetch the 4 most recent news items
$query = "SELECT id, title, slug, image_url, post_date 
          FROM server_news 
          ORDER BY is_important DESC, post_date DESC 
          LIMIT 4";
$result = $site_db->query($query);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo translate('home_meta_description', 'Bienvenidos a Noxertez Artesanía. Descubre nuestros artículos únicos hechos a mano y gestiona tus pedidos fácilmente.'); ?>">
    <meta name="robots" content="index">
    <title><?php echo $site_title_name ." ". translate('home_page_title', 'Home'); ?></title>
    <style>
    :root {
        --bg-home: url("<?php echo $base_path; ?>img/backgrounds/bg-home.jpg");
        }

    @media (max-width: 900px) {
    .brand-cards-container {
        grid-template-columns: 1fr;
    }
}

/* WhatsApp Floating Button */
.whatsapp-float {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background-color: #25d366;
    color: #FFF;
    border-radius: 50px;
    text-align: center;
    font-size: 35px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    z-index: 1000;
    width: 65px;
    height: 65px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.whatsapp-float:hover {
    transform: scale(1.1) rotate(5deg);
    background-color: #128c7e;
    box-shadow: 0 6px 15px rgba(37, 211, 102, 0.5);
}

/* Social Buttons Welcome Section */
.social-icon-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
    text-decoration: none;
    transition: all 0.3s ease;
}
.social-icon-btn.instagram { background: #E1306C; }
.social-icon-btn.tiktok { background: #000; border: 1px solid #fff; }
.social-icon-btn.whatsapp { background: #25D366; }

.social-icon-btn:hover {
    transform: translateY(-5px) scale(1.1);
    filter: brightness(1.2);
}
    </style>
</head>
<body class="home">
    <main>
        <!-- Brand Cards Section -->
        <section class="brand-cards-container">
            <div class="brand-card noxertez">
                <a href="<?php echo $base_path; ?>pages/brand.php?name=NOXERTEZ">
                    <div class="brand-content">
                        <h2 class="brand-name">NOXERTEZ</h2>
                        <p class="brand-slogan">EL ARTE CON MADERA PERFECTAMENTE IMPERFECTO</p>
                    </div>
                </a>
            </div>
            <div class="brand-card candle-holder">
                <a href="<?php echo $base_path; ?>pages/brand.php?name=CANDLE HOLDER OF THE SOUL">
                    <div class="brand-content">
                        <h2 class="brand-name">CANDLE HOLDER OF THE SOUL</h2>
                        <p class="brand-slogan">PORTAVELAS CON ALMA Y CALIDEZ PARA TU HOGAR</p>
                    </div>
                </a>
            </div>
            <div class="brand-card zen-garden">
                <a href="<?php echo $base_path; ?>pages/brand.php?name=THE SECRET ZEN GARDEN">
                    <div class="brand-content">
                        <h2 class="brand-name">THE SECRET ZEN GARDEN</h2>
                        <p class="brand-slogan">EL EQUILIBRIO DE LA NATURALEZA EN PIEDRAS DE MADERA ANCESTRALES</p>
                    </div>
                </a>
            </div>
        </section>

        <!-- Intro Container -->
        <section class="intro-container">
            <h1 class="intro-title"><?php echo translate('home_intro_title', 'Bienvenido a ')." ".$site_title_name ; ?></h1>
            <p class="intro-tagline"><?php echo translate('home_intro_tagline', 'Diseños únicos hechos con pasión y dedicación por manos expertas.'); ?></p>
            <div class="intro-buttons" style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo $base_path; ?>pages/catalogo_publico.php" class="intro-button"><?php echo translate('home_catalog', 'Ver Catálogo'); ?></a>
                <a href="<?php echo $base_path; ?>seguimiento" class="intro-button" style="background: rgba(212,175,55,0.2); border: 1px solid #d4af37;">
                    <i class="fas fa-truck-fast"></i> <?php echo translate('home_tracking', 'Seguimiento'); ?>
                </a>
            </div>
            <div class="social-container">
                <hr class="social-line">
                <a href="<?php echo $social_links['instagram']; ?>" class="social-icon-btn instagram" target="_blank" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <hr class="social-line">
                <a href="<?php echo $social_links['tiktok']; ?>" class="social-icon-btn tiktok" target="_blank" aria-label="TikTok">
                    <i class="fab fa-tiktok"></i>
                </a>
                <hr class="social-line">
                <a href="https://wa.me/<?php echo htmlspecialchars(str_replace(' ', '', $social_links['whatsapp'])); ?>" class="social-icon-btn whatsapp" target="_blank" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <hr class="social-line">
            </div>
        </section>



        <!-- 🔁 Image Gallery Slider -->
        <section class="hero-gallery">
            <div class="slider" id="slider">
                <div class="slide active"><img src="<?php echo $base_path; ?>img/homeimg/slide1.jpg" alt="<?php echo translate('slider_alt_1', 'Artesanía Única'); ?>"></div>
                <div class="slide"><img src="<?php echo $base_path; ?>img/homeimg/slide2.jpg" alt="<?php echo translate('slider_alt_2', 'Procesos Manuales'); ?>"></div>
                <div class="slide"><img src="<?php echo $base_path; ?>img/homeimg/slide3.jpg" alt="<?php echo translate('slider_alt_3', 'Calidad Superior'); ?>"></div>
            </div>
            <button class="slider-nav prev" aria-label="<?php echo translate('slider_prev', 'Previous Slide'); ?>">❮</button>
            <button class="slider-nav next" aria-label="<?php echo translate('slider_next', 'Next Slide'); ?>">❯</button>
            <div class="slider-dots">
                <span class="dot active" data-slide="0"></span>
                <span class="dot" data-slide="1"></span>
                <span class="dot" data-slide="2"></span>
            </div>
        </section>

        <!-- 📰 News Preview Section -->
        <section class="news-preview">
            <div class="news-grid">
                <?php if ($result->num_rows === 0): ?>
                    <p><?php echo translate('home_no_news', 'No news available at the time.'); ?></p>
                <?php else: ?>
                    <?php while ($news = $result->fetch_assoc()): ?>
                        <div class="news-item">
                            <a href="<?php echo $base_path; ?>news?slug=<?php echo htmlspecialchars($news['slug']); ?>">
                                <div class="news-image">
                                    <img src="<?php echo $base_path . htmlspecialchars($news['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($news['title']); ?>">
                                    <span class="news-title"><?php echo htmlspecialchars($news['title']); ?></span>
                                </div>
                                <p class="news-date"><?php echo date('M j, Y', strtotime($news['post_date'])); ?></p>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- 🔲 Menubar Tabs -->
        <section class="tabs-container">
            <div class="tabs">
                <button class="tab active" data-tab="bugtracker"><?php echo translate('home_tab_bugtracker', 'Bugtracker'); ?></button>
                <button class="tab" data-tab="stream"><?php echo translate('home_tab_stream', 'Stream'); ?></button>
            </div>
            <div class="tab-content" id="tab-content">
                <h2><?php echo translate('home_bugtracker_title', 'Bugtracker'); ?></h2>
                <p><?php echo translate('home_bugtracker_content', 'View and report issues with the server to help us improve your experience.'); ?></p>
            </div>
        </section>

        <?php if (isset($global_ig_feed) && !empty($global_ig_feed)): ?>
            <!-- Instagram Global Feed -->
            <section class="instagram-global shadow-sm mt-5 mb-5 p-4 rounded bg-white mx-auto" style="max-width: 1200px;">
                <h2 class="text-center mb-4" style="font-family: 'Cinzel', serif; font-weight: 700;">
                    <i class="fab fa-instagram me-2"></i> Instagram @noxertez
                </h2>
                <div class="instagram-feed-wrapper">
                    <?php echo $global_ig_feed; ?>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Floating WhatsApp Button -->
    <a href="https://wa.me/<?php echo htmlspecialchars(str_replace(' ', '', $social_links['whatsapp'])); ?>" class="whatsapp-float" target="_blank">
        <i class="fab fa-whatsapp"></i>
    </a>
    
    <?php
    $footer_file = $project_root . 'includes/footer.php';
    if (file_exists($footer_file)) {
        include $footer_file;
    } else {
        die(translate('error_footer_not_found', 'Error: Footer file not found.'));
    }
    ?>
    <script src="<?php echo $base_path; ?>assets/js/home.js?v=<?php echo time(); ?>"></script>
</body>
</html>
<?php
// Close database connection
if (isset($site_db)) {
    $site_db->close();
}
?>