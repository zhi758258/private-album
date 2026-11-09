<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$me = (int) $user['id'];
$isAdmin = $user['role'] === 'admin';

$name = basename((string) ($_GET['f'] ?? ''));
if ($name === '' || !preg_match('/^m\d+_[a-f0-9]{24}\.(jpg|jpeg|png|gif|webp|mp4|webm|mov)$/', $name)) {
    http_response_code(404);
    exit('文件不存在。');
}

$stmt = db()->prepare('SELECT sender_id, receiver_id FROM private_messages WHERE media_file = :f LIMIT 1');
$stmt->execute([':f' => $name]);
$pm = $stmt->fetch();
if ($pm) {
    if (!$isAdmin && (int) $pm['sender_id'] !== $me && (int) $pm['receiver_id'] !== $me) {
        http_response_code(403);
        exit('无权访问。');
    }
} else {
    $stmt = db()->prepare('SELECT id FROM messages WHERE media_file = :f LIMIT 1');
    $stmt->execute([':f' => $name]);
    if ($stmt->fetch() === false) {
        http_response_code(404);
        exit('文件不存在。');
    }
}

$realDir = realpath(MEDIA_DIR);
$realFile = realpath(MEDIA_DIR . '/' . $name);
if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
    http_response_code(404);
    exit('文件不存在。');
}

$ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
];
$type = $types[$ext] ?? 'application/octet-stream';
$size = (int) filesize($realFile);

$start = 0;
$end = $size - 1;
$range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
$partial = false;
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
    if ($m[1] === '' && $m[2] !== '') {
        $suffix = (int) $m[2];
        if ($suffix > 0) {
            $start = max(0, $size - $suffix);
        }
    } elseif ($m[1] !== '') {
        $start = (int) $m[1];
        if ($m[2] !== '') {
            $end = (int) $m[2];
        }
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $partial = true;
}

session_write_close();

header('Content-Type: ' . $type);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
if ($partial) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . ($end - $start + 1));

while (ob_get_level() > 0) {
    ob_end_clean();
}
$fp = fopen($realFile, 'rb');
if ($fp === false) {
    exit;
}
fseek($fp, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fp)) {
    $chunk = fread($fp, min(8192, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($fp);
exit;
