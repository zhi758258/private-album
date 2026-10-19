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
    <title><?= h($title) ?> · <?= h(app_name()) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="grain"></div>
    <header class="topbar">
        <button class="menu-toggle" type="button" aria-label="打开菜单" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <a class="brand" href="/index.php">
            <span class="brand-mark"></span>
            <?= h(app_name()) ?>
        </a>
        <nav class="desk-nav">
            <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="/index.php">相册墙</a>
            <a class="<?= $active === 'album' ? 'is-active' : '' ?>" href="/album.php">我的相册</a>
            <a class="<?= $active === 'board' ? 'is-active' : '' ?>" href="/board.php">留言板</a>
            <a class="<?= $active === 'memo' ? 'is-active' : '' ?>" href="/memo.php">备忘录</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="/admin.php">用户管理</a>
                <a class="<?= $active === 'admin_memos' ? 'is-active' : '' ?>" href="/admin_memos.php">全部备忘录</a>
                <a class="<?= $active === 'files' ? 'is-active' : '' ?>" href="/files.php">文件管理</a>
                <a class="<?= $active === 'admin_settings' ? 'is-active' : '' ?>" href="/admin_settings.php">后台设置</a>
            <?php else: ?>
                <a class="<?= $active === 'settings' ? 'is-active' : '' ?>" href="/settings.php">设置</a>
            <?php endif; ?>
        </nav>
        <div class="who desk-who">
            <span><?= h($user['display_name']) ?></span>
            <a href="/logout.php">退出</a>
        </div>
    </header>
    <div class="nav-mask" hidden></div>
    <aside class="drawer" aria-hidden="true">
        <div class="drawer-head">
            <div>
                <div class="drawer-title"><?= h(app_name()) ?></div>
                <div class="drawer-sub"><?= h($user['display_name']) ?></div>
            </div>
            <button class="drawer-close" type="button" aria-label="关闭菜单">关闭</button>
        </div>
        <nav class="drawer-nav">
            <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="/index.php">相册墙</a>
            <a class="<?= $active === 'album' ? 'is-active' : '' ?>" href="/album.php">我的相册</a>
            <a class="<?= $active === 'board' ? 'is-active' : '' ?>" href="/board.php">留言板</a>
            <a class="<?= $active === 'memo' ? 'is-active' : '' ?>" href="/memo.php">备忘录</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="/admin.php">用户管理</a>
                <a class="<?= $active === 'admin_memos' ? 'is-active' : '' ?>" href="/admin_memos.php">全部备忘录</a>
                <a class="<?= $active === 'files' ? 'is-active' : '' ?>" href="/files.php">文件管理</a>
                <a class="<?= $active === 'admin_settings' ? 'is-active' : '' ?>" href="/admin_settings.php">后台设置</a>
            <?php else: ?>
                <a class="<?= $active === 'settings' ? 'is-active' : '' ?>" href="/settings.php">设置</a>
            <?php endif; ?>
        </nav>
        <div class="drawer-foot">
            <div>
                <strong><?= h($user['display_name']) ?></strong>
                <span><?= $user['role'] === 'admin' ? '管理员' : '用户' ?></span>
            </div>
            <a href="/logout.php">退出</a>
        </div>
    </aside>
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
    <footer class="foot"></footer>
    <script>
    (function () {
        var btn = document.querySelector('.menu-toggle');
        var closeBtn = document.querySelector('.drawer-close');
        var mask = document.querySelector('.nav-mask');
        var drawer = document.querySelector('.drawer');
        if (!btn || !mask || !drawer) return;
        function openMenu() {
            document.body.classList.add('nav-open');
            drawer.setAttribute('aria-hidden', 'false');
            btn.setAttribute('aria-expanded', 'true');
            mask.hidden = false;
        }
        function closeMenu() {
            document.body.classList.remove('nav-open');
            drawer.setAttribute('aria-hidden', 'true');
            btn.setAttribute('aria-expanded', 'false');
            mask.hidden = true;
        }
        btn.addEventListener('click', openMenu);
        closeBtn.addEventListener('click', closeMenu);
        mask.addEventListener('click', closeMenu);
    })();
    </script>
</body>
</html>
    <?php
}
