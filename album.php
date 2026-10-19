<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $caption = trim((string) ($_POST['caption'] ?? ''));
    if ($caption === '') {
        $caption = '';
    }
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
    $stmt = db()->prepare('
        INSERT INTO photos (user_id, filename, original_name, caption, created_at)
        VALUES (:user_id, :filename, :original_name, :caption, :created_at)
    ');
    $stmt->execute([
        ':user_id' => $user['id'],
        ':filename' => $filename,
        ':original_name' => (string) $file['name'],
        ':caption' => mb_substr($caption, 0, 120),
        ':created_at' => now(),
    ]);
    flash_set('ok', '已上传。照片无法删除。');
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
    <input id="caption" name="caption" maxlength="120" placeholder="写一句想留下的话">
    <button type="submit">上传到相册</button>
</form>
<?php if (!$photos): ?>
    <div class="empty">你还没有上传过照片。</div>
<?php else: ?>
    <div class="masonry">
        <?php foreach ($photos as $photo): ?>
            <figure class="card">
                <img src="/uploads/<?= h($photo['filename']) ?>" alt="<?= h($photo['caption'] ?: $photo['original_name']) ?>">
                <figcaption>
                    <div><?= h($photo['caption'] ?: '未写说明') ?></div>
                    <div class="meta"><span><?= h($photo['created_at']) ?></span></div>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer();
