<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'nickname') {
        $name = trim((string) ($_POST['display_name'] ?? ''));
        if ($name === '') {
            flash_set('err', '昵称不能为空。');
            redirect('/profile.php');
        }
        $name = mb_substr($name, 0, 40);
        $stmt = db()->prepare('UPDATE users SET display_name = :name WHERE id = :id');
        $stmt->execute([':name' => $name, ':id' => $uid]);
        flash_set('ok', '昵称已更新。');
        redirect('/profile.php');
    }
    if ($action === 'contact') {
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{3,32}$/', $phone)) {
            flash_set('err', '手机号格式不正确。');
            redirect('/profile.php');
        }
        $phone = mb_substr($phone, 0, 32);
        $stmt = db()->prepare('UPDATE users SET phone = :phone WHERE id = :id');
        $stmt->execute([':phone' => $phone, ':id' => $uid]);
        flash_set('ok', '联系方式已更新。');
        redirect('/profile.php');
    }
    flash_set('err', '未知操作。');
    redirect('/profile.php');
}

$stmt = db()->prepare('SELECT id, username, display_name, avatar, role, phone, created_at FROM users WHERE id = :id');
$stmt->execute([':id' => $uid]);
$me = $stmt->fetch();
if (!$me) {
    flash_set('err', '用户不存在。');
    redirect('/logout.php');
}

$photoCount = (int) db()->query('SELECT COUNT(*) FROM photos WHERE user_id = ' . $uid)->fetchColumn();
$friendCount = (int) db()->query('SELECT COUNT(*) FROM friends WHERE user_id = ' . $uid)->fetchColumn();

render_header('个人中心', $user, '');
?>
<section class="profile-hero">
    <button type="button" class="profile-avatar js-avatar-edit" title="更换头像" aria-label="更换头像">
        <?= avatar_html($me['avatar'] ?? null, $me['display_name'], 'avatar avatar-xl') ?>
        <span class="profile-avatar-edit">
            <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><path d="M4 7h3l2-2h6l2 2h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
        </span>
    </button>
    <div class="profile-id">
        <h1 class="profile-name"><?= h($me['display_name']) ?></h1>
        <div class="profile-username">@<?= h($me['username']) ?></div>
        <div class="profile-meta">
            <span class="tag <?= $me['role'] === 'admin' ? 'tag-ok' : '' ?>"><?= $me['role'] === 'admin' ? '管理员' : '用户' ?></span>
            <?php if (($me['phone'] ?? '') !== ''): ?>
                <a class="profile-phone" href="tel:<?= h($me['phone']) ?>">
                    <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><path d="M6 3h3l1.6 4-2 1.4a12 12 0 0 0 5 5l1.4-2 4 1.6V20a1 1 0 0 1-1 1A16 16 0 0 1 4 6a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    <?= h($me['phone']) ?>
                </a>
            <?php endif; ?>
            <span class="profile-join">加入于 <?= h(date('Y-m-d', strtotime((string) $me['created_at']))) ?></span>
        </div>
    </div>
</section>

<section class="profile-stats">
    <a class="profile-stat" href="/space.php">
        <span class="profile-stat-num"><?= $photoCount ?></span>
        <span class="profile-stat-label">照片</span>
    </a>
    <a class="profile-stat" href="/friends.php">
        <span class="profile-stat-num"><?= $friendCount ?></span>
        <span class="profile-stat-label">好友</span>
    </a>
</section>

<section class="panel">
    <div class="block-head"><h2>修改昵称</h2></div>
    <form method="post" class="profile-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="nickname">
        <input name="display_name" type="text" maxlength="40" value="<?= h($me['display_name']) ?>" placeholder="输入新的昵称" required>
        <button type="submit" class="btn">保存</button>
    </form>
</section>

<section class="panel">
    <div class="block-head"><h2>联系方式</h2></div>
    <form method="post" class="profile-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="contact">
        <input name="phone" type="tel" maxlength="32" value="<?= h($me['phone'] ?? '') ?>" placeholder="手机号（可留空）">
        <button type="submit" class="btn">保存</button>
    </form>
    <p class="profile-hint">填写后，你的个人中心会显示手机号，好友可点击直接拨打。</p>
</section>

<section class="panel">
    <div class="block-head"><h2>更多</h2></div>
    <div class="profile-links">
        <a class="profile-link" href="/space.php">
            <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><path d="M4 5h16v15H4z"/><circle cx="12" cy="10" r="2.6"/><path d="M7.5 17a4.5 4.5 0 0 1 9 0"/></svg>
            <span>我的空间</span>
        </a>
        <a class="profile-link" href="/friends.php">
            <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><path d="M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 11a3 3 0 1 0 0-6"/><path d="M14.8 20a5.2 5.2 0 0 1 5.7-5.1"/></svg>
            <span>好友列表</span>
        </a>
        <a class="profile-link" href="/settings.php">
            <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><path d="M4 8h7M4 14h16"/><circle cx="14" cy="8" r="2"/><circle cx="9" cy="14" r="2"/></svg>
            <span>账号设置</span>
        </a>
        <a class="profile-link is-danger" href="/logout.php">
            <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><path d="M14 5h5v14h-5M10 12h9M13 9l3 3-3 3"/></svg>
            <span>退出登录</span>
        </a>
    </div>
</section>
<?php render_footer();
