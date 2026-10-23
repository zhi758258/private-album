<?php
declare(strict_types=1);

/**
 * 安装向导：连接独立 MySQL 服务器，建库（如有权限）、建表、创建管理员。
 * 连接信息保存在 data/config.php，安装完成后请删除本文件。
 */
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

const CONFIG_FILE = __DIR__ . '/data/config.php';
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

$errors = [];
$done = false;
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

            <p class="hint" style="margin-top:4px;">
                若数据库不存在，将尝试自动创建（需账号有 CREATE 权限）；无权限时请先在 MySQL 服务器上执行建库与授权语句。
            </p>
            <button type="submit">开始安装</button>
        <?php endif; ?>
    </form>
</div>
</body>
</html>