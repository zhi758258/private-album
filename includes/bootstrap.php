<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

const UPLOAD_DIR = __DIR__ . '/../uploads';
const DATA_DIR = __DIR__ . '/../data';
const FILES_DIR = __DIR__ . '/../wenjian123';
const AVATAR_DIR = UPLOAD_DIR . '/avatars';
const VOICE_DIR = UPLOAD_DIR . '/voice';
const MEDIA_DIR = UPLOAD_DIR . '/media';
const CONFIG_FILE = DATA_DIR . '/config.php';
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const MAX_AVATAR_BYTES = 5 * 1024 * 1024;
const MAX_VOICE_BYTES = 6 * 1024 * 1024;
const MAX_MEDIA_BYTES = 25 * 1024 * 1024;
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
const NAV_DEFAULTS = [
    'home' => '首页',
    'board' => '留言板',
    'space' => '空间',
    'friends' => '好友',
    'album' => '我的相册',
    'memo' => '备忘录',
    'ddz' => '斗地主',
    'profile' => '个人中心',
    'settings' => '设置',
    'admin' => '用户管理',
    'admin_cards' => '卡密管理',
    'admin_photos' => '全部相册',
    'admin_memos' => '全部备忘录',
    'files' => '文件管理',
    'admin_settings' => '后台设置',
];

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(AVATAR_DIR)) {
    mkdir(AVATAR_DIR, 0755, true);
}
if (!is_dir(VOICE_DIR)) {
    mkdir(VOICE_DIR, 0755, true);
}
if (!is_dir(MEDIA_DIR)) {
    mkdir(MEDIA_DIR, 0755, true);
}
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(FILES_DIR)) {
    mkdir(FILES_DIR, 0755, true);
}

function db_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    if (!is_file(CONFIG_FILE)) {
        http_response_code(503);
        exit('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>尚未安装</title></head>'
            . '<body style="font-family:sans-serif; background:#14110e; color:#f3eadb; display:grid; place-items:center; min-height:100vh; margin:0;">'
            . '<div style="max-width:520px; padding:40px; text-align:center; border:1px solid #3a3228; border-radius:14px; background:#1d1914;">'
            . '<h1 style="margin:0 0 12px; font-weight:500;">还没有配置数据库</h1>'
            . '<p style="color:#b9a894; line-height:1.8;">请先访问 <a href="/install.php" style="color:#d4a574;">安装向导</a> 完成数据库配置，'
            . '或参考根目录 config.example.php 手动创建 data/config.php。</p>'
            . '</div></body></html>');
    }
    $config = require CONFIG_FILE;
    foreach (['host', 'port', 'name', 'user', 'pass'] as $field) {
        if (!array_key_exists($field, $config)) {
            http_response_code(500);
            exit('data/config.php 配置不完整，缺少 ' . $field . '，请重新运行安装向导。');
        }
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        (int) $cfg['port'],
        $cfg['name']
    );
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    init_schema($pdo);
    return $pdo;
}

