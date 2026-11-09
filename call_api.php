<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$me = (int) $user['id'];
$pdo = db();

function call_peer_info(int $id): array
{
    $stmt = db()->prepare('SELECT id, display_name, username, avatar FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $u = $stmt->fetch();
    if (!$u) {
        return ['id' => $id, 'name' => '用户', 'username' => '', 'avatar_html' => ''];
    }
    return [
        'id' => (int) $u['id'],
        'name' => (string) $u['display_name'],
        'username' => (string) $u['username'],
        'avatar_html' => avatar_html($u['avatar'] ?? null, (string) $u['display_name'], 'avatar avatar-md'),
    ];
}

function call_render(array $row, int $me): array
{
    $isCaller = (int) $row['caller_id'] === $me;
    $peerId = $isCaller ? (int) $row['callee_id'] : (int) $row['caller_id'];
    $status = (string) $row['status'];
    return [
        'id' => (int) $row['id'],
        'role' => $isCaller ? 'caller' : 'callee',
        'status' => $status,
        'peer' => call_peer_info($peerId),
        'answer' => ($isCaller && $status === 'accepted') ? (string) ($row['answer'] ?? '') : '',
    ];
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? 'poll');
if ($method === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
}

if ($method === 'POST') {
    switch ($action) {
        case 'start':
            $to = (int) ($_POST['to'] ?? 0);
            $offer = (string) ($_POST['offer'] ?? '');
            if ($to <= 0 || $to === $me) {
                json_fail('通话对象无效。');
            }
            if (!are_friends($me, $to)) {
                json_fail('只能和好友视频通话。');
            }
            if ($offer === '' || strlen($offer) > 200000) {
                json_fail('通话信息无效。');
            }
            $now = now();
            $end = $pdo->prepare("UPDATE calls SET status = 'ended', updated_at = :now
                WHERE status IN ('ringing', 'accepted') AND (caller_id = :me OR callee_id = :me2)");
            $end->execute([':now' => $now, ':me' => $me, ':me2' => $me]);
            $busy = $pdo->prepare("SELECT id FROM calls
                WHERE ((status = 'accepted' AND updated_at >= :seen_cut)
                    OR (status = 'ringing' AND created_at >= :ring_cut))
                  AND (caller_id = :to OR callee_id = :to2)
                LIMIT 1");
            $busy->execute([
                ':seen_cut' => date('Y-m-d H:i:s', time() - 100),
                ':ring_cut' => date('Y-m-d H:i:s', time() - 50),
                ':to' => $to,
                ':to2' => $to,
            ]);
            if ($busy->fetch() !== false) {
                json_fail('对方正在通话中，请稍后再拨。');
            }
            $ins = $pdo->prepare('INSERT INTO calls (caller_id, callee_id, status, offer, caller_seen, created_at, updated_at)
                VALUES (:caller_id, :callee_id, :status, :offer, :caller_seen, :created_at, :updated_at)');
            $ins->execute([
                ':caller_id' => $me,
                ':callee_id' => $to,
                ':status' => 'ringing',
                ':offer' => $offer,
                ':caller_seen' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('SELECT * FROM calls WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            exit(json_encode(['ok' => true, 'call' => $row ? call_render($row, $me) : null], JSON_UNESCAPED_UNICODE));

        case 'accept':
            $id = (int) ($_POST['call_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM calls WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row || (int) $row['callee_id'] !== $me || $row['status'] !== 'ringing') {
                json_fail('通话已失效。');
            }
            $now = now();
            $upd = $pdo->prepare("UPDATE calls SET status = 'accepted', callee_seen = :now, updated_at = :now2 WHERE id = :id AND status = 'ringing'");
            $upd->execute([':now' => $now, ':now2' => $now, ':id' => $id]);
            exit(json_encode(['ok' => true, 'offer' => (string) ($row['offer'] ?? '')], JSON_UNESCAPED_UNICODE));

        case 'answer':
            $id = (int) ($_POST['call_id'] ?? 0);
            $answer = (string) ($_POST['answer'] ?? '');
            if ($answer === '' || strlen($answer) > 200000) {
                json_fail('通话信息无效。');
            }
            $stmt = $pdo->prepare('SELECT * FROM calls WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row || (int) $row['callee_id'] !== $me || $row['status'] !== 'accepted') {
                json_fail('通话已失效。');
            }
            $now = now();
            $upd = $pdo->prepare('UPDATE calls SET answer = :answer, callee_seen = :now, updated_at = :now2 WHERE id = :id');
            $upd->execute([':answer' => $answer, ':now' => $now, ':now2' => $now, ':id' => $id]);
            exit(json_encode(['ok' => true], JSON_UNESCAPED_UNICODE));

        case 'candidate':
            $id = (int) ($_POST['call_id'] ?? 0);
            $payload = (string) ($_POST['payload'] ?? '');
            if ($payload === '' || strlen($payload) > 20000) {
                json_fail('候选信息无效。');
            }
            $stmt = $pdo->prepare('SELECT * FROM calls WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row || ((int) $row['caller_id'] !== $me && (int) $row['callee_id'] !== $me)
                || !in_array($row['status'], ['ringing', 'accepted'], true)) {
                json_fail('通话已失效。');
            }
            $ins = $pdo->prepare('INSERT INTO call_ice (call_id, sender_id, payload, created_at) VALUES (:call_id, :sender_id, :payload, :created_at)');
            $ins->execute([':call_id' => $id, ':sender_id' => $me, ':payload' => $payload, ':created_at' => now()]);
            exit(json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE));

        case 'decline':
            $id = (int) ($_POST['call_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM calls WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row || (int) $row['callee_id'] !== $me || $row['status'] !== 'ringing') {
                json_fail('通话已失效。');
            }
            $upd = $pdo->prepare("UPDATE calls SET status = 'declined', updated_at = :now WHERE id = :id");
            $upd->execute([':now' => now(), ':id' => $id]);
            exit(json_encode(['ok' => true], JSON_UNESCAPED_UNICODE));

        case 'end':
            $id = (int) ($_POST['call_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM calls WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row || ((int) $row['caller_id'] !== $me && (int) $row['callee_id'] !== $me)) {
                json_fail('通话已失效。');
            }
            $upd = $pdo->prepare("UPDATE calls SET status = 'ended', updated_at = :now WHERE id = :id AND status IN ('ringing', 'accepted')");
            $upd->execute([':now' => now(), ':id' => $id]);
            exit(json_encode(['ok' => true], JSON_UNESCAPED_UNICODE));
    }
    json_fail('未知操作。');
}

$now = now();
$seenCut = date('Y-m-d H:i:s', time() - 90);
$ringCut = date('Y-m-d H:i:s', time() - 50);
$pdo->prepare("UPDATE calls SET status = 'ended', updated_at = :now
    WHERE status = 'accepted'
      AND (
        (caller_seen IS NULL OR caller_seen < :seen_cut)
        OR (callee_seen IS NULL OR callee_seen < :seen_cut2)
      )")->execute([':now' => $now, ':seen_cut' => $seenCut, ':seen_cut2' => $seenCut]);
$pdo->prepare("UPDATE calls SET status = 'missed', updated_at = :now
    WHERE status = 'ringing' AND created_at < :ring_cut")->execute([':now' => $now, ':ring_cut' => $ringCut]);

$stmt = $pdo->prepare("SELECT * FROM calls
    WHERE status IN ('ringing', 'accepted') AND (caller_id = :me OR callee_id = :me2)
    ORDER BY id DESC LIMIT 1");
$stmt->execute([':me' => $me, ':me2' => $me]);
$row = $stmt->fetch();

$call = null;
$candidates = [];
if ($row) {
    $isCaller = (int) $row['caller_id'] === $me;
    $seenCol = $isCaller ? 'caller_seen' : 'callee_seen';
    $upd = $pdo->prepare("UPDATE calls SET `{$seenCol}` = :now WHERE id = :id");
    $upd->execute([':now' => $now, ':id' => (int) $row['id']]);
    $call = call_render($row, $me);

    $afterIce = (int) ($_GET['after_ice'] ?? 0);
    $ice = $pdo->prepare('SELECT id, payload FROM call_ice
        WHERE call_id = :call_id AND sender_id <> :me AND id > :after
        ORDER BY id ASC LIMIT 100');
    $ice->execute([':call_id' => (int) $row['id'], ':me' => $me, ':after' => $afterIce]);
    foreach ($ice->fetchAll() as $c) {
        $candidates[] = ['id' => (int) $c['id'], 'payload' => (string) $c['payload']];
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'me' => $me,
    'call' => $call,
    'candidates' => $candidates,
    'server_time' => $now,
], JSON_UNESCAPED_UNICODE);
