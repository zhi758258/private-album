<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

$otherId = (int) ($_GET['u'] ?? 0);
if ($otherId <= 0 || $otherId === $uid || !are_friends($uid, $otherId)) {
    flash_set('err', '只能和好友发消息。');
    redirect('/friends.php');
}

$stmt = db()->prepare('SELECT id, username, display_name, avatar FROM users WHERE id = :id');
$stmt->execute([':id' => $otherId]);
$other = $stmt->fetch();
if (!$other) {
    flash_set('err', '用户不存在。');
    redirect('/friends.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $content = trim((string) ($_POST['content'] ?? ''));
    if ($content === '') {
        flash_set('err', '消息不能为空。');
        redirect('/chat.php?u=' . $otherId);
    }
    $ins = db()->prepare('INSERT INTO private_messages (sender_id, receiver_id, content, is_read, created_at) VALUES (:sender_id, :receiver_id, :content, 0, :created_at)');
    $ins->execute([
        ':sender_id' => $uid,
        ':receiver_id' => $otherId,
        ':content' => mb_substr($content, 0, 2000),
        ':created_at' => now(),
    ]);
    redirect('/chat.php?u=' . $otherId);
}

$stmt = db()->prepare('
    SELECT * FROM private_messages
    WHERE (sender_id = :me AND receiver_id = :other)
       OR (sender_id = :other2 AND receiver_id = :me2)
    ORDER BY id ASC
    LIMIT 500
');
$stmt->execute([':me' => $uid, ':other' => $otherId, ':other2' => $otherId, ':me2' => $uid]);
$messages = $stmt->fetchAll();

$mark = db()->prepare('UPDATE private_messages SET is_read = 1 WHERE receiver_id = :receiver_id AND sender_id = :sender_id AND is_read = 0');
$mark->execute([':receiver_id' => $uid, ':sender_id' => $otherId]);

render_header('与 ' . $other['display_name'] . ' 的对话', $user, 'friends');
?>
<section class="page-head chat-head">
    <a class="btn ghost-btn" href="/friends.php">← 好友列表</a>
    <div class="chat-peer">
        <?= avatar_html($other['avatar'] ?? null, $other['display_name'], 'avatar avatar-md') ?>
        <div>
            <div class="friend-name"><?= h($other['display_name']) ?></div>
            <div class="friend-sub">@<?= h($other['username']) ?></div>
        </div>
    </div>
</section>

<div class="chat-box" id="chatBox">
    <?php if (!$messages): ?>
        <div class="empty">还没有消息，说点什么吧。</div>
    <?php else: ?>
        <?php foreach ($messages as $m):
            $mine = (int) $m['sender_id'] === $uid; ?>
            <div class="chat-line <?= $mine ? 'is-mine' : 'is-theirs' ?>">
                <?php if (!$mine): ?>
                    <?= avatar_html($other['avatar'] ?? null, $other['display_name'], 'avatar avatar-sm') ?>
                <?php endif; ?>
                <div class="chat-bubble">
                    <div class="chat-text"><?= nl2br(h($m['content'])) ?></div>
                    <div class="chat-time"><?= h($m['created_at']) ?></div>
                </div>
                <?php if ($mine): ?>
                    <?= avatar_html($user['avatar'] ?? null, $user['display_name'], 'avatar avatar-sm') ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<form class="panel chat-form" method="post" action="/chat.php?u=<?= $otherId ?>">
    <?= csrf_field() ?>
    <textarea id="content" name="content" maxlength="2000" required placeholder="输入消息，点击发送" aria-label="消息内容"></textarea>
    <button type="submit">发送</button>
</form>
<script>
(function () {
    var box = document.getElementById('chatBox');
    if (box) box.scrollTop = box.scrollHeight;
    var ta = document.getElementById('content');
    if (ta) ta.focus();
})();
</script>
<?php render_footer();