function mysql_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column
    ');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function init_schema(PDO $pdo): void
{
    require_once __DIR__ . '/schema.php';
    run_schema($pdo);

    if (!mysql_column_exists($pdo, 'messages', 'parent_id')) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN parent_id INT UNSIGNED NULL');
        $pdo->exec('CREATE INDEX idx_messages_parent ON messages (parent_id)');
    }
    foreach (['messages', 'private_messages'] as $locTable) {
        if (!mysql_column_exists($pdo, $locTable, 'loc_lat')) {
            $pdo->exec("ALTER TABLE `{$locTable}`
                ADD COLUMN loc_lat DECIMAL(10,7) NULL,
                ADD COLUMN loc_lng DECIMAL(10,7) NULL,
                ADD COLUMN loc_name VARCHAR(120) NOT NULL DEFAULT ''");
        }
        if (!mysql_column_exists($pdo, $locTable, 'voice_file')) {
            $pdo->exec("ALTER TABLE `{$locTable}`
                ADD COLUMN voice_file VARCHAR(100) NULL,
                ADD COLUMN voice_dur SMALLINT UNSIGNED NOT NULL DEFAULT 0");
        }
        if (!mysql_column_exists($pdo, $locTable, 'media_file')) {
            $pdo->exec("ALTER TABLE `{$locTable}`
                ADD COLUMN media_file VARCHAR(100) NULL,
                ADD COLUMN media_dur SMALLINT UNSIGNED NOT NULL DEFAULT 0");
        }
    }
    if (!mysql_column_exists($pdo, 'users', 'avatar')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(100) NULL');
    }
    if (!mysql_column_exists($pdo, 'users', 'phone')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(32) NOT NULL DEFAULT ''");
    }
    if (!mysql_column_exists($pdo, 'photos', 'on_wall')) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN on_wall TINYINT UNSIGNED NOT NULL DEFAULT 1');
        $pdo->exec('CREATE INDEX idx_photos_wall ON photos (on_wall)');
        $pdo->exec("
            UPDATE photos SET on_wall = 0
            WHERE user_id IN (SELECT id FROM users WHERE role = 'admin')
        ");
    }

    $pdo->exec("UPDATE users SET display_name = '死鬼老公' WHERE username = 'admin' AND display_name = '管理员'");

    $wallMigrated = (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE `key` = 'wall_private_migrated'")->fetchColumn();
    if ($wallMigrated === 0) {
        $pdo->exec("UPDATE photos SET on_wall = 0 WHERE user_id IN (SELECT id FROM users WHERE role <> 'admin')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('wall_private_migrated', '1')");
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('
            INSERT INTO users (username, password_hash, display_name, role, created_at)
            VALUES (:username, :password_hash, :display_name, :role, :created_at)
        ');
        $stmt->execute([
            ':username' => 'admin',
            ':password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            ':display_name' => '死鬼老公',
            ':role' => 'admin',
            ':created_at' => now(),
        ]);
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function setting_get(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT `value` FROM settings WHERE `key` = :key');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : $default;
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function app_name(): string
{
    static $name = null;
    if ($name !== null) {
        return $name;
    }
    $value = setting_get('site_name');
    $name = $value !== '' ? $value : '私藏';
    return $name;
}

function nav_label(string $key): string
{
    $default = NAV_DEFAULTS[$key] ?? $key;
    $value = setting_get('nav_' . $key, $default);
    return $value === '' ? $default : $value;
}

function notice_data(): ?array
{
    if (setting_get('notice_enabled') !== '1') {
        return null;
    }
    $content = trim(setting_get('notice_content'));
    if ($content === '') {
        return null;
    }
    return [
        'content' => $content,
        'updated' => setting_get('notice_updated'),
        'once' => setting_get('notice_once') === '1' ? '1' : '0',
    ];
}

function notice_markup(): string
{
    $notice = notice_data();
    if (!$notice) {
        return '';
    }
    $content = nl2br(h($notice['content']));
    $updated = h($notice['updated']);
    $once = h($notice['once']);
    return <<<HTML
<div class="notice-mask" id="noticeMask" hidden data-once="{$once}" data-updated="{$updated}">
    <div class="notice-box" role="dialog" aria-modal="true">
        <div class="notice-body">{$content}</div>
        <button type="button" class="notice-ok" id="noticeOk">知道了</button>
    </div>
</div>
<script>
(function () {
    var mask = document.getElementById('noticeMask');
    var ok = document.getElementById('noticeOk');
    if (!mask || !ok) return;
    var once = mask.getAttribute('data-once') === '1';
    var key = 'notice_ack_' + (mask.getAttribute('data-updated') || '');
    function hide() {
        mask.hidden = true;
        document.body.classList.remove('modal-open');
    }
    function show() {
        mask.hidden = false;
        document.body.classList.add('modal-open');
    }
    ok.addEventListener('click', function () {
        if (once) {
            try { localStorage.setItem(key, '1'); } catch (e) {}
        }
        hide();
    });
    try {
        if (once && localStorage.getItem(key) === '1') return;
    } catch (e) {}
    var type = 'navigate';
    try {
        var nav = performance.getEntriesByType('navigation')[0];
        if (nav && nav.type) type = nav.type;
        else if (performance.navigation) {
            if (performance.navigation.type === 1) type = 'reload';
            else if (performance.navigation.type === 2) type = 'back_forward';
        }
    } catch (e) {}
    var isReload = type === 'reload';
    var isEntry = false;
    try {
        isEntry = !sessionStorage.getItem('site_nav');
        sessionStorage.setItem('site_nav', '1');
    } catch (e) {
        isEntry = true;
    }
    if (isReload || isEntry) show();
})();
</script>
HTML;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_xhr(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function json_ok(string $message = ''): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_fail(string $error): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

function avatar_html(?string $avatar, string $name, string $cls = 'avatar'): string
{
    if ($avatar !== null && $avatar !== '') {
        $src = '/avatar.php?u=' . rawurlencode($avatar);
        return '<img class="' . h($cls) . '" src="' . h($src) . '" alt="' . h($name) . '" loading="lazy">';
    }
    $ch = mb_substr(trim($name), 0, 1);
    if ($ch === '') {
        $ch = '?';
    }
    return '<span class="' . h($cls) . ' avatar-fallback">' . h($ch) . '</span>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('请求校验失败，请刷新后重试。');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, display_name, role, avatar FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('需要管理员权限。');
    }
    return $user;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $path): void
{
    if (is_xhr()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'redirect' => $path], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . $path);
    exit;
}

function delete_photo_by_id(int $id): bool
{
    $stmt = db()->prepare('SELECT id, filename FROM photos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $photo = $stmt->fetch();
    if (!$photo) {
        return false;
    }
    $name = basename((string) $photo['filename']);
    $path = UPLOAD_DIR . '/' . $name;
    $realDir = realpath(UPLOAD_DIR);
    $realFile = realpath($path);
    if ($realDir !== false && $realFile !== false && str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) && is_file($realFile)) {
        unlink($realFile);
    }
    $del = db()->prepare('DELETE FROM photos WHERE id = :id');
    $del->execute([':id' => $id]);
    return true;
}

function detect_image_mime(string $path): ?string
{
    $info = @getimagesize($path);
    if (is_array($info) && !empty($info['mime'])) {
        $mime = (string) $info['mime'];
        return isset(ALLOWED_MIME[$mime]) ? $mime : null;
    }
    if (function_exists('mime_content_type')) {
        $mime = (string) @mime_content_type($path);
        return isset(ALLOWED_MIME[$mime]) ? $mime : null;
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);
        return isset(ALLOWED_MIME[$mime]) ? $mime : null;
    }
    return null;
}

function photo_like_count(int $photoId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM photo_likes WHERE photo_id = :photo_id');
    $stmt->execute([':photo_id' => $photoId]);
    return (int) $stmt->fetchColumn();
}

function photo_liked_by(int $photoId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM photo_likes WHERE photo_id = :photo_id AND user_id = :user_id');
    $stmt->execute([':photo_id' => $photoId, ':user_id' => $userId]);
    return $stmt->fetch() !== false;
}

function toggle_photo_like(int $photoId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM photo_likes WHERE photo_id = :photo_id AND user_id = :user_id');
    $stmt->execute([':photo_id' => $photoId, ':user_id' => $userId]);
    if ($stmt->fetch()) {
        $del = db()->prepare('DELETE FROM photo_likes WHERE photo_id = :photo_id AND user_id = :user_id');
        $del->execute([':photo_id' => $photoId, ':user_id' => $userId]);
        return false;
    }
    $ins = db()->prepare('
        INSERT INTO photo_likes (photo_id, user_id, created_at)
        VALUES (:photo_id, :user_id, :created_at)
    ');
    $ins->execute([':photo_id' => $photoId, ':user_id' => $userId, ':created_at' => now()]);
    return true;
}

function register_open(): bool
{
    return setting_get('register_open') === '1';
}

function register_auto_card(): bool
{
    return setting_get('register_auto_card') === '1';
}

function card_code(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 16; $i++) {
        $s .= $chars[random_int(0, strlen($chars) - 1)];
        if (($i + 1) % 4 === 0 && $i < 15) {
            $s .= '-';
        }
    }
    return $s;
}

function cards_insert(int $adminId, int $count, string $prefix = ''): int
{
    $prefix = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $prefix));
    if ($prefix !== '') {
        $prefix = mb_substr($prefix, 0, 8) . '-';
    }
    $ins = db()->prepare('
        INSERT INTO cards (code, created_by, created_at)
        VALUES (:code, :created_by, :created_at)
    ');
    $now = now();
    $added = 0;
    $attempts = 0;
    $existsStmt = db()->prepare('SELECT 1 FROM cards WHERE code = :code');
    while ($added < $count && $attempts < $count * 20 + 20) {
        $attempts++;
        $code = $prefix . card_code();
        $existsStmt->execute([':code' => $code]);
        if ($existsStmt->fetch()) {
            continue;
        }
        $ins->execute([':code' => $code, ':created_by' => $adminId, ':created_at' => $now]);
        $added++;
    }
    return $added;
}

function card_find(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM cards WHERE code = :code');
    $stmt->execute([':code' => trim($code)]);
    $card = $stmt->fetch();
    return is_array($card) ? $card : null;
}

function card_available(string $code): bool
{
    $card = card_find($code);
    return $card !== null && empty($card['used_by']);
}

function card_consume(string $code, int $userId): bool
{
    if (!card_available($code)) {
        return false;
    }
    $stmt = db()->prepare('
        UPDATE cards SET used_by = :used_by, used_at = :used_at
        WHERE code = :code AND used_by IS NULL
    ');
    $stmt->execute([
        ':used_by' => $userId,
        ':used_at' => now(),
        ':code' => trim($code),
    ]);
    return $stmt->rowCount() > 0;
}

function card_issue_auto(int $userId): string
{
    $prefix = 'AUTO-';
    $existsStmt = db()->prepare('SELECT 1 FROM cards WHERE code = :code');
    $ins = db()->prepare('
        INSERT INTO cards (code, created_by, used_by, created_at, used_at)
        VALUES (:code, :created_by, :used_by, :created_at, :used_at)
    ');
    $now = now();
    for ($i = 0; $i < 50; $i++) {
        $code = $prefix . card_code();
        $existsStmt->execute([':code' => $code]);
        if ($existsStmt->fetch()) {
            continue;
        }
        $ins->execute([
            ':code' => $code,
            ':created_by' => $userId,
            ':used_by' => $userId,
            ':created_at' => $now,
            ':used_at' => $now,
        ]);
        return $code;
    }
    return '';
}

function are_friends(int $a, int $b): bool
{
    if ($a === $b || $a <= 0 || $b <= 0) {
        return false;
    }
    $stmt = db()->prepare('SELECT 1 FROM friends WHERE user_id = :user_id AND friend_id = :friend_id');
    $stmt->execute([':user_id' => $a, ':friend_id' => $b]);
    return $stmt->fetch() !== false;
}

function add_friendship(int $a, int $b): void
{
    if ($a === $b || $a <= 0 || $b <= 0) {
        return;
    }
    $stmt = db()->prepare('INSERT IGNORE INTO friends (user_id, friend_id, created_at) VALUES (:user_id, :friend_id, :created_at)');
    $now = now();
    $stmt->execute([':user_id' => $a, ':friend_id' => $b, ':created_at' => $now]);
    $stmt->execute([':user_id' => $b, ':friend_id' => $a, ':created_at' => $now]);
}

function remove_friendship(int $a, int $b): void
{
    $stmt = db()->prepare('DELETE FROM friends WHERE (user_id = :a AND friend_id = :b) OR (user_id = :b2 AND friend_id = :a2)');
    $stmt->execute([':a' => $a, ':b' => $b, ':b2' => $b, ':a2' => $a]);
}

function friend_request_find(int $from, int $to): ?array
{
    $stmt = db()->prepare('SELECT * FROM friend_requests WHERE from_user_id = :from_user_id AND to_user_id = :to_user_id');
    $stmt->execute([':from_user_id' => $from, ':to_user_id' => $to]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function friends_of(int $uid): array
{
    $stmt = db()->prepare('
        SELECT users.id, users.display_name, users.username, users.avatar, friends.created_at
        FROM friends
        JOIN users ON users.id = friends.friend_id
        WHERE friends.user_id = :user_id
        ORDER BY users.display_name ASC
    ');
    $stmt->execute([':user_id' => $uid]);
    return $stmt->fetchAll();
}

function friend_unread_count(int $uid): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM private_messages WHERE receiver_id = :receiver_id AND is_read = 0');
    $stmt->execute([':receiver_id' => $uid]);
    return (int) $stmt->fetchColumn();
}

function friend_unread_between(int $uid, int $otherId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM private_messages WHERE receiver_id = :receiver_id AND sender_id = :sender_id AND is_read = 0');
    $stmt->execute([':receiver_id' => $uid, ':sender_id' => $otherId]);
    return (int) $stmt->fetchColumn();
}

function notification_add(int $userId, ?int $actorId, string $type, ?int $refId, string $content, string $link = ''): void
{
    if ($userId <= 0) {
        return;
    }
    if ($actorId !== null && $actorId === $userId) {
        return;
    }
    $stmt = db()->prepare('
        INSERT INTO notifications (user_id, actor_id, type, ref_id, content, link, is_read, created_at)
        VALUES (:user_id, :actor_id, :type, :ref_id, :content, :link, 0, :created_at)
    ');
    $stmt->execute([
        ':user_id' => $userId,
        ':actor_id' => $actorId,
        ':type' => $type,
        ':ref_id' => $refId,
        ':content' => mb_substr($content, 0, 255),
        ':link' => mb_substr($link, 0, 255),
        ':created_at' => now(),
    ]);
}

function notifications_unread_count(int $uid): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $stmt->execute([':user_id' => $uid]);
    return (int) $stmt->fetchColumn();
}

function notifications_recent(int $uid, int $limit = 20): array
{
    $limit = max(1, min(50, $limit));
    $stmt = db()->prepare('
        SELECT n.id, n.type, n.ref_id, n.content, n.link, n.is_read, n.created_at,
               u.display_name AS actor_name, u.avatar AS actor_avatar
        FROM notifications n
        LEFT JOIN users u ON u.id = n.actor_id
        WHERE n.user_id = :user_id
        ORDER BY n.id DESC
        LIMIT ' . $limit
    );
    $stmt->execute([':user_id' => $uid]);
    return $stmt->fetchAll();
}

function notifications_mark_read(int $uid, ?int $id = null): void
{
    if ($id !== null && $id > 0) {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND id = :id');
        $stmt->execute([':user_id' => $uid, ':id' => $id]);
        return;
    }
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
    $stmt->execute([':user_id' => $uid]);
}

function location_icon_svg(): string
{
    return '<svg viewBox="0 0 24 24" class="loc-icon" aria-hidden="true"><path d="M12 21.2s6.6-6.1 6.6-11a6.6 6.6 0 1 0-13.2 0c0 4.9 6.6 11 6.6 11z"/><circle cx="12" cy="10" r="2.4"/></svg>';
}

function location_from_post(array $post): array
{
    $latRaw = $post['loc_lat'] ?? '';
    $lngRaw = $post['loc_lng'] ?? '';
    $name = trim((string) ($post['loc_name'] ?? ''));
    if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
        return [null, null, ''];
    }
    $lat = (float) $latRaw;
    $lng = (float) $lngRaw;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return [null, null, ''];
    }
    if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
        return [null, null, ''];
    }
    if ($name === '') {
        $name = '我的位置';
    }
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    $name = mb_substr(trim($name), 0, 120);
    return [$lat, $lng, $name];
}

function location_chip_html($lat, $lng, ?string $name = ''): string
{
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return '';
    }
    $lat = (float) $lat;
    $lng = (float) $lng;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return '';
    }
    $label = trim((string) $name);
    if ($label === '') {
        $label = '我的位置';
    }
    $label = mb_substr($label, 0, 120);
    $position = number_format($lng, 6, '.', '') . ',' . number_format($lat, 6, '.', '');
    $url = 'https://uri.amap.com/marker?position=' . $position
        . '&name=' . rawurlencode($label)
        . '&coordinate=wgs84&callnative=1';
    $title = number_format($lat, 5, '.', '') . ', ' . number_format($lng, 5, '.', '');
    return '<a class="loc-chip" href="' . h($url) . '" target="_blank" rel="noopener noreferrer" title="' . h($title) . '">'
        . location_icon_svg() . '<span class="loc-label">' . h($label) . '</span></a>';
}

function fmt_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

function voice_from_post(array $post, ?int $ownerId = null): array
{
    $file = basename((string) ($post['voice_file'] ?? ''));
    $dur = (int) ($post['voice_dur'] ?? 0);
    if ($file === '' || !preg_match('/^v(\d+)_[a-f0-9]{24}\.(webm|ogg|m4a|mp3|wav|aac)$/', $file, $m)) {
        return [null, 0];
    }
    if ($ownerId !== null && (int) $m[1] !== $ownerId) {
        return [null, 0];
    }
    $realDir = realpath(VOICE_DIR);
    $realFile = realpath(VOICE_DIR . '/' . $file);
    if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
        return [null, 0];
    }
    if ($dur < 0) {
        $dur = 0;
    }
    if ($dur > 600) {
        $dur = 600;
    }
    return [$file, $dur];
}

function voice_html(?string $file, int $dur = 0): string
{
    if ($file === null || $file === '') {
        return '';
    }
    if (!preg_match('/^v\d+_[a-f0-9]{24}\.(webm|ogg|m4a|mp3|wav|aac)$/', $file)) {
        return '';
    }
    $src = '/voice.php?f=' . rawurlencode($file);
    $dur = max(0, $dur);
    $label = $dur > 0 ? fmt_duration($dur) : '语音';
    return '<button type="button" class="voice-msg" data-src="' . h($src) . '" data-dur="' . $dur . '" aria-label="播放语音">'
        . '<span class="voice-play" aria-hidden="true">'
        . '<svg class="vp-play" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>'
        . '<svg class="vp-pause" viewBox="0 0 24 24"><path d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>'
        . '</span>'
        . '<span class="voice-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>'
        . '<span class="voice-dur">' . h($label) . '</span>'
        . '</button>';
}

function audio_magic_ok(string $path, string $ext): bool
{
    $fp = @fopen($path, 'rb');
    if ($fp === false) {
        return false;
    }
    $head = (string) fread($fp, 16);
    fclose($fp);
    if ($head === '') {
        return false;
    }
    switch ($ext) {
        case 'webm':
            return strncmp($head, "\x1A\x45\xDF\xA3", 4) === 0;
        case 'ogg':
            return strncmp($head, 'OggS', 4) === 0;
        case 'm4a':
            return strlen($head) >= 8 && substr($head, 4, 4) === 'ftyp';
        case 'wav':
            return strncmp($head, 'RIFF', 4) === 0;
        case 'mp3':
            return strncmp($head, 'ID3', 3) === 0
                || (strlen($head) >= 2 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0);
        case 'aac':
            return strlen($head) >= 2 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xF6) === 0xF0;
    }
    return false;
}

function video_magic_ok(string $path, string $ext): bool
{
    $fp = @fopen($path, 'rb');
    if ($fp === false) {
        return false;
    }
    $head = (string) fread($fp, 16);
    fclose($fp);
    if ($head === '') {
        return false;
    }
    switch ($ext) {
        case 'webm':
            return strncmp($head, "\x1A\x45\xDF\xA3", 4) === 0;
        case 'mp4':
        case 'mov':
            return strlen($head) >= 8 && substr($head, 4, 4) === 'ftyp';
    }
    return false;
}

function media_kind(?string $file): string
{
    if ($file === null || $file === '') {
        return '';
    }
    $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return 'image';
    }
    if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
        return 'video';
    }
    return '';
}

