<?php
if (!defined('ALLOWED_ACCESS')) exit('Direct access not allowed.');

/**
 * Visitor Tracker for SahtoutCMS
 * Records unique daily visitors by IP.
 */

// Function to get real IP
function getVisitorIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

$visitor_ip = getVisitorIP();
$visitor_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$current_date = date('Y-m-d');

// We use $site_db from config.php
if (isset($site_db)) {
    // Check if this IP already visited today
    $stmt = $site_db->prepare("SELECT id FROM visitor_log WHERE ip_address = ? AND visit_date = ? LIMIT 1");
    $stmt->bind_param("ss", $visitor_ip, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // First visit today, record it
        $stmt_ins = $site_db->prepare("INSERT INTO visitor_log (ip_address, user_agent, visit_date, visit_time) VALUES (?, ?, ?, NOW())");
        $stmt_ins->bind_param("sss", $visitor_ip, $visitor_ua, $current_date);
        $stmt_ins->execute();
        $stmt_ins->close();
    }
    $stmt->close();
}
?>
