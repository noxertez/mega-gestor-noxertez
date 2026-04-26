<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: {$base_path}login");
    exit;
}

// Define icons globally to avoid scope issues
$icons = [
    'facebook'  => 'fab fa-facebook-f',
    'twitter'   => 'fab fa-x-twitter',
    'tiktok'    => 'fab fa-tiktok',
    'youtube'   => 'fab fa-youtube',
    'discord'   => 'fab fa-discord',
    'twitch'    => 'fab fa-twitch',
    'kick'      => 'custom',
    'instagram' => 'fab fa-instagram',
    'whatsapp'  => 'fab fa-whatsapp',
    'trendioff' => 'fas fa-shopping-cart',
    'etsy'      => 'fab fa-etsy',
    'github'    => 'fab fa-github',
    'linkedin'  => 'fab fa-linkedin-in',
];

$page_class = 'general';
require_once $project_root . 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($langCode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('page_title_general', 'General Settings'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/admin/settings/general.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/admin/admin_sidebar.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/admin/settings/settings_navbar.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include $project_root . 'includes/admin_sidebar.php'; ?>

            <main class="col-md-10 main-content">
                <?php include dirname(__DIR__) . '/settings/settings_navbar.php'; ?>

                <div class="content">
                    <h2><?php echo translate('settings_general', 'General Settings'); ?></h2>

                    <!-- Success / Error Messages -->
                    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
                        <div class="success-box mb-3 col-md-6 mx-auto">
                            <i class="fas fa-check-circle me-2"></i>
                            <span class="success"><?php echo translate('msg_settings_saved', 'Settings updated successfully!'); ?></span>
                        </div>
                    <?php elseif (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
                        <div class="error-box mb-3 col-md-6 mx-auto">
                            <strong><?php echo translate('err_fix_errors', 'Please fix the following errors:'); ?></strong>
                            <div class="db-status mt-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <span class="error"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- General Settings Form -->
                    <div class="row justify-content-center">
                        <form action="<?php echo $base_path; ?>admin/settings/save_general" method="POST" enctype="multipart/form-data" class="col-md-8" novalidate>

                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="MAX_FILE_SIZE" value="3145728">

                            <!-- Navigation Tabs -->
                            <ul class="nav nav-tabs mb-4 px-3" id="settingsTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#tab-general" type="button" role="tab"><?php echo translate('tab_general', 'General'); ?></button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#tab-backup" type="button" role="tab">
                                        <i class="fas fa-database me-1"></i> Copia de Seguridad
                                    </button>
                                </li>
                                <?php foreach ($brand_settings as $brand => $data): 
                                    $tab_id = str_replace(' ', '-', strtolower($brand));
                                ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="<?php echo $tab_id; ?>-tab" data-bs-toggle="tab" data-bs-target="#tab-<?php echo $tab_id; ?>" type="button" role="tab">
                                            <?php echo htmlspecialchars($brand); ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="tab-content pb-4" id="settingsTabsContent" style="min-height: 400px;">
                                <!-- GENERAL TAB -->
                                <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                                    <!-- Website Title -->
                                    <div class="mb-4">
                                        <label for="site_title_name" class="form-label fw-bold">
                                            <?php echo translate('label_website_title', 'Website Title'); ?>
                                        </label>
                                        <input type="text"
                                               id="site_title_name"
                                               name="site_title_name"
                                               class="form-control form-control-lg border-primary shadow-sm"
                                               value="<?php echo htmlspecialchars($site_title_name); ?>"
                                               placeholder="<?php echo translate('placeholder_site_title', 'e.g. My Awesome Site'); ?>"
                                               required>
                                        <div class="form-text">
                                            <?php echo translate('help_site_title', 'This title appears in the browser tab, site header, and SEO.'); ?>
                                        </div>
                                    </div>

                                    <!-- Logo Upload -->
                                    <div class="mb-4">
                                        <label for="logo" class="form-label fw-bold"><?php echo translate('label_website_logo', 'Website Logo'); ?></label>
                                        <div class="mb-3 text-center bg-light p-3 rounded shadow-inner">
                                            <img src="<?php echo $base_path . htmlspecialchars($site_logo); ?>" alt="Current Logo" class="img-fluid rounded shadow-sm border" style="max-height: 120px;">
                                        </div>
                                        <div class="custom-file-upload">
                                            <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.svg">
                                            <button type="button" class="btn btn-outline-secondary w-100" onclick="document.getElementById('logo').click();">
                                                <i class="fas fa-upload me-2"></i> <?php echo translate('btn_choose_file', 'Change Logo'); ?>
                                            </button>
                                            <div class="file-name mt-2 text-muted small" id="file-name">
                                                <?php echo translate('placeholder_logo', 'No file chosen – PNG, JPG or SVG (max 3MB)'); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Global Social Links -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold"><?php echo translate('label_social_media', 'Global Social Media'); ?></label>
                                        <?php foreach ($icons as $platform => $icon): ?>
                                            <div class="input-group mb-2 shadow-sm">
                                                <span class="input-group-text social-icon" style="width: 45px; justify-content: center;">
                                                    <?php if ($platform === 'kick'): ?>
                                                        <img src="<?php echo $base_path; ?>img/icons/kick-logo.png" alt="Kick" style="width:16px;">
                                                    <?php else: ?>
                                                        <i class="<?php echo $icon; ?>"></i>
                                                    <?php endif; ?>
                                                </span>
                                                <input type="url"
                                                       name="<?php echo $platform; ?>"
                                                       class="form-control"
                                                       placeholder="<?php echo ucfirst($platform); ?> URL"
                                                       value="<?php echo htmlspecialchars($social_links[$platform] ?? ''); ?>">
                                            </div>
                                        <?php endforeach; ?>
                                     </div>

                                     <!-- Global Instagram Feed Embed -->
                                     <div class="mb-4 pt-3 border-top">
                                         <label class="form-label fw-bold">Global Instagram Feed Embed Code (Home Page)</label>
                                         <textarea name="global_ig_feed" 
                                                   class="form-control font-monospace" 
                                                   rows="3" 
                                                   placeholder="Paste global <iframe> or <script> embed code here..."><?php echo htmlspecialchars($global_ig_feed ?? ''); ?></textarea>
                                         <div class="form-text small">This feed will be displayed on the Home Page.</div>
                                     </div>
                                 </div>

                                 <!-- BACKUP TAB -->
                                 <div class="tab-pane fade" id="tab-backup" role="tabpanel">
                                     <div class="card border-0 shadow-sm mb-4">
                                         <div class="card-body text-center py-5">
                                             <div class="mb-4">
                                                 <i class="fas fa-archive fa-4x text-primary mb-3"></i>
                                                 <h3>Respaldo Total del Sistema</h3>
                                                 <p class="text-muted">Genera un archivo comprimido que incluye todos los archivos de la web y un volcado completo de la base de datos MySQL.</p>
                                             </div>
                                             
                                             <div id="backup-status" class="alert alert-info d-none mb-4">
                                                 <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                                 <span>Generando copia de seguridad... Esto puede tardar varios minutos dependiendo del tamaño de las imágenes.</span>
                                             </div>

                                             <!-- Backup Path Input -->
                                             <div class="mb-4 text-start">
                                                 <label for="backup_path" class="form-label fw-bold">Ruta de Destino Local (Opcional):</label>
                                                 <div class="input-group">
                                                     <span class="input-group-text"><i class="fas fa-folder-open"></i></span>
                                                     <input type="text" id="backup_path" name="backup_path" class="form-control" 
                                                            placeholder="Ej: D:\Backups o C:\MisCopias" 
                                                            value="<?php echo htmlspecialchars($backup_path ?? ''); ?>">
                                                 </div>
                                                 <div class="form-text mt-1">Si se deja vacío, se guardará en la carpeta <code>backups/</code> del proyecto.</div>
                                             </div>

                                             <div id="backup-result" class="d-none mb-4">
                                                 <div class="alert alert-success">
                                                     <i class="fas fa-check-circle me-2"></i>
                                                     <strong>¡Éxito!</strong> La copia de seguridad se ha generado correctamente.
                                                 </div>
                                                 <a href="#" id="download-backup-btn" class="btn btn-success btn-lg">
                                                     <i class="fas fa-download me-2"></i> Descargar Backup (.zip)
                                                 </a>
                                             </div>

                                             <button type="button" id="start-backup-btn" class="btn btn-primary btn-lg px-5">
                                                 <i class="fas fa-play me-2"></i> Iniciar Copia de Seguridad
                                             </button>
                                             
                                             <div class="mt-4 small text-muted">
                                                 <i class="fas fa-info-circle me-1"></i>
                                                 Nota: No cierres esta pestaña mientras el proceso esté en curso.
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                <!-- BRAND TABS -->
                                <?php
                                 $index = 0;
                                 foreach ($brand_settings as $brand => $data):
                                     $tab_id = str_replace(' ', '-', strtolower($brand));
                                 ?>
                                     <div class="tab-pane fade" id="tab-<?php echo $tab_id; ?>" role="tabpanel">
                                         <div class="card border-0 shadow-sm mb-4">
                                             <div class="card-body">
                                                 <h4 class="card-title mb-4 border-bottom pb-2">
                                                     <i class="fas fa-palette me-2"></i> <?php echo htmlspecialchars($brand); ?> Appearance
                                                 </h4>

                                                 <div class="mb-4">
                                                     <label class="form-label fw-bold">Brand Slogan</label>
                                                     <input type="text"
                                                            name="brand[<?php echo $index; ?>][slogan]"
                                                            class="form-control form-control-lg"
                                                            value="<?php echo htmlspecialchars($data['slogan']); ?>"
                                                            placeholder="Enter brand slogan...">
                                                 </div>

                                                 <div class="row mb-4">
                                                     <div class="col-md-6 mb-3">
                                                         <label class="form-label fw-bold">Primary Theme Color</label>
                                                         <div class="input-group">
                                                             <input type="color"
                                                                    name="brand[<?php echo $index; ?>][primary]"
                                                                    class="form-control form-control-color"
                                                                    style="width: 60px; height: 45px;"
                                                                    value="<?php echo $data['primary']; ?>"
                                                                    title="Choose primary color">
                                                             <input type="text" class="form-control" value="<?php echo $data['primary']; ?>" readonly>
                                                         </div>
                                                     </div>
                                                     <div class="col-md-6 mb-3">
                                                         <label class="form-label fw-bold">Accent / Button Color</label>
                                                         <div class="input-group">
                                                             <input type="color"
                                                                    name="brand[<?php echo $index; ?>][accent]"
                                                                    class="form-control form-control-color"
                                                                    style="width: 60px; height: 45px;"
                                                                    value="<?php echo $data['accent']; ?>"
                                                                    title="Choose accent color">
                                                             <input type="text" class="form-control" value="<?php echo $data['accent']; ?>" readonly>
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <h4 class="card-title mt-5 mb-4 border-bottom pb-2">
                                                     <i class="fas fa-share-nodes me-2"></i> <?php echo htmlspecialchars($brand); ?> Social Links
                                                 </h4>

                                                 <div class="row">
                                                     <?php foreach ($icons as $platform => $icon): ?>
                                                         <div class="col-md-6 mb-2">
                                                             <div class="input-group input-group-sm shadow-xs">
                                                                 <span class="input-group-text social-icon" style="width: 35px; justify-content: center;">
                                                                     <?php if ($platform === 'kick'): ?>
                                                                         <img src="<?php echo $base_path; ?>img/icons/kick-logo.png" alt="Kick" style="width:14px;">
                                                                     <?php else: ?>
                                                                         <i class="<?php echo $icon; ?> fa-sm"></i>
                                                                     <?php endif; ?>
                                                                 </span>
                                                                 <input type="url"
                                                                        name="brand[<?php echo $index; ?>][social_links][<?php echo $platform; ?>]"
                                                                        class="form-control"
                                                                        placeholder="<?php echo ucfirst($platform); ?> URL"
                                                                        value="<?php echo htmlspecialchars($data['social_links'][$platform] ?? ''); ?>">
                                                             </div>
                                                         </div>
                                                     <?php endforeach; ?>
                                                 </div>

                                                 <div class="mt-4">
                                                     <label class="form-label fw-bold">Instagram Feed Embed Code (Optional)</label>
                                                     <textarea name="brand[<?php echo $index; ?>][ig_feed_code]" 
                                                               class="form-control font-monospace" 
                                                               rows="3" 
                                                               placeholder="Paste <iframe> or <script> embed code here..."><?php echo htmlspecialchars($data['ig_feed_code'] ?? ''); ?></textarea>
                                                     <div class="form-text small">Use widgets from Elfsight, SnapWidget or similar to display the live feed.</div>
                                                 </div>

                                                 <input type="hidden" name="brand[<?php echo $index; ?>][original_name]" value="<?php echo htmlspecialchars($brand); ?>">
                                                 <input type="hidden" name="brand[<?php echo $index; ?>][db_name]" value="<?php echo htmlspecialchars($data['db_name']); ?>">

                                                 <div class="alert alert-secondary py-2 small mt-4 mb-0">
                                                     <i class="fas fa-info-circle me-1"></i>
                                                     Changes will be applied instantly to the public brand page.
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 <?php
                                 $index++;
                                 endforeach; ?>
                            </div>

                            <!-- Save Button -->
                            <div class="text-center mt-2 border-top pt-4 sticky-bottom bg-white py-3 shadow-top">
                                <button type="submit" class="btn btn-primary btn-lg px-5 shadow animate-up">
                                    <i class="fas fa-save me-2"></i> <?php echo translate('btn_save_settings', 'Save All Settings'); ?>
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php require_once $project_root . 'includes/footer.php'; ?>

    <script>
        document.getElementById('logo').addEventListener('change', function() {
            const fileName = this.files.length > 0 
                ? this.files[0].name 
                : '<?php echo translate('placeholder_logo', 'No file chosen – PNG, JPG or SVG (max 3MB)'); ?>';
            document.getElementById('file-name').textContent = fileName;
        });

        // Backup Logic
        document.getElementById('start-backup-btn').addEventListener('click', function() {
            const btn = this;
            const status = document.getElementById('backup-status');
            const result = document.getElementById('backup-result');
            const downloadBtn = document.getElementById('download-backup-btn');

            btn.disabled = true;
            status.classList.remove('d-none');
            result.classList.add('d-none');

            fetch('<?php echo $base_path; ?>admin/backup_execute.php')
                .then(response => response.json())
                .then(data => {
                    status.classList.add('d-none');
                    btn.disabled = false;

                    if (data.status === 'success') {
                        result.classList.remove('d-none');
                        downloadBtn.href = data.download_url;
                        downloadBtn.setAttribute('download', data.filename);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    status.classList.add('d-none');
                    btn.disabled = false;
                    alert('Error en la solicitud: ' + error);
                });
        });
    </script>
</body>
</html>