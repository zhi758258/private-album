<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT id, username, display_name, role FROM users WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash_set('err', '用户不存在。');
        redirect('/admin.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $username = trim((string) ($_POST['username'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

    if ($username === '' || $displayName === '') {
        flash_set('err', '账号和显示名都要填。');
        redirect($id > 0 ? '/admin.php?edit=' . $id : '/admin.php');
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        flash_set('err', '账号只能是 3-32 位字母、数字或下划线。');
        redirect($id > 0 ? '/admin.php?edit=' . $id : '/admin.php');
    }
    if ($id === 0 && $password === '') {
        flash_set('err', '新用户必须设置密码。');
        redirect('/admin.php');
    }
    if ($password !== '' && strlen($password) < 6) {
        flash_set('err', '密码至少 6 位。');
        redirect($id > 0 ? '/admin.php?edit=' . $id : '/admin.php');
    }

    try {
        if ($id > 0) {
            if ($password !== '') {
                $stmt = db()->prepare('
                    UPDATE users
                    SET username = :username, display_name = :display_name, role = :role, password_hash = :password_hash
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':username' => $username,
                    ':display_name' => mb_substr($displayName, 0, 40),
                    ':role' => $role,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $id,
                ]);
            } else {
                $stmt = db()->prepare('
                    UPDATE users
                    SET username = :username, display_name = :display_name, role = :role
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':username' => $username,
                    ':display_name' => mb_substr($displayName, 0, 40),
                    ':role' => $role,
                    ':id' => $id,
                ]);
            }
            flash_set('ok', '已保存用户：' . $username);
        } else {
            $stmt = db()->prepare('
                INSERT INTO users (username, password_hash, display_name, role, created_at)
                VALUES (:username, :password_hash, :display_name, :role, :created_at)
            ');
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':display_name' => mb_substr($displayName, 0, 40),
                ':role' => $role,
                ':created_at' => now(),
            ]);
            flash_set('ok', '已添加用户：' . $username);
        }
    } catch (PDOException $e) {
        flash_set('err', '账号已存在。');
        redirect($id > 0 ? '/admin.php?edit=' . $id : '/admin.php');
    }
    redirect('/admin.php');
}

$users = db()->query('SELECT id, username, display_name, role, created_at FROM users ORDER BY id ASC')->fetchAll();

render_header(nav_label('admin'), $admin, 'admin');
?>
<section class="page-head">
    <h1><?= $editing ? '编辑用户' : h(nav_label('admin')) ?></h1>
    <p>每个用户拥有唯一数字 ID（#ID），注册或添加时自动分配。</p>
</section>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <?php endif; ?>
    <label for="username">登录账号</label>
    <input id="username" name="username" required maxlength="32" placeholder="字母数字下划线" value="<?= h($editing['username'] ?? '') ?>">
    <label for="display_name">显示名</label>
    <input id="display_name" name="display_name" required maxlength="40" value="<?= h($editing['display_name'] ?? '') ?>">
    <label for="password"><?= $editing ? '新密码（留空则不改）' : '初始密码' ?></label>
    <input id="password" name="password" type="password" <?= $editing ? '' : 'required' ?> minlength="6">
    <label for="role">角色</label>
    <select id="role" name="role">
        <option value="user" <?= ($editing['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>普通用户</option>
        <option value="admin" <?= ($editing['role'] ?? '') === 'admin' ? 'selected' : '' ?>>管理员</option>
    </select>
    <button type="submit"><?= $editing ? '保存' : '添加用户' ?></button>
    <?php if ($editing): ?>
        <a class="btn" href="/admin.php">取消</a>
    <?php endif; ?>
</form>
<div class="panel">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>账号</th>
                <th>显示名</th>
                <th>角色</th>
                <th>创建时间</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td>#<?= (int) $row['id'] ?></td>
                <td><?= h($row['username']) ?></td>
                <td><?= h($row['display_name']) ?></td>
                <td><?= $row['role'] === 'admin' ? '管理员' : '用户' ?></td>
                <td><?= h($row['created_at']) ?></td>
                <td><a href="/admin.php?edit=<?= (int) $row['id'] ?>">编辑</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer();
