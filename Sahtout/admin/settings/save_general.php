<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    $_SESSION['debug_errors'] = [translate('error_access_denied', 'Access denied.')];
    header("Location: {$base_path}login");
    exit;
}

// Log execution to a known location (Keep for one last trace)
file_put_contents(__DIR__ . '/log.txt', date('[Y-m-d H:i:s] ') . "Script hit correctly with POST\n", FILE_APPEND);

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['debug_errors'] = [translate('error_csrf_invalid', 'Invalid CSRF token.')];
    header("Location: {$base_path}admin/settings/general?status=error&message=" . urlencode(translate('error_csrf_invalid', 'Invalid CSRF token.')));
    exit;
}
require_once $project_root . 'includes/config.settings.php';

// ==================== NEW: Handle Website Title ====================
$site_title_name_new = trim($_POST['site_title_name'] ?? '');
if (empty($site_title_name_new)) {
    $site_title_name_new = 'SahtoutCMS'; // fallback if empty
}
// Sanitize: prevent PHP injection when writing to config
$site_title_name_new = htmlspecialchars($site_title_name_new, ENT_QUOTES, 'UTF-8');

// Initialize variables
$errors = [];
$success = false;

// Handle logo upload (YOUR ORIGINAL CODE - UNTOUCHED)
$logo_path = $site_logo;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['logo']['tmp_name'];
    $file_name = $_FILES['logo']['name'];
    $file_size = $_FILES['logo']['size'];
    $file_type = $_FILES['logo']['type'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_exts = ['png', 'svg', 'jpg', 'jpeg'];
    $max_size = 3 * 1024 * 1024;

    if ($file_size > $max_size) {
        $errors[] = translate('error_file_too_large', 'File size exceeds 3MB.');
    }
    elseif (!in_array($file_ext, $allowed_exts)) {
        $errors[] = translate('error_invalid_file_type', 'Invalid file type. Only PNG, SVG, or JPG allowed.');
    }
    elseif ($file_ext === 'png' && $file_type !== 'image/png') {
        $errors[] = translate('error_invalid_file_type', 'Invalid PNG file. MIME type must be image/png.');
    }
    elseif (in_array($file_ext, ['jpg', 'jpeg']) && !in_array($file_type, ['image/jpeg', 'image/jpg'])) {
        $errors[] = translate('error_invalid_file_type', 'Invalid JPG file. MIME type must be image/jpeg or image/jpg.');
    }
    elseif ($file_ext === 'svg' && $file_type !== 'image/svg+xml') {
        $errors[] = translate('error_invalid_file_type', 'Invalid SVG file. MIME type must be image/svg+xml.');
    }
    else {
        $upload_dir = $project_root . 'img/';
        if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
            $errors[] = translate('error_file_upload_failed', 'Upload directory is not accessible or writable.');
        } else {
            $new_file_name = 'logo.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $logo_path = "img/$new_file_name";
            } else {
                $errors[] = translate('error_file_upload_failed', 'Failed to upload logo. Check server permissions.');
            }
        }
    }
} elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Your full upload error handling (kept 100% intact)
    switch ($_FILES['logo']['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $errors[] = translate('error_file_too_large', 'Logo file exceeds server upload limit (3MB).');
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = translate('error_file_too_large', 'Logo file exceeds form limit (3MB).');
            break;
        case UPLOAD_ERR_PARTIAL:
            $errors[] = translate('error_file_upload_failed', 'Logo file was only partially uploaded.');
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errors[] = translate('error_file_upload_failed', 'Server error: Temporary directory missing.');
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errors[] = translate('error_file_upload_failed', 'Server error: Failed to write file to disk.');
            break;
        default:
            $errors[] = translate('error_file_upload_failed', 'Error uploading logo: Code ' . $_FILES['logo']['error']);
    }
}