function media_from_post(array $post, ?int $ownerId = null): array
{
    $file = basename((string) ($post['media_file'] ?? ''));
    $dur = (int) ($post['media_dur'] ?? 0);
    if ($file === '' || !preg_match('/^m(\d+)_[a-f0-9]{24}\.(jpg|jpeg|png|gif|webp|mp4|webm|mov)$/', $file, $m)) {
        return [null, 0];
    }
    if ($ownerId !== null && (int) $m[1] !== $ownerId) {
        return [null, 0];
    }
    $realDir = realpath(MEDIA_DIR);
    $realFile = realpath(MEDIA_DIR . '/' . $file);
    if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
        return [null, 0];
    }
    if ($dur < 0) {
        $dur = 0;
    }
    if ($dur > 600) {
        $dur = 600;
    }
    return [$file, $dur];
}

function media_html(?string $file, int $dur = 0): string
{
    if ($file === null || $file === '') {
        return '';
    }
    if (!preg_match('/^m\d+_[a-f0-9]{24}\.(jpg|jpeg|png|gif|webp|mp4|webm|mov)$/', $file)) {
        return '';
    }
    $src = '/media.php?f=' . rawurlencode($file);
    $kind = media_kind($file);
    if ($kind === 'image') {
        return '<a class="msg-media" href="' . h($src) . '" target="_blank" rel="noopener noreferrer" aria-label="查看照片">'
            . '<img src="' . h($src) . '" alt="照片" loading="lazy"></a>';
    }
    if ($kind === 'video') {
        $label = $dur > 0 ? h(fmt_duration($dur)) : '视频';
        return '<div class="msg-video-wrap">'
            . '<video class="msg-video" controls preload="metadata" playsinline src="' . h($src) . '"></video>'
            . '<span class="msg-video-dur">' . $label . '</span></div>';
    }
    return '';
}

function space_can_view(int $viewerId, int $targetId, string $viewerRole): bool
{
    if ($viewerId === $targetId) {
        return true;
    }
    if ($viewerRole === 'admin') {
        return true;
    }
    return are_friends($viewerId, $targetId);
}

require_once __DIR__ . '/emoji.php';
