<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => '仅支持 POST。'], JSON_UNESCAPED_UNICODE));
}
verify_csrf();

if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
    json_fail('请选择头像图片。');
}
$file = $_FILES['avatar'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
    json_fail('上传失败，请重试。');
}
if (($file['size'] ?? 0) > MAX_AVATAR_BYTES) {
    json_fail('头像图片不能超过 5MB。');
}
$info = @getimagesize((string) $file['tmp_name']);
if (!is_array($info) || !in_array((string) ($info['mime'] ?? ''), ['image/jpeg', 'image/png'], true)) {
    json_fail('只支持 jpg / png 图片。');
}
if ((int) $info[0] < 64 || (int) $info[1] < 64) {
    json_fail('图片尺寸太小，至少 64×64。');
}

$filename = 'u' . (int) $user['id'] . '_' . bin2hex(random_bytes(6)) . '.jpg';
$dest = AVATAR_DIR . '/' . $filename;
if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
    json_fail('保存头像失败。');
}

$old = $user['avatar'];
$stmt = db()->prepare('UPDATE users SET avatar = :avatar WHERE id = :id');
$stmt->execute([':avatar' => $filename, ':id' => $user['id']]);

if (is_string($old) && $old !== '' && $old !== $filename) {
    $oldPath = AVATAR_DIR . '/' . basename($old);
    $realDir = realpath(AVATAR_DIR);
    $realOld = realpath($oldPath);
    if ($realDir !== false && $realOld !== false && str_starts_with($realOld, $realDir . DIRECTORY_SEPARATOR) && is_file($realOld)) {
        @unlink($realOld);
    }
}

exit(json_encode(['ok' => true, 'avatar' => $filename], JSON_UNESCAPED_UNICODE));