// Handle social links
$social_links_new = [
    'facebook'  => filter_input(INPUT_POST, 'facebook', FILTER_VALIDATE_URL) ?: '',
    'twitter'   => filter_input(INPUT_POST, 'twitter', FILTER_VALIDATE_URL) ?: '',
    'tiktok'    => filter_input(INPUT_POST, 'tiktok', FILTER_VALIDATE_URL) ?: '',
    'youtube'   => filter_input(INPUT_POST, 'youtube', FILTER_VALIDATE_URL) ?: '',
    'discord'   => filter_input(INPUT_POST, 'discord', FILTER_VALIDATE_URL) ?: '',
    'twitch'    => filter_input(INPUT_POST, 'twitch', FILTER_VALIDATE_URL) ?: '',
    'kick'      => filter_input(INPUT_POST, 'kick', FILTER_VALIDATE_URL) ?: '',
    'instagram' => filter_input(INPUT_POST, 'instagram', FILTER_VALIDATE_URL) ?: '',
    'whatsapp'  => trim($_POST['whatsapp'] ?? ''),
    'trendioff' => filter_input(INPUT_POST, 'trendioff', FILTER_VALIDATE_URL) ?: '',
    'etsy'      => filter_input(INPUT_POST, 'etsy', FILTER_VALIDATE_URL) ?: '',
    'github'    => filter_input(INPUT_POST, 'github', FILTER_VALIDATE_URL) ?: '',
    'linkedin'  => filter_input(INPUT_POST, 'linkedin', FILTER_VALIDATE_URL) ?: '',
];

// Handle brand settings
$brand_settings_post = $_POST['brand'] ?? [];
$brand_settings_new = [];

foreach ($brand_settings_post as $idx => $data) {
    $brand_name = $data['original_name'] ?? ('Brand ' . $idx);
    
    if (isset($data['slogan'])) {
        $data['slogan'] = htmlspecialchars($data['slogan'], ENT_QUOTES, 'UTF-8');
    }
    // Basic color validation (should be hex)
    if (isset($data['primary']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['primary'])) {
        $data['primary'] = $brand_settings[$brand_name]['primary'] ?? '#1a1a1a';
    }
    if (isset($data['accent']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['accent'])) {
        $data['accent'] = $brand_settings[$brand_name]['accent'] ?? '#d4af37';
    }

    // Handle per-brand social links
    if (isset($data['social_links']) && is_array($data['social_links'])) {
        foreach ($data['social_links'] as $plat => &$url) {
            $url = trim($url);
            if (!empty($url) && $plat !== 'whatsapp' && !filter_var($url, FILTER_VALIDATE_URL)) {
                // If invalid URL but not empty, maybe we should keep it or clear it? 
            }
        }
    }

    // Remove temporary keys before saving
    $clean_data = $data;
    unset($clean_data['original_name']);
    
    $brand_settings_new[$brand_name] = $clean_data;
}

// Update config.settings.php only if no errors
if (empty($errors)) {
    $config_file = $project_root . 'includes/config.settings.php';
    if (!is_writable($config_file)) {
        $errors[] = translate('error_file_write_failed', 'Configuration file is not writable.');
    } else {
        $config_content = "<?php\n";
        $config_content .= "if (!defined('ALLOWED_ACCESS')) {\n";
        $config_content .= "    header('HTTP/1.1 403 Forbidden');\n";
        $config_content .= "    exit('Direct access not allowed.');\n";
        $config_content .= "}\n\n";

        // === Site Title ===
        $config_content .= "// Site Title\n";
        $config_content .= "\$site_title_name = " . var_export($site_title_name_new, true) . ";\n\n";

        // === Logo ===
        $config_content .= "// Logo\n";
        $config_content .= "\$site_logo = " . var_export($logo_path, true) . ";\n\n";

        // === Social Links ===
        $config_content .= "// Global Social links\n";
        $config_content .= "\$social_links = " . var_export($social_links_new, true) . ";\n\n";

        // === Global IG Feed ===
        $global_ig_feed_new = $_POST['global_ig_feed'] ?? '';
        $config_content .= "// Global Instagram Feed\n";
        $config_content .= "\$global_ig_feed = " . var_export($global_ig_feed_new, true) . ";\n\n";

        // === Brand Settings ===
        $config_content .= "// Brand specific settings (Slogans, Colors and Social Links)\n";
        $config_content .= "\$brand_settings = " . var_export($brand_settings_new, true) . ";\n\n";

        // === Backup Path ===
        $backup_path_new = trim($_POST['backup_path'] ?? '');
        $config_content .= "// Backup Path\n";
        $config_content .= "\$backup_path = " . var_export($backup_path_new, true) . ";\n";

        if (file_put_contents($config_file, $config_content)) {
            $success = true;
        } else {
            $errors[] = translate('error_file_write_failed', 'Failed to update configuration file.');
        }
    }
}

// Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Redirect
$redirect_url = "{$base_path}admin/settings/general";
if ($success) {
    header("Location: $redirect_url?status=success");
} else {
    $_SESSION['debug_errors'] = $errors;
    header("Location: $redirect_url?status=error&message=" . urlencode(implode(', ', $errors)));
}
exit;
?>
