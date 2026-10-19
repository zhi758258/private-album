<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $content = trim((string) ($_POST['content'] ?? ''));
    $parentId = (int) ($_POST['parent_id'] ?? 0);
    if ($content === '') {
        flash_set('err', '留言不能为空。');
        redirect('/board.php');
    }
    $parent = null;
    if ($parentId > 0) {
        $stmt = db()->prepare('SELECT id, parent_id FROM messages WHERE id = :id');
        $stmt->execute([':id' => $parentId]);
        $parent = $stmt->fetch();
        if (!$parent) {
            flash_set('err', '要回复的留言不存在。');
            redirect('/board.php');
        }
        if (!empty($parent['parent_id'])) {
            $parentId = (int) $parent['parent_id'];
        }
    }
    $stmt = db()->prepare('
        INSERT INTO messages (user_id, parent_id, content, created_at)
        VALUES (:user_id, :parent_id, :content, :created_at)
    ');
    $stmt->execute([
        ':user_id' => $user['id'],
        ':parent_id' => $parentId > 0 ? $parentId : null,
        ':content' => mb_substr($content, 0, 1000),
        ':created_at' => now(),
    ]);
    flash_set('ok', $parentId > 0 ? '回复已发布。' : '留言已发布。');
    redirect('/board.php');
}

$rows = db()->query('
    SELECT messages.*, users.display_name
    FROM messages
    JOIN users ON users.id = messages.user_id
    ORDER BY messages.id ASC
')->fetchAll();

$threads = [];
$replies = [];
foreach ($rows as $row) {
    if (empty($row['parent_id'])) {
        $threads[] = $row;
    } else {
        $replies[(int) $row['parent_id']][] = $row;
    }
}
$threads = array_reverse($threads);

render_header('留言板', $user, 'board');
?>
<section class="page-head">
    <h1>留言板</h1>
</section>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <label for="content">写一条留言</label>
    <textarea id="content" name="content" maxlength="1000" required placeholder="最多 1000 字"></textarea>
    <button type="submit">发布</button>
</form>
<?php if (!$threads): ?>
    <div class="empty">还没有留言。</div>
<?php else: ?>
    <?php foreach ($threads as $msg): ?>
        <article class="msg" id="msg-<?= (int) $msg['id'] ?>">
            <div class="who-line"><?= h($msg['display_name']) ?> · <?= h($msg['created_at']) ?></div>
            <p><?= h($msg['content']) ?></p>
            <?php foreach ($replies[(int) $msg['id']] ?? [] as $reply): ?>
                <article class="msg reply">
                    <div class="who-line"><?= h($reply['display_name']) ?> · <?= h($reply['created_at']) ?></div>
                    <p><?= h($reply['content']) ?></p>
                </article>
            <?php endforeach; ?>
            <form class="reply-form" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="parent_id" value="<?= (int) $msg['id'] ?>">
                <textarea name="content" maxlength="1000" required placeholder="回复 <?= h($msg['display_name']) ?>"></textarea>
                <button type="submit">回复</button>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php render_footer();
