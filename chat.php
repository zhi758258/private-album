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
    [$voiceFile, $voiceDur] = voice_from_post($_POST, $uid);
    [$mediaFile, $mediaDur] = media_from_post($_POST, $uid);
    if ($content === '' && $voiceFile === null && $mediaFile === null) {
        flash_set('err', '消息不能为空。');
        redirect('/chat.php?u=' . $otherId);
    }
    $ins = db()->prepare('INSERT INTO private_messages (sender_id, receiver_id, content, loc_lat, loc_lng, loc_name, voice_file, voice_dur, media_file, media_dur, is_read, created_at) VALUES (:sender_id, :receiver_id, :content, :loc_lat, :loc_lng, :loc_name, :voice_file, :voice_dur, :media_file, :media_dur, 0, :created_at)');
    [$locLat, $locLng, $locName] = location_from_post($_POST);
    $ins->execute([
        ':sender_id' => $uid,
        ':receiver_id' => $otherId,
        ':content' => mb_substr($content, 0, 2000),
        ':loc_lat' => $locLat,
        ':loc_lng' => $locLng,
        ':loc_name' => $locName,
        ':voice_file' => $voiceFile,
        ':voice_dur' => $voiceDur,
        ':media_file' => $mediaFile,
        ':media_dur' => $mediaDur,
        ':created_at' => now(),
    ]);
    notification_add($otherId, $uid, 'message', null, $user['display_name'] . ' 给你发来一条私信', '/chat.php?u=' . $uid);
    redirect('/chat.php?u=' . $otherId);
}

