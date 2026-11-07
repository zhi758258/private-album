<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/ddz_engine.php';

@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function ddz_json(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ddz_load(string $code): ?array
{
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT id, code, owner_id, status, state FROM ddz_rooms WHERE code = :code');
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $state = json_decode((string) $row['state'], true);
    if (!is_array($state)) {
        return null;
    }
    $state['_roomId'] = (int) $row['id'];
    $state['_ownerId'] = (int) $row['owner_id'];
    return $state;
}

function ddz_save(string $code, array $state): void
{
    $phase = $state['phase'] ?? 'waiting';
    $status = $phase === 'waiting' ? 'waiting' : ($phase === 'over' ? 'over' : 'playing');
    $stmt = db()->prepare('UPDATE ddz_rooms SET state = :state, status = :status, updated_at = :updated_at WHERE code = :code');
    $stmt->execute([
        ':state' => json_encode($state, JSON_UNESCAPED_UNICODE),
        ':status' => $status,
        ':updated_at' => now(),
        ':code' => $code,
    ]);
}

function ddz_seat_of(array $state, int $uid): int
{
    foreach ($state['players'] as $p) {
        if (empty($p['is_ai']) && (int) ($p['uid'] ?? 0) === $uid) {
            return (int) $p['seat'];
        }
    }
    return -1;
}

function ddz_new_code(): string
{
    for ($i = 0; $i < 20; $i++) {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = db()->prepare('SELECT 1 FROM ddz_rooms WHERE code = :code');
        $stmt->execute([':code' => $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    return strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
}

function ddz_member(array $user): array
{
    return [
        'uid' => (int) $user['id'],
        'name' => (string) $user['display_name'],
        'avatar' => $user['avatar'] ?? null,
        'is_ai' => 0,
        'ready' => 1,
        'score' => 0,
        'last_delta' => 0,
    ];
}

function ddz_seat(int $seat, array $member): array
{
    return array_merge(['seat' => $seat], $member);
}

function ddz_ai_name(int $seat): string
{
    $names = ['小美', '阿强', '大宝', '豆豆'];
    return $names[$seat % count($names)] . '(AI)';
}

function ddz_record(string $code, array &$state): void
{
    if (!empty($state['recorded'])) {
        return;
    }
    $state['recorded'] = true;
    $ins = db()->prepare('INSERT INTO ddz_games (user_id, room_code, delta, created_at) VALUES (:user_id, :room_code, :delta, :created_at)');
    foreach ($state['players'] as $p) {
        if (!empty($p['is_ai'])) {
            continue;
        }
        $uid = (int) ($p['uid'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $ins->execute([':user_id' => $uid, ':room_code' => $code, ':delta' => (int) ($p['last_delta'] ?? 0), ':created_at' => now()]);
    }
}

function ddz_lobby_rooms(): array
{
    $stmt = db()->query("SELECT code, owner_id, state, updated_at FROM ddz_rooms WHERE status = 'waiting' ORDER BY id DESC LIMIT 30");
    $rooms = [];
    foreach ($stmt->fetchAll() as $row) {
        $state = json_decode((string) $row['state'], true);
        if (!is_array($state)) {
            continue;
        }
        $occupied = 0;
        $ai = 0;
        foreach ($state['players'] as $p) {
            if (!empty($p['is_ai'])) {
                $ai++;
            } elseif ((int) ($p['uid'] ?? 0) > 0) {
                $occupied++;
            }
        }
        $rooms[] = [
            'code' => (string) $row['code'],
            'ownerId' => (int) $row['owner_id'],
            'people' => $occupied + $ai,
            'updatedAt' => (string) $row['updated_at'],
        ];
    }
    return $rooms;
}

$user = current_user();
if (!$user) {
    ddz_json(['ok' => false, 'error' => '请先登录。'], 401);
}
$uid = (int) $user['id'];
$action = (string) ($_REQUEST['action'] ?? 'state');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

if ($action === 'quick_start') {
    $players = [
        ddz_seat(0, ddz_member($user)),
        ddz_seat(1, ['uid' => 0, 'name' => ddz_ai_name(1), 'avatar' => null, 'is_ai' => 1, 'ready' => 1, 'score' => 0, 'last_delta' => 0]),
        ddz_seat(2, ['uid' => 0, 'name' => ddz_ai_name(2), 'avatar' => null, 'is_ai' => 1, 'ready' => 1, 'score' => 0, 'last_delta' => 0]),
    ];
    $state = ddz_new_state($players);
    ddz_start_game($state);
    ddz_advance($state);
    $code = ddz_new_code();
    $ins = db()->prepare('INSERT INTO ddz_rooms (code, owner_id, status, state, created_at, updated_at) VALUES (:code, :owner_id, :status, :state, :created_at, :updated_at)');
    $ins->execute([
        ':code' => $code,
        ':owner_id' => $uid,
        ':status' => 'playing',
        ':state' => json_encode($state, JSON_UNESCAPED_UNICODE),
        ':created_at' => now(),
        ':updated_at' => now(),
    ]);
    ddz_json(['ok' => true, 'code' => $code]);
}

if ($action === 'create') {
    $players = [
        ddz_seat(0, ddz_member($user)),
        ddz_seat(1, ['uid' => 0, 'name' => '虚位以待', 'avatar' => null, 'is_ai' => 0, 'ready' => 0, 'score' => 0, 'last_delta' => 0]),
        ddz_seat(2, ['uid' => 0, 'name' => '虚位以待', 'avatar' => null, 'is_ai' => 0, 'ready' => 0, 'score' => 0, 'last_delta' => 0]),
    ];
    $state = ddz_new_state($players);
    $code = ddz_new_code();
    $ins = db()->prepare('INSERT INTO ddz_rooms (code, owner_id, status, state, created_at, updated_at) VALUES (:code, :owner_id, :status, :state, :created_at, :updated_at)');
    $ins->execute([
        ':code' => $code,
        ':owner_id' => $uid,
        ':status' => 'waiting',
        ':state' => json_encode($state, JSON_UNESCAPED_UNICODE),
        ':created_at' => now(),
        ':updated_at' => now(),
    ]);
    ddz_json(['ok' => true, 'code' => $code]);
}

if ($action === 'join') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null) {
        ddz_json(['ok' => false, 'error' => '房间不存在或已结束。'], 404);
    }
    if (ddz_seat_of($state, $uid) >= 0) {
        ddz_json(['ok' => true, 'code' => $code]);
    }
    if ($state['phase'] !== 'waiting') {
        ddz_json(['ok' => false, 'error' => '该房间已开始对局。'], 400);
    }
    $placed = false;
    foreach ($state['players'] as $i => $p) {
        if (empty($p['is_ai']) && (int) ($p['uid'] ?? 0) === 0) {
            $state['players'][$i] = ddz_seat((int) $p['seat'], ddz_member($user));
            $placed = true;
            break;
        }
    }
    if (!$placed) {
        ddz_json(['ok' => false, 'error' => '房间已满。'], 400);
    }
    ddz_save($code, $state);
    ddz_json(['ok' => true, 'code' => $code]);
}

if ($action === 'start') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null) {
        ddz_json(['ok' => false, 'error' => '房间不存在。'], 404);
    }
    if ((int) $state['_ownerId'] !== $uid) {
        ddz_json(['ok' => false, 'error' => '只有房主可以开始。'], 403);
    }
    if ($state['phase'] !== 'waiting') {
        ddz_json(['ok' => true, 'code' => $code]);
    }
    foreach ($state['players'] as $i => $p) {
        if (empty($p['is_ai']) && (int) ($p['uid'] ?? 0) === 0) {
            $state['players'][$i] = ddz_seat((int) $p['seat'], [
                'uid' => 0,
                'name' => ddz_ai_name((int) $p['seat']),
                'avatar' => null,
                'is_ai' => 1,
                'ready' => 1,
                'score' => 0,
                'last_delta' => 0,
            ]);
        }
    }
    ddz_start_game($state);
    ddz_advance($state);
    if ($state['phase'] === 'over') {
        ddz_record($code, $state);
    }
    ddz_save($code, $state);
    ddz_json(['ok' => true, 'code' => $code]);
}

if ($action === 'restart') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null) {
        ddz_json(['ok' => false, 'error' => '房间不存在。'], 404);
    }
    if (ddz_seat_of($state, $uid) < 0) {
        ddz_json(['ok' => false, 'error' => '你不在该房间。'], 403);
    }
    foreach ($state['players'] as $i => $p) {
        if (empty($p['is_ai']) && (int) ($p['uid'] ?? 0) === 0) {
            $state['players'][$i] = ddz_seat((int) $p['seat'], [
                'uid' => 0,
                'name' => ddz_ai_name((int) $p['seat']),
                'avatar' => null,
                'is_ai' => 1,
                'ready' => 1,
                'score' => (int) ($p['score'] ?? 0),
                'last_delta' => 0,
            ]);
        }
    }
    $state['recorded'] = false;
    ddz_start_game($state);
    ddz_advance($state);
    if ($state['phase'] === 'over') {
        ddz_record($code, $state);
    }
    ddz_save($code, $state);
    ddz_json(['ok' => true, 'code' => $code]);
}

