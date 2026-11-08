(function () {
    'use strict';

    var PIN = '<svg viewBox="0 0 24 24" class="loc-icon" aria-hidden="true"><path d="M12 21.2s6.6-6.1 6.6-11a6.6 6.6 0 1 0-13.2 0c0 4.9 6.6 11 6.6 11z"/><circle cx="12" cy="10" r="2.4"/></svg>';

    function mapUrl(lat, lng, name) {
        return 'https://uri.amap.com/marker?position=' + Number(lng).toFixed(6) + ',' + Number(lat).toFixed(6)
            + '&name=' + encodeURIComponent(name || '我的位置')
            + '&coordinate=wgs84&callnative=1';
    }

    function buildName(d) {
        if (!d) return '';
        var parts = [d.principalSubdivision, d.city, d.locality];
        var seen = [];
        parts.forEach(function (x) {
            x = String(x || '').trim();
            if (x && seen.indexOf(x) === -1) seen.push(x);
        });
        return seen.join('');
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
            btn.title = '定位';
        }
    }

    function setup(form) {
        if (form.getAttribute('data-location-bound')) return;
        form.setAttribute('data-location-bound', '1');
        var ta = form.querySelector('textarea');
        if (!ta) return;

        var f = fields(form);
        if (!f.lat || !f.lng || !f.name) {
            if (!f.lat) { f.lat = document.createElement('input'); f.lat.type = 'hidden'; f.lat.name = 'loc_lat'; form.appendChild(f.lat); }
            if (!f.lng) { f.lng = document.createElement('input'); f.lng.type = 'hidden'; f.lng.name = 'loc_lng'; form.appendChild(f.lng); }
            if (!f.name) { f.name = document.createElement('input'); f.name.type = 'hidden'; f.name.name = 'loc_name'; form.appendChild(f.name); }
        }

        var composer = ta.closest ? ta.closest('.emoji-composer') : null;
        var anchor = composer || ta;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'emoji-toggle loc-toggle';
        btn.title = '定位';
        btn.setAttribute('aria-label', '定位');
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

        var label = preview.querySelector('.loc-label');
        var chip = preview.querySelector('.loc-chip');
        var clear = preview.querySelector('.loc-clear');

        function setBusy(busy) {
            btn.disabled = busy;
            btn.classList.toggle('is-busy', busy);
            btn.title = busy ? '定位中…' : '定位';
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!navigator.geolocation) {
                alert('当前浏览器不支持定位。');
                return;
            }
            setBusy(true);
            var seq = (form.__locSeq = (form.__locSeq || 0) + 1);
            navigator.geolocation.getCurrentPosition(function (pos) {
                if (seq !== form.__locSeq) return;
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var latS = Number(lat).toFixed(6);
                var lngS = Number(lng).toFixed(6);
                f.lat.value = latS;
                f.lng.value = lngS;
                f.name.value = '我的位置';
                label.textContent = '定位中…';
                chip.setAttribute('href', mapUrl(latS, lngS, '我的位置'));
                preview.hidden = false;
                setBusy(false);
                reverseGeocode(Number(latS).toFixed(4), Number(lngS).toFixed(4)).then(function (name) {
                    if (seq !== form.__locSeq) return;
                    var finalName = String(name || '').trim() || '我的位置';
                    if (finalName.length > 60) finalName = finalName.slice(0, 60);
                    label.textContent = finalName;
                    f.name.value = finalName;
                    chip.setAttribute('href', mapUrl(latS, lngS, finalName));
                });
            }, function (err) {
                if (seq !== form.__locSeq) return;
                setBusy(false);
                var msg = '定位失败，请检查定位权限。';
                if (err && err.code === 1) msg = '你拒绝了定位权限，请在浏览器设置中允许后重试。';
                else if (err && err.code === 3) msg = '定位超时，请到信号较好的地方重试。';
                alert(msg);
            }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
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
