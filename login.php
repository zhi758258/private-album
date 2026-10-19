<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $found = $stmt->fetch();
    if ($found && password_verify($password, $found['password_hash'])) {
        $_SESSION['user_id'] = (int) $found['id'];
        redirect('/index.php');
    }
    $error = '账号或密码不正确。本站不对外开放注册。';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 · <?= h(app_name()) ?></title>
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
        <label for="username">账号</label>
        <input id="username" name="username" required autocomplete="username">
        <label for="password">密码</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button type="submit">进入</button>
    </form>
</div>
<?= notice_markup() ?>
</body>
</html>
