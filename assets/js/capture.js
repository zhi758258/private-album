(function () {
    'use strict';

    var MAX_SECONDS = 60;
    var MAX_BYTES = 25 * 1024 * 1024;
    var VIDEO_MIMES = [
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=vp8,opus',
        'video/webm',
        'video/mp4'
    ];

    var CAM = '<svg viewBox="0 0 24 24" class="cam-icon" aria-hidden="true"><path d="M4 7h3l2-2h6l2 2h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';

    var modal = null;
    var live, shot, review, timerBox, timerText, shutter, retake, useBtn, hint, tabs, pickBtn;
    var activeForm = null;
    var mode = 'photo';
    var stream = null;
    var recorder = null;
    var chunks = [];
    var seconds = 0;
    var tick = 0;
    var blob = null;
    var blobUrl = '';
    var blobKind = '';
    var blobDur = 0;
    var blobName = '';
    var pickInput = null;
    var uploading = false;
    var discard = false;

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
    }

    function pickVideoMime() {
        if (typeof window.MediaRecorder === 'undefined') return '';
        for (var i = 0; i < VIDEO_MIMES.length; i++) {
            try {
                if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(VIDEO_MIMES[i])) {
                    return VIDEO_MIMES[i];
                }
            } catch (e) { /* ignore */ }
        }
        return '';
    }

    function videoExt(mime) {
        return String(mime || '').toLowerCase().indexOf('mp4') !== -1 ? 'mp4' : 'webm';
    }

    function ensureModal() {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.className = 'capture-modal';
        modal.hidden = true;
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-label', '拍照或录像');
        modal.innerHTML = ''
            + '<div class="capture-panel">'
            + '  <div class="capture-head">'
            + '    <div class="capture-tabs">'
            + '      <button type="button" class="capture-tab is-on" data-mode="photo">拍照</button>'
            + '      <button type="button" class="capture-tab" data-mode="video">录像</button>'
            + '      <button type="button" class="capture-tab" data-mode="album">相册</button>'
            + '    </div>'
            + '    <button type="button" class="capture-close" aria-label="关闭">×</button>'
            + '  </div>'
            + '  <div class="capture-stage">'
            + '    <video class="capture-live" autoplay playsinline muted></video>'
            + '    <img class="capture-shot" alt="照片预览" hidden>'
            + '    <video class="capture-review" controls playsinline hidden></video>'
            + '    <div class="capture-timer" hidden><span class="capture-dot"></span><span class="capture-time">0:00</span></div>'
            + '  </div>'
            + '  <div class="capture-actions">'
            + '    <button type="button" class="btn capture-shutter">拍照</button>'
            + '    <button type="button" class="btn capture-pick" hidden>从相册选择</button>'
            + '    <button type="button" class="btn ghost-btn capture-retake" hidden>重拍</button>'
            + '    <button type="button" class="btn capture-use" hidden>发送</button>'
            + '  </div>'
            + '  <input type="file" class="capture-file" accept="image/*,video/*" hidden>'
            + '  <p class="capture-hint"></p>'
            + '</div>';
        document.body.appendChild(modal);

        live = modal.querySelector('.capture-live');
        shot = modal.querySelector('.capture-shot');
        review = modal.querySelector('.capture-review');
        timerBox = modal.querySelector('.capture-timer');
        timerText = modal.querySelector('.capture-time');
        shutter = modal.querySelector('.capture-shutter');
        retake = modal.querySelector('.capture-retake');
        useBtn = modal.querySelector('.capture-use');
        hint = modal.querySelector('.capture-hint');
        tabs = modal.querySelectorAll('.capture-tab');
        pickBtn = modal.querySelector('.capture-pick');
        pickInput = modal.querySelector('.capture-file');

        modal.querySelector('.capture-close').addEventListener('click', close);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) close();
        });
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                if (recorder && recorder.state === 'recording') return;
                mode = t.getAttribute('data-mode');
                tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
                if (mode === 'album') {
                    resetLive();
                    stopStream();
                    live.hidden = true;
                    shutter.hidden = true;
                    pickBtn.hidden = false;
                    setHint('从相册或电脑里选择照片、视频。');
                    openPicker();
                    return;
                }
                resetLive();
                requestStream();
            });
        });
        shutter.addEventListener('click', function () {
            if (uploading) return;
            if (mode === 'photo') {
                capturePhoto();
            } else if (recorder && recorder.state === 'recording') {
                stopVideo();
            } else {
                startVideo();
            }
        });
        pickBtn.addEventListener('click', openPicker);
        pickInput.addEventListener('change', onPickedFile);
        retake.addEventListener('click', function () {
            if (mode === 'album') {
                resetLive();
                stopStream();
                live.hidden = true;
                shutter.hidden = true;
                if (pickBtn) pickBtn.hidden = false;
                setHint('从相册或电脑里选择照片、视频。');
                openPicker();
                return;
            }
            resetLive();
            if (!stream) requestStream();
        });
        useBtn.addEventListener('click', useMedia);

        return modal;
    }

    function setHint(text, isError) {
        hint.textContent = text || '';
        hint.classList.toggle('is-error', !!isError);
    }

    function requestStream() {
        stopStream();
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setHint('当前浏览器不支持摄像头，请更换浏览器。', true);
            shutter.disabled = true;
            return;
        }
        var constraints = mode === 'video'
            ? { video: { facingMode: 'user' }, audio: true }
            : { video: { facingMode: 'user' }, audio: false };
        shutter.disabled = true;
        setHint('正在打开摄像头…');
        var request = navigator.mediaDevices.getUserMedia(constraints);
        var silent = false;
        if (mode === 'video') {
            request = request.catch(function (err) {
                return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }).then(function (s) {
                    silent = true;
                    return s;
                });
            });
        }
        request.then(function (s) {
            stream = s;
            live.srcObject = s;
            live.hidden = false;
            var p = live.play();
            if (p && p.catch) p.catch(function () {});
            shutter.disabled = false;
            setHint(silent ? '麦克风不可用，将录制无声视频。' : '');
            updateShutter();
        }).catch(function () {
            shutter.disabled = true;
            setHint('无法访问摄像头。请允许权限，并确保通过 HTTPS 访问。', true);
        });
    }

    function updateShutter() {
        if (mode === 'photo') {
            shutter.textContent = '拍照';
        } else {
            shutter.textContent = '开始录像';
        }
        shutter.classList.remove('is-recording');
    }

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) { /* ignore */ } });
            stream = null;
        }
        if (live) live.srcObject = null;
    }

    function revokeBlob() {
        if (blobUrl) {
            try { URL.revokeObjectURL(blobUrl); } catch (e) { /* ignore */ }
            blobUrl = '';
        }
    }

    function resetLive() {
        clearInterval(tick);
        tick = 0;
        if (recorder && recorder.state !== 'inactive') {
            discard = true;
            try { recorder.stop(); } catch (e) { /* ignore */ }
        } else {
            discard = false;
        }
        recorder = null;
        chunks = [];
        seconds = 0;
        blob = null;
        blobKind = '';
        blobDur = 0;
        blobName = '';
        revokeBlob();
        shot.hidden = true;
        shot.removeAttribute('src');
        review.hidden = true;
        try { review.pause(); } catch (e) { /* ignore */ }
        review.removeAttribute('src');
        timerBox.hidden = true;
        live.hidden = false;
        shutter.hidden = false;
        retake.hidden = true;
        useBtn.hidden = true;
        if (pickBtn) pickBtn.hidden = true;
        shutter.disabled = !stream;
        updateShutter();
        setHint('');
    }

    function capturePhoto() {
        if (!live.videoWidth) {
            setHint('摄像头还没准备好，请稍候。', true);
            return;
        }
        var canvas = document.createElement('canvas');
        canvas.width = live.videoWidth;
        canvas.height = live.videoHeight;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(live, 0, 0, canvas.width, canvas.height);
        var done = function (b) {
            if (!b) {
                setHint('拍照失败，请重试。', true);
                return;
            }
            blob = b;
            blobKind = 'image';
            blobDur = 0;
            revokeBlob();
            blobUrl = URL.createObjectURL(b);
            shot.src = blobUrl;
            shot.hidden = false;
            live.hidden = true;
            shutter.hidden = true;
            retake.hidden = false;
            useBtn.hidden = false;
            setHint('');
        };
        if (canvas.toBlob) {
            canvas.toBlob(done, 'image/jpeg', 0.9);
        } else {
            var data = canvas.toDataURL('image/jpeg', 0.9);
            var bin = atob(data.split(',')[1]);
            var arr = new Uint8Array(bin.length);
            for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
            done(new Blob([arr], { type: 'image/jpeg' }));
        }
    }

    function startVideo() {
        if (!stream) return;
        var mime = pickVideoMime();
        if (!mime) {
            setHint('当前浏览器不支持录像。', true);
            return;
        }
        chunks = [];
        seconds = 0;
        timerText.textContent = '0:00';
        timerBox.hidden = false;
        try {
            recorder = new MediaRecorder(stream, { mimeType: mime });
        } catch (e) {
            setHint('无法开始录像，请重试。', true);
            timerBox.hidden = true;
            return;
        }
        recorder.addEventListener('dataavailable', function (ev) {
            if (ev.data && ev.data.size > 0) chunks.push(ev.data);
        });
        recorder.addEventListener('stop', finalizeVideo);
        recorder.start();
        shutter.textContent = '停止录像';
        shutter.classList.add('is-recording');
        setHint('');
        tick = setInterval(function () {
            seconds += 1;
            timerText.textContent = fmt(seconds);
            if (seconds >= MAX_SECONDS) stopVideo();
        }, 1000);
    }

    function stopVideo() {
        clearInterval(tick);
        tick = 0;
        if (recorder && recorder.state !== 'inactive') {
            try { recorder.stop(); } catch (e) { /* ignore */ }
        }
    }

    function finalizeVideo() {
        clearInterval(tick);
        tick = 0;
        if (discard) {
            discard = false;
            return;
        }
        var mime = (chunks[0] && chunks[0].type) || pickVideoMime() || 'video/webm';
        var b = new Blob(chunks, { type: mime });
        chunks = [];
        recorder = null;
        shutter.classList.remove('is-recording');
        timerBox.hidden = true;
        if (!b.size) {
            resetLive();
            setHint('没有录到画面，请重试。', true);
            return;
        }
        blob = b;
        blobKind = 'video';
        blobDur = Math.max(1, seconds);
        revokeBlob();
        blobUrl = URL.createObjectURL(b);
        review.src = blobUrl;
        review.hidden = false;
        live.hidden = true;
        shutter.hidden = true;
        retake.hidden = false;
        useBtn.hidden = false;
        setHint('');
    }

    function openPicker() {
        if (!pickInput) return;
        pickInput.value = '';
        try { pickInput.click(); } catch (e) { /* ignore */ }
    }

    function onPickedFile() {
        var f = pickInput && pickInput.files && pickInput.files[0];
        if (!f) return;
        var type = String(f.type || '').toLowerCase();
        var isImage = type.indexOf('image/') === 0;
        var isVideo = type.indexOf('video/') === 0;
        if (!isImage && !isVideo) {
            var lower = String(f.name || '').toLowerCase();
            if (/\.(jpg|jpeg|png|gif|webp)$/.test(lower)) isImage = true;
            else if (/\.(mp4|webm|mov|m4v)$/.test(lower)) isVideo = true;
        }
        if (!isImage && !isVideo) {
            setHint('只能选择照片或 mp4 / webm / mov 视频。', true);
            return;
        }
        if (f.size > MAX_BYTES) {
            setHint('文件超过 25MB，请选择更小的文件。', true);
            return;
        }
        blob = f;
        blobKind = isImage ? 'image' : 'video';
        blobName = f.name || (isImage ? 'photo.jpg' : 'video.webm');
        blobDur = 0;
        revokeBlob();
        blobUrl = URL.createObjectURL(f);
        if (isImage) {
            shot.src = blobUrl;
            shot.hidden = false;
            live.hidden = true;
            review.hidden = true;
        } else {
            review.src = blobUrl;
            review.hidden = false;
            live.hidden = true;
            shot.hidden = true;
        }
        shutter.hidden = true;
        if (pickBtn) pickBtn.hidden = true;
        retake.hidden = false;
        useBtn.hidden = false;
        setHint('');
    }

    function useMedia() {
        var form = activeForm;
        if (!blob || !form || uploading) return;
        if (!form.querySelector('input[name="csrf"]')) {
            setHint('缺少校验信息，请刷新页面。', true);
            return;
        }
        if (blob.size > MAX_BYTES) {
            setHint('文件超过 25MB，请缩短录像。', true);
            return;
        }
        uploading = true;
        useBtn.disabled = true;
        retake.disabled = true;
        setHint('正在上传…');

        var ext = blobKind === 'image' ? 'jpg' : videoExt(blob.type);
        var uploadName;
        if (blobName && blobName.indexOf('.') !== -1) {
            var picked = blobName.replace(/[^A-Za-z0-9._-]/g, '');
            uploadName = picked || (blobKind === 'image' ? 'photo.jpg' : ('video.' + ext));
        } else {
            uploadName = blobKind === 'image' ? 'photo.jpg' : ('video.' + ext);
        }
        var fd = new FormData();
        fd.append('media', blob, uploadName);
        fd.append('csrf', form.querySelector('input[name="csrf"]').value);

        fetch('/media_upload.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (d) {
                uploading = false;
                useBtn.disabled = false;
                retake.disabled = false;
                if (!d || !d.ok || !d.file) {
                    setHint((d && d.error) || '上传失败，请重试。', true);
                    return;
                }
                applyPreview(form, d.file, d.kind || blobKind, blobDur);
                close();
            })
            .catch(function () {
                uploading = false;
                useBtn.disabled = false;
                retake.disabled = false;
                setHint('上传失败，请重试。', true);
            });
    }

    function close() {
        if (modal) modal.hidden = true;
        document.body.classList.remove('modal-open');
        clearInterval(tick);
        tick = 0;
        if (recorder && recorder.state !== 'inactive') {
            discard = true;
            try { recorder.stop(); } catch (e) { /* ignore */ }
        } else {
            discard = false;
        }
        recorder = null;
        chunks = [];
        uploading = false;
        stopStream();
        revokeBlob();
        if (shot) { shot.hidden = true; shot.removeAttribute('src'); }
        if (review) { review.hidden = true; try { review.pause(); } catch (e) { /* ignore */ } review.removeAttribute('src'); }
        if (live) live.hidden = false;
        if (shutter) { shutter.hidden = false; shutter.classList.remove('is-recording'); shutter.disabled = false; }
        if (retake) retake.hidden = true;
        if (useBtn) useBtn.hidden = true;
        if (timerBox) timerBox.hidden = true;
        activeForm = null;
    }

    function open(form) {
        ensureModal();
        activeForm = form;
        uploading = false;
        discard = false;
        mode = 'photo';
        tabs.forEach(function (x) { x.classList.toggle('is-on', x.getAttribute('data-mode') === 'photo'); });
        resetLive();
        modal.hidden = false;
        document.body.classList.add('modal-open');
        requestStream();
    }

    function previewBox(form) {
        var box = form.querySelector('.media-preview');
        if (box) return box;
        box = document.createElement('div');
        box.className = 'media-preview';
        box.hidden = true;
        box.innerHTML = '<span class="media-thumb"></span>'
            + '<span class="media-label"></span>'
            + '<button type="button" class="media-clear" aria-label="移除">×</button>';
        var ta = form.querySelector('textarea');
        var composer = ta && ta.closest ? ta.closest('.emoji-composer') : null;
        var anchor = composer || ta || form;
        anchor.parentNode.insertBefore(box, anchor.nextSibling);
        box.querySelector('.media-clear').addEventListener('click', function (e) {
            e.preventDefault();
            reset(form);
        });
        return box;
    }

    function applyPreview(form, file, kind, dur) {
        var fields = ensureFields(form);
        fields.file.value = file;
        fields.dur.value = String(dur || 0);
        var box = previewBox(form);
        var thumb = box.querySelector('.media-thumb');
        var label = box.querySelector('.media-label');
        thumb.innerHTML = '';
        var src = '/media.php?f=' + encodeURIComponent(file);
        if (kind === 'image') {
            var img = document.createElement('img');
            img.src = src;
            img.alt = '照片';
            thumb.appendChild(img);
            label.textContent = '照片';
        } else {
            thumb.innerHTML = '<span class="media-video-icon">▶</span>';
            label.textContent = '视频' + (dur > 0 ? ' ' + fmt(dur) : '');
        }
        box.hidden = false;
    }

    function ensureFields(form) {
        var f = form.querySelector('input[name="media_file"]');
        var d = form.querySelector('input[name="media_dur"]');
        if (!f) { f = document.createElement('input'); f.type = 'hidden'; f.name = 'media_file'; form.appendChild(f); }
        if (!d) { d = document.createElement('input'); d.type = 'hidden'; d.name = 'media_dur'; form.appendChild(d); }
        return { file: f, dur: d };
    }

    function reset(form) {
        if (!form) return;
        var fields = ensureFields(form);
        fields.file.value = '';
        fields.dur.value = '';
        var box = form.querySelector('.media-preview');
        if (box) {
            box.hidden = true;
            var thumb = box.querySelector('.media-thumb');
            if (thumb) thumb.innerHTML = '';
            var label = box.querySelector('.media-label');
            if (label) label.textContent = '';
        }
        if (activeForm === form) close();
    }

    function setup(form) {
        if (form.getAttribute('data-capture-bound')) return;
        form.setAttribute('data-capture-bound', '1');
        ensureFields(form);

        var ta = form.querySelector('textarea');
        var composer = ta && ta.closest ? ta.closest('.emoji-composer') : null;
        var anchor = composer || ta || form;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'emoji-toggle capture-toggle';
        btn.title = '拍照/录像';
        btn.setAttribute('aria-label', '拍照或录像');
        btn.innerHTML = CAM;

        var bar = composer ? composer.querySelector('.emoji-bar') : null;
        if (bar) {
            bar.appendChild(btn);
        } else {
            var camBar = document.createElement('div');
            camBar.className = 'emoji-bar capture-bar';
            camBar.appendChild(btn);
            anchor.parentNode.insertBefore(camBar, anchor.nextSibling);
        }
        previewBox(form);

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            open(form);
        });
    }

    function init() {
        document.querySelectorAll('form[data-capture]').forEach(setup);
    }

    window.SiteCapture = { reset: reset };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
