<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'upload');

    if ($action === 'wall' && $user['role'] === 'admin') {
        $photoId = (int) ($_POST['photo_id'] ?? 0);
        $onWall = isset($_POST['on_wall']) ? 1 : 0;
        $stmt = db()->prepare('UPDATE photos SET on_wall = :on_wall WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':on_wall' => $onWall,
            ':id' => $photoId,
            ':user_id' => $user['id'],
        ]);
        flash_set('ok', $onWall ? '已添加到相册墙。' : '已从相册墙移除。');
        redirect('/album.php');
    }

    $caption = trim((string) ($_POST['caption'] ?? ''));
    if (!isset($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
        flash_set('err', '请选择要上传的图片。');
        redirect('/album.php');
    }
    $file = $_FILES['photo'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash_set('err', '上传失败，请重试。');
        redirect('/album.php');
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        flash_set('err', '图片不能超过 8MB。');
        redirect('/album.php');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset(ALLOWED_MIME[$mime])) {
        flash_set('err', '只支持 jpg / png / gif / webp。');
        redirect('/album.php');
    }
    $ext = ALLOWED_MIME[$mime];
    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        flash_set('err', '保存文件失败。');
        redirect('/album.php');
    }
    $onWall = $user['role'] === 'admin' ? 0 : 1;
    $stmt = db()->prepare('
        INSERT INTO photos (user_id, filename, original_name, caption, created_at, on_wall)
        VALUES (:user_id, :filename, :original_name, :caption, :created_at, :on_wall)
    ');
    $stmt->execute([
        ':user_id' => $user['id'],
        ':filename' => $filename,
        ':original_name' => (string) $file['name'],
        ':caption' => mb_substr($caption, 0, 120),
        ':created_at' => now(),
        ':on_wall' => $onWall,
    ]);
    flash_set('ok', '已上传。');
    redirect('/album.php');
}

$stmt = db()->prepare('
    SELECT * FROM photos WHERE user_id = :user_id ORDER BY id DESC
');
$stmt->execute([':user_id' => $user['id']]);
$photos = $stmt->fetchAll();

render_header('我的相册', $user, 'album');
?>
<section class="page-head">
    <h1>我的相册</h1>
</section>
<form class="panel" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="photo">选择图片</label>
    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
    <label for="caption">说明（可选）</label>
    <input id="caption" name="caption" maxlength="120">
    <button type="submit">上传到相册</button>
</form>
<?php if (!$photos): ?>
    <div class="empty">你还没有上传过照片。</div>
<?php else: ?>
    <div class="masonry">
        <?php foreach ($photos as $photo): ?>
            <figure class="card">
                <img src="/photo.php?f=<?= rawurlencode($photo['filename']) ?>" alt="<?= h($photo['caption'] ?: $photo['original_name']) ?>">
                <figcaption>
                    <div><?= h($photo['caption'] ?: '未写说明') ?></div>
                    <div class="meta"><span><?= h($photo['created_at']) ?></span></div>
                    <a class="btn save-btn" href="/photo.php?f=<?= rawurlencode($photo['filename']) ?>&dl=1">保存到相册</a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <form method="post" class="wall-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="wall">
                            <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                            <label class="check">
                                <input type="checkbox" name="on_wall" value="1" <?= !empty($photo['on_wall']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                添加到相册墙
                            </label>
                        </form>
                    <?php endif; ?>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer();
