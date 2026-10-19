<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

    if ($username === '' || $displayName === '' || $password === '') {
        flash_set('err', '账号、显示名和密码都要填。');
        redirect('/admin.php');
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        flash_set('err', '账号只能是 3-32 位字母、数字或下划线。');
        redirect('/admin.php');
    }
    if (strlen($password) < 6) {
        flash_set('err', '密码至少 6 位。');
        redirect('/admin.php');
    }

    try {
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
    } catch (PDOException $e) {
        flash_set('err', '账号已存在。');
    }
    redirect('/admin.php');
}

$users = db()->query('SELECT id, username, display_name, role, created_at FROM users ORDER BY id ASC')->fetchAll();

render_header('用户管理', $admin, 'admin');
?>
<section class="page-head">
    <h1>用户管理</h1>
    <p>本站关闭对外注册，只能由管理员在这里添加账号。</p>
</section>
<form class="panel" method="post">
    <?= csrf_field() ?>
    <label for="username">登录账号</label>
    <input id="username" name="username" required maxlength="32" placeholder="字母数字下划线">
    <label for="display_name">显示名</label>
    <input id="display_name" name="display_name" required maxlength="40">
    <label for="password">初始密码</label>
    <input id="password" name="password" type="password" required minlength="6">
    <label for="role">角色</label>
    <select id="role" name="role">
        <option value="user">普通用户</option>
        <option value="admin">管理员</option>
    </select>
    <button type="submit">添加用户</button>
</form>
<div class="panel">
    <table class="table">
        <thead>
            <tr>
                <th>账号</th>
                <th>显示名</th>
                <th>角色</th>
                <th>创建时间</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><?= h($row['username']) ?></td>
                <td><?= h($row['display_name']) ?></td>
                <td><?= $row['role'] === 'admin' ? '管理员' : '用户' ?></td>
                <td><?= h($row['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer();
