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

if (!isset($_FILES['voice']) || !is_array($_FILES['voice'])) {
    json_fail('没有收到语音。');
}
$file = $_FILES['voice'];
$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
    json_fail('语音太大，服务器限制更小。');
}
if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
    json_fail('上传失败，请重试。');
}
if ((int) ($file['size'] ?? 0) <= 0) {
    json_fail('录音为空，请重试。');
}
if ((int) ($file['size'] ?? 0) > MAX_VOICE_BYTES) {
    json_fail('语音不能超过 6MB。');
}

$mime = strtolower(trim((string) ($file['type'] ?? '')));
$mime = explode(';', $mime)[0];
$map = [
    'audio/webm' => 'webm',
    'video/webm' => 'webm',
    'audio/ogg' => 'ogg',
    'application/ogg' => 'ogg',
    'audio/mp4' => 'm4a',
    'video/mp4' => 'm4a',
    'audio/mpeg' => 'mp3',
    'audio/mp3' => 'mp3',
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
    'audio/aac' => 'aac',
];
$ext = $map[$mime] ?? '';
if ($ext === '') {
    json_fail('不支持的音频格式。');
}
if (!audio_magic_ok((string) $file['tmp_name'], $ext)) {
    json_fail('音频文件校验失败，请重新录制。');
}

$filename = 'v' . (int) $user['id'] . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
if (!move_uploaded_file((string) $file['tmp_name'], VOICE_DIR . '/' . $filename)) {
    json_fail('保存语音失败。');
}

exit(json_encode(['ok' => true, 'file' => $filename], JSON_UNESCAPED_UNICODE));