if ($action === 'state') {
    $code = strtoupper(trim((string) ($_GET['code'] ?? $_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null) {
        ddz_json(['ok' => false, 'error' => '房间不存在或已结束。'], 404);
    }
    $seat = ddz_seat_of($state, $uid);
    if ($seat < 0) {
        ddz_json(['ok' => false, 'error' => '你不在该房间。'], 403);
    }
    if ($state['phase'] === 'bid' || $state['phase'] === 'play') {
        ddz_advance($state);
        if ($state['phase'] === 'over') {
            ddz_record($code, $state);
        }
        ddz_save($code, $state);
    }
    ddz_json([
        'ok' => true,
        'code' => $code,
        'seat' => $seat,
        'isOwner' => (int) $state['_ownerId'] === $uid,
        'state' => ddz_public_state($state, $seat),
    ]);
}

if ($action === 'bid') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null || $state['phase'] !== 'bid') {
        ddz_json(['ok' => false, 'error' => '当前不能抢地主。'], 400);
    }
    $seat = ddz_seat_of($state, $uid);
    if ($seat < 0) {
        ddz_json(['ok' => false, 'error' => '你不在该房间。'], 403);
    }
    ddz_apply_bid($state, $seat, (int) ($_POST['rob'] ?? 0) === 1);
    ddz_advance($state);
    if ($state['phase'] === 'over') {
        ddz_record($code, $state);
    }
    ddz_save($code, $state);
    ddz_json(['ok' => true]);
}

