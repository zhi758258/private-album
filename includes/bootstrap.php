<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

const UPLOAD_DIR = __DIR__ . '/../uploads';
const DATA_DIR = __DIR__ . '/../data';
const FILES_DIR = __DIR__ . '/../wenjian123';
const DB_PATH = DATA_DIR . '/app.sqlite';
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(FILES_DIR)) {
    mkdir(FILES_DIR, 0755, true);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            display_name TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "user",
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            caption TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            parent_id INTEGER,
            content TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (parent_id) REFERENCES messages(id)
        );
        CREATE TABLE IF NOT EXISTS memos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ');
    $exists = $pdo->prepare('SELECT value FROM settings WHERE key = :key');
    $exists->execute([':key' => 'site_name']);
    if ($exists->fetch() === false) {
        $ins = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)');
        $ins->execute([':key' => 'site_name', ':value' => '私藏']);
    }
    $exists->execute([':key' => 'notice_title']);
    if ($exists->fetch() === false) {
        $ins = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)');
        $ins->execute([':key' => 'notice_title', ':value' => '']);
        $ins->execute([':key' => 'notice_content', ':value' => '']);
        $ins->execute([':key' => 'notice_updated', ':value' => '']);
    }
    $exists->execute([':key' => 'notice_enabled']);
    if ($exists->fetch() === false) {
        $ins = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)');
        $hasNotice = trim(setting_get('notice_content')) !== '';
        $ins->execute([':key' => 'notice_enabled', ':value' => $hasNotice ? '1' : '0']);
    }
    $cols = $pdo->query('PRAGMA table_info(messages)')->fetchAll();
    $names = array_column($cols, 'name');
    if (!in_array('parent_id', $names, true)) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN parent_id INTEGER');
    }
    $photoCols = array_column($pdo->query('PRAGMA table_info(photos)')->fetchAll(), 'name');
    if (!in_array('on_wall', $photoCols, true)) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN on_wall INTEGER NOT NULL DEFAULT 1');
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
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : $default;
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
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
    ];
}

function notice_markup(): string
{
    $notice = notice_data();
    if (!$notice) {
        return '';
    }
    $content = nl2br(h($notice['content']));
    return <<<HTML
<div class="notice-mask" id="noticeMask">
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
    function hide() { mask.style.display = 'none'; }
    ok.addEventListener('click', hide);
})();
</script>
HTML;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    $stmt = db()->prepare('SELECT id, username, display_name, role FROM users WHERE id = :id');
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
