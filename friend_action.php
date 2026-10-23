<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => '请先登录。'], JSON_UNESCAPED_UNICODE));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => '仅支持 POST。'], JSON_UNESCAPED_UNICODE));
}
verify_csrf();

$me = (int) $user['id'];
$action = (string) ($_POST['action'] ?? '');
$otherId = (int) ($_POST['user_id'] ?? 0);
if ($otherId <= 0 || $otherId === $me) {
    json_fail('无效的用户。');
}

$stmt = db()->prepare('SELECT id FROM users WHERE id = :id');
$stmt->execute([':id' => $otherId]);
if (!$stmt->fetch()) {
    json_fail('用户不存在。');
}

$state = 'none';

if ($action === 'request') {
    if (are_friends($me, $otherId)) {
        $state = 'friend';
    } else {
        $reverse = friend_request_find($otherId, $me);
        if ($reverse) {
            $del = db()->prepare('DELETE FROM friend_requests WHERE id = :id');
            $del->execute([':id' => (int) $reverse['id']]);
            add_friendship($me, $otherId);
            $state = 'friend';
        } elseif (friend_request_find($me, $otherId)) {
            $state = 'sent';
        } else {
            $ins = db()->prepare('INSERT INTO friend_requests (from_user_id, to_user_id, created_at) VALUES (:from_user_id, :to_user_id, :created_at)');
            $ins->execute([':from_user_id' => $me, ':to_user_id' => $otherId, ':created_at' => now()]);
            $state = 'sent';
        }
    }
} elseif ($action === 'accept') {
    $req = friend_request_find($otherId, $me);
    if (!$req) {
        json_fail('没有待处理的好友请求。');
    }
    $del = db()->prepare('DELETE FROM friend_requests WHERE id = :id');
    $del->execute([':id' => (int) $req['id']]);
    add_friendship($me, $otherId);
    $state = 'friend';
} elseif ($action === 'remove') {
    if (!are_friends($me, $otherId)) {
        json_fail('对方不是你的好友。');
    }
    remove_friendship($me, $otherId);
    $state = 'none';
} else {
    json_fail('未知操作。');
}

echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);