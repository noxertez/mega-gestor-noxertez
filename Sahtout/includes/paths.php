<?php
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https://' : 'http://';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}

$host = $_SERVER['HTTP_HOST'];

// Dynamic base path calculation
$project_root = rtrim(realpath(__DIR__ . '/..'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$abs_root = str_replace('\\', '/', $project_root);

$relative_path = str_replace($doc_root, '', $abs_root);
$base_path = '/' . trim($relative_path, '/') . '/';

// Final safety check: if we are at root level
if ($base_path === '//' || $base_path === '/') {
    $base_path = '/';
}

// AUTO-DETECTION for domain-mapped installations
// If we are accessing via a domain (e.g. noxertez.com) where the root is already the project folder,
// the REQUEST_URI won't contain the folder name (e.g. /noxertez/).
// But the dynamic calculation might still pick it up if DOCUMENT_ROOT is just 'htdocs'.
if (strpos($_SERVER['REQUEST_URI'], '/noxertez/') === false && $base_path === '/noxertez/') {
    $base_path = '/';
}

$site_url = $protocol . $host . $base_path;
?>