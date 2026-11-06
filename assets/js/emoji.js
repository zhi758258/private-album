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
        if (!/^https:\/\/[a-zA-Z0-9._~:/?#\[\]@!$&()*+,;=%\-]+$/.test(url)) return false;
        try {
            var u = new URL(url);
            if (u.protocol !== 'https:') return false;
            if (!u.hostname || u.hostname.indexOf('.') < 0) return false;
            if (u.hostname === 'localhost') return false;
            return true;
        } catch (e) {
            return false;
        }
    }
    function faceHtml(item) {
        var name = esc(item.name || '');
        var src = esc(item.src || '');
        var cls = item.kind === 'sticker' ? 'emoji-sticker' : 'emoji-face';
        return '<img class="' + cls + '" src="' + src + '" alt="' + name + '" title="' + name + '" loading="lazy">';
    }
    function render(text) {
        var src = String(text || '');
        var re = /(\[(?:qq|wx|dy):[^\[\]]{1,32}\]|\[net:https:\/\/[^\[\s<>"']{8,480}\])/g;
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
            } else if (net && isHttps(net[1])) {
                out += '<img class="emoji-sticker" src="' + esc(net[1]) + '" alt="表情" loading="lazy" referrerpolicy="no-referrer">';
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

    function localHits(q, limit) {
        q = String(q || '').trim();
        if (!q) return [];
        var ql = q.toLowerCase();
        var hits = [];
        ['qq', 'wx', 'dy'].forEach(function (k) {
            (packs[k] || []).forEach(function (it) {
                var n = String(it.name || '').toLowerCase();
                var score = 0;
                if (n === ql) score = 100;
                else if (n.indexOf(ql) >= 0) score = 80 - n.indexOf(ql);
                else if (ql.indexOf(n) >= 0) score = 60;
                else {
                    var aliases = it.aliases || [];
                    for (var i = 0; i < aliases.length; i++) {
                        var al = String(aliases[i] || '').toLowerCase();
                        if (al === ql) { score = 90; break; }
                        if (al.indexOf(ql) >= 0 || ql.indexOf(al) >= 0) { score = 70; break; }
                    }
                }
                if (score > 0) hits.push(Object.assign({ score: score }, it));
            });
        });
        hits.sort(function (a, b) { return b.score - a.score; });
        return hits.slice(0, limit || 24);
    }

    function insertAt(ta, start, end, token) {
        var v = ta.value;
        ta.value = v.slice(0, start) + token + v.slice(end);
        var pos = start + token.length;
        ta.focus();
        try { ta.setSelectionRange(pos, pos); } catch (e) {}
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function keywordAt(ta) {
        var v = ta.value;
        var pos = typeof ta.selectionStart === 'number' ? ta.selectionStart : v.length;
        var left = v.slice(0, pos);
        var open = left.lastIndexOf('[');
        var close = left.lastIndexOf(']');
        if (open > close) return { word: '', start: pos, end: pos };
        var m = left.match(/([^\s\[\]]+)$/);
        if (!m) return { word: '', start: pos, end: pos };
        return { word: m[1], start: pos - m[1].length, end: pos };
    }

    function itemBtn(item, onPick) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'emoji-item' + (item.kind === 'sticker' ? ' is-sticker' : '');
        b.title = item.name || '';
        b.innerHTML = faceHtml(item);
        b.addEventListener('click', function (e) {
            e.preventDefault();
            onPick(item);
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

        var suggest = document.createElement('div');
        suggest.className = 'emoji-suggest';
        suggest.hidden = true;
        wrap.appendChild(suggest);

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
        var sugTimer = 0;
        var sugSeq = 0;
        var sugItems = [];

        function hideSuggest() { suggest.hidden = true; suggest.innerHTML = ''; sugItems = []; }
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
        }
        function openPanel(nextPack, focusSearch) {
            if (nextPack) setTab(nextPack);
            panel.hidden = false;
            hideSuggest();
            paint();
            syncButtons();
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
                netSearch(q, true);
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
        function netSearch(q, intoPanel) {
            var seq = ++sugSeq;
            var target = intoPanel ? grid : suggest;
            if (intoPanel) target.innerHTML = '<div class="emoji-empty">搜索中…</div>';
            fetch('/emoji_api.php?action=search&q=' + encodeURIComponent(q) + '&net=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) {
                    if (seq !== sugSeq) return;
                    if (!d || !d.ok) {
                        if (intoPanel) target.innerHTML = '<div class="emoji-empty">搜索失败，请稍后重试</div>';
                        return;
                    }
                    var faces = d.faces || [];
                    var stickers = d.stickers || [];
                    if (intoPanel) {
                        target.innerHTML = '';
                        if (pack === 'net') {
                            if (!stickers.length) {
                                target.innerHTML = '<div class="emoji-empty">没有搜到相关斗图</div>';
                                return;
                            }
                            stickers.forEach(function (it) { target.appendChild(itemBtn(it, pick)); });
                            return;
                        }
                        if (!faces.length && !stickers.length) {
                            target.innerHTML = '<div class="emoji-empty">没有搜到相关表情</div>';
                            return;
                        }
                        faces.forEach(function (it) { target.appendChild(itemBtn(it, pick)); });
                        stickers.forEach(function (it) { target.appendChild(itemBtn(it, pick)); });
                    } else if (faces.length || stickers.length) {
                        showSuggest(faces, stickers);
                    }
                })
                .catch(function () {
                    if (seq !== sugSeq) return;
                    if (intoPanel) target.innerHTML = '<div class="emoji-empty">搜索失败，请稍后重试</div>';
                });
        }
        function showSuggest(faces, stickers) {
            suggest.innerHTML = '';
            var all = (faces || []).concat(stickers || []);
            if (!all.length) { hideSuggest(); return; }
            sugItems = all.slice(0, 18);
            var lab = document.createElement('div');
            lab.className = 'emoji-suggest-lab';
            lab.textContent = '相关表情 / 斗图';
            suggest.appendChild(lab);
            var row = document.createElement('div');
            row.className = 'emoji-suggest-row';
            sugItems.forEach(function (it) { row.appendChild(itemBtn(it, pickReplace)); });
            suggest.appendChild(row);
            suggest.hidden = false;
        }
        function pick(item) {
            var token = item.token || '';
            if (!token) return;
            var start = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length;
            var end = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : start;
            insertAt(ta, start, end, token);
            closePanel();
            hideSuggest();
        }
        function pickReplace(item) {
            var token = item.token || '';
            if (!token) return;
            var kw = keywordAt(ta);
            insertAt(ta, kw.start, kw.end, token);
            closePanel();
            hideSuggest();
        }

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
                netTimer = setTimeout(function () { netSearch(q, true); }, 280);
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
            if (!wrap.contains(e.target)) {
                closePanel();
                hideSuggest();
            }
        });

        function maybeSuggest() {
            if (!panel.hidden) return;
            var kw = keywordAt(ta);
            var word = kw.word;
            var hasCjk = /[\u4e00-\u9fff]/.test(word);
            if (!word || word.length < (hasCjk ? 1 : 2) || word.length > 12) {
                hideSuggest();
                return;
            }
            var faces = localHits(word, 12);
            if (faces.length) showSuggest(faces, []);
            clearTimeout(sugTimer);
            sugTimer = setTimeout(function () { netSearch(word, false); }, 320);
        }
        ta.addEventListener('input', function () {
            if (ta.composing) return;
            maybeSuggest();
        });
        ta.addEventListener('compositionstart', function () { ta.composing = true; });
        ta.addEventListener('compositionend', function () {
            ta.composing = false;
            maybeSuggest();
        });
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideSuggest();
                closePanel();
                return;
            }
            if (e.key === 'Enter' && !e.shiftKey && !suggest.hidden && sugItems.length) {
                e.preventDefault();
                e.stopImmediatePropagation();
                pickReplace(sugItems[0]);
            }
        }, true);

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
