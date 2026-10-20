<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

if (!register_open()) {
    http_response_code(403);
    exit('暂未开放注册。');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $code = trim(strtoupper((string) ($_POST['code'] ?? '')));

    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $error = '账号只能是 3-32 位字母、数字或下划线。';
    } elseif ($displayName === '') {
        $error = '请填写显示名。';
    } elseif (mb_strlen($displayName) > 40) {
        $error = '显示名不能超过 40 字。';
    } elseif (strlen($password) < 6) {
        $error = '密码至少 6 位。';
    } elseif ($password !== $confirm) {
        $error = '两次输入的密码不一致。';
    } elseif (!register_auto_card() && !card_available($code)) {
        $error = '邀请码（卡密）无效或已被使用。';
    }

    if ($error === '') {
        try {
            $stmt = db()->prepare('
                INSERT INTO users (username, password_hash, display_name, role, created_at)
                VALUES (:username, :password_hash, :display_name, :role, :created_at)
            ');
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':display_name' => mb_substr($displayName, 0, 40),
                ':role' => 'user',
                ':created_at' => now(),
            ]);
        } catch (PDOException $e) {
            $error = '该账号已被注册。';
        }
    }

    if ($error === '') {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $newUser = $stmt->fetch();
        $newId = (int) $newUser['id'];
        if (register_auto_card()) {
            card_issue_auto($newId);
        } else {
            card_consume($code, $newId);
        }
        $_SESSION['flash'] = ['type' => 'ok', 'message' => '注册成功，请登录。'];
        redirect('/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 · <?= h(app_name()) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="grain"></div>
<div class="login-shell">
    <form class="login-card" method="post">
        <?= csrf_field() ?>
        <h1><?= h(app_name()) ?></h1>
        <?php if ($error): ?>
            <div class="flash flash-err"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if (register_auto_card()): ?>
            <p>当前开放注册，注册后自动领取一张卡密。</p>
        <?php else: ?>
            <p>注册需要填写邀请码（卡密），每人仅可使用一次。</p>
        <?php endif; ?>
        <label for="username">账号</label>
        <input id="username" name="username" required maxlength="32" autocomplete="username" placeholder="字母数字下划线" value="<?= h($username ?? '') ?>">
        <label for="display_name">显示名</label>
        <input id="display_name" name="display_name" required maxlength="40" value="<?= h($displayName ?? '') ?>">
        <label for="password">密码</label>
        <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password">
        <label for="confirm_password">确认密码</label>
        <input id="confirm_password" name="confirm_password" type="password" required minlength="6" autocomplete="new-password">
        <?php if (!register_auto_card()): ?>
            <label for="code">邀请码（卡密）</label>
            <input id="code" name="code" required maxlength="40" autocomplete="off" placeholder="例如 XXXX-XXXX-XXXX-XXXX" value="<?= h($code ?? '') ?>">
        <?php endif; ?>
        <button type="submit">注册</button>
        <p class="back-home"><a href="/login.php">已有账号，去登录</a></p>
    </form>
</div>
</body>
</html>