<?php
require_once 'C:/xampp/htdocs/noxertez/api/config.php';
$db = conectar();
$stmt = $db->query("SELECT mensaje_error FROM linkedin_queue WHERE mensaje_error IS NOT NULL AND mensaje_error != '' ORDER BY id DESC LIMIT 1");
$error = $stmt->fetchColumn();
echo "ULTIMO_ERROR_LINKEDIN: " . $error;
?>
