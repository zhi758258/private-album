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

render_header('相册墙', $user, 'home');
?>
<section class="page-head">
    <h1>相册墙</h1>
    <p>所有人上传的照片都会留在这里。只能上传，不能删除。</p>
</section>
<?php if (!$photos): ?>
    <div class="empty">还没有照片。去「我的相册」上传第一张。</div>
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
