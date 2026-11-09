(function () {
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function nl(s) {
        return esc(s).replace(/\n/g, '<br>');
    }
    function isHttps(url) {
        if (!url || url.length > 480 || url.length < 12) return false;
        if (/[\s<>"']/.test(url)) return false;
        try {
            var u = new URL(url);
            if (u.protocol !== 'https:') return false;
            if (!u.hostname || u.hostname.indexOf('.') < 0) return false;
            if (u.hostname === 'localhost') return false;
            if (/^\d{1,3}(\.\d{1,3}){3}$/.test(u.hostname)) return false;
            return true;
        } catch (e) {
            return false;
        }
    }
    var IMG_RE = /^https:\/\/[^\s<>"'\[\]]+\.(?:[gG][iI][fF]|[pP][nN][gG]|[jJ][pP][eE]?[gG]|[wW][eE][bB][pP])(?:\?[^\s<>"'\[\]]*)?$/;
    function looksImageUrl(url) {
        return IMG_RE.test(url);
    }
    function faceHtml(item) {
        var name = esc(item.name || '');
        var src = esc(item.src || '');
        var cls = item.kind === 'sticker' ? 'emoji-sticker' : 'emoji-face';
        return '<img class="' + cls + '" src="' + src + '" alt="' + name + '" title="' + name + '" loading="lazy">';
    }
    function render(text) {
        var src = String(text || '');
        var re = /(\[(?:qq|wx|dy):[^\[\]]{1,32}\]|\[net:https:\/\/[^\[\s<>"']{8,480}\]|https:\/\/[^\s<>"'\[\]]+?\.(?:[gG][iI][fF]|[pP][nN][gG]|[jJ][pP][eE]?[gG]|[wW][eE][bB][pP])(?:\?[^\s<>"'\[\]]*)?)/g;
        var out = '';
        var last = 0;
        var m;
        while ((m = re.exec(src))) {
            if (m.index > last) out += nl(src.slice(last, m.index));
            var tok = m[0];
            var pack = tok.match(/^\[(qq|wx|dy):([^\[\]]{1,32})\]$/);
            var net = tok.match(/^\[net:(https:\/\/[^\[\s<>"']{8,480})\]$/);
            if (pack && window.Emoji && window.Emoji.lookup) {
                var item = window.Emoji.lookup[pack[1] + ':' + pack[2]];
                out += item ? faceHtml(item) : nl(tok);
            } else if (net) {
                out += '<img class="emoji-sticker" src="' + esc(net[1]) + '" alt="表情" loading="lazy" referrerpolicy="no-referrer">';
            } else if (looksImageUrl(tok) && isHttps(tok)) {
                out += '<img class="emoji-sticker" src="' + esc(tok) + '" alt="表情" loading="lazy" referrerpolicy="no-referrer">';
            } else {
                out += nl(tok);
            }
            last = m.index + tok.length;
        }
        if (last < src.length) out += nl(src.slice(last));
        return out;
    }
    function renderNode(el, text) {
        if (!el) return;
        el.innerHTML = render(text);
    }

    var packs = { qq: [], wx: [], dy: [] };
    var lookup = {};
    var catalogReady = false;
    var catalogWaiters = [];

    function setCatalog(data) {
        packs = data || packs;
        lookup = {};
        ['qq', 'wx', 'dy'].forEach(function (k) {
            (packs[k] || []).forEach(function (it) {
                lookup[k + ':' + it.name] = it;
            });
        });
        window.Emoji.lookup = lookup;
        window.Emoji.packs = packs;
        catalogReady = true;
        catalogWaiters.splice(0).forEach(function (fn) { fn(); });
        document.querySelectorAll('.chat-text, .msg-text').forEach(function (el) {
            if (el.getAttribute('data-raw') !== null) {
                el.innerHTML = render(el.getAttribute('data-raw'));
            }
        });
    }
    function whenCatalog(fn) {
        if (catalogReady) fn();
        else catalogWaiters.push(fn);
    }
    function loadCatalog() {
        fetch('/emoji_api.php?action=catalog', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (d) {
                if (d && d.ok && d.packs) setCatalog(d.packs);
                else setCatalog(packs);
            })
            .catch(function () { setCatalog(packs); });
    }

    function insertAt(ta, start, end, token) {
        var v = ta.value;
        ta.value = v.slice(0, start) + token + v.slice(end);
        var pos = start + token.length;
        ta.focus();
        try { ta.setSelectionRange(pos, pos); } catch (e) {}
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function itemBtn(item, onPick) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'emoji-item' + (item.kind === 'sticker' ? ' is-sticker' : '');
        b.title = item.name || '';
        b.innerHTML = faceHtml(item);
        var sx = 0;
        var sy = 0;
        var armed = false;
        function choose(e) {
            e.preventDefault();
            e.stopPropagation();
            if (armed) return;
            armed = true;
            onPick(item);
            setTimeout(function () { armed = false; }, 360);
        }
        b.addEventListener('click', choose);
        b.addEventListener('touchstart', function (e) {
            var t = e.changedTouches[0];
            sx = t.clientX;
            sy = t.clientY;
        }, { passive: true });
        b.addEventListener('touchend', function (e) {
            var t = e.changedTouches[0];
            if (Math.abs(t.clientX - sx) < 14 && Math.abs(t.clientY - sy) < 14) {
                choose(e);
            }
        });
        return b;
    }

    function bind(ta) {
        if (!ta || ta.dataset.emojiBound) return;
        ta.dataset.emojiBound = '1';
        var wrap = document.createElement('div');
        wrap.className = 'emoji-composer';
        ta.parentNode.insertBefore(wrap, ta);
        wrap.appendChild(ta);

        var bar = document.createElement('div');
        bar.className = 'emoji-bar';
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'emoji-toggle';
        toggle.title = '表情';
        toggle.setAttribute('aria-label', '表情');
        toggle.innerHTML = '<svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.4 14.2c1.1 1.4 2.3 2.1 3.6 2.1s2.5-.7 3.6-2.1"/><circle cx="9" cy="10" r="1.1"/><circle cx="15" cy="10" r="1.1"/></svg>';
        var searchBtn = document.createElement('button');
        searchBtn.type = 'button';
        searchBtn.className = 'emoji-toggle emoji-search-btn';
        searchBtn.title = '搜索表情';
        searchBtn.setAttribute('aria-label', '搜索表情');
        searchBtn.innerHTML = '<svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/></svg>';
        bar.appendChild(toggle);
        bar.appendChild(searchBtn);
        wrap.appendChild(bar);

        var panel = document.createElement('div');
        panel.className = 'emoji-panel';
        panel.hidden = true;
        panel.innerHTML = '<div class="emoji-tabs" role="tablist">'
            + '<button type="button" class="is-on" data-pack="qq">QQ</button>'
            + '<button type="button" data-pack="wx">微信</button>'
            + '<button type="button" data-pack="dy">抖音</button>'
            + '<button type="button" data-pack="net">网络表情</button>'
            + '</div>'
            + '<label class="emoji-search-wrap">'
            + '<svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/></svg>'
            + '<input class="emoji-search" type="search" placeholder="搜索表情、斗图" maxlength="24" autocomplete="off">'
            + '</label>'
            + '<div class="emoji-grid"></div>';
        wrap.appendChild(panel);

        var grid = panel.querySelector('.emoji-grid');
        var search = panel.querySelector('.emoji-search');
        var tabBtns = panel.querySelectorAll('[data-pack]');
        var pack = 'qq';
        var netTimer = 0;
        var sugSeq = 0;

        function isSheet() {
            var w = window.innerWidth || document.documentElement.clientWidth || 0;
            return w <= 900
                || window.matchMedia('(max-width: 900px)').matches
                || (window.matchMedia('(pointer: coarse)').matches && w <= 1200);
        }
        function clearSheetStyle(el) {
            ['position', 'left', 'right', 'top', 'bottom', 'width', 'height', 'max-height', 'min-height', 'z-index', 'padding', 'border-radius', 'box-sizing', 'display', 'flex-direction', 'overflow'].forEach(function (k) {
                el.style.removeProperty(k);
            });
            el.style.removeProperty('--emoji-sheet-h');
        }
        function applySheetStyle(el, h, shown) {
            el.style.setProperty('position', 'fixed', 'important');
            el.style.setProperty('left', '0', 'important');
            el.style.setProperty('right', '0', 'important');
            el.style.setProperty('top', 'auto', 'important');
            el.style.setProperty('bottom', '0', 'important');
            el.style.setProperty('width', '100%', 'important');
            el.style.setProperty('height', h + 'px', 'important');
            el.style.setProperty('max-height', h + 'px', 'important');
            el.style.setProperty('min-height', '0', 'important');
            el.style.setProperty('z-index', '120', 'important');
            el.style.setProperty('padding', '12px 12px calc(12px + env(safe-area-inset-bottom, 0px))');
            el.style.setProperty('border-radius', '18px 18px 0 0');
            el.style.setProperty('box-sizing', 'border-box');
            if (shown) {
                el.style.setProperty('display', 'flex', 'important');
            } else {
                el.style.removeProperty('display');
            }
            el.style.setProperty('flex-direction', 'column');
            el.style.setProperty('overflow', 'hidden');
            el.style.setProperty('--emoji-sheet-h', h + 'px');
        }
        function fitSheet() {
            var sheet = isSheet();
            var tabs = panel.querySelector('.emoji-tabs');
            panel.classList.toggle('is-sheet', sheet);
            if (tabs) {
                tabs.style.display = 'grid';
                tabs.style.gridTemplateColumns = 'repeat(4, minmax(0, 1fr))';
                tabs.style.flex = '0 0 auto';
                tabBtns.forEach(function (b) {
                    b.style.width = '100%';
                    b.style.height = '36px';
                    b.style.minHeight = '36px';
                    b.style.padding = '0 4px';
                    b.style.margin = '0';
                });
            }
            grid.style.flex = '1 1 auto';
            grid.style.minHeight = '0';
            grid.style.overflowY = 'auto';
            if (!sheet) {
                clearSheetStyle(panel);
                return;
            }
            var vv = window.visualViewport;
            var vh = Math.round((vv && vv.height) ? vv.height : (window.innerHeight || 640));
            var h = Math.round(vh * 0.82);
            if (h > vh - 36) { h = vh - 36; }
            if (h < 280) { h = Math.max(Math.round(vh * 0.72), 240); }
            applySheetStyle(panel, h, !panel.hidden);
        }
        function syncDock() {
            var home = isSheet() ? document.body : wrap;
            if (panel.parentNode !== home) home.appendChild(panel);
            fitSheet();
            document.body.classList.toggle('emoji-open', isSheet() && !panel.hidden);
        }
        function setTab(next) {
            pack = next;
            tabBtns.forEach(function (x) {
                x.classList.toggle('is-on', x.getAttribute('data-pack') === next);
            });
        }
        function syncButtons() {
            var searching = !panel.hidden && pack === 'net';
            toggle.classList.toggle('is-on', !panel.hidden && !searching);
            searchBtn.classList.toggle('is-on', searching);
        }
        function closePanel() {
            panel.hidden = true;
            syncButtons();
            syncDock();
        }
        function openPanel(nextPack, focusSearch) {
            if (nextPack) setTab(nextPack);
            panel.hidden = false;
            paint();
            syncButtons();
            syncDock();
            if (focusSearch) {
                search.value = '';
                search.focus();
            }
        }

        function paintLocal(list) {
            grid.innerHTML = '';
            if (!list.length) {
                grid.innerHTML = '<div class="emoji-empty">没有匹配的表情</div>';
                return;
            }
            list.forEach(function (it) { grid.appendChild(itemBtn(it, pick)); });
        }
        function paint() {
            var q = search.value.trim();
            grid.classList.toggle('is-sticker-grid', pack === 'net');
            if (pack === 'net') {
                grid.innerHTML = '<div class="emoji-empty">加载热门斗图…</div>';
                netSearch(q);
                return;
            }
            var list = packs[pack] || [];
            if (q) {
                var ql = q.toLowerCase();
                list = list.filter(function (it) {
                    var n = String(it.name || '').toLowerCase();
                    if (n.indexOf(ql) >= 0) return true;
                    return (it.aliases || []).some(function (a) {
                        return String(a).toLowerCase().indexOf(ql) >= 0;
                    });
                });
            }
            paintLocal(list);
        }
        function netSearch(q) {
            var seq = ++sugSeq;
            grid.innerHTML = '<div class="emoji-empty">搜索中…</div>';
            fetch('/emoji_api.php?action=search&q=' + encodeURIComponent(q) + '&net=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) {
                    if (seq !== sugSeq) return;
                    if (!d || !d.ok) {
                        grid.innerHTML = '<div class="emoji-empty">搜索失败，请稍后重试</div>';
                        return;
                    }
                    var faces = d.faces || [];
                    var stickers = d.stickers || [];
                    grid.innerHTML = '';
                    if (pack === 'net') {
                        if (!stickers.length) {
                            grid.innerHTML = '<div class="emoji-empty">没有搜到相关斗图</div>';
                            return;
                        }
                        stickers.forEach(function (it) { grid.appendChild(itemBtn(it, pick)); });
                        return;
                    }
                    if (!faces.length && !stickers.length) {
                        grid.innerHTML = '<div class="emoji-empty">没有搜到相关表情</div>';
                        return;
                    }
                    faces.forEach(function (it) { grid.appendChild(itemBtn(it, pick)); });
                    stickers.forEach(function (it) { grid.appendChild(itemBtn(it, pick)); });
                })
                .catch(function () {
                    if (seq !== sugSeq) return;
                    grid.innerHTML = '<div class="emoji-empty">搜索失败，请稍后重试</div>';
                });
        }
        function pick(item) {
            var token = item.token || '';
            if (!token) return;
            var start = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length;
            var end = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : start;
            insertAt(ta, start, end, token);
            closePanel();
        }

        function isolateTouch(el) {
            if (!el) return;
            el.addEventListener('touchmove', function (e) {
                e.stopPropagation();
            }, { passive: true });
            el.addEventListener('wheel', function (e) {
                e.stopPropagation();
            }, { passive: true });
        }
        isolateTouch(panel);
        isolateTouch(grid);

        tabBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                setTab(b.getAttribute('data-pack'));
                paint();
                syncButtons();
                if (pack === 'net') search.focus();
            });
        });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });
        search.addEventListener('input', function () {
            clearTimeout(netTimer);
            if (pack === 'net') {
                var q = search.value.trim();
                netTimer = setTimeout(function () { netSearch(q); }, 280);
                return;
            }
            paint();
        });
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (!panel.hidden && pack !== 'net') closePanel();
            else openPanel(pack === 'net' ? 'qq' : pack, false);
        });
        searchBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!panel.hidden && pack === 'net') closePanel();
            else openPanel('net', true);
        });
        document.addEventListener('click', function (e) {
            var t = e.target;
            if (wrap.contains(t) || panel.contains(t)) return;
            if (toggle.contains(t) || searchBtn.contains(t)) return;
            if (t && t.closest && t.closest('.wc-panel')) return;
            closePanel();
        });
        window.addEventListener('resize', syncDock);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', syncDock);
        }

        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closePanel();
        });

        whenCatalog(paint);
    }

    window.Emoji = {
        render: render,
        renderNode: renderNode,
        bind: bind,
        lookup: lookup,
        packs: packs
    };

    function boot() {
        loadCatalog();
        document.querySelectorAll('textarea[data-emoji]').forEach(bind);
        document.querySelectorAll('[data-emoji-html]').forEach(function (el) {
            if (el.getAttribute('data-emoji-done')) return;
            el.setAttribute('data-emoji-done', '1');
            var raw = el.getAttribute('data-emoji-html');
            if (raw !== null) el.innerHTML = render(raw);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
