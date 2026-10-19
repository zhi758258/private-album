<?php
declare(strict_types=1);

function render_header(string $title, array $user, string $active = ''): void
{
    $flash = flash_get();
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> · <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="grain"></div>
    <header class="topbar">
        <a class="brand" href="/index.php">
            <span class="brand-mark"></span>
            <?= h(APP_NAME) ?>
        </a>
        <nav>
            <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="/index.php">相册墙</a>
            <a class="<?= $active === 'album' ? 'is-active' : '' ?>" href="/album.php">我的相册</a>
            <a class="<?= $active === 'board' ? 'is-active' : '' ?>" href="/board.php">留言板</a>
            <a class="<?= $active === 'memo' ? 'is-active' : '' ?>" href="/memo.php">备忘录</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="/admin.php">用户管理</a>
                <a class="<?= $active === 'admin_memos' ? 'is-active' : '' ?>" href="/admin_memos.php">全部备忘录</a>
            <?php endif; ?>
        </nav>
        <div class="who">
            <span><?= h($user['display_name']) ?></span>
            <a href="/logout.php">退出</a>
        </div>
    </header>
    <main class="wrap">
        <?php if ($flash): ?>
            <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="foot">照片只增不减 · 不对外开放注册</footer>
</body>
</html>
    <?php
}
