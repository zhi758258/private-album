(function () {
    'use strict';

    var me = parseInt((document.body && document.body.getAttribute('data-user-id')) || '0', 10) || 0;
    if (me <= 0) return;

    var csrfEl = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfEl ? csrfEl.content : '';

    var ui = null;
    var pc = null;
    var localStream = null;
    var current = null;
    var pendingRemote = [];
    var queuedLocal = [];
    var lastIce = 0;
    var incomingFor = 0;
    var pollTimer = 0;
    var pollBusy = false;
    var callTick = 0;
    var callStart = 0;
    var wantMic = true;
    var wantCam = true;
    var accepting = false;
    var ignoreUntil = 0;

    var ICE = {
        iceServers: [
            { urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] }
        ]
    };

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        return ('0' + Math.floor(sec / 60)).slice(-2) + ':' + ('0' + (sec % 60)).slice(-2);
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf', csrf);
        Object.keys(data || {}).forEach(function (k) {
            if (data[k] !== null && data[k] !== undefined) fd.append(k, data[k]);
        });
        return fetch('/call_api.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json().catch(function () { return null; }); });
    }

    function ensureUI() {
        if (ui) return ui;
        var layer = document.createElement('div');
        layer.className = 'call-layer';
        layer.hidden = true;
        layer.innerHTML = ''
            + '<div class="call-incoming" hidden>'
            + '  <div class="call-card">'
            + '    <div class="call-avatar"></div>'
            + '    <div class="call-in-name"></div>'
            + '    <p class="call-tip">邀请你视频通话</p>'
            + '    <div class="call-in-actions">'
            + '      <button type="button" class="btn call-accept">接听</button>'
            + '      <button type="button" class="btn ghost-btn call-decline">拒绝</button>'
            + '    </div>'
            + '  </div>'
            + '</div>'
            + '<div class="call-active" hidden>'
            + '  <video class="call-remote" autoplay playsinline></video>'
            + '  <video class="call-local" autoplay playsinline muted></video>'
            + '  <div class="call-top"><span class="call-peer-name"></span><span class="call-status"></span></div>'
            + '  <div class="call-controls">'
            + '    <button type="button" class="call-ctrl" data-call-toggle="mic">静音</button>'
            + '    <button type="button" class="call-ctrl" data-call-toggle="cam">关闭摄像头</button>'
            + '    <button type="button" class="call-ctrl call-hangup">挂断</button>'
            + '  </div>'
            + '</div>'
            + '<div class="call-toast" hidden></div>';
        document.body.appendChild(layer);

        ui = {
            layer: layer,
            incoming: layer.querySelector('.call-incoming'),
            avatar: layer.querySelector('.call-avatar'),
            inName: layer.querySelector('.call-in-name'),
            accept: layer.querySelector('.call-accept'),
            decline: layer.querySelector('.call-decline'),
            active: layer.querySelector('.call-active'),
            remote: layer.querySelector('.call-remote'),
            local: layer.querySelector('.call-local'),
            peerName: layer.querySelector('.call-peer-name'),
            status: layer.querySelector('.call-status'),
            mic: layer.querySelector('[data-call-toggle="mic"]'),
            cam: layer.querySelector('[data-call-toggle="cam"]'),
            hangup: layer.querySelector('.call-hangup'),
            toast: layer.querySelector('.call-toast')
        };

        ui.accept.addEventListener('click', acceptCall);
        ui.decline.addEventListener('click', declineCall);
        ui.hangup.addEventListener('click', hangup);
        ui.mic.addEventListener('click', function () { toggleTrack('audio'); });
        ui.cam.addEventListener('click', function () { toggleTrack('video'); });
        return ui;
    }

    function toast(text) {
        ensureUI();
        ui.toast.textContent = text || '';
        ui.toast.hidden = false;
        clearTimeout(ui.toast.__t);
        ui.toast.__t = setTimeout(function () { if (ui) ui.toast.hidden = true; }, 2600);
    }

    function setStatus(text) {
        ensureUI();
        ui.status.textContent = text || '';
    }

    function showIncoming(peer, id) {
        ensureUI();
        incomingFor = id;
        ui.layer.hidden = false;
        ui.incoming.hidden = false;
        ui.active.hidden = true;
        ui.avatar.innerHTML = peer && peer.avatar_html ? peer.avatar_html : '';
        ui.inName.textContent = peer ? peer.name : '好友';
    }

    function hideIncoming() {
        if (!ui) return;
        ui.incoming.hidden = true;
        if (ui.active.hidden) ui.layer.hidden = true;
        incomingFor = 0;
    }

    function showActive(peer) {
        ensureUI();
        ui.layer.hidden = false;
        ui.incoming.hidden = true;
        ui.active.hidden = false;
        if (peer && peer.name) ui.peerName.textContent = peer.name;
    }

    function startTimer() {
        if (callTick) return;
        callStart = Date.now();
        callTick = setInterval(function () {
            if (!ui) return;
            ui.status.textContent = '通话中 ' + fmt((Date.now() - callStart) / 1000);
        }, 1000);
    }

    function stopTimer() {
        if (callTick) { clearInterval(callTick); callTick = 0; }
    }

    function getLocalMedia() {
        if (localStream) return Promise.resolve(localStream);
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return Promise.reject(new Error('当前浏览器不支持视频通话。'));
        }
        return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: true })
            .catch(function () {
                return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            })
            .then(function (s) { localStream = s; return s; });
    }

    function createPeer(stream) {
        pc = new RTCPeerConnection(ICE);
        stream.getTracks().forEach(function (t) {
            try { pc.addTrack(t, stream); } catch (e) { /* ignore */ }
        });
        pc.ontrack = function (e) {
            ensureUI();
            if (e.streams && e.streams[0]) ui.remote.srcObject = e.streams[0];
        };
        pc.onicecandidate = function (e) {
            if (!e.candidate) return;
            var payload = JSON.stringify(e.candidate);
            if (!current || !current.id) {
                queuedLocal.push(payload);
                return;
            }
            post('candidate', { call_id: current.id, payload: payload });
        };
        pc.onconnectionstatechange = function () {
            if (!pc) return;
            if (pc.connectionState === 'connected') {
                setStatus('通话中');
                startTimer();
            } else if (pc.connectionState === 'failed') {
                toast('连接失败');
                hangup();
            }
        };
    }

    function flushLocal() {
        if (!current) return;
        var list = queuedLocal;
        queuedLocal = [];
        list.forEach(function (payload) {
            post('candidate', { call_id: current.id, payload: payload });
        });
    }

    function flushPendingRemote() {
        if (!pc || !pc.remoteDescription) return;
        var list = pendingRemote;
        pendingRemote = [];
        list.forEach(function (payload) {
            try { pc.addIceCandidate(new RTCIceCandidate(JSON.parse(payload))); } catch (e) { /* ignore */ }
        });
    }

    function toggleTrack(kind) {
        if (!localStream) return;
        var tracks = kind === 'audio' ? localStream.getAudioTracks() : localStream.getVideoTracks();
        var enabled = !tracks.every(function (t) { return !t.enabled; });
        tracks.forEach(function (t) { t.enabled = !enabled; });
        if (kind === 'audio') {
            wantMic = !enabled;
            ui.mic.textContent = wantMic ? '静音' : '取消静音';
            ui.mic.classList.toggle('is-off', !wantMic);
        } else {
            wantCam = !enabled;
            ui.cam.textContent = wantCam ? '关闭摄像头' : '打开摄像头';
            ui.cam.classList.toggle('is-off', !wantCam);
        }
    }

    function startCall(peerId, peerName) {
        if (!peerId || peerId === me) return;
        if (current) { toast('你正在通话中'); return; }
        ensureUI();
        showActive({ name: peerName || '好友' });
        setStatus('正在呼叫…');
        getLocalMedia().then(function (stream) {
            createPeer(stream);
            ui.local.srcObject = stream;
            return pc.createOffer();
        }).then(function (offer) {
            return pc.setLocalDescription(offer).then(function () { return offer; });
        }).then(function () {
            return post('start', { to: peerId, offer: JSON.stringify(pc.localDescription) });
        }).then(function (d) {
            if (!d || !d.ok || !d.call) throw new Error((d && d.error) || '呼叫失败。');
            current = { id: d.call.id, role: 'caller', status: 'ringing', peer: d.call.peer, remoteApplied: false };
            if (d.call.peer) ui.peerName.textContent = d.call.peer.name;
            flushLocal();
            setStatus('等待对方接听…');
            schedulePoll(900);
        }).catch(function (err) {
            cleanup(false);
            toast((err && err.message) || '呼叫失败。');
        });
    }

    function acceptCall() {
        if (!current || current.status !== 'ringing') return;
        var call = current;
        ensureUI();
        accepting = true;
        ui.accept.disabled = true;
        getLocalMedia().then(function (stream) {
            ui.local.srcObject = stream;
            return post('accept', { call_id: call.id });
        }).then(function (d) {
            if (!d || !d.ok || !d.offer) throw new Error((d && d.error) || '接听失败。');
            createPeer(localStream);
            return pc.setRemoteDescription(JSON.parse(d.offer)).then(function () {
                if (current) current.remoteApplied = true;
                flushPendingRemote();
                return pc.createAnswer();
            }).then(function (answer) {
                return pc.setLocalDescription(answer).then(function () {
                    return post('answer', { call_id: call.id, answer: JSON.stringify(pc.localDescription) });
                });
            });
        }).then(function () {
            accepting = false;
            if (current) current.status = 'accepted';
            hideIncoming();
            showActive(current ? current.peer : null);
            setStatus('连接中…');
            lastIce = 0;
            schedulePoll(800);
        }).catch(function (err) {
            accepting = false;
            ui.accept.disabled = false;
            if (current) post('decline', { call_id: current.id });
            cleanup(false);
            toast((err && err.message) || '接听失败。');
        });
    }

    function declineCall() {
        var id = incomingFor || (current && current.id) || 0;
        if (id) post('decline', { call_id: id });
        ignoreUntil = Date.now() + 2500;
        hideIncoming();
        cleanup(false);
        schedulePoll(3000);
    }

    function hangup() {
        var id = current && current.id;
        if (id) post('end', { call_id: id });
        ignoreUntil = Date.now() + 2500;
        cleanup(false);
        schedulePoll(3000);
    }

    function cleanup(remoteEnded) {
        stopTimer();
        if (pc) { try { pc.close(); } catch (e) { /* ignore */ } pc = null; }
        if (localStream) {
            localStream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) { /* ignore */ } });
            localStream = null;
        }
        if (ui) {
            ui.remote.srcObject = null;
            ui.local.srcObject = null;
            ui.incoming.hidden = true;
            ui.active.hidden = true;
            ui.layer.hidden = true;
            ui.accept.disabled = false;
            ui.mic.textContent = '静音';
            ui.cam.textContent = '关闭摄像头';
            ui.mic.classList.remove('is-off');
            ui.cam.classList.remove('is-off');
        }
        current = null;
        pendingRemote = [];
        queuedLocal = [];
        incomingFor = 0;
        lastIce = 0;
        if (remoteEnded) toast('通话已结束');
    }

    function handlePoll(d) {
        if (Date.now() < ignoreUntil) return;
        var call = d && d.call ? d.call : null;
        if (call) {
            if (current && current.id !== call.id) {
                cleanup(false);
            }
            if (!current) {
                current = { id: call.id, role: call.role, status: call.status, peer: call.peer, remoteApplied: false };
            } else {
                current.status = call.status;
                if (call.peer) current.peer = call.peer;
            }

            if (call.role === 'callee' && call.status === 'ringing') {
                if (!accepting && incomingFor !== call.id) showIncoming(call.peer, call.id);
                setStatus('来电');
            } else if (call.status === 'ringing' && call.role === 'caller') {
                showActive(call.peer);
                setStatus('等待对方接听…');
            } else if (call.status === 'accepted') {
                hideIncoming();
                showActive(call.peer);
                if (call.role === 'caller' && call.answer && !current.remoteApplied && pc) {
                    pc.setRemoteDescription(JSON.parse(call.answer)).then(function () {
                        if (current) current.remoteApplied = true;
                        flushPendingRemote();
                    }).catch(function () { /* ignore */ });
                }
                if (ui && !callTick && pc && pc.connectionState === 'connected') startTimer();
                if (!callTick) setStatus('已接通');
            }

            (d.candidates || []).forEach(function (c) {
                lastIce = Math.max(lastIce, c.id);
                if (pc && pc.remoteDescription) {
                    try { pc.addIceCandidate(new RTCIceCandidate(JSON.parse(c.payload))); } catch (e) { /* ignore */ }
                } else {
                    pendingRemote.push(c.payload);
                }
            });
        } else if (current) {
            cleanup(true);
        }
    }

    function poll() {
        if (pollBusy) return;
        pollBusy = true;
        fetch('/call_api.php?after_ice=' + lastIce, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (d) { if (d && d.ok) handlePoll(d); })
            .catch(function () {})
            .then(function () {
                pollBusy = false;
                schedulePoll(current ? 1400 : 3500);
            });
    }

    function schedulePoll(delay) {
        if (pollTimer) clearTimeout(pollTimer);
        pollTimer = setTimeout(poll, delay || 3500);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.call-start-btn') : null;
        if (!btn) return;
        e.preventDefault();
        startCall(
            parseInt(btn.getAttribute('data-call-user'), 10) || 0,
            btn.getAttribute('data-call-name') || '好友'
        );
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });

    window.addEventListener('pagehide', function () {
        if (!current || !current.id || !navigator.sendBeacon) return;
        var fd = new FormData();
        fd.append('action', 'end');
        fd.append('csrf', csrf);
        fd.append('call_id', current.id);
        try { navigator.sendBeacon('/call_api.php', fd); } catch (e) { /* ignore */ }
    });

    window.SiteCall = {
        start: startCall,
        hangup: hangup
    };

    ensureUI();
    schedulePoll(1200);
})();
