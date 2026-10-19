<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();
$memos = db()->query('
    SELECT memos.*, users.display_name, users.username
    FROM memos
    JOIN users ON users.id = memos.user_id
    ORDER BY memos.id DESC
')->fetchAll();

render_header('全部备忘录', $admin, 'admin_memos');
?>
<section class="page-head">
    <h1>全部备忘录</h1>
</section>
<?php if (!$memos): ?>
    <div class="empty">还没有人写过备忘。</div>
<?php else: ?>
    <?php foreach ($memos as $memo): ?>
        <article class="memo-card">
            <h3><?= h($memo['title']) ?></h3>
            <div class="memo-time"><?= h($memo['display_name']) ?>（<?= h($memo['username']) ?>） · <?= h($memo['created_at']) ?></div>
            <p><?= h($memo['content']) ?></p>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php render_footer();
