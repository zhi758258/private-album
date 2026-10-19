<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $photoId = (int) ($_POST['photo_id'] ?? 0);
    if ($action === 'delete') {
        if (delete_photo_by_id($photoId)) {
            flash_set('ok', '已删除照片。');
        } else {
            flash_set('err', '照片不存在。');
        }
        redirect('/admin_photos.php');
    }
    if ($action === 'wall') {
        $onWall = isset($_POST['on_wall']) ? 1 : 0;
        $stmt = db()->prepare('UPDATE photos SET on_wall = :on_wall WHERE id = :id');
        $stmt->execute([
            ':on_wall' => $onWall,
            ':id' => $photoId,
        ]);
        flash_set('ok', $onWall ? '已添加到相册墙。' : '已从相册墙移除。');
        redirect('/admin_photos.php');
    }
}

$photos = db()->query('
    SELECT photos.*, users.display_name, users.username, users.role
    FROM photos
    JOIN users ON users.id = photos.user_id
    ORDER BY photos.id DESC
')->fetchAll();

render_header('全部相册', $admin, 'admin_photos');
?>
<section class="page-head">
    <h1>全部相册</h1>
</section>
<?php if (!$photos): ?>
    <div class="empty">还没有照片。</div>
<?php else: ?>
    <div class="masonry">
        <?php foreach ($photos as $photo): ?>
            <figure class="card">
                <img src="/photo.php?f=<?= rawurlencode($photo['filename']) ?>" alt="<?= h($photo['caption'] ?: $photo['original_name']) ?>">
                <figcaption>
                    <div><?= h($photo['caption'] ?: '未写说明') ?></div>
                    <div class="meta">
                        <span><?= h($photo['display_name']) ?>（<?= h($photo['username']) ?>）</span>
                        <span><?= h($photo['created_at']) ?></span>
                    </div>
                    <a class="btn save-btn" href="/photo.php?f=<?= rawurlencode($photo['filename']) ?>&dl=1">保存到相册</a>
                    <form method="post" class="wall-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="wall">
                        <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                        <label class="check">
                            <input type="checkbox" name="on_wall" value="1" <?= !empty($photo['on_wall']) ? 'checked' : '' ?> onchange="this.form.submit()">
                            显示在相册墙
                        </label>
                    </form>
                    <form method="post" class="wall-form js-delete-photo">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                        <button class="btn danger-btn" type="submit">删除照片</button>
                    </form>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer();
