<?php
// debug.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "PHP Version: " . phpversion() . "<br>";
echo "Current Dir: " . __DIR__ . "<br>";
echo "Vendor autoload exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO') . "<br>";
echo ".env exists: " . (file_exists(__DIR__ . '/../.env') ? 'YES' : 'NO') . "<br>";

try {
    require __DIR__ . '/../vendor/autoload.php';
    echo "Autoload loaded successfully<br>";
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "Laravel App bootstrapped successfully<br>";
} catch (Throwable $e) {
    echo "<b>Fatal Error:</b> " . $e->getMessage() . "<br><pre>" . $e->getTraceAsString() . "</pre>";
}
