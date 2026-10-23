<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

$targetId = (int) ($_GET['u'] ?? 0);
if ($targetId <= 0) {
    $targetId = $uid;
}

$stmt = db()->prepare('SELECT id, username, display_name, avatar, role FROM users WHERE id = :id');
$stmt->execute([':id' => $targetId]);
$owner = $stmt->fetch();
if (!$owner) {
    flash_set('err', '用户不存在。');
    redirect('/space.php');
}

$isSelf = $targetId === $uid;
$canView = space_can_view($uid, $targetId, (string) $user['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$isSelf) {
        flash_set('err', '只能在自己的空间发布内容。');
        redirect('/space.php?u=' . $targetId);
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'shuoshuo') {
        $content = trim((string) ($_POST['content'] ?? ''));
        $mood = trim((string) ($_POST['mood'] ?? ''));
        $visibility = ($_POST['visibility'] ?? 'friends') === 'private' ? 'private' : 'friends';
        if ($content === '') {
            flash_set('err', '说说内容不能为空。');
            redirect('/space.php');
        }
        $ins = db()->prepare('INSERT INTO shuoshuo (user_id, content, mood, visibility, created_at) VALUES (:user_id, :content, :mood, :visibility, :created_at)');
        $ins->execute([
            ':user_id' => $uid,
            ':content' => mb_substr($content, 0, 1000),
            ':mood' => mb_substr($mood, 0, 20),
            ':visibility' => $visibility,
            ':created_at' => now(),
        ]);
        flash_set('ok', '说说已发布。');
        redirect('/space.php');
    }

    if ($action === 'diary') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $visibility = ($_POST['visibility'] ?? 'friends') === 'private' ? 'private' : 'friends';
        if ($title === '' || $content === '') {
            flash_set('err', '日志标题和内容都要填。');
            redirect('/space.php');
        }
        $now = now();
        $ins = db()->prepare('INSERT INTO diaries (user_id, title, content, visibility, created_at, updated_at) VALUES (:user_id, :title, :content, :visibility, :created_at, :updated_at)');
        $ins->execute([
            ':user_id' => $uid,
            ':title' => mb_substr($title, 0, 120),
            ':content' => mb_substr($content, 0, 20000),
            ':visibility' => $visibility,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        flash_set('ok', '日志已发布。');
        redirect('/space.php');
    }

    if ($action === 'delete_shuoshuo') {
        $id = (int) ($_POST['id'] ?? 0);
        $del = db()->prepare('DELETE FROM shuoshuo WHERE id = :id AND user_id = :user_id');
        $del->execute([':id' => $id, ':user_id' => $uid]);
        flash_set($del->rowCount() > 0 ? 'ok' : 'err', $del->rowCount() > 0 ? '已删除说说。' : '说说不存在。');
        redirect('/space.php');
    }

    if ($action === 'delete_diary') {
        $id = (int) ($_POST['id'] ?? 0);
        $del = db()->prepare('DELETE FROM diaries WHERE id = :id AND user_id = :user_id');
        $del->execute([':id' => $id, ':user_id' => $uid]);
        flash_set($del->rowCount() > 0 ? 'ok' : 'err', $del->rowCount() > 0 ? '已删除日志。' : '日志不存在。');
        redirect('/space.php');
    }

    flash_set('err', '未知操作。');
    redirect('/space.php');
}

if (!$canView) {
    http_response_code(403);
    render_header(nav_label('space'), $user, 'space');
    ?>
    <section class="page-head">
        <h1><?= h($owner['display_name']) ?> 的空间</h1>
    </section>
    <div class="empty">TA 的空间仅好友可见。</div>
    <?php
    render_footer();
    exit;
}

$visCond = $isSelf ? '' : " AND visibility = 'friends'";

$stmt = db()->prepare('SELECT * FROM shuoshuo WHERE user_id = :user_id' . $visCond . ' ORDER BY id DESC LIMIT 100');
$stmt->execute([':user_id' => $targetId]);
$shuoshuoList = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM diaries WHERE user_id = :user_id' . $visCond . ' ORDER BY id DESC LIMIT 100');
$stmt->execute([':user_id' => $targetId]);
$diaryList = $stmt->fetchAll();

$moods = ['开心', '难过', '生气', '疲倦', '期待', '平静'];

render_header(nav_label('space') . ' · ' . $owner['display_name'], $user, 'space');
?>
<section class="page-head space-head">
    <button type="button" class="js-user avatar-btn" data-user-id="<?= $targetId ?>">
        <?= avatar_html($owner['avatar'] ?? null, $owner['display_name'], 'avatar avatar-lg') ?>
    </button>
    <div>
        <h1><?= h($owner['display_name']) ?> 的空间</h1>
        <p>@<?= h($owner['username']) ?><?= $isSelf ? ' · 这是你的空间' : '' ?></p>
    </div>
</section>

<?php if ($isSelf): ?>
<section class="panel">
    <div class="block-head"><h2>发条说说</h2></div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="shuoshuo">
        <textarea name="content" maxlength="1000" required placeholder="此刻的心情…"></textarea>
        <div class="space-form-row">
            <label for="mood">心情</label>
            <select id="mood" name="mood">
                <option value="">不说心情</option>
                <?php foreach ($moods as $m): ?>
                    <option value="<?= h($m) ?>"><?= h($m) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="shuoshuo_vis">可见范围</label>
            <select id="shuoshuo_vis" name="visibility">
                <option value="friends">好友可见</option>
                <option value="private">仅自己可见</option>
            </select>
        </div>
        <button type="submit">发布说说</button>
    </form>
</section>

<section class="panel">
    <div class="block-head"><h2>写篇日志</h2></div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="diary">
        <label for="title">标题</label>
        <input id="title" name="title" maxlength="120" required>
        <label for="diary_content">正文</label>
        <textarea id="diary_content" name="content" class="diary-text" maxlength="20000" required placeholder="写点什么…"></textarea>
        <div class="space-form-row">
            <label for="diary_vis">可见范围</label>
            <select id="diary_vis" name="visibility">
                <option value="friends">好友可见</option>
                <option value="private">仅自己可见</option>
            </select>
        </div>
        <button type="submit">发布日志</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="block-head"><h2>心情说说</h2></div>
    <?php if (!$shuoshuoList): ?>
        <div class="empty">还没有说说。</div>
    <?php else: ?>
        <div class="shuoshuo-list">
            <?php foreach ($shuoshuoList as $s): ?>
                <article class="shuoshuo-item">
                    <div class="shuoshuo-top">
                        <?php if ($s['mood'] !== ''): ?><span class="mood-tag"><?= h($s['mood']) ?></span><?php endif; ?>
                        <span class="shuoshuo-time"><?= h($s['created_at']) ?></span>
                        <?php if ($isSelf && $s['visibility'] === 'private'): ?><span class="vis-tag">仅自己可见</span><?php endif; ?>
                    </div>
                    <p class="shuoshuo-text"><?= nl2br(h($s['content'])) ?></p>
                    <?php if ($isSelf): ?>
                        <form method="post" class="shuoshuo-del">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_shuoshuo">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="btn ghost-btn">删除</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="block-head"><h2>日志</h2></div>
    <?php if (!$diaryList): ?>
        <div class="empty">还没有日志。</div>
    <?php else: ?>
        <div class="diary-list">
            <?php foreach ($diaryList as $d):
                $excerpt = mb_substr(strip_tags((string) $d['content']), 0, 120); ?>
                <article class="diary-item">
                    <a class="diary-title" href="/diary.php?id=<?= (int) $d['id'] ?>"><?= h($d['title']) ?></a>
                    <div class="diary-meta">
                        <span><?= h($d['created_at']) ?></span>
                        <?php if ($isSelf && $d['visibility'] === 'private'): ?><span class="vis-tag">仅自己可见</span><?php endif; ?>
                    </div>
                    <p class="diary-excerpt"><?= h($excerpt) ?><?= mb_strlen((string) $d['content']) > 120 ? '…' : '' ?></p>
                    <?php if ($isSelf): ?>
                        <form method="post" class="shuoshuo-del">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_diary">
                            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                            <button type="submit" class="btn ghost-btn">删除</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php render_footer();