<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => '请先登录。']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => '仅支持 POST。']));
}
verify_csrf();

$photoId = (int) ($_POST['photo_id'] ?? 0);
if ($photoId <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => '参数错误。']));
}

$stmt = db()->prepare('SELECT id FROM photos WHERE id = :id');
$stmt->execute([':id' => $photoId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => '照片不存在。']));
}

$liked = toggle_photo_like($photoId, (int) $user['id']);
exit(json_encode([
    'ok' => true,
    'liked' => $liked,
    'count' => photo_like_count($photoId),
]));