<?php
// deploy_unzip.php - Helper untuk uncompress release.zip dan migrasi database
$secret = 'SwitchCodeDeploy2026SecretKey';

if (($_GET['key'] ?? '') !== $secret) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$rootPath = dirname(__DIR__);
// release.zip diupload ke folder public atau root
$zipFile = file_exists(__DIR__ . '/release.zip') ? __DIR__ . '/release.zip' : $rootPath . '/release.zip';

if (!file_exists($zipFile)) {
    die(json_encode([
        'status' => 'error',
        'message' => 'release.zip not found',
        'checked' => [__DIR__ . '/release.zip', $rootPath . '/release.zip'],
    ]));
}

$zip = new ZipArchive();
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($rootPath);
    $zip->close();
    @unlink($zipFile);

    @mkdir($rootPath . '/storage/framework/views', 0775, true);
    @mkdir($rootPath . '/storage/framework/cache', 0775, true);
    @mkdir($rootPath . '/storage/framework/sessions', 0775, true);
    @mkdir($rootPath . '/storage/logs', 0775, true);
    @mkdir($rootPath . '/bootstrap/cache', 0775, true);

    echo json_encode([
        'status' => 'success',
        'message' => 'Successfully unzipped release.zip into project root',
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to open release.zip']);
}
