<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    if ($title === '' || $content === '') {
        flash_set('err', '标题和内容都要填。');
        redirect('/memo.php');
    }
    $stmt = db()->prepare('
        INSERT INTO memos (user_id, title, content, created_at, updated_at)
        VALUES (:user_id, :title, :content, :created_at, :updated_at)
    ');
    $now = now();
    $stmt->execute([
        ':user_id' => $user['id'],
        ':title' => mb_substr($title, 0, 80),
        ':content' => mb_substr($content, 0, 4000),
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    flash_set('ok', '备忘已保存，仅你和管理员可见。');
    redirect('/memo.php');
}

$stmt = db()->prepare('
    SELECT * FROM memos WHERE user_id = :user_id ORDER BY id DESC
');
$stmt->execute([':user_id' => $user['id']]);
$memos = $stmt->fetchAll();

render_header('备忘录', $user, 'memo');
?>
<section class="page-head">
    <h1>备忘录</h1>
    <p>只属于你。其他成员看不到，管理员可以查看。</p>
</section>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <label for="title">标题</label>
    <input id="title" name="title" required maxlength="80" placeholder="今天要记住的事">
    <label for="content">内容</label>
    <textarea id="content" name="content" maxlength="4000" required placeholder="最多 4000 字"></textarea>
    <button type="submit">保存备忘</button>
</form>
<?php if (!$memos): ?>
    <div class="empty">还没有备忘。</div>
<?php else: ?>
    <?php foreach ($memos as $memo): ?>
        <article class="memo-card">
            <h3><?= h($memo['title']) ?></h3>
            <div class="memo-time"><?= h($memo['created_at']) ?></div>
            <p><?= h($memo['content']) ?></p>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php render_footer();
