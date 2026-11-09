(function () {
    'use strict';
    var cfg = window.DDZ || {};
    var app = document.getElementById('ddzApp');
    if (!app) { return; }

    var ui = {};
    var uiMap = {
        lobby: 'ddzLobby', table: 'ddzTable', score: 'ddzScore', roomList: 'ddzRoomList',
        flash: 'ddzFlash', roomCode: 'ddzRoomCode', mult: 'ddzMult', phaseTip: 'ddzPhaseTip',
        seatLeft: 'seatLeft', seatRight: 'seatRight', meInfo: 'meInfo',
        playLeft: 'playLeft', playRight: 'playRight', playMe: 'playMe',
        bottom: 'ddzBottom', turn: 'ddzTurn', myHand: 'myHand',
        myActions: 'myActions', over: 'ddzOver', overTitle: 'ddzOverTitle', overBody: 'ddzOverBody',
        rotateTip: 'ddzRotateTip'
    };
    Object.keys(uiMap).forEach(function (k) { ui[k] = document.getElementById(uiMap[k]); });

    var state = { code: cfg.code || '', seat: -1, isOwner: false, data: null, selected: {}, timer: null, busy: false, hintIdx: 0, lastFlyKey: '', playHtml: {}, snd: null };

    var RANK_TXT = { 11: 'J', 12: 'Q', 13: 'K', 14: 'A', 15: '2' };
    var SUITS = ['♠', '♥', '♣', '♦'];
    var TYPE_TXT = {
        single: '单张', pair: '对子', trio: '三张', trio1: '三带一', trio2: '三带二',
        bomb: '炸弹', rocket: '王炸', straight: '顺子', tube: '连对', plane: '飞机',
        plane1: '飞机带单', plane2: '飞机带对', four1: '四带二', four2: '四带两对'
    };
    var FACES = [
        { bg: '#ffe0b8', skin: '#ffd2ab', hair: '#2f2620' },
        { bg: '#cdeafd', skin: '#ffd9b8', hair: '#5b3a24' },
        { bg: '#ffd9e6', skin: '#ffd8bb', hair: '#333333' },
        { bg: '#e6ffd9', skin: '#f8cfa8', hair: '#3a2b1c' },
        { bg: '#fff3c4', skin: '#ffd2ab', hair: '#7a4a22' },
        { bg: '#e4dcff', skin: '#ffd9b8', hair: '#241f2e' }
    ];

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function rankText(r) {
        if (r >= 16) { return r === 17 ? '大王' : '小王'; }
        return RANK_TXT[r] || String(r);
    }
    function dcard(id, extra) {
        id = Number(id);
        var r = Math.floor(id / 10);
        var s = id % 10;
        var cls = 'dcard' + (extra ? ' ' + extra : '');
        if (r >= 16) {
            return '<span class="' + cls + ' dcard-joker' + (r === 17 ? ' is-big' : '') + '" data-id="' + id + '"><span class="dcard-rank">' + rankText(r) + '</span></span>';
        }
        var red = (s === 1 || s === 3);
        var suit = SUITS[s];
        return '<span class="' + cls + (red ? ' is-red' : '') + '" data-id="' + id + '"><span class="dcard-tl"><span class="dcard-rank">' + rankText(r) + '</span><span class="dcard-suit">' + suit + '</span></span><span class="dcard-face">' + suit + '</span></span>';
    }
    function faceSVG(seed) {
        var f = FACES[Math.abs(seed) % FACES.length];
        return '<svg viewBox="0 0 64 64" aria-hidden="true">' +
            '<circle cx="32" cy="32" r="32" fill="' + f.bg + '"/>' +
            '<path d="M32 12c11 0 18 8 18 19 0 12-8 21-18 21s-18-9-18-21c0-11 7-19 18-19z" fill="' + f.skin + '"/>' +
            '<path d="M14 30q3-18 18-18t18 18q-6-10-18-11t-18 11z" fill="' + f.hair + '"/>' +
            '<circle cx="25" cy="34" r="2.7" fill="#2a2320"/>' +
            '<circle cx="39" cy="34" r="2.7" fill="#2a2320"/>' +
            '<path d="M27 43q5 5 10 0" stroke="#a4523c" stroke-width="2.2" fill="none" stroke-linecap="round"/>' +
            '<circle cx="21" cy="40" r="3" fill="#ff9aa2" opacity="0.55"/>' +
            '<circle cx="43" cy="40" r="3" fill="#ff9aa2" opacity="0.55"/>' +
            '</svg>';
    }
    function avatarHTML(p, s) {
        var seed = p.is_ai ? s + 7 : ((Number(p.uid) || 0) + 3);
        var crown = '';
        return '<div class="ddz-avatar">' + crown + faceSVG(seed) + '</div>';
    }
    function lastEvent(d, seat, types) {
        if (!d.events) { return null; }
        for (var i = d.events.length - 1; i >= 0; i--) {
            var e = d.events[i];
            if (e.seat === seat && types.indexOf(e.t) >= 0) { return e; }
        }
        return null;
    }
    var sound = (function () {
        var ctx = null;
        var on = true;
        try { on = localStorage.getItem('ddz_sound') !== '0'; } catch (e) { on = true; }
        function ac() {
            if (!ctx) {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) { return null; }
                try { ctx = new Ctx(); } catch (e) { return null; }
            }
            if (ctx.state === 'suspended') { ctx.resume().catch(function () {}); }
            return ctx;
        }
        function tone(t, freq, dur, peak, type, slideTo) {
            var o = ctx.createOscillator();
            var g = ctx.createGain();
            o.type = type || 'sine';
            o.frequency.setValueAtTime(freq, t);
            if (slideTo) { o.frequency.exponentialRampToValueAtTime(slideTo, t + dur); }
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(peak, t + 0.012);
            g.gain.exponentialRampToValueAtTime(Math.max(0.0001, peak * 0.5), t + 0.012 + dur * 0.5);
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur + 0.06);
            o.connect(g); g.connect(ctx.destination);
            o.start(t); o.stop(t + dur + 0.1);
        }
        function noise(t, dur, peak, filterFreq) {
            var len = Math.floor(ctx.sampleRate * dur);
            var buf = ctx.createBuffer(1, len, ctx.sampleRate);
            var data = buf.getChannelData(0);
            for (var i = 0; i < len; i++) { data[i] = (Math.random() * 2 - 1) * (1 - i / len); }
            var src = ctx.createBufferSource();
            src.buffer = buf;
            var flt = ctx.createBiquadFilter();
            flt.type = 'lowpass';
            flt.frequency.value = filterFreq || 1200;
            var g = ctx.createGain();
            g.gain.value = peak;
            src.connect(flt); flt.connect(g); g.connect(ctx.destination);
            src.start(t);
        }
        function now() { return ctx.currentTime; }

        /* 语音：用浏览器语音合成念出经典台词（无需版权音频素材） */
        var synth = window.speechSynthesis || null;
        var zhVoice = null;
        function pickVoice() {
            if (!synth || !synth.getVoices) { return; }
            var vs = synth.getVoices() || [];
            for (var i = 0; i < vs.length; i++) {
                if (/zh|Chinese|中文/i.test(vs[i].lang || '') || /zh|Chinese/i.test(vs[i].name || '')) { zhVoice = vs[i]; return; }
            }
        }
        if (synth) {
            pickVoice();
            if (synth.onvoiceschanged !== undefined) { synth.onvoiceschanged = pickVoice; }
        }
        function say(text) {
            if (!on || !synth || !text) { return; }
            try {
                synth.cancel();
                var u = new SpeechSynthesisUtterance(text);
                if (zhVoice) { u.voice = zhVoice; }
                u.lang = 'zh-CN';
                u.rate = 1.15;
                u.pitch = 1.1;
                u.volume = 0.95;
                synth.speak(u);
            } catch (e) { /* ignore */ }
        }

        var API = {
            enabled: function () { return on; },
            set: function (v) { on = !!v; try { localStorage.setItem('ddz_sound', on ? '1' : '0'); } catch (e) {} if (!on && synth) { try { synth.cancel(); } catch (e) {} } },
            resume: function () { ac(); if (synth) { pickVoice(); } },
            say: say,
            card: function () { if (!ac() || !on) return; var t = now(); tone(t, 620, 0.09, 0.16, 'triangle'); tone(t + 0.02, 880, 0.07, 0.1, 'sine'); },
            pass: function () { if (!ac() || !on) return; tone(now(), 300, 0.3, 0.16, 'sine', 180); say('不出'); },
            cantbeat: function () { if (!ac() || !on) return; tone(now(), 320, 0.3, 0.16, 'sine', 190); say('要不起'); },
            bid: function () { if (!ac() || !on) return; var t = now(); tone(t, 523, 0.14, 0.18, 'square'); tone(t + 0.12, 784, 0.22, 0.2, 'square'); say('抢地主'); },
            nobid: function () { if (!ac() || !on) return; tone(now(), 392, 0.2, 0.14, 'sine', 300); say('不抢'); },
            deal: function () { if (!ac() || !on) return; var t = now(); for (var i = 0; i < 6; i++) { tone(t + i * 0.05, 700 + (i % 2) * 160, 0.05, 0.09, 'triangle'); } },
            bomb: function () { if (!ac() || !on) return; var t = now(); noise(t, 0.4, 0.5, 900); tone(t, 140, 0.4, 0.3, 'sawtooth', 60); say('炸弹'); },
            rocket: function () { if (!ac() || !on) return; var t = now(); noise(t, 0.55, 0.6, 1400); tone(t, 220, 0.5, 0.34, 'sawtooth', 70); tone(t + 0.05, 440, 0.4, 0.2, 'square', 120); say('王炸'); },
            turn: function () { if (!ac() || !on) return; var t = now(); tone(t, 880, 0.16, 0.16, 'sine'); tone(t + 0.14, 1175, 0.22, 0.16, 'sine'); },
            win: function () { if (!ac() || !on) return; var t = now(); [523, 659, 784, 1047].forEach(function (f, i) { tone(t + i * 0.14, f, 0.28, 0.2, 'triangle'); }); say('赢了'); },
            lose: function () { if (!ac() || !on) return; var t = now(); [494, 440, 392, 330].forEach(function (f, i) { tone(t + i * 0.16, f, 0.3, 0.18, 'sine'); }); say('输了'); },
            start: function () { if (!ac() || !on) return; var t = now(); tone(t, 659, 0.16, 0.18, 'square'); tone(t + 0.15, 880, 0.16, 0.18, 'square'); tone(t + 0.30, 1175, 0.3, 0.2, 'square'); say('开始'); },
            byType: function (type, mine) {
                if (type === 'bomb') { API.bomb(); }
                else if (type === 'rocket') { API.rocket(); }
                else { API.card(); if (mine) { say('出牌'); } }
            }
        };
        return API;
    })();

    function flash(msg, ok) {
        if (!ui.flash) { return; }
        ui.flash.hidden = false;
        ui.flash.className = 'ddz-flash' + (ok ? ' is-ok' : ' is-err');
        ui.flash.textContent = msg;
        clearTimeout(flash._t);
        flash._t = setTimeout(function () { ui.flash.hidden = true; }, 3000);
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf', cfg.csrf);
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch('/ddz_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, error: '服务器返回异常：' + t.slice(0, 120) }; } });
    }
    function get(action, params) {
        var q = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch('/ddz_api.php?' + q.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, error: '服务器返回异常：' + t.slice(0, 120) }; } });
    }

    function loadScore() {
        get('score').then(function (d) { if (d && d.ok) { ui.score.textContent = '累计积分：' + d.total; } });
    }
    function loadRooms() {
        get('rooms').then(function (d) {
            if (!d || !d.ok) { return; }
            if (!d.rooms.length) { ui.roomList.innerHTML = '<div class="empty">暂无等待中的房间。</div>'; return; }
            ui.roomList.innerHTML = d.rooms.map(function (r) {
                return '<div class="ddz-room-item"><span class="ddz-room-code">#' + esc(r.code) + '</span>' +
                    '<span class="ddz-room-people">' + r.people + '/3 人</span>' +
                    '<button type="button" class="ddz-mini" data-join="' + esc(r.code) + '">加入</button></div>';
            }).join('');
        });
    }
    function isPhone() {
        return window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(max-width: 900px)').matches;
    }
    function portraitNow() {
        return window.innerHeight > window.innerWidth + 24;
    }
    function syncRotateTip() {
        if (!ui.rotateTip) { return; }
        var show = isPhone() && !!state.code && portraitNow();
        ui.rotateTip.hidden = !show;
    }
    function lockLand() {
        if (!isPhone()) { return; }
        document.documentElement.classList.add('ddz-ls');
        document.body.classList.add('ddz-ls');
        var o = screen.orientation;
        if (o && o.lock) {
            o.lock('landscape').catch(function () {});
        } else if (screen.lockOrientation) {
            try { screen.lockOrientation('landscape'); } catch (e) {}
        }
        syncRotateTip();
        setTimeout(fitHand, 60);
    }
    function unlockLand() {
        document.documentElement.classList.remove('ddz-ls');
        document.body.classList.remove('ddz-ls');
        if (ui.rotateTip) { ui.rotateTip.hidden = true; }
        var o = screen.orientation;
        if (o && o.unlock) {
            try { o.unlock(); } catch (e) {}
        }
    }
    function fitHand() {
        var el = ui.myHand;
        if (!el) { return; }
        var cards = el.querySelectorAll('.dcard');
        var n = cards.length;
        if (n < 2) {
            el.style.setProperty('--ddz-overlap', '0px');
            return;
        }
        var w = el.clientWidth || (el.parentNode && el.parentNode.clientWidth) || window.innerWidth;
        var cardW = cards[0].offsetWidth || 46;
        var need = (n * cardW - w) / (n - 1);
        if (need < 16) { need = 16; }
        if (need > cardW - 14) { need = cardW - 14; }
        el.style.setProperty('--ddz-overlap', '-' + Math.round(need) + 'px');
    }
    function leaveRoom() {
        var code = state.code;
        showLobby();
        if (code) { post('leave', { code: code }); }
    }
    function showLobby() {
        stopPoll();
        unlockLand();
        state.code = ''; state.seat = -1; state.data = null; state.selected = {};
        state.hintIdx = 0; state.lastFlyKey = ''; state.playHtml = {}; state.snd = null;
        ui.table.hidden = true; ui.lobby.hidden = false; ui.over.hidden = true;
        loadScore(); loadRooms();
    }
    function enterRoom(code) {
        state.code = code; ui.lobby.hidden = true; ui.table.hidden = false; ui.over.hidden = true;
        state.hintIdx = 0; state.lastFlyKey = ''; state.playHtml = {}; state.snd = null;
        lockLand();
        poll(true);
    }
    function stopPoll() { if (state.timer) { clearTimeout(state.timer); state.timer = null; } }
    function poll(immediate) {
        stopPoll();
        var run = function () {
            if (!state.code) { return; }
            get('state', { code: state.code }).then(function (d) {
                if (!d || !d.ok) {
                    flash((d && d.error) || '房间已结束。', false);
                    setTimeout(showLobby, 1000);
                    return;
                }
                state.seat = d.seat; state.isOwner = d.isOwner; state.data = d.state;
                render(d.state);
                var wait = (d.state.phase === 'play' || d.state.phase === 'bid') ? 1500 : 2500;
                state.timer = setTimeout(run, wait);
            }).catch(function () { state.timer = setTimeout(run, 2500); });
        };
        if (immediate) { run(); } else { state.timer = setTimeout(run, 300); }
    }

    function bubbleFor(s, d) {
        if (d.phase === 'play') {
            if (d.passed && d.passed[s]) { return { text: '不要', cls: '' }; }
            if (d.lastPlay && d.lastPlay.seat === s) { return { text: TYPE_TXT[d.lastPlay.type] || '出牌', cls: 'is-green' }; }
        }
        var be = lastEvent(d, s, ['bid']);
        if (be) { return { text: be.rob ? '抢地主！' : '不抢', cls: be.rob ? 'is-blue' : '' }; }
        return null;
    }

    function backStack(n) {
        var show = Math.min(Math.max(n, 0), 8);
        var html = '<div class="ddz-backs">';
        for (var i = 0; i < show; i++) { html += '<span class="dcard-back"></span>'; }
        html += '<span class="ddz-back-n">' + n + '</span></div>';
        return html;
    }
    function roleText(d, s) {
        if (d.phase === 'waiting' || d.phase === 'bid') { return '玩家'; }
        return d.landlord === s ? '地主' : '农民';
    }
    function renderSeat(el, s, d, isMe) {
        if (!el) { return; }
        var p = d.players[s];
        if (!p) { el.innerHTML = ''; return; }
        var isLandlord = d.landlord === s && d.phase !== 'waiting' && d.phase !== 'bid';
        var active = (d.phase === 'bid' && d.bidTurn === s) || (d.phase === 'play' && d.turn === s);
        var bubble = bubbleFor(s, d);
        var count = d.handCounts ? (d.handCounts[s] || 0) : 0;
        el.innerHTML =
            '<div class="ddz-player-card' + (active ? ' is-active' : '') + '">' +
            '<div class="ddz-avatar-wrap">' +
            (isLandlord ? '<span class="ddz-crown"></span>' : '') +
            (active ? '<span class="ddz-clock"></span>' : '') +
            avatarHTML(p, s) +
            '</div>' +
            '<div class="ddz-p-meta">' +
            '<span class="ddz-p-name">' + esc(p.name) + (isMe ? '（你）' : '') + '</span>' +
            '<span class="ddz-p-role' + (isLandlord ? ' is-landlord' : '') + '">' + roleText(d, s) + '</span>' +
            (isMe || d.phase === 'waiting' ? '' : backStack(count)) +
            '</div>' +
            (bubble ? '<span class="ddz-bubble ' + bubble.cls + '">' + bubble.text + '</span>' : '') +
            '</div>';
    }
    function renderPlayer(el, s, d) { renderSeat(el, s, d, false); }

    function playKey(d) {
        if (d.lastPlay && d.lastPlay.cards) {
            return d.lastPlay.seat + ':' + d.lastPlay.cards.join(',');
        }
        return 'none';
    }
    function renderPlayGroup(el, s, d) {
        if (!el) { return; }
        var html = '';
        if (d.lastPlay && d.lastPlay.seat === s) {
            html = d.lastPlay.cards.map(function (id) { return dcard(id, 'dcard-mini'); }).join('');
        } else if (d.passed && d.passed[s]) {
            html = '<span class="ddz-no">不要</span>';
        }
        if (state.playHtml[s] === html) { return; }
        el.innerHTML = html;
        state.playHtml[s] = html;
        if (d.lastPlay && d.lastPlay.seat === s && playKey(d) !== state.lastFlyKey) {
            var nodes = el.querySelectorAll('.dcard');
            for (var i = 0; i < nodes.length; i++) { nodes[i].classList.add('ddz-fly'); }
        }
    }

    function renderMeInfo(d) {
        renderSeat(ui.meInfo, d.mySeat, d, true);
    }

    function renderHand(d) {
        var ids = (d.myHand || []).slice().sort(function (a, b) { return b - a; });
        var key = ids.join(' ');
        if (ui.myHand.__handKey === key) { return; }
        ui.myHand.__handKey = key;
        ui.myHand.innerHTML = ids.map(function (id) { return dcard(id, state.selected[id] ? 'is-selected' : ''); }).join('');
        requestAnimationFrame(fitHand);
    }

    function renderBottom(d) {
        if (!ui.bottom) { return; }
        if (d.phase === 'waiting' || !d.bottom || !d.bottom.length) {
            ui.bottom.innerHTML = '';
            return;
        }
        if (d.bottomRevealed) {
            ui.bottom.innerHTML = d.bottom.map(function (id) { return dcard(id, 'dcard-mini'); }).join('');
        } else {
            ui.bottom.innerHTML = '<span class="dcard-backface"></span><span class="dcard-backface"></span><span class="dcard-backface"></span>';
        }
    }

    function renderTurn(d) {
        if (!ui.turn) { return; }
        if (d.phase === 'bid') {
            ui.turn.innerHTML = d.bidTurn === d.mySeat ? '<span class="ddz-my-turn">轮到你抢地主</span>' : '抢地主中…';
        } else if (d.phase === 'play') {
            if (d.turn === d.mySeat) {
                ui.turn.innerHTML = '<span class="ddz-my-turn">轮到你出牌</span>';
            } else if (d.lastPlay) {
                ui.turn.textContent = '当前牌型：' + (TYPE_TXT[d.lastPlay.type] || d.lastPlay.type);
            } else {
                ui.turn.textContent = '新一轮出牌';
            }
        } else if (d.phase === 'waiting') {
            ui.turn.textContent = '房间号 ' + state.code;
        } else {
            ui.turn.textContent = '';
        }
    }

    function renderActions(d) {
        ui.myActions.innerHTML = '';
        function btn(label, cls, fn, disabled) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ddz-btn ' + (cls || '');
            b.textContent = label;
            if (disabled) { b.disabled = true; }
            b.addEventListener('click', fn);
            ui.myActions.appendChild(b);
        }
        if (d.phase === 'waiting') {
            if (state.isOwner) {
                btn('开始对局', 'ddz-btn-gold', function () { post('start', { code: state.code }).then(function (r) { if (r.ok) { poll(true); } else { flash(r.error || '开始失败', false); } }); });
            } else {
                ui.myActions.innerHTML = '<span class="ddz-waiting">等待房主开始…</span>';
            }
            return;
        }
        if (d.phase === 'bid') {
            if (d.bidTurn === d.mySeat) {
                btn('抢地主', 'ddz-btn-gold', function () { bid(1); });
                btn('不抢', '', function () { bid(0); });
            } else {
                ui.myActions.innerHTML = '<span class="ddz-waiting">等待其他玩家…</span>';
            }
            return;
        }
        if (d.phase === 'play') {
            if (d.turn === d.mySeat) {
                var canPass = d.lastPlay && d.lastPlay.seat !== d.mySeat;
                if (canPass) { btn('不出', '', function () { pass(); }); }
                btn('提示', 'ddz-btn-hint', function () { hintCards(); });
                btn('出牌', 'ddz-btn-green', function () { playCards(); }, Object.keys(state.selected).length === 0);
            } else {
                var name = (d.players[d.turn] || {}).name || '';
                ui.myActions.innerHTML = '<span class="ddz-waiting">等待 ' + esc(name) + ' 出牌…</span>';
            }
            return;
        }
    }

    function renderOver(d) {
        ui.over.hidden = false;
        var landlord = d.landlord;
        var myWin = (d.winner === landlord) === (landlord === d.mySeat);
        ui.overTitle.textContent = myWin ? '你赢了' : '你输了';
        var rows = d.players.map(function (p) {
            var delta = p.last_delta || 0;
            return '<div class="ddz-over-row"><span>' + esc(p.name) + (p.is_ai ? '（AI）' : '') + '</span>' +
                '<span class="' + (delta > 0 ? 'is-win' : delta < 0 ? 'is-lose' : '') + '">' + (delta > 0 ? '+' : '') + delta + '</span></div>';
        }).join('');
        ui.overBody.innerHTML = '<p>地主：' + esc((d.players[landlord] || {}).name || '') + ' · 倍数 ×' + d.mult + (d.spring ? ' · 春天' : '') + '</p>' + rows;
    }

    function soundsFor(d) {
        var s = state.snd || (state.snd = { playKey: '', passed: {}, bidKey: '', turnKey: '', phase: '', over: false, started: false });
        // 开局/发牌
        if (!s.started && d.phase !== 'waiting') {
            s.started = true;
            sound.start();
        }
        if (d.phase === 'waiting') { s.started = false; }
        // 出牌 / 不要
        var pk = d.lastPlay && d.lastPlay.cards ? (d.lastPlay.seat + ':' + d.lastPlay.cards.join(',')) : '';
        if (pk && pk !== s.playKey) {
            s.playKey = pk;
            sound.byType(d.lastPlay.type, Number(d.lastPlay.seat) === Number(d.mySeat));
        } else if (!pk) {
            s.playKey = '';
        }
        var passed = d.passed || {};
        Object.keys(passed).forEach(function (seat) {
            if (passed[seat] && !s.passed[seat] && Number(seat) !== Number(d.mySeat)) { sound.pass(); }
        });
        s.passed = {};
        Object.keys(passed).forEach(function (seat) { if (passed[seat]) { s.passed[seat] = true; } });
        // 抢地主
        var bids = [];
        (d.events || []).forEach(function (e) { if (e.t === 'bid') { bids.push(e.seat + ':' + (e.rob ? 1 : 0)); } });
        var bidKey = bids.join('|');
        if (bidKey !== s.bidKey) {
            var added = bids.length > (s.bidKey ? s.bidKey.split('|').length : 0);
            s.bidKey = bidKey;
            if (added && bids.length) {
                var lastBid = bids[bids.length - 1];
                if (lastBid.indexOf(':1') !== -1) { sound.bid(); } else { sound.nobid(); }
            }
        }
        // 轮到我
        var turnKey = d.phase + ':' + (d.phase === 'bid' ? d.bidTurn : d.turn);
        if (turnKey !== s.turnKey) {
            s.turnKey = turnKey;
            var myTurn = (d.phase === 'bid' && d.bidTurn === d.mySeat) || (d.phase === 'play' && d.turn === d.mySeat);
            if (myTurn) { sound.turn(); }
        }
        // 结束
        if (d.phase === 'over' && !s.over) {
            s.over = true;
            var landlord = d.landlord;
            var myWin = (d.winner === landlord) === (landlord === d.mySeat);
            setTimeout(function () { if (myWin) { sound.win(); } else { sound.lose(); } }, 250);
        }
        if (d.phase !== 'over') { s.over = false; }
    }

    function render(d) {
        soundsFor(d);
        ui.lobby.hidden = true; ui.table.hidden = false;
        ui.roomCode.textContent = '房间 ' + state.code;
        ui.mult.textContent = '倍数 ×' + d.mult;
        ui.phaseTip.textContent = d.phase === 'bid' ? '抢地主' : d.phase === 'play' ? '对局中' : d.phase === 'waiting' ? '等待中' : '已结束';

        var mySeat = d.mySeat;
        var leftSeat = (mySeat + 2) % 3;
        var rightSeat = (mySeat + 1) % 3;
        renderPlayer(ui.seatLeft, leftSeat, d);
        renderPlayer(ui.seatRight, rightSeat, d);
        renderPlayGroup(ui.playLeft, leftSeat, d);
        renderPlayGroup(ui.playRight, rightSeat, d);
        renderPlayGroup(ui.playMe, mySeat, d);
        var nextFly = playKey(d);
        if (nextFly !== state.lastFlyKey) { state.hintIdx = 0; }
        state.lastFlyKey = nextFly;
        renderBottom(d);
        renderTurn(d);
        renderMeInfo(d);
        renderHand(d);
        if (d.phase === 'over') {
            renderOver(d);
            ui.myActions.innerHTML = '';
        } else {
            ui.over.hidden = true;
            renderActions(d);
        }
    }

    function bid(rob) {
        if (state.busy) { return; }
        state.busy = true;
        post('bid', { code: state.code, rob: rob }).then(function (r) {
            state.busy = false;
            if (!r.ok) { flash(r.error || '操作失败', false); }
            poll(true);
        });
    }
    function pass(cantbeat) {
        if (state.busy) { return; }
        state.busy = true; state.selected = {}; state.hintIdx = 0;
        if (cantbeat) { sound.cantbeat(); } else { sound.pass(); }
        post('pass', { code: state.code }).then(function (r) {
            state.busy = false;
            if (!r.ok) { flash(r.error || '操作失败', false); }
            poll(true);
        });
    }
    function applyHint(ids) {
        state.selected = {};
        (ids || []).forEach(function (id) { state.selected[id] = true; });
        var cards = ui.myHand.querySelectorAll('.dcard');
        for (var i = 0; i < cards.length; i++) {
            var id = Number(cards[i].getAttribute('data-id'));
            cards[i].classList.toggle('is-selected', !!state.selected[id]);
        }
        if (state.data) { renderActions(state.data); }
    }
    function hintCards() {
        if (state.busy || !state.code) { return; }
        state.busy = true;
        post('hint', { code: state.code }).then(function (r) {
            state.busy = false;
            if (!r.ok) { flash(r.error || '提示失败', false); return; }
            var hints = r.hints || [];
            if (!hints.length) {
                if (r.pass) {
                    flash('要不起，已自动不出', false);
                    pass(true);
                } else {
                    flash('没有可出的牌', false);
                }
                return;
            }
            var idx = state.hintIdx % hints.length;
            state.hintIdx += 1;
            applyHint(hints[idx]);
        });
    }
    function playCards() {
        var ids = Object.keys(state.selected).map(Number);
        if (!ids.length || state.busy) { return; }
        state.busy = true; state.hintIdx = 0;
        post('play', { code: state.code, cards: JSON.stringify(ids) }).then(function (r) {
            state.busy = false;
            if (!r.ok) { flash(r.error || '出牌失败', false); }
            state.selected = {};
            poll(true);
        });
    }

    function refreshPlayBtn() {
        var playBtn = ui.myActions.querySelector('.ddz-btn-green');
        if (playBtn) { playBtn.disabled = Object.keys(state.selected).length === 0; }
    }
    function toggleCard(card, force) {
        var id = card.getAttribute('data-id');
        var on = (force === undefined) ? !state.selected[id] : !!force;
        if (on) { state.selected[id] = true; } else { delete state.selected[id]; }
        card.classList.toggle('is-selected', on);
    }

    var dragging = false;
    var dragSelect = null;
    var dragSeen = {};

    function cardFromPoint(x, y) {
        var el = document.elementFromPoint(x, y);
        return el && el.closest ? el.closest('.dcard') : null;
    }
    ui.myHand.addEventListener('pointerdown', function (e) {
        if (e.button !== undefined && e.button !== null && e.button !== 0 && e.pointerType !== 'touch') { return; }
        var card = e.target.closest ? e.target.closest('.dcard') : null;
        if (!card) { return; }
        e.preventDefault();
        dragging = true;
        dragSeen = {};
        var id = card.getAttribute('data-id');
        dragSelect = !state.selected[id];
        dragSeen[id] = true;
        toggleCard(card, dragSelect);
        refreshPlayBtn();
        try { ui.myHand.setPointerCapture(e.pointerId); } catch (err) {}
    });
    ui.myHand.addEventListener('pointermove', function (e) {
        if (!dragging) { return; }
        var card = cardFromPoint(e.clientX, e.clientY);
        if (!card || !ui.myHand.contains(card)) { return; }
        var id = card.getAttribute('data-id');
        if (dragSeen[id]) { return; }
        dragSeen[id] = true;
        toggleCard(card, dragSelect);
        refreshPlayBtn();
    });
    function endDrag() {
        if (!dragging) { return; }
        dragging = false;
        dragSelect = null;
        dragSeen = {};
    }
    ui.myHand.addEventListener('pointerup', endDrag);
    ui.myHand.addEventListener('pointercancel', endDrag);
    window.addEventListener('pointerup', endDrag);

    document.getElementById('btnQuick').addEventListener('click', function () {
        post('quick_start').then(function (r) { if (r.ok) { enterRoom(r.code); } else { flash(r.error || '创建失败', false); } });
    });
    document.getElementById('btnCreate').addEventListener('click', function () {
        post('create').then(function (r) { if (r.ok) { enterRoom(r.code); flash('房间已创建，分享房间号邀请好友。', true); } else { flash(r.error || '创建失败', false); } });
    });
    document.getElementById('btnJoin').addEventListener('click', function () {
        var code = (document.getElementById('ddzCodeInput').value || '').trim().toUpperCase();
        if (!code) { flash('请输入房间号', false); return; }
        post('join', { code: code }).then(function (r) { if (r.ok) { enterRoom(r.code); } else { flash(r.error || '加入失败', false); } });
    });
    document.getElementById('btnRefreshRooms').addEventListener('click', loadRooms);
    ui.roomList.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-join]') : null;
        if (!b) { return; }
        post('join', { code: b.getAttribute('data-join') }).then(function (r) { if (r.ok) { enterRoom(r.code); } else { flash(r.error || '加入失败', false); } });
    });
    document.getElementById('btnLeave').addEventListener('click', leaveRoom);
    var btnLeaveRotate = document.getElementById('btnLeaveRotate');
    if (btnLeaveRotate) { btnLeaveRotate.addEventListener('click', leaveRoom); }
    document.getElementById('btnAgain').addEventListener('click', function () {
        post('restart', { code: state.code }).then(function (r) {
            ui.over.hidden = true;
            if (!r.ok) { flash(r.error || '重开失败', false); }
            poll(true);
        });
    });
    document.getElementById('btnBackLobby').addEventListener('click', leaveRoom);

    var sndBtn = document.getElementById('btnSound');
    function syncSoundBtn() {
        if (!sndBtn) { return; }
        var on = sound.enabled();
        sndBtn.textContent = on ? '🔊 音效' : '🔇 静音';
        sndBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        sndBtn.classList.toggle('is-muted', !on);
    }
    syncSoundBtn();
    if (sndBtn) {
        sndBtn.addEventListener('click', function () {
            sound.set(!sound.enabled());
            syncSoundBtn();
            if (sound.enabled()) { sound.turn(); }
        });
    }
    document.addEventListener('pointerdown', function once() {
        sound.resume();
        document.removeEventListener('pointerdown', once);
    }, { once: true });

    window.addEventListener('resize', function () { syncRotateTip(); fitHand(); });
    window.addEventListener('orientationchange', function () { setTimeout(function () { syncRotateTip(); fitHand(); }, 80); });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden || !state.code) { return; }
        lockLand();
    });

    loadScore();
    loadRooms();
    if (state.code) { enterRoom(state.code); }
})();