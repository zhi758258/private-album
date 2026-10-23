<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'request') {
        $toId = (int) ($_POST['to_user_id'] ?? 0);
        if ($toId <= 0 || $toId === $uid) {
            flash_set('err', '无效的用户。');
            redirect('/friends.php');
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE id = :id');
        $stmt->execute([':id' => $toId]);
        if (!$stmt->fetch()) {
            flash_set('err', '用户不存在。');
            redirect('/friends.php');
        }
        if (are_friends($uid, $toId)) {
            flash_set('err', '你们已经是好友了。');
            redirect('/friends.php');
        }
        $reverse = friend_request_find($toId, $uid);
        if ($reverse) {
            $del = db()->prepare('DELETE FROM friend_requests WHERE id = :id');
            $del->execute([':id' => (int) $reverse['id']]);
            add_friendship($uid, $toId);
            flash_set('ok', '已同意对方的好友请求，你们现在是好友了。');
            redirect('/friends.php');
        }
        if (friend_request_find($uid, $toId)) {
            flash_set('err', '好友请求已发送，请等待对方确认。');
            redirect('/friends.php');
        }
        $ins = db()->prepare('INSERT INTO friend_requests (from_user_id, to_user_id, created_at) VALUES (:from_user_id, :to_user_id, :created_at)');
        $ins->execute([':from_user_id' => $uid, ':to_user_id' => $toId, ':created_at' => now()]);
        flash_set('ok', '好友请求已发送。');
        redirect('/friends.php');
    }

    if ($action === 'accept' || $action === 'reject' || $action === 'cancel') {
        $reqId = (int) ($_POST['request_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM friend_requests WHERE id = :id');
        $stmt->execute([':id' => $reqId]);
        $req = $stmt->fetch();
        if (!$req) {
            flash_set('err', '请求不存在或已处理。');
            redirect('/friends.php');
        }
        if ($action === 'cancel') {
            if ((int) $req['from_user_id'] !== $uid) {
                flash_set('err', '无权操作该请求。');
                redirect('/friends.php');
            }
            $del = db()->prepare('DELETE FROM friend_requests WHERE id = :id');
            $del->execute([':id' => $reqId]);
            flash_set('ok', '已取消好友请求。');
            redirect('/friends.php');
        }
        if ((int) $req['to_user_id'] !== $uid) {
            flash_set('err', '无权操作该请求。');
            redirect('/friends.php');
        }
        $del = db()->prepare('DELETE FROM friend_requests WHERE id = :id');
        $del->execute([':id' => $reqId]);
        if ($action === 'accept') {
            add_friendship($uid, (int) $req['from_user_id']);
            flash_set('ok', '已添加为好友。');
        } else {
            flash_set('ok', '已拒绝好友请求。');
        }
        redirect('/friends.php');
    }

    if ($action === 'remove') {
        $friendId = (int) ($_POST['friend_id'] ?? 0);
        if ($friendId > 0 && are_friends($uid, $friendId)) {
            remove_friendship($uid, $friendId);
            flash_set('ok', '已删除好友。');
        } else {
            flash_set('err', '对方不是你的好友。');
        }
        redirect('/friends.php');
    }

    flash_set('err', '未知操作。');
    redirect('/friends.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$searchResults = [];
if ($q !== '') {
    $stmt = db()->prepare('
        SELECT id, display_name, username, avatar
        FROM users
        WHERE id <> :me AND (username LIKE :kw OR display_name LIKE :kw2)
        ORDER BY display_name ASC
        LIMIT 30
    ');
    $like = '%' . $q . '%';
    $stmt->execute([':me' => $uid, ':kw' => $like, ':kw2' => $like]);
    $searchResults = $stmt->fetchAll();
    foreach ($searchResults as $i => $r) {
        $rid = (int) $r['id'];
        $state = 'none';
        if (are_friends($uid, $rid)) {
            $state = 'friend';
        } elseif (friend_request_find($uid, $rid)) {
            $state = 'sent';
        } elseif (friend_request_find($rid, $uid)) {
            $state = 'incoming';
        }
        $searchResults[$i]['state'] = $state;
    }
}

$stmt = db()->prepare('
    SELECT fr.id, fr.from_user_id, fr.created_at, u.display_name, u.username, u.avatar
    FROM friend_requests fr
    JOIN users u ON u.id = fr.from_user_id
    WHERE fr.to_user_id = :me
    ORDER BY fr.id DESC
');
$stmt->execute([':me' => $uid]);
$incoming = $stmt->fetchAll();

$stmt = db()->prepare('
    SELECT fr.id, fr.to_user_id, fr.created_at, u.display_name, u.username, u.avatar
    FROM friend_requests fr
    JOIN users u ON u.id = fr.to_user_id
    WHERE fr.from_user_id = :me
    ORDER BY fr.id DESC
');
$stmt->execute([':me' => $uid]);
$outgoing = $stmt->fetchAll();

$friends = friends_of($uid);

render_header(nav_label('friends'), $user, 'friends');
?>
<section class="page-head">
    <h1><?= h(nav_label('friends')) ?></h1>
    <p>搜索用户添加好友，好友之间可以互发私信。</p>
</section>

<form class="panel" method="get" action="/friends.php">
    <label for="q">查找用户</label>
    <input id="q" name="q" value="<?= h($q) ?>" placeholder="输入用户名或昵称" autocomplete="off">
    <button type="submit">搜索</button>
</form>

<?php if ($q !== ''): ?>
<section class="panel">
    <div class="block-head"><h2>搜索结果</h2></div>
    <?php if (!$searchResults): ?>
        <div class="empty">没有找到匹配的用户。</div>
    <?php else: ?>
        <div class="friend-list">
            <?php foreach ($searchResults as $r): ?>
                <div class="friend-item">
                    <button type="button" class="js-user avatar-btn" data-user-id="<?= (int) $r['id'] ?>">
                        <?= avatar_html($r['avatar'] ?? null, $r['display_name'], 'avatar avatar-md') ?>
                    </button>
                    <div class="friend-info">
                        <div class="friend-name"><?= h($r['display_name']) ?></div>
                        <div class="friend-sub">@<?= h($r['username']) ?></div>
                    </div>
                    <div class="friend-actions">
                        <?php if ($r['state'] === 'friend'): ?>
                            <span class="tag tag-ok">已是好友</span>
                            <a class="btn ghost-btn" href="/chat.php?u=<?= (int) $r['id'] ?>">发消息</a>
                        <?php elseif ($r['state'] === 'sent'): ?>
                            <span class="tag">已发送</span>
                        <?php elseif ($r['state'] === 'incoming'): ?>
                            <span class="tag">待你确认</span>
                        <?php else: ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="request">
                                <input type="hidden" name="to_user_id" value="<?= (int) $r['id'] ?>">
                                <button class="btn" type="submit">加好友</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($incoming): ?>
<section class="panel">
    <div class="block-head"><h2>好友请求</h2></div>
    <div class="friend-list">
        <?php foreach ($incoming as $req): ?>
            <div class="friend-item">
                <?= avatar_html($req['avatar'] ?? null, $req['display_name'], 'avatar avatar-md') ?>
                <div class="friend-info">
                    <div class="friend-name"><?= h($req['display_name']) ?></div>
                    <div class="friend-sub">@<?= h($req['username']) ?> · <?= h($req['created_at']) ?></div>
                </div>
                <div class="friend-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="accept">
                        <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                        <button class="btn" type="submit">接受</button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                        <button class="btn ghost-btn" type="submit">拒绝</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($outgoing): ?>
<section class="panel">
    <div class="block-head"><h2>已发送的请求</h2></div>
    <div class="friend-list">
        <?php foreach ($outgoing as $req): ?>
            <div class="friend-item">
                <?= avatar_html($req['avatar'] ?? null, $req['display_name'], 'avatar avatar-md') ?>
                <div class="friend-info">
                    <div class="friend-name"><?= h($req['display_name']) ?></div>
                    <div class="friend-sub">@<?= h($req['username']) ?> · <?= h($req['created_at']) ?></div>
                </div>
                <div class="friend-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                        <button class="btn ghost-btn" type="submit">取消</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="block-head"><h2>我的好友</h2></div>
    <?php if (!$friends): ?>
        <div class="empty">还没有好友，搜索用户名添加吧。</div>
    <?php else: ?>
        <div class="friend-list">
            <?php foreach ($friends as $f):
                $fid = (int) $f['id'];
                $unread = friend_unread_between($uid, $fid); ?>
                <div class="friend-item">
                    <button type="button" class="js-user avatar-btn" data-user-id="<?= $fid ?>">
                        <?= avatar_html($f['avatar'] ?? null, $f['display_name'], 'avatar avatar-md') ?>
                    </button>
                    <div class="friend-info">
                        <div class="friend-name"><?= h($f['display_name']) ?></div>
                        <div class="friend-sub">@<?= h($f['username']) ?></div>
                    </div>
                    <div class="friend-actions">
                        <a class="btn" href="/chat.php?u=<?= $fid ?>">发消息<?php if ($unread > 0): ?><span class="nav-badge"><?= (int) $unread ?></span><?php endif; ?></a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="friend_id" value="<?= $fid ?>">
                            <button class="btn ghost-btn" type="submit">删除好友</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php render_footer();