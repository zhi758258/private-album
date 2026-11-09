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

if (!isset($_FILES['media']) || !is_array($_FILES['media'])) {
    json_fail('没有收到文件。');
}
$file = $_FILES['media'];
$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
    json_fail('文件太大，服务器限制更小。');
}
if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
    json_fail('上传失败，请重试。');
}
if ((int) ($file['size'] ?? 0) <= 0) {
    json_fail('文件为空，请重试。');
}
if ((int) ($file['size'] ?? 0) > MAX_MEDIA_BYTES) {
    json_fail('照片或视频不能超过 25MB。');
}

$tmp = (string) $file['tmp_name'];
$ext = '';
$kind = '';

$imageMime = detect_image_mime($tmp);
if ($imageMime !== null) {
    $ext = ALLOWED_MIME[$imageMime];
    $kind = 'image';
} else {
    $mime = strtolower(trim((string) ($file['type'] ?? '')));
    $mime = explode(';', $mime)[0];
    $map = [
        'video/webm' => 'webm',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-m4v' => 'mp4',
        'application/octet-stream' => '',
    ];
    $ext = $map[$mime] ?? '';
    if ($ext === '' && video_magic_ok($tmp, 'webm')) {
        $ext = 'webm';
    }
    if ($ext === '' && video_magic_ok($tmp, 'mp4')) {
        $ext = 'mp4';
    }
    if ($ext === '' || !video_magic_ok($tmp, $ext)) {
        json_fail('只支持照片或 mp4 / webm / mov 视频。');
    }
    $kind = 'video';
}

$filename = 'm' . (int) $user['id'] . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
if (!move_uploaded_file($tmp, MEDIA_DIR . '/' . $filename)) {
    json_fail('保存文件失败。');
}

exit(json_encode(['ok' => true, 'file' => $filename, 'kind' => $kind], JSON_UNESCAPED_UNICODE));
