<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('
    SELECT diaries.*, users.display_name, users.username, users.avatar, users.id AS owner_id
    FROM diaries
    JOIN users ON users.id = diaries.user_id
    WHERE diaries.id = :id
');
$stmt->execute([':id' => $id]);
$diary = $stmt->fetch();
if (!$diary) {
    flash_set('err', '日志不存在。');
    redirect('/space.php');
}

$ownerId = (int) $diary['owner_id'];
$isSelf = $ownerId === $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$isSelf) {
        flash_set('err', '无权操作这篇日志。');
        redirect('/diary.php?id=' . $id);
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $del = db()->prepare('DELETE FROM diaries WHERE id = :id AND user_id = :user_id');
        $del->execute([':id' => $id, ':user_id' => $uid]);
        flash_set('ok', '日志已删除。');
        redirect('/space.php');
    }
    redirect('/diary.php?id=' . $id);
}

$canView = space_can_view($uid, $ownerId, (string) $user['role'])
    && ($isSelf || $diary['visibility'] === 'friends');

if (!$canView) {
    http_response_code(403);
    render_header('日志', $user, 'space');
    ?>
    <div class="empty">这篇日志仅好友可见。</div>
    <?php
    render_footer();
    exit;
}

render_header($diary['title'], $user, 'space');
?>
<section class="page-head space-head">
    <button type="button" class="js-user avatar-btn" data-user-id="<?= $ownerId ?>">
        <?= avatar_html($diary['avatar'] ?? null, $diary['display_name'], 'avatar avatar-md') ?>
    </button>
    <div>
        <h1><?= h($diary['title']) ?></h1>
        <p>@<?= h($diary['username']) ?> · <?= h($diary['created_at']) ?><?= $isSelf && $diary['visibility'] === 'private' ? ' · 仅自己可见' : '' ?></p>
    </div>
</section>
<article class="panel diary-view">
    <div class="diary-content"><?= nl2br(h($diary['content'])) ?></div>
    <div class="diary-actions">
        <a class="btn ghost-btn" href="/space.php?u=<?= $ownerId ?>">返回空间</a>
        <?php if ($isSelf): ?>
            <form method="post" class="shuoshuo-del">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn danger-btn">删除日志</button>
            </form>
        <?php endif; ?>
    </div>
</article>
<?php render_footer();