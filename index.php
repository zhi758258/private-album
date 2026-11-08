<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($user['role'] === 'admin' && ($_POST['action'] ?? '') === 'delete') {
        $photoId = (int) ($_POST['photo_id'] ?? 0);
        if (delete_photo_by_id($photoId)) {
            flash_set('ok', '已删除照片。');
        } else {
            flash_set('err', '照片不存在。');
        }
        redirect('/index.php');
    }
}

$stmt = db()->prepare("
    SELECT photos.*, users.display_name, users.role, users.id AS user_id,
        (SELECT COUNT(*) FROM photo_likes pl WHERE pl.photo_id = photos.id) AS like_count,
        (SELECT 1 FROM photo_likes pl WHERE pl.photo_id = photos.id AND pl.user_id = :uid) AS liked
    FROM photos
    JOIN users ON users.id = photos.user_id
    WHERE photos.on_wall = 1
    ORDER BY photos.id DESC
");
$stmt->execute([':uid' => $user['id']]);
$photos = $stmt->fetchAll();

$rows = db()->query('
    SELECT messages.*, users.display_name, users.id AS user_id, users.avatar
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

render_header(nav_label('home'), $user, 'home');
?>
<section class="page-head">
    <h1><?= h(nav_label('board')) ?></h1>
</section>
<?php if (!$threads): ?>
    <div class="empty">还没有留言。</div>
<?php else: ?>
    <?php foreach ($threads as $msg): ?>
        <article class="msg" id="msg-<?= (int) $msg['id'] ?>">
            <button type="button" class="js-user avatar-btn" data-user-id="<?= (int) $msg['user_id'] ?>">
                <?= avatar_html($msg['avatar'] ?? null, $msg['display_name'], 'avatar avatar-sm') ?>
            </button>
            <div class="msg-main">
                <div class="who-line"><?= h($msg['display_name']) ?> #<?= (int) $msg['user_id'] ?> · <?= h($msg['created_at']) ?></div>
                <p class="msg-text"><?= render_message_html($msg['content']) ?></p>
                <?= location_chip_html($msg['loc_lat'] ?? null, $msg['loc_lng'] ?? null, $msg['loc_name'] ?? '') ?>
                <?php foreach ($replies[(int) $msg['id']] ?? [] as $reply): ?>
                    <article class="msg reply">
                        <button type="button" class="js-user avatar-btn" data-user-id="<?= (int) $reply['user_id'] ?>">
                            <?= avatar_html($reply['avatar'] ?? null, $reply['display_name'], 'avatar avatar-sm') ?>
                        </button>
                        <div class="msg-main">
                            <div class="who-line"><?= h($reply['display_name']) ?> #<?= (int) $reply['user_id'] ?> · <?= h($reply['created_at']) ?></div>
                            <p class="msg-text"><?= render_message_html($reply['content']) ?></p>
                            <?= location_chip_html($reply['loc_lat'] ?? null, $reply['loc_lng'] ?? null, $reply['loc_name'] ?? '') ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!$photos): ?>
    <div class="empty">还没有照片。</div>
<?php else: ?>
    <section class="block-head album-head">
        <h2>相册墙</h2>
        <div class="view-bar" data-viewbar data-target="wall-track">
            <div class="seg" role="group" aria-label="显示方式">
                <button type="button" data-mode="slide" class="is-on on">滑动</button>
                <button type="button" data-mode="grid">平铺</button>
            </div>
            <button type="button" class="btn ghost-btn" data-order>排序·<span data-label>最新在前</span></button>
        </div>
    </section>
    <div class="wall-slider" data-slider>
        <div class="wall-track" id="wall-track" data-mode="slide" data-order="desc">
            <?php foreach ($photos as $photo): ?>
                <figure class="card wall-slide">
                    <a class="thumb-link" href="/photo.php?f=<?= rawurlencode($photo['filename']) ?>" data-lightbox data-caption="<?= h($photo['caption'] ?: '未写说明') ?>" data-dl="/photo.php?f=<?= rawurlencode($photo['filename']) ?>&dl=1">
                        <img class="thumb-img" src="/photo.php?f=<?= rawurlencode($photo['filename']) ?>" alt="<?= h($photo['caption'] ?: $photo['original_name']) ?>" loading="lazy">
                    </a>
                    <figcaption>
                        <div><?= h($photo['caption'] ?: '未写说明') ?></div>
                        <div class="meta">
                            <span><?= h($photo['display_name']) ?></span>
                            <span><?= h($photo['created_at']) ?></span>
                        </div>
                        <button type="button" class="btn like-btn<?= !empty($photo['liked']) ? ' is-liked' : '' ?>" data-id="<?= (int) $photo['id'] ?>">
                            <span class="heart">♥</span>
                            <span class="cnt"><?= (int) $photo['like_count'] ?></span>
                            <span class="lbl"><?= !empty($photo['liked']) ? '已喜欢' : '喜欢' ?></span>
                        </button>
                        <a class="btn save-btn" href="/photo.php?f=<?= rawurlencode($photo['filename']) ?>&dl=1">保存到相册</a>
                        <?php if ($user['role'] === 'admin'): ?>
                            <form method="post" class="wall-form js-delete-photo">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                <button class="btn danger-btn" type="submit">删除照片</button>
                            </form>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php if (count($photos) > 1): ?>
            <button class="wall-prev" type="button" data-prev>上一张</button>
            <button class="wall-next" type="button" data-next>下一张</button>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php render_footer();