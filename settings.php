<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        flash_set('err', '当前密码不正确。');
        redirect('/settings.php');
    }
    if (strlen($new) < 6) {
        flash_set('err', '新密码至少 6 位。');
        redirect('/settings.php');
    }
    if ($new !== $confirm) {
        flash_set('err', '两次输入的新密码不一致。');
        redirect('/settings.php');
    }

    $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute([
        ':password_hash' => password_hash($new, PASSWORD_DEFAULT),
        ':id' => $user['id'],
    ]);
    flash_set('ok', '密码已更新。');
    redirect('/settings.php');
}

render_header(nav_label('settings'), $user, 'settings');
?>
<section class="page-head">
    <h1><?= h(nav_label('settings')) ?></h1>
    <p><?= h($user['display_name']) ?> · 我的 ID：#<?= (int) $user['id'] ?>（<?= $user['role'] === 'admin' ? '管理员' : '用户' ?>）</p>
</section>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <label for="current_password">当前密码</label>
    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
    <label for="new_password">新密码</label>
    <input id="new_password" name="new_password" type="password" required minlength="6" autocomplete="new-password">
    <label for="confirm_password">确认新密码</label>
    <input id="confirm_password" name="confirm_password" type="password" required minlength="6" autocomplete="new-password">
    <button type="submit">保存密码</button>
</form>
<?php render_footer();