if ($action === 'play') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null || $state['phase'] !== 'play') {
        ddz_json(['ok' => false, 'error' => '当前不能出牌。'], 400);
    }
    $seat = ddz_seat_of($state, $uid);
    if ($seat < 0) {
        ddz_json(['ok' => false, 'error' => '你不在该房间。'], 403);
    }
    $cards = json_decode((string) ($_POST['cards'] ?? '[]'), true);
    if (!is_array($cards)) {
        $cards = [];
    }
    $r = ddz_apply_play($state, $seat, $cards);
    if (!$r['ok']) {
        ddz_json(['ok' => false, 'error' => $r['error']], 400);
    }
    ddz_advance($state);
    if ($state['phase'] === 'over') {
        ddz_record($code, $state);
    }
    ddz_save($code, $state);
    ddz_json(['ok' => true]);
}

if ($action === 'pass') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null || $state['phase'] !== 'play') {
        ddz_json(['ok' => false, 'error' => '当前不能过牌。'], 400);
    }
    $seat = ddz_seat_of($state, $uid);
    if ($seat < 0) {
        ddz_json(['ok' => false, 'error' => '你不在该房间。'], 403);
    }
    $r = ddz_apply_pass($state, $seat);
    if (!$r['ok']) {
        ddz_json(['ok' => false, 'error' => $r['error']], 400);
    }
    ddz_advance($state);
    if ($state['phase'] === 'over') {
        ddz_record($code, $state);
    }
    ddz_save($code, $state);
    ddz_json(['ok' => true]);
}

if ($action === 'hint') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? $_GET['code'] ?? '')));
    $state = ddz_load($code);
    if ($state === null || $state['phase'] !== 'play') {
        ddz_json(['ok' => false, 'error' => '当前不能提示。'], 400);
    }
    $seat = ddz_seat_of($state, $uid);
    if ($seat < 0) {
        ddz_json(['ok' => false, 'error' => '你不在该房间。'], 403);
    }
    if ((int) $state['turn'] !== $seat) {
        ddz_json(['ok' => false, 'error' => '还没轮到你。'], 400);
    }
    $hand = $state['hands'][$seat] ?? [];
    $last = $state['lastPlay'];
    if (is_array($last) && (int) ($last['seat'] ?? -1) === $seat) {
        $last = null;
    }
    $hints = ddz_hints($hand, is_array($last) ? $last : null);
    ddz_json(['ok' => true, 'hints' => $hints, 'pass' => !$hints && $last !== null]);
}

if ($action === 'leave') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $state = ddz_load($code);
    if ($state !== null) {
        $seat = ddz_seat_of($state, $uid);
        if ($seat >= 0) {
            if ($state['phase'] === 'waiting') {
                if ((int) $state['_ownerId'] === $uid) {
                    $del = db()->prepare('DELETE FROM ddz_rooms WHERE code = :code');
                    $del->execute([':code' => $code]);
                    ddz_json(['ok' => true]);
                }
                foreach ($state['players'] as $i => $p) {
                    if ((int) $p['seat'] === $seat) {
                        $state['players'][$i] = ddz_seat($seat, ['uid' => 0, 'name' => '虚位以待', 'avatar' => null, 'is_ai' => 0, 'ready' => 0, 'score' => 0, 'last_delta' => 0]);
                    }
                }
            } else {
                foreach ($state['players'] as $i => $p) {
                    if ((int) $p['seat'] === $seat) {
                        $state['players'][$i] = ddz_seat($seat, ['uid' => 0, 'name' => '托管(AI)', 'avatar' => null, 'is_ai' => 1, 'ready' => 1, 'score' => (int) ($p['score'] ?? 0), 'last_delta' => 0]);
                    }
                }
                ddz_advance($state);
            }
            ddz_save($code, $state);
        }
    }
    ddz_json(['ok' => true]);
}

if ($action === 'rooms') {
    ddz_json(['ok' => true, 'rooms' => ddz_lobby_rooms()]);
}

if ($action === 'score') {
    $stmt = db()->prepare('SELECT COALESCE(SUM(delta), 0) FROM ddz_games WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $uid]);
    $total = (int) $stmt->fetchColumn();
    $stmt = db()->prepare('SELECT room_code, delta, created_at FROM ddz_games WHERE user_id = :user_id ORDER BY id DESC LIMIT 10');
    $stmt->execute([':user_id' => $uid]);
    ddz_json(['ok' => true, 'total' => $total, 'recent' => $stmt->fetchAll()]);
}

ddz_json(['ok' => false, 'error' => '未知操作。'], 400);