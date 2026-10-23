<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$name = basename((string) ($_GET['f'] ?? ''));
if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
    http_response_code(404);
    exit('文件不存在。');
}
$stmt = db()->prepare('SELECT filename, original_name, user_id, on_wall FROM photos WHERE filename = :filename');
$stmt->execute([':filename' => $name]);
$photo = $stmt->fetch();
if ($photo) {
    $canView = (int) $photo['user_id'] === (int) $user['id'] || !empty($photo['on_wall']) || $user['role'] === 'admin';
    if (!$canView) {
        http_response_code(403);
        exit('无权访问该照片。');
    }
}
$path = UPLOAD_DIR . '/' . $name;
$realDir = realpath(UPLOAD_DIR);
$realFile = realpath($path);
if (!$photo || $realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
    http_response_code(404);
    exit('文件不存在。');
}
$download = isset($_GET['dl']);
$ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($realFile));
$outName = $photo['original_name'] ?: $name;
$outName = str_replace(['"', "\r", "\n"], '', $outName);
if ($download) {
    header('Content-Disposition: attachment; filename="' . $outName . '"; filename*=UTF-8\'\'' . rawurlencode($outName));
} else {
    header('Content-Disposition: inline; filename="' . $outName . '"');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($realFile);
exit;
