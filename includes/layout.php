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
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
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
        <nav class="topnav" aria-label="主导航">
            <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="/index.php"><?= h(nav_label('home')) ?></a>
            <a class="<?= $active === 'board' ? 'is-active' : '' ?>" href="/board.php"><?= h(nav_label('board')) ?></a>
            <a class="<?= $active === 'album' ? 'is-active' : '' ?>" href="/album.php"><?= h(nav_label('album')) ?></a>
            <a class="<?= $active === 'memo' ? 'is-active' : '' ?>" href="/memo.php"><?= h(nav_label('memo')) ?></a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="/admin.php"><?= h(nav_label('admin')) ?></a>
                <a class="<?= $active === 'admin_cards' ? 'is-active' : '' ?>" href="/admin_cards.php"><?= h(nav_label('admin_cards')) ?></a>
                <a class="<?= $active === 'admin_photos' ? 'is-active' : '' ?>" href="/admin_photos.php"><?= h(nav_label('admin_photos')) ?></a>
                <a class="<?= $active === 'admin_memos' ? 'is-active' : '' ?>" href="/admin_memos.php"><?= h(nav_label('admin_memos')) ?></a>
                <a class="<?= $active === 'files' ? 'is-active' : '' ?>" href="/files.php"><?= h(nav_label('files')) ?></a>
                <a class="<?= $active === 'admin_settings' ? 'is-active' : '' ?>" href="/admin_settings.php"><?= h(nav_label('admin_settings')) ?></a>
            <?php else: ?>
                <a class="<?= $active === 'settings' ? 'is-active' : '' ?>" href="/settings.php"><?= h(nav_label('settings')) ?></a>
            <?php endif; ?>
        </nav>
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
            <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="/index.php"><?= h(nav_label('home')) ?></a>
            <a class="<?= $active === 'board' ? 'is-active' : '' ?>" href="/board.php"><?= h(nav_label('board')) ?></a>
            <a class="<?= $active === 'album' ? 'is-active' : '' ?>" href="/album.php"><?= h(nav_label('album')) ?></a>
            <a class="<?= $active === 'memo' ? 'is-active' : '' ?>" href="/memo.php"><?= h(nav_label('memo')) ?></a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="/admin.php"><?= h(nav_label('admin')) ?></a>
                <a class="<?= $active === 'admin_cards' ? 'is-active' : '' ?>" href="/admin_cards.php"><?= h(nav_label('admin_cards')) ?></a>
                <a class="<?= $active === 'admin_photos' ? 'is-active' : '' ?>" href="/admin_photos.php"><?= h(nav_label('admin_photos')) ?></a>
                <a class="<?= $active === 'admin_memos' ? 'is-active' : '' ?>" href="/admin_memos.php"><?= h(nav_label('admin_memos')) ?></a>
                <a class="<?= $active === 'files' ? 'is-active' : '' ?>" href="/files.php"><?= h(nav_label('files')) ?></a>
                <a class="<?= $active === 'admin_settings' ? 'is-active' : '' ?>" href="/admin_settings.php"><?= h(nav_label('admin_settings')) ?></a>
            <?php else: ?>
                <a class="<?= $active === 'settings' ? 'is-active' : '' ?>" href="/settings.php"><?= h(nav_label('settings')) ?></a>
            <?php endif; ?>
        </nav>
        <div class="drawer-foot">
            <div>
                <strong><?= h($user['display_name']) ?></strong>
                <span>#<?= (int) $user['id'] ?> · <?= $user['role'] === 'admin' ? '管理员' : '用户' ?></span>
            </div>
            <a class="drawer-logout" href="/logout.php">退出</a>
        </div>
    </aside>
    <main class="wrap" id="page">
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
    <div class="lightbox" id="lightbox" hidden aria-modal="true" role="dialog" aria-label="照片预览">
        <figure class="lightbox-stage">
            <img id="lightbox-img" src="" alt="照片预览">
            <figcaption class="lightbox-cap">
                <span id="lightbox-info"></span>
                <a class="btn ghost-btn" id="lightbox-dl" href="" download>保存</a>
            </figcaption>
        </figure>
        <button class="lightbox-close" type="button" aria-label="关闭预览" data-lb-close>×</button>
        <button class="lightbox-nav lightbox-prev" type="button" aria-label="上一张" data-lb-prev>‹</button>
        <button class="lightbox-nav lightbox-next" type="button" aria-label="下一张" data-lb-next>›</button>
    </div>
    <script>
    (function () {
        var btn = document.querySelector('.menu-toggle');
        var closeBtn = document.querySelector('.drawer-close');
        var mask = document.querySelector('.nav-mask');
        var drawer = document.querySelector('.drawer');
        if (btn && mask && drawer && closeBtn) {
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
        }
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        document.querySelectorAll('.js-like').forEach(function (btnEl) {
            btnEl.addEventListener('click', function () {
                if (btnEl.disabled) return;
                btnEl.disabled = true;
                var photoId = btnEl.getAttribute('data-id');
                var fd = new FormData();
                fd.append('photo_id', photoId);
                fd.append('csrf', csrf);
                fetch('/like.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json().catch(function () { return null; }); })
                    .then(function (data) {
                        if (!data || !data.ok) throw new Error('fail');
                        btnEl.classList.toggle('is-liked', !!data.liked);
                        var cnt = btnEl.querySelector('.cnt');
                        if (cnt) cnt.textContent = String(data.count);
                        var lbl = btnEl.querySelector('.lbl');
                        if (lbl) lbl.textContent = data.liked ? '已喜欢' : '喜欢';
                    })
                    .catch(function () {
                        alert('操作失败，请刷新后重试。');
                    })
                    .then(function () { btnEl.disabled = false; });
            });
        });
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
        (function () {
            var slider = document.querySelector('[data-slider]');
            if (slider) {
                var track = slider.querySelector('.wall-track');
                var prev = slider.querySelector('[data-prev]');
                var next = slider.querySelector('[data-next]');
                if (track) {
                    function step(dir) {
                        var card = track.querySelector('.wall-slide');
                        var width = card ? card.getBoundingClientRect().width + 16 : track.clientWidth;
                        track.scrollBy({ left: dir * width, behavior: 'smooth' });
                    }
                    if (prev) prev.addEventListener('click', function () { step(-1); });
                    if (next) next.addEventListener('click', function () { step(1); });
                }
            }
        })();
        (function () {
            var box = document.querySelector('[data-viewbar]');
            if (!box) return;
            var modeBtns = box.querySelectorAll('[data-mode]');
            var orderBtn = box.querySelector('[data-order]');
            var targetId = box.getAttribute('data-target');
            var target = document.getElementById(targetId);
            var sliderBtns = target ? target.closest('[data-slider]') : null;
            function applyMode() {
                var mode = target.getAttribute('data-mode');
                var isGrid = mode === 'grid';
                if (sliderBtns) {
                    var p = sliderBtns.querySelector('[data-prev]');
                    var n = sliderBtns.querySelector('[data-next]');
                    if (p) p.style.display = isGrid ? 'none' : '';
                    if (n) n.style.display = isGrid ? 'none' : '';
                    var track2 = sliderBtns.querySelector('.wall-track');
                    if (track2) track2.style.overflowX = isGrid ? 'visible' : 'auto';
                }
            }
            if (modeBtns.length && target) {
                modeBtns.forEach(function (b) {
                    b.addEventListener('click', function () {
                        modeBtns.forEach(function (x) { x.classList.remove('on'); });
                        b.classList.add('on');
                        target.classList.toggle('masonry-wall', b.getAttribute('data-mode') === 'grid');
                        target.setAttribute('data-mode', b.getAttribute('data-mode'));
                        applyMode();
                        try { localStorage.setItem('wall_mode', b.getAttribute('data-mode')); } catch (e) {}
                    });
                });
            }
            if (orderBtn && target) {
                orderBtn.addEventListener('click', function () {
                    var dir = target.getAttribute('data-order') === 'asc' ? 'desc' : 'asc';
                    target.setAttribute('data-order', dir);
                    var slides = Array.prototype.slice.call(target.querySelectorAll('.wall-slide'));
                    slides.reverse();
                    slides.forEach(function (s) { target.appendChild(s); });
                    var label = orderBtn.querySelector('[data-label]');
                    if (label) label.textContent = dir === 'desc' ? '最新在前' : '最早在前';
                    try { localStorage.setItem('wall_order', dir); } catch (e) {}
                });
            }
            try {
                var savedMode = localStorage.getItem('wall_mode');
                if (savedMode === 'grid') {
                    modeBtns.forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-mode') === 'grid'); });
                    target.classList.add('masonry-wall');
                    target.setAttribute('data-mode', 'grid');
                    applyMode();
                }
                var savedOrder = localStorage.getItem('wall_order');
                if (savedOrder === 'asc' && target && target.getAttribute('data-order') === 'desc') {
                    var slides2 = Array.prototype.slice.call(target.querySelectorAll('.wall-slide'));
                    slides2.reverse();
                    slides2.forEach(function (s) { target.appendChild(s); });
                    target.setAttribute('data-order', 'asc');
                    var label2 = orderBtn.querySelector('[data-label]');
                    if (label2) label2.textContent = '最早在前';
                }
            } catch (e2) {}
        })();
        (function () {
            var lb = document.getElementById('lightbox');
            if (!lb) return;
            var imgEl = document.getElementById('lightbox-img');
            var infoEl = document.getElementById('lightbox-info');
            var dlEl = document.getElementById('lightbox-dl');
            var items = [];
            var current = -1;
            var touchX = 0;
            function srcOf(el) {
                return el.tagName === 'IMG' ? (el.getAttribute('data-full') || el.src) : el.getAttribute('href');
            }
            function collect() {
                items = [];
                document.querySelectorAll('[data-lightbox]').forEach(function (el) {
                    items.push({
                        src: srcOf(el),
                        caption: el.getAttribute('data-caption') || '',
                        dl: el.getAttribute('data-dl') || ''
                    });
                });
            }
            function show(i) {
                if (!items.length || i < 0 || i >= items.length) return;
                current = i;
                var it = items[i];
                imgEl.src = it.src;
                infoEl.textContent = it.caption ? it.caption + ('【' + (i + 1) + '/' + items.length + '】') : ((i + 1) + '/' + items.length);
                if (it.dl) {
                    dlEl.href = it.dl;
                    dlEl.style.display = '';
                } else {
                    dlEl.style.display = 'none';
                }
            }
            function next() { show(current + 1); }
            function prev() { show(current - 1); }
            function closeLb() {
                lb.hidden = true;
                document.body.classList.remove('modal-open');
                imgEl.src = '';
            }
            function openStart(el) {
                collect();
                var idx = -1;
                var src = srcOf(el);
                items.forEach(function (it, i) { if (it.src === src) idx = i; });
                if (idx < 0) return;
                current = idx;
                lb.hidden = false;
                document.body.classList.add('modal-open');
                show(idx);
            }
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-lightbox]');
                if (trigger) {
                    e.preventDefault();
                    openStart(trigger);
                }
            });
            var closeBtn = lb.querySelector('[data-lb-close]');
            if (closeBtn) closeBtn.addEventListener('click', closeLb);
            var prevBtn = lb.querySelector('[data-lb-prev]');
            var nextBtn = lb.querySelector('[data-lb-next]');
            if (prevBtn) prevBtn.addEventListener('click', function (e) { e.stopPropagation(); prev(); });
            if (nextBtn) nextBtn.addEventListener('click', function (e) { e.stopPropagation(); next(); });
            lb.addEventListener('click', function (e) {
                if (e.target === lb || e.target === imgEl) closeLb();
            });
            document.addEventListener('keydown', function (e) {
                if (lb.hidden) return;
                if (e.key === 'Escape') closeLb();
                if (e.key === 'ArrowRight') next();
                if (e.key === 'ArrowLeft') prev();
            });
            lb.addEventListener('touchstart', function (e) {
                touchX = e.touches[0].clientX;
            }, { passive: true });
            lb.addEventListener('touchend', function (e) {
                var dx = e.changedTouches[0].clientX - touchX;
                if (Math.abs(dx) > 40) {
                    if (dx < 0) next();
                    else prev();
                }
            }, { passive: true });
        })();
        (function () {
            var sy = 0, pulling = false, sup = false;
            function onTouchStart(e) {
                if (window.scrollY > 0) { sup = false; return; }
                sy = e.touches[0].clientY;
                pulling = true;
                sup = false;
            }
            function onTouchMove(e) {
                if (!pulling) return;
                var dy = e.touches[0].clientY - sy;
                if (dy > 70 && window.scrollY <= 0 && !sup) {
                    sup = true;
                    try { sessionStorage.setItem('pr', '1'); } catch (err) {}
                    location.reload();
                }
            }
            function onTouchEnd() {
                pulling = false;
                sup = false;
            }
            var html = document.documentElement;
            if (window.matchMedia('(pointer: coarse)').matches && 'ontouchstart' in window) {
                html.addEventListener('touchstart', onTouchStart, { passive: true });
                html.addEventListener('touchmove', onTouchMove, { passive: true });
                html.addEventListener('touchend', onTouchEnd, { passive: true });
            }
        })();
    })();
    </script>
</body>
</html>
    <?php
}