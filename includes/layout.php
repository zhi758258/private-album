<?php
declare(strict_types=1);

function render_header(string $title, array $user, string $active = ''): void
{
    $flash = flash_get();
    $isAdmin = $user['role'] === 'admin';
    $friendUnread = friend_unread_count((int) $user['id']);

    $navIcons = [
        'home' => '<path d="M3 12 12 4l9 8M5 11v9h14v-9"/>',
        'board' => '<path d="M4 5h16v11H8l-4 4z"/><path d="M8 10h8M8 13h5"/>',
        'space' => '<path d="M4 5h16v15H4z"/><circle cx="12" cy="10" r="2.6"/><path d="M7.5 17a4.5 4.5 0 0 1 9 0"/>',
        'friends' => '<path d="M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 11a3 3 0 1 0 0-6"/><path d="M14.8 20a5.2 5.2 0 0 1 5.7-5.1"/>',
        'album' => '<path d="M4 5h16v14H4z"/><path d="M4 16l5-5 3 3 4-4 4 4"/><circle cx="8.5" cy="9" r="1.4"/>',
        'memo' => '<path d="M6 3h12v18H6z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'ddz' => '<path d="M4 6h11v13H4z"/><path d="M15 9l5-3v12l-5-3z"/><path d="M8 10l1 1-1 1"/>',
        'admin' => '<path d="M9 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/><path d="M4 20a5 5 0 0 1 10 0"/><path d="M16 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/><path d="M14.5 20a5.5 5.5 0 0 1 6.5-5.2"/>',
        'admin_cards' => '<path d="M4 5h16v14H4z"/><path d="M4 9h16M4 15h16"/>',
        'admin_photos' => '<path d="M4 5h16v14H4z"/><path d="M4 16l5-5 3 3 4-4 4 4"/><circle cx="8.5" cy="9" r="1.4"/>',
        'admin_memos' => '<path d="M6 3h9l5 5v13H6z"/><path d="M14 3v6h6"/>',
        'files' => '<path d="M4 5h5l2 2h9v12H4z"/><path d="M4 9h16"/>',
        'admin_settings' => '<path d="M4 8h7M4 14h16"/><circle cx="14" cy="8" r="2"/><circle cx="9" cy="14" r="2"/>',
        'settings' => '<path d="M4 8h7M4 14h16"/><circle cx="14" cy="8" r="2"/><circle cx="9" cy="14" r="2"/>',
    ];
    $baseNav = [
        ['key' => 'home', 'href' => '/index.php'],
        ['key' => 'board', 'href' => '/board.php'],
        ['key' => 'space', 'href' => '/space.php'],
        ['key' => 'friends', 'href' => '/friends.php'],
        ['key' => 'album', 'href' => '/album.php'],
        ['key' => 'memo', 'href' => '/memo.php'],
        ['key' => 'ddz', 'href' => '/ddz.php'],
    ];
    $adminNav = [
        ['key' => 'admin', 'href' => '/admin.php'],
        ['key' => 'admin_cards', 'href' => '/admin_cards.php'],
        ['key' => 'admin_photos', 'href' => '/admin_photos.php'],
        ['key' => 'admin_memos', 'href' => '/admin_memos.php'],
        ['key' => 'files', 'href' => '/files.php'],
        ['key' => 'admin_settings', 'href' => '/admin_settings.php'],
    ];
    $userNav = [['key' => 'settings', 'href' => '/settings.php']];
    $navItems = $isAdmin ? array_merge($baseNav, $adminNav) : array_merge($baseNav, $userNav);

    $themes = [
        'light-blue' => ['name' => '晴空蓝', 'gradient' => 'linear-gradient(135deg,#2563eb,#0f766e)'],
        'dark-blue' => ['name' => '深海蓝', 'gradient' => 'linear-gradient(135deg,#60a5fa,#2dd4bf)'],
        'light-pink' => ['name' => '樱花粉', 'gradient' => 'linear-gradient(135deg,#f472b6,#ec4899)'],
        'light-green' => ['name' => '清新绿', 'gradient' => 'linear-gradient(135deg,#4ade80,#22c55e)'],
        'dark-purple' => ['name' => '紫罗兰', 'gradient' => 'linear-gradient(135deg,#c084fc,#a855f7)'],
        'dark-orange' => ['name' => '暖阳橙', 'gradient' => 'linear-gradient(135deg,#fbbf24,#f59e0b)'],
        'dark-teal' => ['name' => '青空青', 'gradient' => 'linear-gradient(135deg,#22d3ee,#06b6d4)'],
        'dark-red' => ['name' => '绯红夜', 'gradient' => 'linear-gradient(135deg,#fb7185,#f43f5e)'],
    ];
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1">
    <title><?= h($title) ?> · <?= h(app_name()) ?></title>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script src="/assets/js/emoji.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/emoji.js') ?>" defer></script>
    <script src="/assets/js/location.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/location.js') ?>" defer></script>
</head>
<body>
    <div class="grain"></div>
    <div class="app-shell">
        <div class="nav-mask" hidden></div>
        <aside class="drawer" aria-hidden="true">
            <div class="drawer-head">
                <div class="drawer-user">
                    <button class="drawer-avatar js-avatar-edit" type="button" title="设置头像" aria-label="设置头像">
                        <?= avatar_html($user['avatar'] ?? null, $user['display_name'], 'avatar avatar-lg') ?>
                    </button>
                    <div class="drawer-user-text">
                        <div class="drawer-title"><?= h(app_name()) ?></div>
                        <div class="drawer-sub"><?= h($user['display_name']) ?></div>
                    </div>
                </div>
                <button class="drawer-close" type="button" aria-label="关闭菜单">
                    <svg viewBox="0 0 24 24" class="nav-icon"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="sidebar-divider"></div>
            <nav class="drawer-nav" aria-label="主导航">
                <?php foreach ($navItems as $it):
                    $k = $it['key']; ?>
                    <a class="nav-item <?= $active === $k ? 'is-active' : '' ?>" href="<?= h($it['href']) ?>">
                        <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><?= $navIcons[$k] ?? '' ?></svg>
                        <span class="nav-label"><?= h(nav_label($k)) ?></span>
                        <?php if ($k === 'friends' && $friendUnread > 0): ?>
                            <span class="nav-badge"><?= (int) $friendUnread ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-user">
                <button class="sidebar-user-avatar js-avatar-edit" type="button" title="设置头像" aria-label="设置头像">
                    <?= avatar_html($user['avatar'] ?? null, $user['display_name'], 'avatar') ?>
                </button>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= h($user['display_name']) ?></div>
                    <div class="sidebar-user-role"><?= $isAdmin ? '管理员' : '用户' ?></div>
                </div>
                <a class="sidebar-logout" href="/logout.php" title="退出登录" aria-label="退出登录">
                    <svg viewBox="0 0 24 24" class="nav-icon"><path d="M14 5h5v14h-5M10 12h9M13 9l3 3-3 3"/></svg>
                </a>
            </div>
            <div class="sidebar-foot">
                <button class="theme-toggle" type="button" aria-label="切换主题" title="主题设置">
                    <svg viewBox="0 0 24 24" class="nav-icon"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z"/><path d="M12 7v10M7 12h10"/></svg>
                </button>
                <div class="theme-panel" id="theme-panel" hidden>
                    <?php foreach ($themes as $tk => $t): ?>
                        <button class="theme-chip" type="button" data-theme="<?= h($tk) ?>" style="--chip-bg:<?= $t['gradient'] ?>">
                            <span class="theme-chip-name"><?= h($t['name']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
        <div class="main-col">
            <header class="topbar">
                <button class="menu-toggle" type="button" aria-label="打开菜单" aria-expanded="false">
                    <svg viewBox="0 0 24 24" class="nav-icon"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <a class="brand" href="/index.php">
                    <span class="brand-mark"></span>
                    <span class="brand-name"><?= h(app_name()) ?></span>
                </a>
                <div class="topbar-actions">
                    <div class="notif" id="notif">
                        <button class="notif-toggle" type="button" aria-label="消息提醒" aria-expanded="false">
                            <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><path d="M18 16V11a6 6 0 1 0-12 0v5l-2 2h16z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
                            <span class="notif-dot" id="notifDot" hidden></span>
                        </button>
                        <div class="notif-panel" id="notifPanel" hidden>
                            <div class="notif-head">
                                <span>消息提醒</span>
                                <div class="notif-head-actions">
                                    <button type="button" class="notif-sound" id="notifSound" aria-pressed="true">声音：开</button>
                                    <button type="button" class="notif-readall" id="notifReadAll">全部已读</button>
                                </div>
                            </div>
                            <div class="notif-list" id="notifList">
                                <div class="notif-empty">暂无新提醒</div>
                            </div>
                        </div>
                    </div>
                    <button class="topbar-avatar js-avatar-edit" type="button" title="设置头像" aria-label="设置头像">
                        <?= avatar_html($user['avatar'] ?? null, $user['display_name'], 'avatar avatar-top') ?>
                    </button>
                </div>
            </header>
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
        </div>
    </div>
    <footer class="foot"></footer>
    <?= notice_markup() ?>
    <div class="user-mask" id="user-card" hidden>
        <div class="user-card" role="dialog" aria-modal="true" aria-label="用户资料">
            <button type="button" class="user-card-close" data-uc-close aria-label="关闭">×</button>
            <div class="user-card-avatar" id="uc-avatar"></div>
            <div class="user-card-name" id="uc-name"></div>
            <div class="user-card-sub" id="uc-sub"></div>
            <div class="user-card-actions" id="uc-actions"></div>
        </div>
    </div>
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
    <div class="crop-mask" id="avatar-crop" hidden>
        <div class="crop-box" role="dialog" aria-modal="true" aria-label="设置头像">
            <h2>设置头像</h2>
            <div class="crop-stage">
                <canvas id="crop-canvas" width="560" height="560"></canvas>
            </div>
            <p class="crop-tip">拖动调整位置 · 滚轮或双指缩放</p>
            <div class="confirm-actions">
                <button type="button" class="btn ghost-btn" id="crop-cancel">取消</button>
                <button type="button" class="btn" id="crop-save">使用这张头像</button>
            </div>
        </div>
    </div>
    <aside class="uprog" id="upload-progress" hidden aria-live="polite">
        <div class="uprog-label"></div>
        <div class="uprog-bar"><div class="uprog-fill"></div></div>
        <div class="uprog-row">
            <span class="uprog-state">上传中…</span>
            <span class="uprog-pct">0%</span>
        </div>
        <div class="uprog-row is-dim">
            <span class="uprog-size">—</span>
            <span class="uprog-speed">—</span>
        </div>
    </aside>
    <input type="file" id="avatar-file" accept="image/jpeg,image/png" hidden>
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
            var wrap = document.querySelector('.wrap');
            function pageTop() {
                if (wrap) return wrap.scrollTop;
                return window.scrollY || document.documentElement.scrollTop || 0;
            }
            function insideEmoji(el) {
                return !!(el && el.closest && el.closest('.emoji-panel, .emoji-grid, .emoji-item, .emoji-bar'));
            }
            function onTouchStart(e) {
                if (insideEmoji(e.target)) { pulling = false; sup = false; return; }
                if (pageTop() > 0) { pulling = false; sup = false; return; }
                sy = e.touches[0].clientY;
                pulling = true;
                sup = false;
            }
            function onTouchMove(e) {
                if (!pulling) return;
                if (insideEmoji(e.target)) { pulling = false; return; }
                var dy = e.touches[0].clientY - sy;
                if (dy > 70 && pageTop() <= 0 && !sup) {
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
        (function () {
            var uprog = document.getElementById('upload-progress');
            if (!uprog || !window.XMLHttpRequest || !window.FormData) return;
            var fillEl = uprog.querySelector('.uprog-fill');
            var pctEl = uprog.querySelector('.uprog-pct');
            var speedEl = uprog.querySelector('.uprog-speed');
            var sizeEl = uprog.querySelector('.uprog-size');
            var labelEl = uprog.querySelector('.uprog-label');
            var stateEl = uprog.querySelector('.uprog-state');
            function fmtBytes(n) {
                var units = ['B', 'KB', 'MB', 'GB'];
                var i = 0;
                var v = n;
                while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
                return (i === 0 ? Math.round(v) : (Math.round(v * 10) / 10)) + ' ' + units[i];
            }
            function showProgress(label) {
                var samples = [];
                var finished = false;
                uprog.classList.remove('is-ok', 'is-err');
                uprog.hidden = false;
                labelEl.textContent = label;
                stateEl.textContent = '上传中…';
                pctEl.textContent = '0%';
                speedEl.textContent = '—';
                sizeEl.textContent = '—';
                fillEl.style.width = '0%';
                function update(loaded, total) {
                    if (finished) return;
                    var now = Date.now();
                    samples.push({ t: now, b: loaded });
                    while (samples.length > 1 && now - samples[0].t > 1200) samples.shift();
                    var pct = total > 0 ? Math.min(100, Math.round((loaded / total) * 100)) : 0;
                    fillEl.style.width = pct + '%';
                    pctEl.textContent = pct + '%';
                    sizeEl.textContent = fmtBytes(loaded) + (total > 0 ? ' / ' + fmtBytes(total) : '');
                    if (samples.length > 1) {
                        var a = samples[0];
                        var b = samples[samples.length - 1];
                        var dt = (b.t - a.t) / 1000;
                        if (dt > 0.2) speedEl.textContent = fmtBytes((b.b - a.b) / dt) + '/s';
                    }
                }
                function done(msg) {
                    if (finished) return;
                    finished = true;
                    fillEl.style.width = '100%';
                    pctEl.textContent = '100%';
                    stateEl.textContent = msg || '上传完成';
                    uprog.classList.add('is-ok');
                }
                function fail(msg) {
                    if (finished) return;
                    finished = true;
                    stateEl.textContent = msg || '上传失败';
                    uprog.classList.add('is-err');
                    setTimeout(function () { uprog.hidden = true; }, 2600);
                }
                return { update: update, done: done, fail: fail };
            }
            document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    var fileInput = form.querySelector('input[type="file"]');
                    if (fileInput && (!fileInput.files || fileInput.files.length === 0)) return;
                    e.preventDefault();
                    var fd = new FormData(form);
                    var label = '正在上传';
                    if (fileInput && fileInput.files && fileInput.files.length === 1) {
                        label = fileInput.files[0].name || '正在上传';
                    } else if (fileInput && fileInput.files && fileInput.files.length > 1) {
                        label = fileInput.files.length + ' 个文件';
                    }
                    var prog = showProgress(label);
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', form.getAttribute('action') || window.location.href);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.upload.addEventListener('progress', function (ev) {
                        if (ev.lengthComputable) prog.update(ev.loaded, ev.total);
                    });
                    xhr.addEventListener('load', function () {
                        var data = null;
                        try { data = JSON.parse(xhr.responseText); } catch (err) { data = null; }
                        if (xhr.status === 200 && data && data.ok && data.redirect) {
                            prog.done('上传完成，正在跳转…');
                            setTimeout(function () { window.location.href = data.redirect; }, 500);
                        } else {
                            prog.fail('上传失败，请重试');
                        }
                    });
                    xhr.addEventListener('error', function () {
                        prog.fail('网络错误，请重试');
                    });
                    xhr.send(fd);
                });
            });
            var cropBox = document.getElementById('avatar-crop');
            var fileInput = document.getElementById('avatar-file');
            var canvas = document.getElementById('crop-canvas');
            var saveBtn = document.getElementById('crop-save');
            var cancelBtn = document.getElementById('crop-cancel');
            if (!cropBox || !fileInput || !canvas || !canvas.getContext || !saveBtn || !cancelBtn || !canvas.toBlob) return;
            var ctx = canvas.getContext('2d');
            var STAGE = canvas.width;
            var OUT = 320;
            var img = null;
            var scale = 1;
            var ox = 0;
            var oy = 0;
            var pointers = {};
            var lastDist = 0;
            var lastMid = null;
            var objectUrl = '';
            function clampState() {
                if (!img) return;
                var minScale = Math.max(STAGE / img.width, STAGE / img.height);
                if (scale < minScale) scale = minScale;
                var dw = img.width * scale;
                var dh = img.height * scale;
                if (dw <= STAGE) ox = (STAGE - dw) / 2;
                else ox = Math.max(STAGE - dw, Math.min(0, ox));
                if (dh <= STAGE) oy = (STAGE - dh) / 2;
                else oy = Math.max(STAGE - dh, Math.min(0, oy));
            }
            function draw() {
                if (!img) return;
                ctx.fillStyle = '#0d0b09';
                ctx.fillRect(0, 0, STAGE, STAGE);
                ctx.drawImage(img, ox, oy, img.width * scale, img.height * scale);
                ctx.save();
                ctx.beginPath();
                ctx.rect(0, 0, STAGE, STAGE);
                ctx.arc(STAGE / 2, STAGE / 2, STAGE / 2 - 16, 0, Math.PI * 2, true);
                ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
                ctx.fill('evenodd');
                ctx.restore();
                ctx.beginPath();
                ctx.arc(STAGE / 2, STAGE / 2, STAGE / 2 - 16, 0, Math.PI * 2);
                ctx.lineWidth = 3;
                ctx.strokeStyle = 'rgba(212, 165, 116, 0.85)';
                ctx.stroke();
            }
            function zoomAt(px, py, factor) {
                if (!img) return;
                var ix = (px - ox) / scale;
                var iy = (py - oy) / scale;
                scale = scale * factor;
                clampState();
                ox = px - ix * scale;
                oy = py - iy * scale;
                clampState();
                draw();
            }
            function canvasPos(e) {
                var rect = canvas.getBoundingClientRect();
                var k = STAGE / Math.max(1, rect.width);
                return { x: (e.clientX - rect.left) * k, y: (e.clientY - rect.top) * k };
            }
            function pointerList() {
                var list = [];
                for (var id in pointers) {
                    if (Object.prototype.hasOwnProperty.call(pointers, id)) list.push(pointers[id]);
                }
                return list;
            }
            canvas.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
                pointers[e.pointerId] = canvasPos(e);
                if (pointerList().length === 2) {
                    var ps = pointerList();
                    lastDist = Math.hypot(ps[0].x - ps[1].x, ps[0].y - ps[1].y);
                    lastMid = { x: (ps[0].x + ps[1].x) / 2, y: (ps[0].y + ps[1].y) / 2 };
                }
            });
            canvas.addEventListener('pointermove', function (e) {
                if (!pointers[e.pointerId] || !img) return;
                var p = canvasPos(e);
                var prev = pointers[e.pointerId];
                pointers[e.pointerId] = p;
                var ps = pointerList();
                if (ps.length >= 2) {
                    var dist = Math.hypot(ps[0].x - ps[1].x, ps[0].y - ps[1].y);
                    var mid = { x: (ps[0].x + ps[1].x) / 2, y: (ps[0].y + ps[1].y) / 2 };
                    if (lastDist > 0 && dist > 0) zoomAt(mid.x, mid.y, dist / lastDist);
                    if (lastMid) {
                        ox += mid.x - lastMid.x;
                        oy += mid.y - lastMid.y;
                        clampState();
                        draw();
                    }
                    lastDist = dist;
                    lastMid = mid;
                } else {
                    ox += p.x - prev.x;
                    oy += p.y - prev.y;
                    clampState();
                    draw();
                }
            });
            function releasePointer(e) {
                delete pointers[e.pointerId];
                if (pointerList().length < 2) {
                    lastDist = 0;
                    lastMid = null;
                }
            }
            canvas.addEventListener('pointerup', releasePointer);
            canvas.addEventListener('pointercancel', releasePointer);
            canvas.addEventListener('wheel', function (e) {
                e.preventDefault();
                var p = canvasPos(e);
                zoomAt(p.x, p.y, e.deltaY < 0 ? 1.12 : 1 / 1.12);
            }, { passive: false });
            function closeCrop() {
                cropBox.hidden = true;
                document.body.classList.remove('modal-open');
                img = null;
                saveBtn.disabled = false;
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = '';
                }
            }
            document.addEventListener('click', function (e) {
                if (!e.target || !e.target.closest) return;
                if (!e.target.closest('.js-avatar-edit')) return;
                e.preventDefault();
                fileInput.value = '';
                fileInput.click();
            });
            fileInput.addEventListener('change', function () {
                var f = fileInput.files && fileInput.files[0];
                if (!f) return;
                if (f.type !== 'image/jpeg' && f.type !== 'image/png') {
                    alert('请选择 jpg 或 png 图片。');
                    return;
                }
                if (f.size > 5 * 1024 * 1024) {
                    alert('图片不能超过 5MB。');
                    return;
                }
                var im = new Image();
                objectUrl = URL.createObjectURL(f);
                im.onload = function () {
                    img = im;
                    scale = Math.max(STAGE / im.width, STAGE / im.height);
                    ox = (STAGE - im.width * scale) / 2;
                    oy = (STAGE - im.height * scale) / 2;
                    cropBox.hidden = false;
                    document.body.classList.add('modal-open');
                    draw();
                };
                im.onerror = function () {
                    alert('图片读取失败，请换一张试试。');
                    closeCrop();
                };
                im.src = objectUrl;
            });
            cancelBtn.addEventListener('click', closeCrop);
            cropBox.addEventListener('click', function (e) {
                if (e.target === cropBox) closeCrop();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !cropBox.hidden) closeCrop();
            });
            saveBtn.addEventListener('click', function () {
                if (!img) return;
                saveBtn.disabled = true;
                var out = document.createElement('canvas');
                out.width = OUT;
                out.height = OUT;
                var octx = out.getContext('2d');
                octx.fillStyle = '#0d0b09';
                octx.fillRect(0, 0, OUT, OUT);
                octx.drawImage(img, -ox / scale, -oy / scale, STAGE / scale, STAGE / scale, 0, 0, OUT, OUT);
                out.toBlob(function (blob) {
                    if (!blob) {
                        alert('生成头像失败，请重试。');
                        saveBtn.disabled = false;
                        return;
                    }
                    var fd = new FormData();
                    fd.append('avatar', blob, 'avatar.jpg');
                    fd.append('csrf', csrf);
                    var prog = showProgress('新头像');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '/avatar_upload.php');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.upload.addEventListener('progress', function (ev) {
                        if (ev.lengthComputable) prog.update(ev.loaded, ev.total);
                    });
                    xhr.addEventListener('load', function () {
                        var data = null;
                        try { data = JSON.parse(xhr.responseText); } catch (err) { data = null; }
                        if (xhr.status === 200 && data && data.ok) {
                            closeCrop();
                            prog.done('头像已更新，正在刷新…');
                            setTimeout(function () { window.location.reload(); }, 600);
                        } else {
                            prog.fail((data && data.error) || '上传失败，请重试');
                            saveBtn.disabled = false;
                        }
                    });
                    xhr.addEventListener('error', function () {
                        prog.fail('网络错误，请重试');
                        saveBtn.disabled = false;
                    });
                    xhr.send(fd);
                }, 'image/jpeg', 0.88);
            });
        })();
    })();
    (function () {
        var themes = {
            'light-blue': { dark: false, bg: '#f6f8fb', text: '#172033', primary: '#2563eb', secondary: '#0f766e', gradient: 'linear-gradient(135deg,#2563eb 0%,#0f766e 100%)' },
            'dark-blue': { dark: true, bg: '#0b1020', text: '#e5e7eb', primary: '#60a5fa', secondary: '#2dd4bf', gradient: 'linear-gradient(135deg,#60a5fa 0%,#2dd4bf 100%)' },
            'light-pink': { dark: false, bg: '#fff0f5', text: '#831843', primary: '#ec4899', secondary: '#be185d', gradient: 'linear-gradient(135deg,#f472b6 0%,#ec4899 100%)' },
            'light-green': { dark: false, bg: '#f0fdf4', text: '#14532d', primary: '#22c55e', secondary: '#16a34a', gradient: 'linear-gradient(135deg,#4ade80 0%,#22c55e 100%)' },
            'dark-purple': { dark: true, bg: '#1e1b4b', text: '#e9d5ff', primary: '#a855f7', secondary: '#9333ea', gradient: 'linear-gradient(135deg,#c084fc 0%,#a855f7 100%)' },
            'dark-orange': { dark: true, bg: '#292524', text: '#fef3c7', primary: '#f59e0b', secondary: '#d97706', gradient: 'linear-gradient(135deg,#fbbf24 0%,#f59e0b 100%)' },
            'dark-teal': { dark: true, bg: '#134e4a', text: '#ccfbf1', primary: '#06b6d4', secondary: '#0891b2', gradient: 'linear-gradient(135deg,#22d3ee 0%,#06b6d4 100%)' },
            'dark-red': { dark: true, bg: '#18181b', text: '#fda4af', primary: '#f43f5e', secondary: '#e11d48', gradient: 'linear-gradient(135deg,#fb7185 0%,#f43f5e 100%)' }
        };
        function applyTheme(t) {
            var c = themes[t];
            if (!c) return;
            var r = document.documentElement.style;
            r.setProperty('--theme-bg', c.bg);
            r.setProperty('--theme-text', c.text);
            r.setProperty('--theme-primary', c.primary);
            r.setProperty('--theme-secondary', c.secondary);
            r.setProperty('--theme-gradient', c.gradient);
            document.documentElement.classList.toggle('dark', !!c.dark);
            try { localStorage.setItem('ui_theme', t); } catch (e) {}
            document.querySelectorAll('.theme-chip').forEach(function (b) {
                b.classList.toggle('is-current', b.getAttribute('data-theme') === t);
            });
        }
        var saved = 'light-blue';
        try { saved = localStorage.getItem('ui_theme') || saved; } catch (e) {}
        if (!themes[saved]) saved = 'light-blue';
        applyTheme(saved);
        var toggle = document.querySelector('.theme-toggle');
        var panel = document.getElementById('theme-panel');
        if (toggle && panel) {
            toggle.addEventListener('click', function (e) { e.stopPropagation(); panel.hidden = !panel.hidden; });
            document.addEventListener('click', function (e) {
                if (!panel.hidden && !panel.contains(e.target) && e.target !== toggle) panel.hidden = true;
            });
        }
        document.querySelectorAll('.theme-chip').forEach(function (b) {
            b.addEventListener('click', function () {
                applyTheme(b.getAttribute('data-theme'));
                if (panel) panel.hidden = true;
            });
        });
    })();
    </script>
    <script>
    (function () {
        var mask = document.getElementById('user-card');
        if (!mask) return;
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var avatarEl = document.getElementById('uc-avatar');
        var nameEl = document.getElementById('uc-name');
        var subEl = document.getElementById('uc-sub');
        var actionsEl = document.getElementById('uc-actions');

        function close() {
            mask.hidden = true;
            document.body.classList.remove('modal-open');
        }
        function makeBtn(label, cls, onClick) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn' + (cls ? ' ' + cls : '');
            b.textContent = label;
            b.addEventListener('click', onClick);
            return b;
        }
        function tag(label, cls) {
            var s = document.createElement('span');
            s.className = 'tag' + (cls ? ' ' + cls : '');
            s.textContent = label;
            return s;
        }
        function renderActions(data) {
            actionsEl.innerHTML = '';
            if (data.state === 'self') {
                actionsEl.appendChild(makeBtn('我的空间', '', function () {
                    window.location.href = '/space.php';
                }));
                return;
            }
            if (data.state === 'friend') {
                actionsEl.appendChild(makeBtn('逛空间', '', function () {
                    window.location.href = '/space.php?u=' + data.id;
                }));
                actionsEl.appendChild(makeBtn('发消息', '', function () {
                    window.location.href = '/chat.php?u=' + data.id;
                }));
                actionsEl.appendChild(makeBtn('删除好友', 'ghost-btn', function () { doAction('remove', data); }));
                return;
            }
            if (data.state === 'sent') {
                actionsEl.appendChild(tag('好友请求已发送'));
                return;
            }
            if (data.state === 'incoming') {
                actionsEl.appendChild(makeBtn('接受好友请求', '', function () { doAction('accept', data); }));
                return;
            }
            actionsEl.appendChild(makeBtn('加好友', '', function () { doAction('request', data); }));
        }
        function doAction(action, data) {
            actionsEl.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            var fd = new FormData();
            fd.append('action', action);
            fd.append('user_id', data.id);
            fd.append('csrf', csrf);
            fetch('/friend_action.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) {
                    if (!d || !d.ok) {
                        alert((d && d.error) || '操作失败，请重试。');
                        actionsEl.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                        return;
                    }
                    data.state = d.state;
                    renderActions(data);
                })
                .catch(function () {
                    alert('操作失败，请重试。');
                    actionsEl.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                });
        }
        function show(data) {
            avatarEl.innerHTML = data.avatar_html;
            nameEl.textContent = data.display_name;
            subEl.textContent = '@' + data.username;
            renderActions(data);
        }
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var trigger = e.target.closest('.js-user');
            if (!trigger) return;
            e.preventDefault();
            var id = trigger.getAttribute('data-user-id');
            if (!id) return;
            mask.hidden = false;
            document.body.classList.add('modal-open');
            avatarEl.innerHTML = '';
            nameEl.textContent = '加载中…';
            subEl.textContent = '';
            actionsEl.innerHTML = '';
            fetch('/user_info.php?u=' + encodeURIComponent(id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) {
                    if (!d || !d.ok) { alert((d && d.error) || '加载失败。'); close(); return; }
                    show(d);
                })
                .catch(function () { alert('加载失败，请重试。'); close(); });
        });
        mask.addEventListener('click', function (e) { if (e.target === mask) close(); });
        var cb = mask.querySelector('[data-uc-close]');
        if (cb) cb.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !mask.hidden) close();
        });
    })();
    </script>
    <script>
    (function () {
        var root = document.getElementById('notif');
        if (!root) return;
        var toggle = root.querySelector('.notif-toggle');
        var panel = document.getElementById('notifPanel');
        var dot = document.getElementById('notifDot');
        var list = document.getElementById('notifList');
        var readAll = document.getElementById('notifReadAll');
        if (!toggle || !panel || !list) return;
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var open = false;
        var loaded = false;
        var lastUnread = null;
        var soundBtn = document.getElementById('notifSound');
        var soundOn = true;
        try { soundOn = localStorage.getItem('notif_sound') !== '0'; } catch (e) {}
        if (soundBtn) soundBtn.textContent = soundOn ? '声音：开' : '声音：关';
        if (soundBtn) soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');

        var audioCtx = null;
        function ensureAudio() {
            if (!audioCtx) {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return null;
                try { audioCtx = new Ctx(); } catch (e) { return null; }
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume().catch(function () {});
            }
            return audioCtx;
        }
        function tone(ctx, freq, start, dur, peak) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(peak, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(start);
            osc.stop(start + dur + 0.02);
        }
        function playDing() {
            if (!soundOn) return;
            var ctx = ensureAudio();
            if (!ctx) return;
            var t = ctx.currentTime;
            tone(ctx, 987.77, t, 0.18, 0.18);
            tone(ctx, 659.25, t + 0.16, 0.26, 0.16);
        }
        window.playDing = playDing;
        document.addEventListener('pointerdown', function once() {
            ensureAudio();
            document.removeEventListener('pointerdown', once);
        }, { once: true });
        if (soundBtn) {
            soundBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                soundOn = !soundOn;
                try { localStorage.setItem('notif_sound', soundOn ? '1' : '0'); } catch (err) {}
                soundBtn.textContent = soundOn ? '声音：开' : '声音：关';
                soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
                if (soundOn) playDing();
            });
        }

        function setDot(n) {
            if (!dot) return;
            if (n > 0) {
                dot.hidden = false;
                dot.textContent = n > 99 ? '99+' : String(n);
            } else {
                dot.hidden = true;
                dot.textContent = '';
            }
        }
        function handleUnread(n) {
            setDot(n);
            if (lastUnread !== null && n > lastUnread) playDing();
            lastUnread = n;
        }
        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : s;
            return d.innerHTML;
        }
        function renderItems(items) {
            list.innerHTML = '';
            if (!items || !items.length) {
                var e = document.createElement('div');
                e.className = 'notif-empty';
                e.textContent = '暂无新提醒';
                list.appendChild(e);
                return;
            }
            items.forEach(function (it) {
                var row = document.createElement('a');
                row.className = 'notif-item' + (it.is_read ? '' : ' is-unread');
                row.href = it.link || 'javascript:void(0)';
                row.setAttribute('data-id', String(it.id));
                var av = document.createElement('span');
                av.className = 'notif-avatar';
                av.innerHTML = it.actor_avatar_html || '';
                var body = document.createElement('span');
                body.className = 'notif-body';
                var txt = document.createElement('span');
                txt.className = 'notif-text';
                txt.innerHTML = esc(it.content);
                var time = document.createElement('span');
                time.className = 'notif-time';
                time.textContent = it.created_at;
                body.appendChild(txt);
                body.appendChild(time);
                row.appendChild(av);
                row.appendChild(body);
                row.addEventListener('click', function () {
                    markRead(it.id);
                });
                list.appendChild(row);
            });
        }
        function fetchList() {
            fetch('/notifications_api.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) {
                    if (!d || !d.ok) return;
                    handleUnread(d.unread);
                    renderItems(d.items);
                })
                .catch(function () {});
        }
        function poll() {
            fetch('/notifications_api.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) {
                    if (!d || !d.ok) return;
                    handleUnread(d.unread);
                    if (open) renderItems(d.items);
                })
                .catch(function () {});
        }
        function markRead(id) {
            var fd = new FormData();
            fd.append('action', 'read');
            fd.append('id', id);
            fd.append('csrf', csrf);
            fetch('/notifications_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) { if (d && d.ok) handleUnread(d.unread); })
                .catch(function () {});
        }
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            open = !open;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && !loaded) {
                loaded = true;
                fetchList();
            } else if (open) {
                fetchList();
            }
        });
        if (readAll) {
            readAll.addEventListener('click', function (e) {
                e.stopPropagation();
                var fd = new FormData();
                fd.append('action', 'read_all');
                fd.append('csrf', csrf);
                fetch('/notifications_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json().catch(function () { return null; }); })
                    .then(function (d) {
                        if (!d || !d.ok) return;
                        setDot(0);
                        lastUnread = 0;
                        list.querySelectorAll('.notif-item').forEach(function (el) { el.classList.remove('is-unread'); });
                    })
                    .catch(function () {});
            });
        }
        document.addEventListener('click', function (e) {
            if (!open) return;
            if (root.contains(e.target)) return;
            open = false;
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        });
        poll();
        setInterval(poll, 12000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    })();
    </script>
</body>
</html>
    <?php
}