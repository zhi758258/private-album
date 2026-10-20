<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();
$filter = (string) ($_GET['filter'] ?? '');
if (!in_array($filter, ['used', 'unused', ''], true)) {
    $filter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'generate') {
        $count = (int) ($_POST['count'] ?? 1);
        if ($count < 1 || $count > 1000) {
            flash_set('err', '生成数量需在 1-1000 之间。');
        } else {
            $prefix = (string) ($_POST['prefix'] ?? '');
            $added = cards_insert((int) $admin['id'], $count, $prefix);
            if ($added > 0) {
                flash_set('ok', $added === $count ? "已生成 {$added} 张卡密。" : "已生成 {$added} 张卡密（部分因重名跳过）。");
            } else {
                flash_set('err', '生成失败，请重试。');
            }
        }
        redirect('/admin_cards.php');
    }

    if ($action === 'delete') {
        $cardId = (int) ($_POST['card_id'] ?? 0);
        $stmt = db()->prepare('SELECT id FROM cards WHERE id = :id');
        $stmt->execute([':id' => $cardId]);
        if ($stmt->fetch()) {
            $del = db()->prepare('DELETE FROM cards WHERE id = :id');
            $del->execute([':id' => $cardId]);
            flash_set('ok', '卡密已删除。');
        } else {
            flash_set('err', '卡密不存在。');
        }
        redirect('/admin_cards.php?filter=' . rawurlencode($filter));
    }

    redirect('/admin_cards.php');
}

$sql = '
    SELECT c.*,
        u.display_name AS used_name
    FROM cards c
    LEFT JOIN users u ON u.id = c.used_by
';
$params = [];
if ($filter === 'used') {
    $sql .= ' WHERE c.used_by IS NOT NULL';
} elseif ($filter === 'unused') {
    $sql .= ' WHERE c.used_by IS NULL';
}
$sql .= ' ORDER BY c.id DESC LIMIT 500';

$cards = [];
$stmt = db()->prepare($sql);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    $cards[] = $row;
}

$usedCount = (int) db()->query('SELECT COUNT(*) FROM cards WHERE used_by IS NOT NULL')->fetchColumn();
$unusedCount = (int) db()->query('SELECT COUNT(*) FROM cards WHERE used_by IS NULL')->fetchColumn();

render_header(nav_label('admin_cards'), $admin, 'admin_cards');
?>
<section class="page-head">
    <h1><?= h(nav_label('admin_cards')) ?></h1>
    <p>卡密永久有效，一张卡密仅供一人使用一次。</p>
</section>

<form class="panel" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="generate">
    <div style="display:flex; flex-wrap:wrap; gap:16px;">
        <div style="flex:1; min-width:200px;">
            <label for="count">生成数量</label>
            <input id="count" name="count" type="number" min="1" max="1000" value="10" required>
        </div>
        <div style="flex:2; min-width:240px;">
            <label for="prefix">前缀（可选，8 位内字母数字）</label>
            <input id="prefix" name="prefix" maxlength="8" placeholder="例如 ALBUM">
        </div>
    </div>
    <button type="submit">生成卡密</button>
</form>

<div class="panel">
    <div class="block-head">
        <h2>卡密列表</h2>
        <span style="color:var(--muted); font-size:13px;">
            未使用 <strong style="color:var(--gold);"><?= $unusedCount ?></strong> · 已使用 <strong style="color:var(--muted);"><?= $usedCount ?></strong>
        </span>
    </div>
    <div class="tabs" style="display:flex; gap:6px; margin-bottom:14px;">
        <a class="btn ghost-btn" href="/admin_cards.php" style="<?= $filter === '' ? 'outline:2px solid var(--gold);' : '' ?>">全部</a>
        <a class="btn ghost-btn" href="/admin_cards.php?filter=unused" style="<?= $filter === 'unused' ? 'outline:2px solid var(--gold);' : '' ?>">未使用</a>
        <a class="btn ghost-btn" href="/admin_cards.php?filter=used" style="<?= $filter === 'used' ? 'outline:2px solid var(--gold);' : '' ?>">已使用</a>
    </div>
    <?php if (!$cards): ?>
        <div class="empty">还没有卡密。</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>卡密</th>
                    <th>状态</th>
                    <th>使用人</th>
                    <th>生成时间</th>
                    <th>使用时间</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cards as $card): ?>
                <tr>
                    <td><code style="font-size:13px; letter-spacing:0.05em;"><?= h($card['code']) ?></code></td>
                    <td><?= $card['used_by'] ? '<span style="color:var(--muted);">已使用</span>' : '<span style="color:var(--ok);">未使用</span>' ?></td>
                    <td><?= $card['used_by'] ? h($card['used_name'] ?? '') : '—' ?></td>
                    <td><?= h($card['created_at']) ?></td>
                    <td><?= $card['used_at'] ? h($card['used_at']) : '—' ?></td>
                    <td>
                        <form method="post" class="wall-form" onsubmit="return confirm('确定删除这张卡密？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                            <button class="btn danger-btn" type="submit">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php render_footer();