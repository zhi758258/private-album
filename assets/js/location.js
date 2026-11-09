(function () {
    'use strict';

    var PIN = '<svg viewBox="0 0 24 24" class="loc-icon" aria-hidden="true"><path d="M12 21.2s6.6-6.1 6.6-11a6.6 6.6 0 1 0-13.2 0c0 4.9 6.6 11 6.6 11z"/><circle cx="12" cy="10" r="2.4"/></svg>';

    function mapUrl(lat, lng, name) {
        return 'https://uri.amap.com/marker?position=' + Number(lng).toFixed(6) + ',' + Number(lat).toFixed(6)
            + '&name=' + encodeURIComponent(name || '我的位置')
            + '&coordinate=wgs84&callnative=1';
    }

    function uniqJoin(parts) {
        var seen = [];
        parts.forEach(function (x) {
            x = String(x || '').trim();
            if (x && seen.indexOf(x) === -1) seen.push(x);
        });
        return seen.join('');
    }

    function buildName(d) {
        if (!d) return '';
        return uniqJoin([
            d.principalSubdivision,
            d.city,
            d.locality,
            d.neighbourhood || d.subLocality,
            d.street || d.thoroughfare
        ]);
    }

    function reverseGeocode(lat, lng) {
        var url = 'https://api.bigdatacloud.net/data/reverse-geocode-client?latitude='
            + encodeURIComponent(lat) + '&longitude=' + encodeURIComponent(lng)
            + '&localityLanguage=zh-Hans';
        var ctrl = null;
        try { ctrl = new AbortController(); } catch (e) { ctrl = null; }
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 6000) : 0;
        var opts = { headers: { 'Accept': 'application/json' } };
        if (ctrl) opts.signal = ctrl.signal;
        return fetch(url, opts)
            .then(function (r) { return r.json(); })
            .then(buildName)
            .catch(function () { return ''; })
            .then(function (name) { if (timer) clearTimeout(timer); return name; });
    }

    function searchPlaces(keyword) {
        var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=8&accept-language=zh-CN&q='
            + encodeURIComponent(keyword);
        var ctrl = null;
        try { ctrl = new AbortController(); } catch (e) { ctrl = null; }
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 8000) : 0;
        var opts = { headers: { 'Accept': 'application/json' } };
        if (ctrl) opts.signal = ctrl.signal;
        return fetch(url, opts)
            .then(function (r) { return r.json(); })
            .then(function (list) {
                if (timer) clearTimeout(timer);
                if (!Array.isArray(list)) return [];
                return list.map(function (p) {
                    return {
                        lat: String(p.lat),
                        lng: String(p.lon),
                        name: String(p.display_name || p.name || keyword)
                    };
                });
            })
            .catch(function () { if (timer) clearTimeout(timer); return []; });
    }

    function fields(form) {
        return {
            lat: form.querySelector('input[name="loc_lat"]'),
            lng: form.querySelector('input[name="loc_lng"]'),
            name: form.querySelector('input[name="loc_name"]')
        };
    }

    function reset(form) {
        if (!form) return;
        form.__locSeq = (form.__locSeq || 0) + 1;
        var f = fields(form);
        if (f.lat) f.lat.value = '';
        if (f.lng) f.lng.value = '';
        if (f.name) f.name.value = '';
        var preview = form.querySelector('.loc-preview');
        if (preview) preview.hidden = true;
        var label = form.querySelector('.loc-preview .loc-label');
        if (label) label.textContent = '';
        var chip = form.querySelector('.loc-preview .loc-chip');
        if (chip) chip.setAttribute('href', '#');
        var btn = form.querySelector('.loc-toggle');
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('is-busy');
            btn.title = '位置';
        }
    }

    /* ===== 位置选择弹窗（对标微信“发送位置”） ===== */
    var modal = null;
    var activeForm = null;
    var searchInput, resultList, currentBtn, hintEl;
    var searchTimer = 0;
    var searchSeq = 0;

    function ensureModal() {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.className = 'loc-modal';
        modal.hidden = true;
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', '选择位置');
        modal.innerHTML = ''
            + '<div class="loc-panel">'
            + '  <div class="loc-head">'
            + '    <button type="button" class="loc-close" aria-label="关闭">×</button>'
            + '    <div class="loc-title">选择位置</div>'
            + '    <span class="loc-head-spacer"></span>'
            + '  </div>'
            + '  <div class="loc-search">'
            + '    <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M16 16l5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
            + '    <input type="search" class="loc-search-input" placeholder="搜索地点" maxlength="40" autocomplete="off">'
            + '  </div>'
            + '  <button type="button" class="loc-current">'
            + PIN
            + '    <span class="loc-current-text">发送当前位置</span>'
            + '  </button>'
            + '  <div class="loc-hint"></div>'
            + '  <div class="loc-results"></div>'
            + '</div>';
        document.body.appendChild(modal);

        searchInput = modal.querySelector('.loc-search-input');
        resultList = modal.querySelector('.loc-results');
        currentBtn = modal.querySelector('.loc-current');
        hintEl = modal.querySelector('.loc-hint');

        modal.querySelector('.loc-close').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        searchInput.addEventListener('input', function () {
            onSearchInput(searchInput.value.trim());
        });
        currentBtn.addEventListener('click', function () {
            useCurrent();
        });
        return modal;
    }

    function setHint(text, error) {
        if (!hintEl) return;
        hintEl.textContent = text || '';
        hintEl.classList.toggle('is-error', !!error);
    }

    function clearResults() {
        if (resultList) resultList.innerHTML = '';
    }

    function openModal(form) {
        ensureModal();
        activeForm = form;
        searchInput.value = '';
        clearResults();
        setHint('');
        modal.hidden = false;
        document.body.classList.add('modal-open');
        setTimeout(function () { try { searchInput.focus(); } catch (e) {} }, 30);
    }

    function closeModal() {
        if (modal) modal.hidden = true;
        document.body.classList.remove('modal-open');
        activeForm = null;
    }

    function commit(lat, lng, name) {
        var form = activeForm;
        if (!form) return;
        var f = fields(form);
        var latS = Number(lat).toFixed(6);
        var lngS = Number(lng).toFixed(6);
        var finalName = String(name || '').trim() || '我的位置';
        if (finalName.length > 60) finalName = finalName.slice(0, 60);
        if (f.lat) f.lat.value = latS;
        if (f.lng) f.lng.value = lngS;
        if (f.name) f.name.value = finalName;
        var preview = form.querySelector('.loc-preview');
        var label = form.querySelector('.loc-preview .loc-label');
        var chip = form.querySelector('.loc-preview .loc-chip');
        if (preview) preview.hidden = false;
        if (label) label.textContent = finalName;
        if (chip) chip.setAttribute('href', mapUrl(latS, lngS, finalName));
        closeModal();
    }

    function useCurrent() {
        if (!navigator.geolocation) {
            setHint('当前浏览器不支持定位。', true);
            return;
        }
        var form = activeForm;
        currentBtn.disabled = true;
        setHint('正在获取当前位置…');
        navigator.geolocation.getCurrentPosition(function (pos) {
            currentBtn.disabled = false;
            setHint('');
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            var latS = Number(lat).toFixed(6);
            var lngS = Number(lng).toFixed(6);
            if (form) {
                var f = fields(form);
                if (f.lat) f.lat.value = latS;
                if (f.lng) f.lng.value = lngS;
                if (f.name) f.name.value = '我的位置';
                var preview = form.querySelector('.loc-preview');
                var label = form.querySelector('.loc-preview .loc-label');
                var chip = form.querySelector('.loc-preview .loc-chip');
                if (preview) preview.hidden = false;
                if (label) label.textContent = '我的位置';
                if (chip) chip.setAttribute('href', mapUrl(latS, lngS, '我的位置'));
            }
            closeModal();
            reverseGeocode(Number(latS).toFixed(4), Number(lngS).toFixed(4)).then(function (name) {
                if (!form) return;
                var finalName = String(name || '').trim() || '我的位置';
                if (finalName.length > 60) finalName = finalName.slice(0, 60);
                var f2 = fields(form);
                if (f2.name) f2.name.value = finalName;
                var label = form.querySelector('.loc-preview .loc-label');
                if (label) label.textContent = finalName;
                var chip = form.querySelector('.loc-preview .loc-chip');
                if (chip) chip.setAttribute('href', mapUrl(latS, lngS, finalName));
            });
        }, function (err) {
            currentBtn.disabled = false;
            var msg = '定位失败，请检查定位权限。';
            if (err && err.code === 1) msg = '你拒绝了定位权限，请在浏览器设置中允许后重试。';
            else if (err && err.code === 3) msg = '定位超时，请到信号较好的地方重试。';
            setHint(msg, true);
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
    }

    function onSearchInput(keyword) {
        clearTimeout(searchTimer);
        var seq = ++searchSeq;
        if (!keyword) {
            clearResults();
            setHint('');
            return;
        }
        setHint('搜索中…');
        searchTimer = setTimeout(function () {
            searchPlaces(keyword).then(function (list) {
                if (seq !== searchSeq) return;
                clearResults();
                if (!list.length) {
                    setHint('没有找到相关地点。', true);
                    return;
                }
                setHint('');
                list.forEach(function (p) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'loc-item';
                    item.innerHTML = PIN + '<span class="loc-item-text"></span>';
                    item.querySelector('.loc-item-text').textContent = p.name;
                    item.addEventListener('click', function () {
                        commit(p.lat, p.lng, p.name);
                    });
                    resultList.appendChild(item);
                });
            });
        }, 400);
    }

    function setup(form) {
        if (form.getAttribute('data-location-bound')) return;
        form.setAttribute('data-location-bound', '1');
        var ta = form.querySelector('textarea');
        if (!ta) return;

        var f = fields(form);
        if (!f.lat) { f.lat = document.createElement('input'); f.lat.type = 'hidden'; f.lat.name = 'loc_lat'; form.appendChild(f.lat); }
        if (!f.lng) { f.lng = document.createElement('input'); f.lng.type = 'hidden'; f.lng.name = 'loc_lng'; form.appendChild(f.lng); }
        if (!f.name) { f.name = document.createElement('input'); f.name.type = 'hidden'; f.name.name = 'loc_name'; form.appendChild(f.name); }

        var composer = ta.closest ? ta.closest('.emoji-composer') : null;
        var anchor = composer || ta;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'emoji-toggle loc-toggle';
        btn.title = '位置';
        btn.setAttribute('aria-label', '发送位置');
        btn.innerHTML = PIN;

        var preview = document.createElement('div');
        preview.className = 'loc-preview';
        preview.hidden = true;
        preview.innerHTML = '<a class="loc-chip" href="#" target="_blank" rel="noopener noreferrer">'
            + PIN + '<span class="loc-label"></span></a>'
            + '<button type="button" class="loc-clear" aria-label="清除定位">×</button>';

        var bar = composer ? composer.querySelector('.emoji-bar') : null;
        if (bar) {
            bar.appendChild(btn);
            composer.parentNode.insertBefore(preview, composer.nextSibling);
        } else {
            var locBar = document.createElement('div');
            locBar.className = 'emoji-bar loc-bar';
            locBar.appendChild(btn);
            anchor.parentNode.insertBefore(locBar, anchor.nextSibling);
            anchor.parentNode.insertBefore(preview, locBar.nextSibling);
        }

        var clear = preview.querySelector('.loc-clear');

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openModal(form);
        });

        clear.addEventListener('click', function (e) {
            e.preventDefault();
            reset(form);
        });
    }

    function init() {
        document.querySelectorAll('form[data-location]').forEach(setup);
    }

    window.SiteLocation = { reset: reset };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
