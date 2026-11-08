<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => '请先登录。'], JSON_UNESCAPED_UNICODE));
}

$me = (int) $user['id'];
$otherId = (int) ($_GET['u'] ?? $_POST['u'] ?? 0);
if ($otherId <= 0 || $otherId === $me || !are_friends($me, $otherId)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => '只能和好友聊天。'], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $content = trim((string) ($_POST['content'] ?? ''));
    [$voiceFile, $voiceDur] = voice_from_post($_POST, $me);
    if ($content === '' && $voiceFile === null) {
        json_fail('消息不能为空。');
    }
    $content = mb_substr($content, 0, 2000);
    $created = now();
    [$locLat, $locLng, $locName] = location_from_post($_POST);
    $ins = db()->prepare('INSERT INTO private_messages (sender_id, receiver_id, content, loc_lat, loc_lng, loc_name, voice_file, voice_dur, is_read, created_at) VALUES (:sender_id, :receiver_id, :content, :loc_lat, :loc_lng, :loc_name, :voice_file, :voice_dur, 0, :created_at)');
    $ins->execute([
        ':sender_id' => $me,
        ':receiver_id' => $otherId,
        ':content' => $content,
        ':loc_lat' => $locLat,
        ':loc_lng' => $locLng,
        ':loc_name' => $locName,
        ':voice_file' => $voiceFile,
        ':voice_dur' => $voiceDur,
        ':created_at' => $created,
    ]);
    notification_add($otherId, $me, 'message', null, $user['display_name'] . ' 给你发来一条私信', '/chat.php?u=' . $me);
    echo json_encode([
        'ok' => true,
        'message' => [
            'id' => (int) db()->lastInsertId(),
            'mine' => true,
            'content' => $content,
            'voice_html' => voice_html($voiceFile, $voiceDur),
            'loc_html' => location_chip_html($locLat, $locLng, $locName),
            'created_at' => $created,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$after = (int) ($_GET['after'] ?? 0);
if ($after > 0) {
    $stmt = db()->prepare('
        SELECT id, sender_id, content, loc_lat, loc_lng, loc_name, voice_file, voice_dur, created_at
        FROM private_messages
        WHERE ((sender_id = :me AND receiver_id = :other) OR (sender_id = :other2 AND receiver_id = :me2))
          AND id > :after
        ORDER BY id ASC
        LIMIT 200
    ');
    $stmt->execute([':me' => $me, ':other' => $otherId, ':other2' => $otherId, ':me2' => $me, ':after' => $after]);
} else {
    $stmt = db()->prepare('
        SELECT id, sender_id, content, loc_lat, loc_lng, loc_name, voice_file, voice_dur, created_at
        FROM private_messages
        WHERE (sender_id = :me AND receiver_id = :other) OR (sender_id = :other2 AND receiver_id = :me2)
        ORDER BY id ASC
        LIMIT 500
    ');
    $stmt->execute([':me' => $me, ':other' => $otherId, ':other2' => $otherId, ':me2' => $me]);
}

$rows = $stmt->fetchAll();
$messages = [];
$last = $after;
foreach ($rows as $r) {
    $rid = (int) $r['id'];
    if ($rid > $last) {
        $last = $rid;
    }
    $messages[] = [
        'id' => $rid,
        'mine' => (int) $r['sender_id'] === $me,
        'content' => $r['content'],
        'voice_html' => voice_html($r['voice_file'] ?? null, (int) ($r['voice_dur'] ?? 0)),
        'loc_html' => location_chip_html($r['loc_lat'] ?? null, $r['loc_lng'] ?? null, $r['loc_name'] ?? ''),
        'created_at' => $r['created_at'],
    ];
}

$mark = db()->prepare('UPDATE private_messages SET is_read = 1 WHERE receiver_id = :receiver_id AND sender_id = :sender_id AND is_read = 0');
$mark->execute([':receiver_id' => $me, ':sender_id' => $otherId]);
$markNotif = db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND actor_id = :actor_id AND type = 'message' AND is_read = 0");
$markNotif->execute([':user_id' => $me, ':actor_id' => $otherId]);

echo json_encode(['ok' => true, 'messages' => $messages, 'last' => $last], JSON_UNESCAPED_UNICODE);