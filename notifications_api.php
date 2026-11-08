<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => '请先登录。'], JSON_UNESCAPED_UNICODE));
}

$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'read_all') {
        notifications_mark_read($uid);
    } elseif ($action === 'read') {
        notifications_mark_read($uid, (int) ($_POST['id'] ?? 0));
    } else {
        json_fail('未知操作。');
    }
    echo json_encode(['ok' => true, 'unread' => notifications_unread_count($uid)], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = [];
foreach (notifications_recent($uid, 20) as $n) {
    $items[] = [
        'id' => (int) $n['id'],
        'type' => (string) $n['type'],
        'content' => (string) $n['content'],
        'link' => (string) $n['link'],
        'is_read' => (int) $n['is_read'],
        'created_at' => (string) $n['created_at'],
        'actor_name' => (string) ($n['actor_name'] ?? ''),
        'actor_avatar_html' => avatar_html($n['actor_avatar'] ?? null, (string) ($n['actor_name'] ?? '系统'), 'avatar avatar-sm'),
    ];
}

echo json_encode([
    'ok' => true,
    'unread' => notifications_unread_count($uid),
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
