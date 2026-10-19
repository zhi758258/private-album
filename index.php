<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$photos = db()->query('
    SELECT photos.*, users.display_name
    FROM photos
    JOIN users ON users.id = photos.user_id
    ORDER BY photos.id DESC
')->fetchAll();

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

render_header('首页', $user, 'home');
?>
<section class="page-head">
    <h1>留言板</h1>
</section>
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
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<section class="page-head album-head">
    <h1>相册墙</h1>
</section>
<?php if (!$photos): ?>
    <div class="empty">还没有照片。</div>
<?php else: ?>
    <div class="masonry">
        <?php foreach ($photos as $photo): ?>
            <figure class="card">
                <img src="/uploads/<?= h($photo['filename']) ?>" alt="<?= h($photo['caption'] ?: $photo['original_name']) ?>">
                <figcaption>
                    <div><?= h($photo['caption'] ?: '未写说明') ?></div>
                    <div class="meta">
                        <span><?= h($photo['display_name']) ?></span>
                        <span><?= h($photo['created_at']) ?></span>
                    </div>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer();
