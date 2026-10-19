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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= h($title) ?> · <?= h(app_name()) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="grain"></div>
    <header class="topbar">
        <button class="menu-toggle" type="button" aria-label="打开菜单" aria-expanded="false">菜单</button>
        <a class="brand" href="/index.php">
            <span class="brand-mark"></span>
            <?= h(app_name()) ?>
        </a>
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
            <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="/index.php">首页</a>
            <a class="<?= $active === 'board' ? 'is-active' : '' ?>" href="/board.php">留言板</a>
            <a class="<?= $active === 'album' ? 'is-active' : '' ?>" href="/album.php">我的相册</a>
            <a class="<?= $active === 'memo' ? 'is-active' : '' ?>" href="/memo.php">备忘录</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="/admin.php">用户管理</a>
                <a class="<?= $active === 'admin_photos' ? 'is-active' : '' ?>" href="/admin_photos.php">全部相册</a>
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
            <a class="drawer-logout" href="/logout.php">退出</a>
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
    <?= notice_markup() ?>
    <div class="confirm-mask" id="delete-confirm" hidden>
        <div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="delete-confirm-title">
            <h2 id="delete-confirm-title">确认删除</h2>
            <p>确定删除这张照片？删除后无法恢复。</p>
            <div class="confirm-actions">
                <button type="button" class="btn ghost-btn" data-cancel>取消</button>
                <button type="button" class="btn danger-btn" data-ok>确定删除</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var btn = document.querySelector('.menu-toggle');
        var closeBtn = document.querySelector('.drawer-close');
        var mask = document.querySelector('.nav-mask');
        var drawer = document.querySelector('.drawer');
        if (!btn || !mask || !drawer || !closeBtn) return;
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
    (function () {
        var slider = document.querySelector('[data-slider]');
        if (!slider) return;
        var track = slider.querySelector('.wall-track');
        var prev = slider.querySelector('[data-prev]');
        var next = slider.querySelector('[data-next]');
        if (!track) return;
        function step(dir) {
            var card = track.querySelector('.wall-slide');
            var width = card ? card.getBoundingClientRect().width + 16 : track.clientWidth;
            track.scrollBy({ left: dir * width, behavior: 'smooth' });
        }
        if (prev) prev.addEventListener('click', function () { step(-1); });
        if (next) next.addEventListener('click', function () { step(1); });
    })();
    (function () {
        var box = document.getElementById('delete-confirm');
        if (!box) return;
        var pending = null;
        var cancelBtn = box.querySelector('[data-cancel]');
        var okBtn = box.querySelector('[data-ok]');
        function close() {
            box.hidden = true;
            document.body.classList.remove('modal-open');
            pending = null;
        }
        document.querySelectorAll('.js-delete-photo').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                pending = form;
                box.hidden = false;
                document.body.classList.add('modal-open');
            });
        });
        if (cancelBtn) cancelBtn.addEventListener('click', close);
        box.addEventListener('click', function (e) {
            if (e.target === box) close();
        });
        if (okBtn) okBtn.addEventListener('click', function () {
            if (!pending) return;
            var form = pending;
            pending = null;
            box.hidden = true;
            HTMLFormElement.prototype.submit.call(form);
        });
    })();
    </script>
</body>
</html>
    <?php
}
