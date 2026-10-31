<?php
// artisan_runner.php - Menjalankan migrasi & seed di server cPanel
$secret = 'SwitchCodeDeploy2026SecretKey';

if (($_GET['key'] ?? '') !== $secret) {
    http_response_code(403);
    die('Unauthorized');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Console\Kernel;

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$cmd = $_GET['cmd'] ?? 'migrate';

echo "<pre>";
echo "Running Artisan command: {$cmd}\n\n";

try {
    if ($cmd === 'migrate:fresh' || $cmd === 'migrate:fresh --seed') {
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    } elseif ($cmd === 'fix:content_urls') {
        // Ganti url storage lama ke api/storage
        \Illuminate\Support\Facades\DB::table('articles')
            ->where('content', 'like', '%api.switchcode.id/storage/%')
            ->update([
                'content' => \Illuminate\Support\Facades\DB::raw("REPLACE(content, 'api.switchcode.id/storage/', 'api.switchcode.id/api/storage/')")
            ]);
        echo "Updated old image URLs in articles table!\n";
    } elseif ($cmd === 'check:files') {
        $dir = __DIR__ . '/../storage/app/public';
        echo "Storage dir: {$dir}\n";
        echo "Exists: " . (is_dir($dir) ? 'YES' : 'NO') . "\n";
        if (is_dir($dir . '/articles/inline')) {
            print_r(scandir($dir . '/articles/inline'));
        }
    } else {
        Artisan::call($cmd, ['--force' => true]);
    }
    echo Artisan::output();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}

echo "</pre>";
