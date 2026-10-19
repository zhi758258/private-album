<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'site') {
        $siteName = trim((string) ($_POST['site_name'] ?? ''));
        if ($siteName === '') {
            flash_set('err', '网站名字不能为空。');
            redirect('/admin_settings.php');
        }
        setting_set('site_name', mb_substr($siteName, 0, 40));
        flash_set('ok', '网站名字已保存。');
        redirect('/admin_settings.php');
    }

    if ($action === 'notice') {
        $enabled = isset($_POST['notice_enabled']) ? '1' : '0';
        $content = trim((string) ($_POST['notice_content'] ?? ''));
        if ($enabled === '1' && $content === '') {
            flash_set('err', '开启公告时请填写内容。');
            redirect('/admin_settings.php');
        }
        setting_set('notice_enabled', $enabled);
        setting_set('notice_content', mb_substr($content, 0, 4000));
        setting_set('notice_updated', now());
        flash_set('ok', $enabled === '1' ? '公告已开启并保存。' : '公告已关闭。');
        redirect('/admin_settings.php');
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $admin['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash_set('err', '当前密码不正确。');
            redirect('/admin_settings.php');
        }
        if (strlen($new) < 6) {
            flash_set('err', '新密码至少 6 位。');
            redirect('/admin_settings.php');
        }
        if ($new !== $confirm) {
            flash_set('err', '两次输入的新密码不一致。');
            redirect('/admin_settings.php');
        }
        $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            ':password_hash' => password_hash($new, PASSWORD_DEFAULT),
            ':id' => $admin['id'],
        ]);
        flash_set('ok', '密码已更新。');
        redirect('/admin_settings.php');
    }

    redirect('/admin_settings.php');
}

render_header('后台设置', $admin, 'admin_settings');
?>
<section class="page-head">
    <h1>后台设置</h1>
</section>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="site">
    <label for="site_name">网站名字</label>
    <input id="site_name" name="site_name" required maxlength="40" value="<?= h(app_name()) ?>">
    <button type="submit">保存</button>
</form>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="notice">
    <label class="check">
        <input type="checkbox" name="notice_enabled" value="1" <?= setting_get('notice_enabled') === '1' ? 'checked' : '' ?>>
        开启公告
    </label>
    <label for="notice_content">公告内容</label>
    <textarea id="notice_content" name="notice_content" maxlength="4000"><?= h(setting_get('notice_content')) ?></textarea>
    <button type="submit">保存公告</button>
</form>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <label for="current_password">当前密码</label>
    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
    <label for="new_password">新密码</label>
    <input id="new_password" name="new_password" type="password" required minlength="6" autocomplete="new-password">
    <label for="confirm_password">确认新密码</label>
    <input id="confirm_password" name="confirm_password" type="password" required minlength="6" autocomplete="new-password">
    <button type="submit">修改密码</button>
</form>
<?php render_footer();
