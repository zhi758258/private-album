<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

require_login();

$name = basename((string) ($_GET['u'] ?? ''));
if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
    http_response_code(404);
    exit('Not Found');
}

$stmt = db()->prepare('SELECT avatar FROM users WHERE avatar = :avatar');
$stmt->execute([':avatar' => $name]);
if ($stmt->fetch() === false) {
    http_response_code(404);
    exit('Not Found');
}

$path = AVATAR_DIR . '/' . $name;
$realDir = realpath(AVATAR_DIR);
$realFile = realpath($path);
if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
    http_response_code(404);
    exit('Not Found');
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . (string) filesize($realFile));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($realFile);
exit;