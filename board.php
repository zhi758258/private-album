<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $content = trim((string) ($_POST['content'] ?? ''));
    $parentId = (int) ($_POST['parent_id'] ?? 0);
    [$voiceFile, $voiceDur] = voice_from_post($_POST, (int) $user['id']);
    [$mediaFile, $mediaDur] = media_from_post($_POST, (int) $user['id']);
    if ($content === '' && $voiceFile === null && $mediaFile === null) {
        flash_set('err', '留言不能为空。');
        redirect('/board.php');
    }
    $parent = null;
    if ($parentId > 0) {
        $stmt = db()->prepare('SELECT id, parent_id, user_id FROM messages WHERE id = :id');
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
        INSERT INTO messages (user_id, parent_id, content, loc_lat, loc_lng, loc_name, voice_file, voice_dur, media_file, media_dur, created_at)
        VALUES (:user_id, :parent_id, :content, :loc_lat, :loc_lng, :loc_name, :voice_file, :voice_dur, :media_file, :media_dur, :created_at)
    ');
    [$locLat, $locLng, $locName] = location_from_post($_POST);
    $stmt->execute([
        ':user_id' => $user['id'],
        ':parent_id' => $parentId > 0 ? $parentId : null,
        ':content' => mb_substr($content, 0, 1000),
        ':loc_lat' => $locLat,
        ':loc_lng' => $locLng,
        ':loc_name' => $locName,
        ':voice_file' => $voiceFile,
        ':voice_dur' => $voiceDur,
        ':media_file' => $mediaFile,
        ':media_dur' => $mediaDur,
        ':created_at' => now(),
    ]);
    $newId = (int) db()->lastInsertId();
    if ($content !== '') {
        $excerpt = mb_substr($content, 0, 60);
    } elseif ($mediaFile !== null) {
        $excerpt = media_kind($mediaFile) === 'video' ? '[视频]' : '[照片]';
    } else {
        $excerpt = '[语音]';
    }
    if ($parentId > 0) {
        $authorId = (int) ($parent['user_id'] ?? 0);
        if ($authorId > 0) {
            notification_add($authorId, (int) $user['id'], 'reply', $newId, $user['display_name'] . ' 回复了你的留言：' . $excerpt, '/board.php#msg-' . $parentId);
        }
    } else {
        $others = db()->prepare('SELECT id FROM users WHERE id <> :me');
        $others->execute([':me' => (int) $user['id']]);
        foreach ($others->fetchAll() as $row) {
            notification_add((int) $row['id'], (int) $user['id'], 'board', $newId, $user['display_name'] . ' 发布了一条新留言：' . $excerpt, '/board.php#msg-' . $newId);
        }
    }
    flash_set('ok', $parentId > 0 ? '回复已发布。' : '留言已发布。');
    $back = (string) ($_POST['back'] ?? '');
    redirect($back === 'home' ? '/index.php' : '/board.php');
}

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

render_header(nav_label('board'), $user, 'board');
?>
<section class="page-head">
    <h1><?= h(nav_label('board')) ?></h1>
</section>
<form class="panel" method="post" data-location data-voice data-capture>
    <?= csrf_field() ?>
    <label for="content">写一条留言</label>
    <textarea id="content" name="content" maxlength="1000" placeholder="最多 1000 字，可点表情或打字搜表情" data-emoji></textarea>
    <button type="submit">发布</button>
</form>
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
                <?= media_html($msg['media_file'] ?? null, (int) ($msg['media_dur'] ?? 0)) ?>
                <?= voice_html($msg['voice_file'] ?? null, (int) ($msg['voice_dur'] ?? 0)) ?>
                <?= location_chip_html($msg['loc_lat'] ?? null, $msg['loc_lng'] ?? null, $msg['loc_name'] ?? '') ?>
                <?php foreach ($replies[(int) $msg['id']] ?? [] as $reply): ?>
                    <article class="msg reply">
                        <button type="button" class="js-user avatar-btn" data-user-id="<?= (int) $reply['user_id'] ?>">
                            <?= avatar_html($reply['avatar'] ?? null, $reply['display_name'], 'avatar avatar-sm') ?>
                        </button>
                        <div class="msg-main">
                            <div class="who-line"><?= h($reply['display_name']) ?> #<?= (int) $reply['user_id'] ?> · <?= h($reply['created_at']) ?></div>
                            <p class="msg-text"><?= render_message_html($reply['content']) ?></p>
                            <?= media_html($reply['media_file'] ?? null, (int) ($reply['media_dur'] ?? 0)) ?>
                            <?= voice_html($reply['voice_file'] ?? null, (int) ($reply['voice_dur'] ?? 0)) ?>
                            <?= location_chip_html($reply['loc_lat'] ?? null, $reply['loc_lng'] ?? null, $reply['loc_name'] ?? '') ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <form class="reply-form" method="post" data-location data-voice data-capture>
                    <?= csrf_field() ?>
                    <input type="hidden" name="parent_id" value="<?= (int) $msg['id'] ?>">
                    <textarea name="content" maxlength="1000" placeholder="回复 <?= h($msg['display_name']) ?>" data-emoji></textarea>
                    <button type="submit">回复</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php render_footer();
