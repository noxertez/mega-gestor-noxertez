<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/Sahtout/includes/paths.php';
require_once __DIR__ . '/Sahtout/includes/srp6.php';

try {
    echo "Attempting to call SRP6::getRegistrationData('test', 'test')...\n";
    $data = SRP6::getRegistrationData('test', 'test');
    echo "Success! Salt and verifier generated.\n";
    print_r($data);
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
