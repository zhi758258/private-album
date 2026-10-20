<?php
declare(strict_types=1);

/**
 * 安装向导：连接独立 MySQL 服务器，建库（如有权限）、建表、创建管理员，
 * 并可选迁移旧 SQLite 数据（data/app.sqlite）。
 * 安装完成后请删除本文件，或保留（已安装时会拒绝重复执行）。
 */
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

const CONFIG_FILE = __DIR__ . '/data/config.php';
const SQLITE_FILE = __DIR__ . '/data/app.sqlite';
const SCHEMA_FILE = __DIR__ . '/schema.sql';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_done(): bool
{
    if (!is_file(CONFIG_FILE)) {
        return false;
    }
    return is_file(__DIR__ . '/data/installed.lock');
}

function connect_mysql(string $host, int $port, string $name, string $user, string $pass, bool $withDb): PDO
{
    $dsn = $withDb && $name !== ''
        ? sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name)
        : sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function run_schema_file(PDO $pdo): void
{
    $raw = (string) file_get_contents(SCHEMA_FILE);
    $lines = preg_split('/\r?\n/', $raw) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $clean[] = $line;
    }
    $sql = implode("\n", $clean);
    foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
        $stmt = trim((string) $stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

function sqlite_rows(PDO $sqlite, string $table): array
{
    try {
        return $sqlite->query("SELECT * FROM {$table}")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function migrate_sqlite(PDO $mysql, PDO $sqlite): array
{
    $stats = [];

    $users = sqlite_rows($sqlite, 'users');
    $stats['users'] = count($users);
    $ins = $mysql->prepare('INSERT IGNORE INTO users (id, username, password_hash, display_name, role, created_at) VALUES (:id, :username, :password_hash, :display_name, :role, :created_at)');
    foreach ($users as $u) {
        $ins->execute([
            ':id' => (int) $u['id'],
            ':username' => (string) $u['username'],
            ':password_hash' => (string) $u['password_hash'],
            ':display_name' => (string) $u['display_name'],
            ':role' => $u['role'] === 'admin' ? 'admin' : 'user',
            ':created_at' => (string) $u['created_at'],
        ]);
    }

    $photos = sqlite_rows($sqlite, 'photos');
    $stats['photos'] = count($photos);
    $ins = $mysql->prepare('INSERT IGNORE INTO photos (id, user_id, filename, original_name, caption, created_at, on_wall) VALUES (:id, :user_id, :filename, :original_name, :caption, :created_at, :on_wall)');
    foreach ($photos as $p) {
        $ins->execute([
            ':id' => (int) $p['id'],
            ':user_id' => (int) $p['user_id'],
            ':filename' => (string) $p['filename'],
            ':original_name' => (string) $p['original_name'],
            ':caption' => (string) ($p['caption'] ?? ''),
            ':created_at' => (string) $p['created_at'],
            ':on_wall' => (int) ($p['on_wall'] ?? 1),
        ]);
    }

    $messages = sqlite_rows($sqlite, 'messages');
    $stats['messages'] = count($messages);
    $ins = $mysql->prepare('INSERT IGNORE INTO messages (id, user_id, parent_id, content, created_at) VALUES (:id, :user_id, :parent_id, :content, :created_at)');
    foreach ($messages as $m) {
        $ins->execute([
            ':id' => (int) $m['id'],
            ':user_id' => (int) $m['user_id'],
            ':parent_id' => $m['parent_id'] !== null && $m['parent_id'] !== '' ? (int) $m['parent_id'] : null,
            ':content' => (string) $m['content'],
            ':created_at' => (string) $m['created_at'],
        ]);
    }

    $memos = sqlite_rows($sqlite, 'memos');
    $stats['memos'] = count($memos);
    $ins = $mysql->prepare('INSERT IGNORE INTO memos (id, user_id, title, content, created_at, updated_at) VALUES (:id, :user_id, :title, :content, :created_at, :updated_at)');
    foreach ($memos as $m) {
        $ins->execute([
            ':id' => (int) $m['id'],
            ':user_id' => (int) $m['user_id'],
            ':title' => (string) $m['title'],
            ':content' => (string) $m['content'],
            ':created_at' => (string) $m['created_at'],
            ':updated_at' => (string) $m['updated_at'],
        ]);
    }

    $likes = sqlite_rows($sqlite, 'photo_likes');
    $stats['photo_likes'] = count($likes);
    $ins = $mysql->prepare('INSERT IGNORE INTO photo_likes (photo_id, user_id, created_at) VALUES (:photo_id, :user_id, :created_at)');
    foreach ($likes as $l) {
        $ins->execute([
            ':photo_id' => (int) $l['photo_id'],
            ':user_id' => (int) $l['user_id'],
            ':created_at' => (string) $l['created_at'],
        ]);
    }

    $cards = sqlite_rows($sqlite, 'cards');
    $stats['cards'] = count($cards);
    $ins = $mysql->prepare('INSERT IGNORE INTO cards (id, code, created_by, used_by, created_at, used_at) VALUES (:id, :code, :created_by, :used_by, :created_at, :used_at)');
    foreach ($cards as $c) {
        $ins->execute([
            ':id' => (int) $c['id'],
            ':code' => (string) $c['code'],
            ':created_by' => (int) $c['created_by'],
            ':used_by' => $c['used_by'] !== null && $c['used_by'] !== '' ? (int) $c['used_by'] : null,
            ':created_at' => (string) $c['created_at'],
            ':used_at' => $c['used_at'] !== null && $c['used_at'] !== '' ? (string) $c['used_at'] : null,
        ]);
    }

    $settings = sqlite_rows($sqlite, 'settings');
    $stats['settings'] = count($settings);
    $ins = $mysql->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (:key, :value)');
    foreach ($settings as $s) {
        $ins->execute([':key' => (string) $s['key'], ':value' => (string) $s['value']]);
    }

    foreach (['users', 'photos', 'messages', 'memos', 'cards'] as $table) {
        $max = (int) $mysql->query("SELECT IFNULL(MAX(id), 0) + 1 FROM `{$table}`")->fetchColumn();
        if ($max > 1) {
            $mysql->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = {$max}");
        }
    }

    return $stats;
}

$errors = [];
$done = false;
$migrated = null;
$installedAlready = install_done();

if ($installedAlready) {
    http_response_code(403);
    exit('已安装完成。如需重新安装，请删除 data/installed.lock 与 data/config.php 后重试。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['host'] ?? ''));
    $port = (int) ($_POST['port'] ?? 3306);
    $name = trim((string) ($_POST['name'] ?? ''));
    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    $adminUser = trim((string) ($_POST['admin_user'] ?? ''));
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $migrate = isset($_POST['migrate']);

    if ($host === '' || $name === '' || $user === '' || $port < 1 || $port > 65535) {
        $errors[] = '请填写完整的数据库连接信息。';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $adminUser)) {
        $errors[] = '管理员账号需为 3-32 位字母数字下划线。';
    }
    if (strlen($adminPass) < 6) {
        $errors[] = '管理员密码至少 6 位。';
    }

    if (!$errors) {
        try {
            $pdo = connect_mysql($host, $port, $name, $user, $pass, false);
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                throw new PDOException("数据库 {$name} 不存在且当前账号无权创建，请先在 MySQL 服务器上执行建库与授权。");
            }
            $pdo->exec("USE `{$name}`");
        } catch (PDOException $e) {
            $errors[] = '无法连接 MySQL：' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            run_schema_file($pdo);
        } catch (PDOException $e) {
            $errors[] = '建表失败：' . $e->getMessage();
        }
    }

    if (!$errors && $migrate && is_file(SQLITE_FILE)) {
        try {
            $sqlite = new PDO('sqlite:' . SQLITE_FILE, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $migrated = migrate_sqlite($pdo, $sqlite);
        } catch (PDOException $e) {
            $errors[] = 'SQLite 数据迁移失败：' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($count === 0) {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, created_at) VALUES (:username, :password_hash, :display_name, :role, :created_at)');
                $ins->execute([
                    ':username' => $adminUser,
                    ':password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
                    ':display_name' => '管理员',
                    ':role' => 'admin',
                    ':created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (PDOException $e) {
            $errors[] = '创建管理员失败：' . $e->getMessage();
        }
    }

    if (!$errors) {
        $config = sprintf(
            "<?php\ndeclare(strict_types=1);\nreturn [\n    'host' => %s,\n    'port' => %d,\n    'name' => %s,\n    'user' => %s,\n    'pass' => %s,\n];\n",
            var_export($host, true),
            $port,
            var_export($name, true),
            var_export($user, true),
            var_export($pass, true)
        );
        if (file_put_contents(CONFIG_FILE, $config) === false) {
            $errors[] = '无法写入 data/config.php，请检查 data 目录写权限。';
        } else {
            @file_put_contents(__DIR__ . '/data/installed.lock', date('Y-m-d H:i:s'));
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 · 私藏</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="grain"></div>
<div class="login-shell">
    <form class="login-card" method="post" style="width:min(560px,100%);">
        <h1>安装向导</h1>
        <p>连接独立部署的 MySQL 服务器，完成建表与初始化。</p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-err"><?= h($err) ?></div>
        <?php endforeach; ?>

        <?php if ($done): ?>
            <div class="flash flash-ok">安装完成！请立即删除本文件（install.php）以保证安全。</div>
            <?php if (is_array($migrated)): ?>
                <div class="flash flash-ok">
                    已迁移 SQLite 数据：
                    <?php foreach ($migrated as $table => $count): ?>
                        <?= h($table) ?> <?= (int) $count ?> 条；
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="btn" href="/login.php">前往登录</a>
        <?php else: ?>
            <label for="host">MySQL 服务器地址（独立服务器 IP 或域名）</label>
            <input id="host" name="host" required value="<?= h($_POST['host'] ?? '127.0.0.1') ?>" placeholder="例如 192.168.1.100 或 mysql.example.com">

            <label for="port">端口</label>
            <input id="port" name="port" type="number" min="1" max="65535" required value="<?= h($_POST['port'] ?? '3306') ?>">

            <label for="name">数据库名</label>
            <input id="name" name="name" required value="<?= h($_POST['name'] ?? 'private_album') ?>">

            <label for="user">数据库用户名</label>
            <input id="user" name="user" required value="<?= h($_POST['user'] ?? '') ?>" autocomplete="off">

            <label for="pass">数据库密码</label>
            <input id="pass" name="pass" type="password" value="" autocomplete="new-password">

            <label for="admin_user">管理员账号（仅当用户表为空时创建）</label>
            <input id="admin_user" name="admin_user" required value="<?= h($_POST['admin_user'] ?? 'admin') ?>" maxlength="32">

            <label for="admin_pass">管理员密码</label>
            <input id="admin_pass" name="admin_pass" type="password" required minlength="6" autocomplete="new-password">

            <?php if (is_file(SQLITE_FILE)): ?>
                <label class="check">
                    <input type="checkbox" name="migrate" value="1" checked>
                    迁移旧 SQLite 数据（留言、照片记录、卡密、设置等）
                </label>
            <?php endif; ?>

            <p class="hint" style="margin-top:4px;">
                若数据库不存在，将尝试自动创建（需账号有 CREATE 权限）；无权限时请先在 MySQL 服务器上执行建库与授权语句。
            </p>
            <button type="submit">开始安装</button>
        <?php endif; ?>
    </form>
</div>
</body>
</html>