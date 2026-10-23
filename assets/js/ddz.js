(function () {
    'use strict';
    var cfg = window.DDZ || {};
    var app = document.getElementById('ddzApp');
    if (!app) { return; }

    var ui = {};
    var uiMap = {
        lobby: 'ddzLobby', table: 'ddzTable', score: 'ddzScore', roomList: 'ddzRoomList',
        flash: 'ddzFlash', roomCode: 'ddzRoomCode', mult: 'ddzMult', phaseTip: 'ddzPhaseTip',
        seatTop: 'seatTop', seatLeft: 'seatLeft', seatRight: 'seatRight', bottom: 'ddzBottom',
        turn: 'ddzTurn', myHand: 'myHand', myActions: 'myActions', over: 'ddzOver',
        overTitle: 'ddzOverTitle', overBody: 'ddzOverBody'
    };
    Object.keys(uiMap).forEach(function (k) { ui[k] = document.getElementById(uiMap[k]); });

    var state = { code: cfg.code || '', seat: -1, isOwner: false, data: null, selected: {}, timer: null, busy: false };

    var RANK_TXT = { 11: 'J', 12: 'Q', 13: 'K', 14: 'A', 15: '2' };
    var SUITS = ['♠', '♥', '♣', '♦'];
    var TYPE_TXT = {
        single: '单张', pair: '对子', trio: '三张', trio1: '三带一', trio2: '三带二',
        bomb: '炸弹', rocket: '王炸', straight: '顺子', tube: '连对', plane: '飞机',
        plane1: '飞机带单', plane2: '飞机带对', four1: '四带二', four2: '四带两对'
    };

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
        return '<span class="' + cls + (red ? ' is-red' : '') + '" data-id="' + id + '"><span class="dcard-rank">' + rankText(r) + '</span><span class="dcard-suit">' + SUITS[s] + '</span></span>';
    }
    function avatarHTML(p) {
        if (!p) { return ''; }
        if (p.is_ai) { return '<span class="ddz-avatar is-ai">AI</span>'; }
        var ch = p.name ? String(p.name).slice(0, 1) : '?';
        return '<span class="ddz-avatar">' + esc(ch) + '</span>';
    }
    function flash(msg, ok) {
        ui.flash.hidden = false;
        ui.flash.className = 'ddz-flash' + (ok ? ' is-ok' : ' is-err');
        ui.flash.textContent = msg;
        clearTimeout(flash._t);
        flash._t = setTimeout(function () { ui.flash.hidden = true; }, 2800);
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf', cfg.csrf);
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch('/ddz_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: '网络错误' }; }); });
    }
    function get(action, params) {
        var q = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch('/ddz_api.php?' + q.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: '网络错误' }; }); });
    }

    function loadScore() {
        get('score').then(function (d) {
            if (d && d.ok) { ui.score.textContent = '累计积分：' + d.total; }
        });
    }
    function loadRooms() {
        get('rooms').then(function (d) {
            if (!d || !d.ok) { return; }
            if (!d.rooms.length) { ui.roomList.innerHTML = '<div class="empty">暂无等待中的房间。</div>'; return; }
            ui.roomList.innerHTML = d.rooms.map(function (r) {
                return '<div class="ddz-room-item"><span class="ddz-room-code">#' + esc(r.code) + '</span>' +
                    '<span class="ddz-room-people">' + r.people + '/3 人</span>' +
                    '<button type="button" class="btn" data-join="' + esc(r.code) + '">加入</button></div>';
            }).join('');
        });
    }
    function showLobby() {
        stopPoll();
        state.code = '';
        state.seat = -1;
        state.data = null;
        state.selected = {};
        ui.table.hidden = true;
        ui.lobby.hidden = false;
        ui.over.hidden = true;
        loadScore();
        loadRooms();
    }
    function enterRoom(code) {
        state.code = code;
        ui.lobby.hidden = true;
        ui.table.hidden = false;
        ui.over.hidden = true;
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
                    setTimeout(showLobby, 800);
                    return;
                }
                state.seat = d.seat;
                state.isOwner = d.isOwner;
                state.data = d.state;
                render(d.state);
                var wait = (d.state.phase === 'play' || d.state.phase === 'bid') ? 1500 : 2500;
                state.timer = setTimeout(run, wait);
            }).catch(function () {
                state.timer = setTimeout(run, 2500);
            });
        };
        if (immediate) { run(); } else { state.timer = setTimeout(run, 300); }
    }

    function renderSeat(el, s, d, pos) {
        if (!el) { return; }
        var p = d.players[s];
        if (!p) { el.innerHTML = ''; el.className = 'ddz-seat ddz-seat-' + pos; return; }
        var isLandlord = d.landlord === s;
        var active = (d.phase === 'bid' && d.bidTurn === s) || (d.phase === 'play' && d.turn === s);
        var play = '';
        if (d.lastPlay && d.lastPlay.seat === s) {
            play = d.lastPlay.cards.map(function (id) { return dcard(id, 'dcard-mini'); }).join('');
        } else if (d.passed && d.passed[s]) {
            play = '<span class="ddz-no">不要</span>';
        }
        el.className = 'ddz-seat ddz-seat-' + pos + (active ? ' is-active' : '') + (isLandlord ? ' is-landlord' : '');
        el.innerHTML =
            '<div class="ddz-seat-head">' + avatarHTML(p) +
            '<div class="ddz-seat-info"><span class="ddz-seat-name">' + esc(p.name) + '</span>' +
            '<span class="ddz-seat-role">' + (isLandlord ? '地主' : '农民') + '</span></div></div>' +
            '<div class="ddz-seat-count">剩 ' + d.handCounts[s] + ' 张</div>' +
            '<div class="ddz-seat-play">' + play + '</div>';
    }

    function renderActions(d) {
        ui.myActions.innerHTML = '';
        function btn(label, cls, fn, disabled) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn ' + (cls || '');
            b.textContent = label;
            if (disabled) { b.disabled = true; }
            b.addEventListener('click', fn);
            ui.myActions.appendChild(b);
        }
        if (d.phase === 'waiting') {
            if (state.isOwner) {
                btn('开始对局', 'ddz-big', function () { post('start', { code: state.code }).then(function (r) { if (r.ok) { poll(true); } else { flash(r.error || '开始失败', false); } }); });
            } else {
                var w = document.createElement('span');
                w.className = 'ddz-waiting';
                w.textContent = '等待房主开始…';
                ui.myActions.appendChild(w);
            }
            return;
        }
        if (d.phase === 'bid') {
            if (d.bidTurn === d.mySeat) {
                btn('抢地主', '', function () { bid(1); });
                btn('不抢', 'ghost-btn', function () { bid(0); });
            } else {
                var w1 = document.createElement('span');
                w1.className = 'ddz-waiting';
                w1.textContent = '等待其他玩家抢地主…';
                ui.myActions.appendChild(w1);
            }
            return;
        }
        if (d.phase === 'play') {
            if (d.turn === d.mySeat) {
                var canPass = d.lastPlay && d.lastPlay.seat !== d.mySeat;
                btn('出牌', '', function () { playCards(); }, Object.keys(state.selected).length === 0);
                if (canPass) { btn('不出', 'ghost-btn', function () { pass(); }); }
            } else {
                var w2 = document.createElement('span');
                w2.className = 'ddz-waiting';
                w2.textContent = '等待 ' + esc((d.players[d.turn] || {}).name || '') + ' 出牌…';
                ui.myActions.appendChild(w2);
            }
            return;
        }
    }

    function renderHand(d) {
        var ids = (d.myHand || []).slice().sort(function (a, b) { return b - a; });
        ui.myHand.innerHTML = ids.map(function (id) { return dcard(id, state.selected[id] ? 'is-selected' : ''); }).join('');
    }

    function renderBottom(d) {
        if (d.phase === 'over' && d.bottom && d.bottom.length) {
            ui.bottom.innerHTML = '<span class="ddz-bottom-label">底牌</span>' + d.bottom.map(function (id) { return dcard(id, 'dcard-mini'); }).join('');
        } else {
            ui.bottom.innerHTML = '';
        }
    }

    function renderTurn(d) {
        if (d.phase === 'bid') {
            ui.turn.textContent = '抢地主阶段';
        } else if (d.phase === 'play') {
            if (d.lastPlay) {
                ui.turn.textContent = '当前牌型：' + (TYPE_TXT[d.lastPlay.type] || d.lastPlay.type);
            } else {
                ui.turn.textContent = '新一轮出牌';
            }
        } else if (d.phase === 'waiting') {
            ui.turn.textContent = '房间号 ' + state.code + '，邀请好友加入';
        } else {
            ui.turn.textContent = '';
        }
    }

    function renderOver(d) {
        ui.over.hidden = false;
        var landlord = d.landlord;
        var myWin = (d.winner === landlord) === (landlord === d.mySeat);
        ui.overTitle.textContent = myWin ? '🎉 你赢了' : '😵 你输了';
        var rows = d.players.map(function (p) {
            var delta = p.last_delta || 0;
            return '<div class="ddz-over-row"><span>' + esc(p.name) + (p.is_ai ? '（AI）' : '') + '</span>' +
                '<span class="' + (delta > 0 ? 'is-win' : delta < 0 ? 'is-lose' : '') + '">' + (delta > 0 ? '+' : '') + delta + '</span></div>';
        }).join('');
        ui.overBody.innerHTML =
            '<p>地主：' + esc((d.players[landlord] || {}).name || '') + ' · 倍数 ×' + d.mult + (d.spring ? ' · 春天' : '') + '</p>' +
            rows;
    }

    function render(d) {
        ui.lobby.hidden = true;
        ui.table.hidden = false;
        ui.roomCode.textContent = '房间 ' + state.code;
        ui.mult.textContent = '倍数 ×' + d.mult;
        ui.phaseTip.textContent = d.phase === 'bid' ? '抢地主' : d.phase === 'play' ? '对局中' : d.phase === 'waiting' ? '等待中' : '已结束';

        var mySeat = d.mySeat;
        renderSeat(ui.seatRight, (mySeat + 1) % 3, d, 'right');
        renderSeat(ui.seatLeft, (mySeat + 2) % 3, d, 'left');
        renderSeat(ui.seatTop, -1, d, 'top');
        if (ui.seatTop) { ui.seatTop.innerHTML = ''; }
        renderBottom(d);
        renderTurn(d);
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
    function pass() {
        if (state.busy) { return; }
        state.busy = true;
        state.selected = {};
        post('pass', { code: state.code }).then(function (r) {
            state.busy = false;
            if (!r.ok) { flash(r.error || '操作失败', false); }
            poll(true);
        });
    }
    function playCards() {
        var ids = Object.keys(state.selected).map(Number);
        if (!ids.length || state.busy) { return; }
        state.busy = true;
        post('play', { code: state.code, cards: JSON.stringify(ids) }).then(function (r) {
            state.busy = false;
            if (!r.ok) { flash(r.error || '出牌失败', false); }
            state.selected = {};
            poll(true);
        });
    }

    ui.myHand.addEventListener('click', function (e) {
        var card = e.target.closest ? e.target.closest('.dcard') : null;
        if (!card) { return; }
        var id = card.getAttribute('data-id');
        if (state.selected[id]) { delete state.selected[id]; } else { state.selected[id] = true; }
        card.classList.toggle('is-selected');
    });

    document.getElementById('btnQuick').addEventListener('click', function () {
        post('quick_start').then(function (r) { if (r.ok) { enterRoom(r.code); } else { flash(r.error || '创建失败', false); } });
    });
    document.getElementById('btnCreate').addEventListener('click', function () {
        post('create').then(function (r) { if (r.ok) { enterRoom(r.code); flash('房间已创建，分享房间号邀请好友。', true); } else { flash(r.error || '创建失败', false); } });
    });
    document.getElementById('btnJoin').addEventListener('click', function () {
        var code = (document.getElementById('ddzCodeInput').value || '').trim().toUpperCase();
        if (!code) { flash('请输入房间号', false); return; }
        post('join', { code: code }).then(function (r) {
            if (r.ok) { enterRoom(r.code); } else { flash(r.error || '加入失败', false); }
        });
    });
    document.getElementById('btnRefreshRooms').addEventListener('click', loadRooms);
    ui.roomList.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-join]') : null;
        if (!b) { return; }
        post('join', { code: b.getAttribute('data-join') }).then(function (r) {
            if (r.ok) { enterRoom(r.code); } else { flash(r.error || '加入失败', false); }
        });
    });
    document.getElementById('btnLeave').addEventListener('click', function () {
        var code = state.code;
        post('leave', { code: code }).then(function () { showLobby(); });
    });
    document.getElementById('btnAgain').addEventListener('click', function () {
        post('restart', { code: state.code }).then(function (r) {
            ui.over.hidden = true;
            if (!r.ok) { flash(r.error || '重开失败', false); }
            poll(true);
        });
    });
    document.getElementById('btnBackLobby').addEventListener('click', function () {
        post('leave', { code: state.code }).then(function () { showLobby(); });
    });

    loadScore();
    loadRooms();
    if (state.code) { enterRoom(state.code); }
})();