<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
session_start();
session_unset();
session_destroy();
header('Location: login');
exit;




