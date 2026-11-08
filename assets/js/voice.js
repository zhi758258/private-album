(function () {
    'use strict';

    var MAX_SECONDS = 60;
    var MICS = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/ogg',
        'audio/mp4',
        'audio/aac'
    ];

    var MIC = '<svg viewBox="0 0 24 24" class="mic-icon" aria-hidden="true"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/><path d="M18.5 11.5a6.5 6.5 0 0 1-13 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 18v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

    function pickMime() {
        if (typeof window.MediaRecorder === 'undefined') return '';
        for (var i = 0; i < MICS.length; i++) {
            try {
                if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(MICS[i])) {
                    return MICS[i];
                }
            } catch (e) { /* ignore */ }
        }
        return '';
    }

    function extFor(mime) {
        mime = String(mime || '').toLowerCase();
        if (mime.indexOf('ogg') !== -1) return 'ogg';
        if (mime.indexOf('mp4') !== -1 || mime.indexOf('m4a') !== -1) return 'm4a';
        if (mime.indexOf('aac') !== -1) return 'aac';
        if (mime.indexOf('mpeg') !== -1 || mime.indexOf('mp3') !== -1) return 'mp3';
        if (mime.indexOf('wav') !== -1) return 'wav';
        return 'webm';
    }

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
    }

    function ensureFields(form) {
        var f = form.querySelector('input[name="voice_file"]');
        var d = form.querySelector('input[name="voice_dur"]');
        if (!f) { f = document.createElement('input'); f.type = 'hidden'; f.name = 'voice_file'; form.appendChild(f); }
        if (!d) { d = document.createElement('input'); d.type = 'hidden'; d.name = 'voice_dur'; form.appendChild(d); }
        return { file: f, dur: d };
    }

    function reset(form) {
        if (!form) return;
        var st = form.__voice;
        if (st) {
            st.gen += 1;
            st.cancel = true;
            st.uploading = false;
            if (st.recorder && st.recorder.state !== 'inactive') {
                try { st.recorder.stop(); } catch (e) { /* ignore */ }
            }
            st.stopTimer();
            st.stopStream();
            if (st.preview) st.preview.hidden = true;
            if (st.btn) {
                st.btn.classList.remove('is-recording');
                st.btn.disabled = false;
                st.btn.title = '语音';
            }
            if (st.audio && st.audio.src) {
                try { URL.revokeObjectURL(st.audio.src); } catch (e) { /* ignore */ }
                st.audio.removeAttribute('src');
            }
            if (st.blobUrl) {
                try { URL.revokeObjectURL(st.blobUrl); } catch (e) { /* ignore */ }
                st.blobUrl = '';
            }
        }
        var f = ensureFields(form);
        f.file.value = '';
        f.dur.value = '';
    }

    function setup(form) {
        if (form.getAttribute('data-voice-bound')) return;
        form.setAttribute('data-voice-bound', '1');

        var ta = form.querySelector('textarea');
        var fields = ensureFields(form);

        var composer = ta && ta.closest ? ta.closest('.emoji-composer') : null;
        var anchor = composer || ta || form;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'emoji-toggle voice-toggle';
        btn.title = '语音';
        btn.setAttribute('aria-label', '录制语音');
        btn.innerHTML = MIC;

        var preview = document.createElement('div');
        preview.className = 'voice-preview';
        preview.hidden = true;
        preview.innerHTML = '<div class="voice-rec"><span class="voice-dot"></span><span class="voice-rec-time">0:00</span></div>'
            + '<audio class="voice-audio" controls preload="metadata"></audio>'
            + '<button type="button" class="voice-clear" aria-label="清除语音">×</button>';

        var bar = composer ? composer.querySelector('.emoji-bar') : null;
        if (bar) {
            bar.appendChild(btn);
            composer.parentNode.insertBefore(preview, composer.nextSibling);
        } else {
            var voiceBar = document.createElement('div');
            voiceBar.className = 'emoji-bar voice-bar';
            voiceBar.appendChild(btn);
            anchor.parentNode.insertBefore(voiceBar, anchor.nextSibling);
            anchor.parentNode.insertBefore(preview, voiceBar.nextSibling);
        }

        var recRow = preview.querySelector('.voice-rec');
        var recTime = preview.querySelector('.voice-rec-time');
        var audio = preview.querySelector('.voice-audio');
        var clear = preview.querySelector('.voice-clear');

        var state = {
            recorder: null,
            stream: null,
            chunks: [],
            seconds: 0,
            timer: 0,
            gen: 0,
            cancel: false,
            uploading: false,
            blobUrl: '',
            btn: btn,
            preview: preview,
            audio: audio,
            stopTimer: function () {
                if (state.timer) { clearInterval(state.timer); state.timer = 0; }
            },
            stopStream: function () {
                if (state.stream) {
                    state.stream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) { /* ignore */ } });
                    state.stream = null;
                }
            }
        };
        form.__voice = state;

        function setRecording(on) {
            btn.classList.toggle('is-recording', on);
            btn.title = on ? '停止录音' : '语音';
            btn.disabled = false;
            recRow.hidden = !on;
            audio.hidden = on;
            clear.hidden = on;
            preview.hidden = false;
        }

        function stopRecording() {
            if (state.recorder && state.recorder.state !== 'inactive') {
                try { state.recorder.stop(); } catch (e) { /* ignore */ }
            }
            state.stopTimer();
        }

        function startRecording() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !pickMime()) {
                alert('当前浏览器不支持录音，请更换浏览器。');
                return;
            }
            state.cancel = false;
            state.chunks = [];
            state.seconds = 0;
            recTime.textContent = '0:00';
            preview.hidden = false;
            btn.disabled = true;
            btn.title = '正在启动…';

            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                state.stream = stream;
                var mime = pickMime();
                var opts = mime ? { mimeType: mime } : {};
                try {
                    state.recorder = new MediaRecorder(stream, opts);
                } catch (e) {
                    state.recorder = new MediaRecorder(stream);
                }
                state.recorder.addEventListener('dataavailable', function (ev) {
                    if (ev.data && ev.data.size > 0) state.chunks.push(ev.data);
                });
                state.recorder.addEventListener('stop', finalize);
                state.recorder.start();
                setRecording(true);
                state.timer = setInterval(function () {
                    state.seconds += 1;
                    recTime.textContent = fmt(state.seconds);
                    if (state.seconds >= MAX_SECONDS) stopRecording();
                }, 1000);
            }).catch(function () {
                btn.disabled = false;
                btn.title = '语音';
                preview.hidden = true;
                alert('无法访问麦克风，请检查录音权限。');
            });
        }

        function finalize() {
            state.stopTimer();
            state.stopStream();
            var cancelling = state.cancel;
            state.cancel = false;
            var chunks = state.chunks;
            state.chunks = [];
            state.recorder = null;
            setRecording(false);

            if (cancelling) {
                preview.hidden = true;
                btn.disabled = false;
                return;
            }
            var mime = (chunks[0] && chunks[0].type) || pickMime() || 'audio/webm';
            var blob = new Blob(chunks, { type: mime });
            if (!blob.size) {
                preview.hidden = true;
                btn.disabled = false;
                alert('没有录到声音，请重试。');
                return;
            }
            var dur = Math.max(1, state.seconds);
            upload(blob, mime, dur);
        }

        function upload(blob, mime, dur) {
            var gen = state.gen;
            state.uploading = true;
            btn.disabled = true;
            btn.title = '上传中…';
            audio.hidden = true;
            clear.hidden = true;

            if (state.blobUrl) { try { URL.revokeObjectURL(state.blobUrl); } catch (e) { /* ignore */ } }
            state.blobUrl = URL.createObjectURL(blob);
            var ext = extFor(mime);
            var fd = new FormData();
            fd.append('voice', blob, 'voice.' + ext);
            var csrf = form.querySelector('input[name="csrf"]');
            if (csrf) fd.append('csrf', csrf.value);

            fetch('/voice_upload.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (d) {
                    if (gen !== state.gen) return;
                    state.uploading = false;
                    btn.disabled = false;
                    btn.title = '语音';
                    if (!d || !d.ok || !d.file) {
                        preview.hidden = true;
                        alert((d && d.error) || '语音上传失败，请重试。');
                        return;
                    }
                    fields.file.value = d.file;
                    fields.dur.value = String(dur);
                    audio.src = state.blobUrl;
                    audio.hidden = false;
                    clear.hidden = false;
                    recRow.hidden = true;
                    preview.hidden = false;
                })
                .catch(function () {
                    if (gen !== state.gen) return;
                    state.uploading = false;
                    btn.disabled = false;
                    btn.title = '语音';
                    preview.hidden = true;
                    alert('语音上传失败，请重试。');
                });
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (state.uploading) return;
            if (state.recorder && state.recorder.state === 'recording') {
                stopRecording();
            } else {
                startRecording();
            }
        });

        clear.addEventListener('click', function (e) {
            e.preventDefault();
            reset(form);
        });
    }

    var currentAudio = null;
    var currentBtn = null;

    function stopCurrent() {
        if (currentAudio) {
            try { currentAudio.pause(); } catch (e) { /* ignore */ }
        }
        if (currentBtn) currentBtn.classList.remove('is-playing');
        currentAudio = null;
        currentBtn = null;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.voice-msg') : null;
        if (!btn) return;
        e.preventDefault();
        var src = btn.getAttribute('data-src');
        if (!src) return;

        if (currentBtn === btn && currentAudio) {
            if (currentAudio.paused) {
                currentAudio.play().catch(function () {});
                btn.classList.add('is-playing');
            } else {
                currentAudio.pause();
                btn.classList.remove('is-playing');
            }
            return;
        }

        stopCurrent();
        var audio = new Audio(src);
        currentAudio = audio;
        currentBtn = btn;
        btn.classList.add('is-playing');
        audio.addEventListener('ended', function () {
            btn.classList.remove('is-playing');
            if (currentAudio === audio) { currentAudio = null; currentBtn = null; }
        });
        audio.addEventListener('error', function () {
            btn.classList.remove('is-playing');
            if (currentAudio === audio) { currentAudio = null; currentBtn = null; }
        });
        audio.play().catch(function () {
            btn.classList.remove('is-playing');
            if (currentAudio === audio) { currentAudio = null; currentBtn = null; }
        });
    });

    function init() {
        document.querySelectorAll('form[data-voice]').forEach(setup);
    }

    window.SiteVoice = { reset: reset };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
