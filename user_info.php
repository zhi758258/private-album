<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => '请先登录。'], JSON_UNESCAPED_UNICODE));
}

$id = (int) ($_GET['u'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => '参数错误。'], JSON_UNESCAPED_UNICODE));
}

$stmt = db()->prepare('SELECT id, username, display_name, avatar, phone FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => '用户不存在。'], JSON_UNESCAPED_UNICODE));
}

$me = (int) $user['id'];
$targetId = (int) $row['id'];
$state = 'none';
if ($targetId === $me) {
    $state = 'self';
} elseif (are_friends($me, $targetId)) {
    $state = 'friend';
} elseif (friend_request_find($me, $targetId)) {
    $state = 'sent';
} elseif (friend_request_find($targetId, $me)) {
    $state = 'incoming';
}

echo json_encode([
    'ok' => true,
    'id' => $targetId,
    'username' => $row['username'],
    'display_name' => $row['display_name'],
    'phone' => (string) ($row['phone'] ?? ''),
    'avatar_html' => avatar_html($row['avatar'] ?? null, $row['display_name'], 'avatar avatar-lg'),
    'state' => $state,
], JSON_UNESCAPED_UNICODE);