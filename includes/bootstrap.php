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
const CONFIG_FILE = DATA_DIR . '/config.php';
const SQLITE_FILE = DATA_DIR . '/app.sqlite';
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const MAX_AVATAR_BYTES = 5 * 1024 * 1024;
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
const NAV_DEFAULTS = [
    'home' => '首页',
    'board' => '留言板',
    'album' => '我的相册',
    'memo' => '备忘录',
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
    if (!mysql_column_exists($pdo, 'users', 'avatar')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(100) NULL');
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