$stmt = db()->prepare('
    SELECT id, sender_id, content, loc_lat, loc_lng, loc_name, voice_file, voice_dur, media_file, media_dur, created_at FROM private_messages
    WHERE (sender_id = :me AND receiver_id = :other)
       OR (sender_id = :other2 AND receiver_id = :me2)
    ORDER BY id ASC
    LIMIT 500
');
$stmt->execute([':me' => $uid, ':other' => $otherId, ':other2' => $otherId, ':me2' => $uid]);
$messages = $stmt->fetchAll();

$last = 0;
foreach ($messages as $m) {
    if ((int) $m['id'] > $last) {
        $last = (int) $m['id'];
    }
}

$mark = db()->prepare('UPDATE private_messages SET is_read = 1 WHERE receiver_id = :receiver_id AND sender_id = :sender_id AND is_read = 0');
$mark->execute([':receiver_id' => $uid, ':sender_id' => $otherId]);
$markNotif = db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND actor_id = :actor_id AND type = 'message' AND is_read = 0");
$markNotif->execute([':user_id' => $uid, ':actor_id' => $otherId]);

$selfAvatar = avatar_html($user['avatar'] ?? null, $user['display_name'], 'avatar avatar-sm');
$otherAvatar = avatar_html($other['avatar'] ?? null, $other['display_name'], 'avatar avatar-sm');

render_header('与 ' . $other['display_name'] . ' 的对话', $user, 'friends');
?>
<section class="page-head chat-head">
    <a class="btn ghost-btn" href="/friends.php">← 好友列表</a>
    <div class="chat-peer">
        <button type="button" class="js-user avatar-btn" data-user-id="<?= $otherId ?>">
            <?= avatar_html($other['avatar'] ?? null, $other['display_name'], 'avatar avatar-md') ?>
        </button>
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
            <div class="chat-line <?= $mine ? 'is-mine' : 'is-theirs' ?>" data-id="<?= (int) $m['id'] ?>">
                <?php if (!$mine): ?><?= $otherAvatar ?><?php endif; ?>
                <div class="chat-bubble">
                    <div class="chat-text"><?= render_message_html($m['content']) ?></div>
                    <?= media_html($m['media_file'] ?? null, (int) ($m['media_dur'] ?? 0)) ?>
                    <?= voice_html($m['voice_file'] ?? null, (int) ($m['voice_dur'] ?? 0)) ?>
                    <?= location_chip_html($m['loc_lat'] ?? null, $m['loc_lng'] ?? null, $m['loc_name'] ?? '') ?>
                    <div class="chat-time"><?= h($m['created_at']) ?></div>
                </div>
                <?php if ($mine): ?><?= $selfAvatar ?><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<form class="panel chat-form" id="chatForm" method="post" action="/chat.php?u=<?= $otherId ?>" data-location data-voice data-capture data-call data-call-user="<?= $otherId ?>" data-call-name="<?= h($other['display_name']) ?>">
    <?= csrf_field() ?>
    <textarea id="content" name="content" maxlength="2000" placeholder="输入消息，回车发送，可点表情或打字搜表情" aria-label="消息内容" data-emoji></textarea>
    <button type="submit">发送</button>
</form>
<script>
window.CHAT = {
    user: <?= (int) $otherId ?>,
    last: <?= (int) $last ?>,
    csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>,
    selfAvatar: <?= json_encode($selfAvatar, JSON_UNESCAPED_UNICODE) ?>,
    otherAvatar: <?= json_encode($otherAvatar, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script>
(function () {
    var box = document.getElementById('chatBox');
    var form = document.getElementById('chatForm');
    var ta = document.getElementById('content');
    var cfg = window.CHAT;
    if (!box || !form || !ta || !cfg) return;
    var last = cfg.last;
    var sending = false;

    function lineEl(msg) {
        var line = document.createElement('div');
        line.className = 'chat-line ' + (msg.mine ? 'is-mine' : 'is-theirs');
        line.setAttribute('data-id', String(msg.id));
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        var text = document.createElement('div');
        text.className = 'chat-text';
        if (window.Emoji && window.Emoji.render) text.innerHTML = window.Emoji.render(msg.content);
        else text.textContent = msg.content;
        bubble.appendChild(text);
        if (msg.media_html) {
            var mbWrap = document.createElement('div');
            mbWrap.innerHTML = msg.media_html;
            if (mbWrap.firstElementChild) bubble.appendChild(mbWrap.firstElementChild);
        }
        if (msg.voice_html) {
            var vbWrap = document.createElement('div');
            vbWrap.innerHTML = msg.voice_html;
            if (vbWrap.firstElementChild) bubble.appendChild(vbWrap.firstElementChild);
        }
        if (msg.loc_html) {
            var locWrap = document.createElement('div');
            locWrap.innerHTML = msg.loc_html;
            if (locWrap.firstElementChild) bubble.appendChild(locWrap.firstElementChild);
        }
        var time = document.createElement('div');
        time.className = 'chat-time';
        time.textContent = msg.created_at;
        bubble.appendChild(time);
        var wrap = document.createElement('span');
        wrap.innerHTML = msg.mine ? cfg.selfAvatar : cfg.otherAvatar;
        var avatar = wrap.firstElementChild;
        if (msg.mine) {
            line.appendChild(bubble);
            if (avatar) line.appendChild(avatar);
        } else {
            if (avatar) line.appendChild(avatar);
            line.appendChild(bubble);
        }
        return line;
    }
    function nearBottom() {
        return box.scrollHeight - box.scrollTop - box.clientHeight < 90;
    }
    function append(list) {
        if (!list || !list.length) return;
        var stick = nearBottom();
        var incoming = false;
        list.forEach(function (m) {
            if (box.querySelector('.chat-line[data-id="' + m.id + '"]')) return;
            box.appendChild(lineEl(m));
            if (m.id > last) last = m.id;
            if (!m.mine) incoming = true;
        });
        var empty = box.querySelector('.empty');
        if (empty) empty.remove();
        if (stick) box.scrollTop = box.scrollHeight;
        if (incoming && window.playDing) window.playDing();
    }
    function poll() {
        fetch('/chat_api.php?u=' + cfg.user + '&after=' + last, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (d) { if (d && d.ok) append(d.messages); })
            .catch(function () {});
    }
    function send() {
        var val = ta.value.trim();
        var voiceEl = form.querySelector('input[name="voice_file"]');
        var mediaEl = form.querySelector('input[name="media_file"]');
        var hasVoice = !!(voiceEl && voiceEl.value);
        var hasMedia = !!(mediaEl && mediaEl.value);
        if ((val === '' && !hasVoice && !hasMedia) || sending) return;
        sending = true;
        var fd = new FormData();
        fd.append('u', cfg.user);
        fd.append('content', val);
        ['loc_lat', 'loc_lng', 'loc_name', 'voice_file', 'voice_dur', 'media_file', 'media_dur'].forEach(function (n) {
            var el = form.querySelector('input[name="' + n + '"]');
            if (el && el.value) fd.append(n, el.value);
        });
        fd.append('csrf', cfg.csrf);
        fetch('/chat_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (d) {
                if (d && d.ok && d.message) {
                    ta.value = '';
                    if (window.SiteLocation) window.SiteLocation.reset(form);
                    if (window.SiteVoice) window.SiteVoice.reset(form);
                    if (window.SiteCapture) window.SiteCapture.reset(form);
                    append([d.message]);
                    box.scrollTop = box.scrollHeight;
                }
            })
            .catch(function () {})
            .then(function () { sending = false; });
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        send();
    });
    ta.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            if (form.requestSubmit) form.requestSubmit();
            else form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });
    box.scrollTop = box.scrollHeight;
    ta.focus();
    setInterval(poll, 4000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
</script>
<?php render_footer();