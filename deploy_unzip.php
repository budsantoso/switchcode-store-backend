<?php
// deploy_unzip.php - Helper untuk uncompress release.zip dan migrasi database
$secret = 'SwitchCodeDeploy2026SecretKey';

if (($_GET['key'] ?? '') !== $secret) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$zipFile = __DIR__ . '/release.zip';
if (!file_exists($zipFile)) {
    die(json_encode(['status' => 'error', 'message' => 'release.zip not found in ' . __DIR__]));
}

$zip = new ZipArchive();
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo(__DIR__);
    $zip->close();
    unlink($zipFile);

    // Pastikan storage permissions
    @mkdir(__DIR__ . '/storage/framework/views', 0775, true);
    @mkdir(__DIR__ . '/storage/framework/cache', 0775, true);
    @mkdir(__DIR__ . '/storage/framework/sessions', 0775, true);
    @mkdir(__DIR__ . '/storage/logs', 0775, true);
    @mkdir(__DIR__ . '/bootstrap/cache', 0775, true);

    echo json_encode([
        'status' => 'success',
        'message' => 'Successfully unzipped release.zip and prepared directories',
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to open release.zip']);
}
