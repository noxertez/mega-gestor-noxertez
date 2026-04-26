<?php
if (!defined('ALLOWED_ACCESS')) exit('Direct access not allowed.');
$db_host = 'localhost';
$db_port = null;
$db_user = 'noxertez_user';
$db_pass = 'Noxertez2024!';
$db_auth = 'noxertez';
$db_world = 'noxertez';
$db_char = 'noxertez';
$db_site = 'noxertez';

// Aliases for admin pages compatibility
$db_auth_name = $db_auth;
$db_char_name = $db_char;
$db_world_name = $db_world;
$db_site_name = $db_site;
$auth_db = new mysqli($db_host, $db_user, $db_pass, $db_auth, $db_port);
$world_db = new mysqli($db_host, $db_user, $db_pass, $db_world, $db_port);
$char_db = new mysqli($db_host, $db_user, $db_pass, $db_char, $db_port);
$site_db = new mysqli($db_host, $db_user, $db_pass, $db_site, $db_port);
if ($auth_db->connect_error) die('Auth DB Connection failed: ' . $auth_db->connect_error);
if ($world_db->connect_error) die('World DB Connection failed: ' . $world_db->connect_error);
if ($char_db->connect_error) die('Char DB Connection failed: ' . $char_db->connect_error);
if ($site_db->connect_error) die('Site DB Connection failed: ' . $site_db->connect_error);
?>