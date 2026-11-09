(function () {
    'use strict';
    var app = document.getElementById('gameApp');
    if (!app) { return; }
    var canvas = document.getElementById('gameCanvas');
    var ctx = canvas.getContext('2d');
    var stageEl = document.getElementById('gameStage');
    var overEl = document.getElementById('gameOver');
    var overTitle = document.getElementById('gameOverTitle');
    var overBody = document.getElementById('gameOverBody');
    var pauseEl = document.getElementById('gamePause');
    var tipEl = document.getElementById('gameTip');
    var sndBtn = document.getElementById('btnSound');

    var DPR = Math.min(window.devicePixelRatio || 1, 2);
    var W = 0, H = 0;

    /* ===== 音效（Web Audio 合成） ===== */
    var sound = (function () {
        var actx = null, on = true;
        try { on = localStorage.getItem('moon_sound') !== '0'; } catch (e) { on = true; }
        function ac() {
            if (!actx) {
                var C = window.AudioContext || window.webkitAudioContext;
                if (!C) return null;
                try { actx = new C(); } catch (e) { return null; }
            }
            if (actx.state === 'suspended') { actx.resume().catch(function () {}); }
            return actx;
        }
        function tone(t, f, dur, peak, type, slide) {
            var o = actx.createOscillator(), g = actx.createGain();
            o.type = type || 'sine';
            o.frequency.setValueAtTime(f, t);
            if (slide) { o.frequency.exponentialRampToValueAtTime(slide, t + dur); }
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(peak, t + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            o.connect(g); g.connect(actx.destination);
            o.start(t); o.stop(t + dur + 0.05);
        }
        function noise(t, dur, peak, freq) {
            var len = Math.floor(actx.sampleRate * dur);
            var buf = actx.createBuffer(1, len, actx.sampleRate);
            var d = buf.getChannelData(0);
            for (var i = 0; i < len; i++) { d[i] = (Math.random() * 2 - 1) * (1 - i / len); }
            var s = actx.createBufferSource(); s.buffer = buf;
            var flt = actx.createBiquadFilter(); flt.type = 'lowpass'; flt.frequency.value = freq || 1200;
            var g = actx.createGain(); g.gain.value = peak;
            s.connect(flt); flt.connect(g); g.connect(actx.destination);
            s.start(t);
        }
        return {
            enabled: function () { return on; },
            set: function (v) { on = !!v; try { localStorage.setItem('moon_sound', on ? '1' : '0'); } catch (e) {} },
            resume: function () { ac(); },
            slash: function () { if (!ac() || !on) return; tone(actx.currentTime, 900, 0.06, 0.08, 'triangle', 500); },
            pickup: function () { if (!ac() || !on) return; var t = actx.currentTime; tone(t, 660, 0.1, 0.16, 'sine'); tone(t + 0.08, 990, 0.12, 0.16, 'sine'); },
            levelup: function () { if (!ac() || !on) return; var t = actx.currentTime; [523, 659, 784, 1047].forEach(function (f, i) { tone(t + i * 0.09, f, 0.22, 0.18, 'triangle'); }); },
            hurt: function () { if (!ac() || !on) return; var t = actx.currentTime; noise(t, 0.18, 0.3, 700); tone(t, 200, 0.15, 0.18, 'sawtooth', 90); },
            chest: function () { if (!ac() || !on) return; var t = actx.currentTime; tone(t, 440, 0.12, 0.16, 'square'); tone(t + 0.1, 660, 0.18, 0.16, 'square'); },
            cut: function () { if (!ac() || !on) return; noise(actx.currentTime, 0.1, 0.2, 2600); },
            diem: function () { if (!ac() || !on) return; var t = actx.currentTime; tone(t, 300, 0.25, 0.16, 'sawtooth', 120); },
            over: function () { if (!ac() || !on) return; var t = actx.currentTime; [440, 392, 330, 262].forEach(function (f, i) { tone(t + i * 0.16, f, 0.3, 0.2, 'sine'); }); }
        };
    })();

    /* ===== 等级配色（颜色区分等级） ===== */
    var LEVELS = [
        { name: '绿月', color: '#3ddc5a', glow: '#9bff9b', dmg: 12 },
        { name: '金月', color: '#ffb800', glow: '#ffe07a', dmg: 22 },
        { name: '蓝月', color: '#3aa0ff', glow: '#9fd4ff', dmg: 36 },
        { name: '紫月', color: '#a55cff', glow: '#d9b3ff', dmg: 55 },
        { name: '红月', color: '#ff3b3b', glow: '#ff9b9b', dmg: 82 },
        { name: '青月', color: '#00e5d0', glow: '#9bfff2', dmg: 120 },
        { name: '橙月', color: '#ff7a18', glow: '#ffc28a', dmg: 170 },
        { name: '白月', color: '#f2f2f2', glow: '#ffffff', dmg: 240 },
        { name: '暗金', color: '#d4af37', glow: '#fff2b0', dmg: 330 },
        { name: '彩虹', color: '#ff4fd8', glow: '#ffe0ff', dmg: 460 }
    ];
    var MAX_LEVEL = LEVELS.length;

    function levelColor(lv) { return LEVELS[Math.max(0, Math.min(MAX_LEVEL - 1, lv - 1))].color; }
    function levelGlow(lv) { return LEVELS[Math.max(0, Math.min(MAX_LEVEL - 1, lv - 1))].glow; }
    function levelDmg(lv) { return LEVELS[Math.max(0, Math.min(MAX_LEVEL - 1, lv - 1))].dmg; }
    // 身上月牙数量 -> 装备等级
    function weaponLevelForCount(count) {
        var lv = 1;
        var need = 3; // 达到 3 个月牙升到 2 级
        var step = 3;
        while (count >= need && lv < MAX_LEVEL) {
            lv++;
            step += 1;
            need += step;
        }
        return Math.min(MAX_LEVEL, lv);
    }

    var WORLD = 4200; // 地图边长（远大于屏幕）
    var MAX_BLADES = 24;
    var state = null;
    var paused = false;
    var running = false;
    var raf = 0;
    var lastT = 0;
    var dragging = false;
    var dragStart = { x: 0, y: 0 };
    var dragCur = { x: 0, y: 0 };
    var moveDir = { x: 0, y: 0 };
    var keys = {};

    function rand(a, b) { return a + Math.random() * (b - a); }
    function dist(ax, ay, bx, by) { var dx = ax - bx, dy = ay - by; return Math.sqrt(dx * dx + dy * dy); }
    function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }

    function newGame() {
        state = {
            t: 0,
            cam: { x: 0, y: 0 },
            player: { x: WORLD / 2, y: WORLD / 2, r: 20, speed: 240, hp: 100, maxHp: 100, hurt: 0, face: 1 },
            angles: 0,
            blades: [{ level: 1 }],
            count: 1,
            weaponLevel: 1,
            grass: [],
            chests: [],
            pickups: [],
            enemies: [],
            dmg: [],
            particles: [],
            spawnTimer: 1.5,
            waveTimer: 0,
            stage: 1,
            stageTimer: 0,
            over: false,
            timeAlive: 0
        };
        // 草丛
        for (var i = 0; i < 90; i++) {
            state.grass.push({ x: rand(200, WORLD - 200), y: rand(200, WORLD - 200), hp: 2, size: rand(46, 70), sway: Math.random() * 6.28 });
        }
        // 宝箱
        for (var j = 0; j < 14; j++) {
            state.chests.push({ x: rand(200, WORLD - 200), y: rand(200, WORLD - 200), opened: false });
        }
        // 全图随机掉落月牙道具（等级随机，不限于当前等级）
        for (var k = 0; k < 60; k++) {
            state.pickups.push(spawnPickup(rand(120, WORLD - 120), rand(120, WORLD - 120)));
        }
        stageEl.textContent = '第1关';
        tipEl.hidden = false;
        overEl.hidden = true;
        pauseEl.hidden = true;
        paused = false;
        running = true;
        lastT = 0;
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(loop);
    }

    function spawnPickup(x, y, level) {
        var lv = level || pickRandomLevel();
        return { x: x, y: y, level: lv, bob: Math.random() * 6.28 };
    }
    // 地图掉落等级：偏向低等级，偶尔高等级（可以与当前装备无关）
    function pickRandomLevel() {
        var r = Math.random();
        if (r < 0.45) { return 1; }
        if (r < 0.68) { return 2; }
        if (r < 0.82) { return 3; }
        if (r < 0.9) { return 4; }
        if (r < 0.95) { return 5; }
        return 6 + Math.floor(Math.random() * Math.max(1, MAX_LEVEL - 5));
    }

    function resize() {
        var rect = app.getBoundingClientRect();
        W = rect.width; H = rect.height;
        canvas.width = Math.round(W * DPR);
        canvas.height = Math.round(H * DPR);
        canvas.style.width = W + 'px';
        canvas.style.height = H + 'px';
        ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    }

    /* ===== 输入 ===== */
    function pointerPos(e) {
        var rect = canvas.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }
    canvas.addEventListener('pointerdown', function (e) {
        dragging = true;
        dragStart = pointerPos(e);
        dragCur = { x: dragStart.x, y: dragStart.y };
        try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
    });
    canvas.addEventListener('pointermove', function (e) {
        if (!dragging) { return; }
        dragCur = pointerPos(e);
    });
    function endDrag() { dragging = false; moveDir.x = 0; moveDir.y = 0; }
    canvas.addEventListener('pointerup', endDrag);
    canvas.addEventListener('pointercancel', endDrag);
    window.addEventListener('pointerup', endDrag);

    document.addEventListener('keydown', function (e) {
        keys[e.key.toLowerCase()] = true;
        if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright', ' '].indexOf(e.key.toLowerCase()) >= 0) { e.preventDefault(); }
    });
    document.addEventListener('keyup', function (e) { keys[e.key.toLowerCase()] = false; });

    /* ===== 更新 ===== */
    function update(dt) {
        if (state.over) { return; }
        state.t += dt;
        state.timeAlive += dt;
        var p = state.player;

        // 移动方向：拖拽优先，其次键盘
        var mx = 0, my = 0;
        if (dragging) {
            var dx = dragCur.x - dragStart.x, dy = dragCur.y - dragStart.y;
            var d = Math.sqrt(dx * dx + dy * dy);
            if (d > 8) { mx = dx / d; my = dy / d; moveDir.x = mx; moveDir.y = my; }
            else { moveDir.x = 0; moveDir.y = 0; }
        } else {
            if (keys['w'] || keys['arrowup']) { my -= 1; }
            if (keys['s'] || keys['arrowdown']) { my += 1; }
            if (keys['a'] || keys['arrowleft']) { mx -= 1; }
            if (keys['d'] || keys['arrowright']) { mx += 1; }
            var m = Math.sqrt(mx * mx + my * my);
            if (m > 0) { mx /= m; my /= m; }
            moveDir.x = mx; moveDir.y = my;
        }
        p.x = clamp(p.x + moveDir.x * p.speed * dt, 40, WORLD - 40);
        p.y = clamp(p.y + moveDir.y * p.speed * dt, 40, WORLD - 40);
        if (moveDir.x !== 0) { p.face = moveDir.x > 0 ? 1 : -1; }
        if (p.hurt > 0) { p.hurt -= dt; }

        // 相机
        state.cam.x += ((p.x - W / 2) - state.cam.x) * Math.min(1, dt * 8);
        state.cam.y += ((p.y - H / 2) - state.cam.y) * Math.min(1, dt * 8);
        state.cam.x = clamp(state.cam.x, 0, Math.max(0, WORLD - W));
        state.cam.y = clamp(state.cam.y, 0, Math.max(0, WORLD - H));

        // 月牙旋转
        state.angles += dt * (2.2 + state.blades.length * 0.06);
        var radius = 78;
        state.bladePos = state.blades.map(function (b, i) {
            var a = state.angles + (i / state.blades.length) * Math.PI * 2;
            return { x: p.x + Math.cos(a) * radius, y: p.y + Math.sin(a) * radius, ang: a, blade: b };
        });

        // 子弹命中敌人
        state.bladePos.forEach(function (bp) {
            state.enemies.forEach(function (e) {
                if (e.dead) { return; }
                if (dist(bp.x, bp.y, e.x, e.y) < e.r + 22) {
                    if (!e.hitCd || state.t - e.hitCd > 0.35) {
                        e.hitCd = state.t;
                        var dmg = levelDmg(bp.blade.level) + Math.floor(state.timeAlive * 0.6);
                        e.hp -= dmg;
                        addDmg(e.x, e.y - e.r - 6, dmg);
                        sound.slash();
                        if (e.hp <= 0) { e.dead = true; sound.diem(); }
                    }
                }
            });
        });
        // 子弹割草
        state.grass.forEach(function (g) {
            if (g.cut) { return; }
            state.bladePos.forEach(function (bp) {
                if (g.cut) { return; }
                if (dist(bp.x, bp.y, g.x, g.y) < g.size * 0.5 + 18) {
                    g.hp -= 1;
                    if (g.hp <= 0) {
                        g.cut = true;
                        sound.cut();
                        state.pickups.push(spawnPickup(g.x, g.y));
                        burst(g.x, g.y, '#8fd94a', 10);
                    }
                }
            });
        });
        // 宝箱（靠近或子弹击中）
        state.chests.forEach(function (c) {
            if (c.opened) { return; }
            var near = dist(p.x, p.y, c.x, c.y) < 46;
            var hit = state.bladePos.some(function (bp) { return dist(bp.x, bp.y, c.x, c.y) < 34; });
            if (near || hit) {
                c.opened = true;
                sound.chest();
                var n = 2 + Math.floor(Math.random() * 3);
                for (var i = 0; i < n; i++) { state.pickups.push(spawnPickup(c.x + rand(-40, 40), c.y + rand(-30, 30))); }
                burst(c.x, c.y, '#ffd34d', 16);
            }
        });

        // 拾取月牙
        var remainingPickups = [];
        state.pickups.forEach(function (pu) {
            if (dist(p.x, p.y, pu.x, pu.y) < p.r + 24) {
                collectPickup(pu);
            } else {
                remainingPickups.push(pu);
            }
        });
        state.pickups = remainingPickups;

        // 敌人
        state.spawnTimer -= dt;
        if (state.spawnTimer <= 0) {
            spawnEnemy();
            state.spawnTimer = Math.max(0.5, 1.7 - state.stage * 0.08);
        }
        state.enemies.forEach(function (e) {
            if (e.dead) { return; }
            var ang = Math.atan2(p.y - e.y, p.x - e.x);
            e.x += Math.cos(ang) * e.speed * dt;
            e.y += Math.sin(ang) * e.speed * dt;
            if (dist(p.x, p.y, e.x, e.y) < p.r + e.r - 2 && p.hurt <= 0) {
                p.hp -= e.dmg;
                p.hurt = 0.8;
                sound.hurt();
                addDmg(p.x, p.y - 30, e.dmg, true);
                if (p.hp <= 0) { p.hp = 0; gameOver(); }
            }
        });
        state.enemies = state.enemies.filter(function (e) { return !e.dead; });

        // 关卡推进
        state.stageTimer += dt;
        if (state.stageTimer > 18) {
            state.stageTimer = 0;
            state.stage += 1;
            stageEl.textContent = '第' + state.stage + '关';
        }

        // 飘字 & 粒子
        state.dmg.forEach(function (d) { d.life -= dt; d.y -= 40 * dt; });
        state.dmg = state.dmg.filter(function (d) { return d.life > 0; });
        state.particles.forEach(function (pt) { pt.life -= dt; pt.x += pt.vx * dt; pt.y += pt.vy * dt; pt.vy += 300 * dt; });
        state.particles = state.particles.filter(function (pt) { return pt.life > 0; });
    }

    function collectPickup(pu) {
        if (state.blades.length < MAX_BLADES) {
            state.blades.push({ level: pu.level });
        } else {
            // 满位则提升已有最低等级月牙
            var min = state.blades.reduce(function (a, b) { return a.level <= b.level ? a : b; });
            if (pu.level > min.level) { min.level = pu.level; }
        }
        var oldW = state.weaponLevel;
        state.count = state.blades.length;
        state.weaponLevel = weaponLevelForCount(state.count);
        if (state.weaponLevel > oldW) {
            // 升级：所有月牙至少提升到装备等级
            state.blades.forEach(function (b) { if (b.level < state.weaponLevel) { b.level = state.weaponLevel; } });
            sound.levelup();
            addDmg(state.player.x, state.player.y - 44, '升级!', false, '#ffe07a');
            burst(state.player.x, state.player.y, '#ffe07a', 22);
        } else {
            sound.pickup();
        }
        burst(pu.x, pu.y, levelGlow(pu.level), 12);
    }

    function spawnEnemy() {
        var p = state.player;
        var ang = Math.random() * Math.PI * 2;
        var d = Math.max(W, H) * 0.62 + rand(40, 160);
        var x = clamp(p.x + Math.cos(ang) * d, 30, WORLD - 30);
        var y = clamp(p.y + Math.sin(ang) * d, 30, WORLD - 30);
        var hp = 24 + state.stage * 16;
        state.enemies.push({
            x: x, y: y, r: 20, hp: hp, maxHp: hp,
            speed: rand(64, 96) + state.stage * 3,
            dmg: 6 + Math.floor(state.stage * 1.6),
            hitCd: 0, type: Math.random() < 0.2 ? 'brute' : 'grunt'
        });
    }

    function addDmg(x, y, val, toPlayer, color) {
        state.dmg.push({ x: x, y: y, val: val, life: 0.9, life0: 0.9, player: !!toPlayer, color: color || (toPlayer ? '#ff6b6b' : '#ffe07a') });
    }
    function burst(x, y, color, n) {
        for (var i = 0; i < n; i++) {
            var a = Math.random() * Math.PI * 2, s = rand(60, 240);
            state.particles.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 60, life: rand(0.3, 0.7), color: color });
        }
    }

    function gameOver() {
        state.over = true;
        running = false;
        sound.over();
        overTitle.textContent = '倒下了';
        overBody.innerHTML = '你到达了 <b>第' + state.stage + '关</b>，存活 <b>' + Math.floor(state.timeAlive) + '</b> 秒<br>最大月牙等级：<b>' + state.weaponLevel + ' 级（' + LEVELS[state.weaponLevel - 1].name + '）</b>';
        overEl.hidden = false;
    }

    /* ===== 渲染 ===== */
    function draw() {
        var cam = state.cam;
        drawFloor(cam);

        function inView(x, y, pad) {
            pad = pad || 80;
            return x - cam.x > -pad && x - cam.x < W + pad && y - cam.y > -pad && y - cam.y < H + pad;
        }

        state.grass.forEach(function (g) {
            if (g.cut || !inView(g.x, g.y, g.size)) { return; }
            drawGrass(g.x - cam.x, g.y - cam.y, g.size, state.t, g.sway);
        });
        state.chests.forEach(function (c) {
            if (!inView(c.x, c.y)) { return; }
            drawChest(c.x - cam.x, c.y - cam.y, c.opened);
        });
        state.pickups.forEach(function (pu) {
            if (!inView(pu.x, pu.y)) { return; }
            drawPickup(pu.x - cam.x, pu.y - cam.y, pu.level, state.t + pu.bob);
        });
        state.enemies.forEach(function (e) {
            if (!inView(e.x, e.y)) { return; }
            drawEnemy(e, e.x - cam.x, e.y - cam.y);
        });

        var px = state.player.x - cam.x, py = state.player.y - cam.y;
        // 月牙（先画，位于角色下层）
        state.bladePos.forEach(function (bp) {
            drawCrescent(bp.x - cam.x, bp.y - cam.y, bp.ang, levelColor(bp.blade.level), levelGlow(bp.blade.level), 32);
        });
        drawPlayer(px, py);
        drawPlayerBar(px, py);

        // 月牙数量
        var cnt = String(state.blades.length);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '900 22px "Arial Black", system-ui, sans-serif';
        ctx.lineWidth = 5;
        ctx.strokeStyle = 'rgba(30,22,10,0.85)';
        ctx.strokeText(cnt, px, py - 46);
        ctx.fillStyle = '#ffffff';
        ctx.fillText(cnt, px, py - 46);

        // 飘字
        state.dmg.forEach(function (d) {
            var sx = d.x - cam.x, sy = d.y - cam.y;
            var a = Math.max(0, d.life / d.life0);
            ctx.globalAlpha = a;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = '900 ' + (d.player ? 18 : 22) + 'px "Arial Black", system-ui, sans-serif';
            ctx.lineWidth = 4;
            ctx.strokeStyle = 'rgba(30,22,10,0.9)';
            ctx.strokeText(String(d.val), sx, sy);
            ctx.fillStyle = d.color;
            ctx.fillText(String(d.val), sx, sy);
            ctx.globalAlpha = 1;
        });
        // 粒子
        state.particles.forEach(function (pt) {
            ctx.globalAlpha = Math.max(0, pt.life / 0.7);
            ctx.fillStyle = pt.color;
            ctx.beginPath(); ctx.arc(pt.x - cam.x, pt.y - cam.y, 3, 0, Math.PI * 2); ctx.fill();
            ctx.globalAlpha = 1;
        });

        // 拖拽摇杆
        if (dragging) {
            var dx = dragCur.x - dragStart.x, dy = dragCur.y - dragStart.y;
            var len = Math.sqrt(dx * dx + dy * dy);
            var baseR = 46;
            ctx.save();
            ctx.globalAlpha = 0.5;
            ctx.fillStyle = 'rgba(255,255,255,0.18)';
            ctx.beginPath(); ctx.arc(dragStart.x, dragStart.y, baseR, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.55)';
            ctx.lineWidth = 3;
            ctx.beginPath(); ctx.arc(dragStart.x, dragStart.y, baseR, 0, Math.PI * 2); ctx.stroke();
            var k = Math.min(1, len / baseR);
            var kx = dragStart.x + (len ? dx / len * baseR * k : 0);
            var ky = dragStart.y + (len ? dy / len * baseR * k : 0);
            ctx.globalAlpha = 0.85;
            ctx.fillStyle = 'rgba(255,255,255,0.85)';
            ctx.beginPath(); ctx.arc(kx, ky, 20, 0, Math.PI * 2); ctx.fill();
            ctx.restore();
        }

        drawVignette();
    }

    function drawFloor(cam) {
        var tile = 108;
        var x0 = Math.floor(cam.x / tile) * tile;
        var y0 = Math.floor(cam.y / tile) * tile;
        for (var gx = x0; gx < cam.x + W + tile; gx += tile) {
            for (var gy = y0; gy < cam.y + H + tile; gy += tile) {
                var sx = gx - cam.x, sy = gy - cam.y;
                var even = ((Math.round(gx / tile) + Math.round(gy / tile)) % 2) === 0;
                ctx.fillStyle = even ? '#8b8472' : '#7f7869';
                ctx.fillRect(sx, sy, tile, tile);
                // 石纹
                ctx.strokeStyle = 'rgba(60,55,45,0.35)';
                ctx.lineWidth = 2;
                ctx.strokeRect(sx + 1, sy + 1, tile - 2, tile - 2);
                ctx.strokeStyle = 'rgba(255,255,255,0.05)';
                ctx.lineWidth = 1;
                ctx.strokeRect(sx + 4, sy + 4, tile - 8, tile - 8);
                // 花砖圆纹
                if (even) {
                    var cx = sx + tile / 2, cy = sy + tile / 2;
                    ctx.strokeStyle = 'rgba(120,70,50,0.16)';
                    ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.arc(cx, cy, tile * 0.32, 0, Math.PI * 2); ctx.stroke();
                    ctx.beginPath(); ctx.arc(cx, cy, tile * 0.2, 0, Math.PI * 2); ctx.stroke();
                    for (var a = 0; a < 8; a++) {
                        var ang = a / 8 * Math.PI * 2;
                        ctx.beginPath();
                        ctx.moveTo(cx + Math.cos(ang) * tile * 0.2, cy + Math.sin(ang) * tile * 0.2);
                        ctx.lineTo(cx + Math.cos(ang) * tile * 0.32, cy + Math.sin(ang) * tile * 0.32);
                        ctx.stroke();
                    }
                }
            }
        }
    }

    function drawVignette() {
        var g = ctx.createRadialGradient(W / 2, H / 2, Math.min(W, H) * 0.35, W / 2, H / 2, Math.max(W, H) * 0.75);
        g.addColorStop(0, 'rgba(0,0,0,0)');
        g.addColorStop(1, 'rgba(0,0,0,0.32)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
    }

    function drawGrass(x, y, size, t, sway) {
        ctx.save();
        ctx.translate(x, y);
        // 底部阴影
        ctx.fillStyle = 'rgba(30,60,20,0.25)';
        ctx.beginPath(); ctx.ellipse(0, size * 0.3, size * 0.5, size * 0.16, 0, 0, Math.PI * 2); ctx.fill();
        var n = 11;
        for (var i = 0; i < n; i++) {
            var ox = (i - (n - 1) / 2) * (size / (n - 0.5));
            var h = size * (0.7 + 0.4 * Math.abs(Math.sin(i * 1.7)));
            var bend = Math.sin(t * 2 + sway + i * 0.5) * 6;
            var grad = ctx.createLinearGradient(0, size * 0.3, 0, size * 0.3 - h);
            grad.addColorStop(0, '#2f7a1e');
            grad.addColorStop(0.5, '#57c93a');
            grad.addColorStop(1, '#a4e85a');
            ctx.strokeStyle = grad;
            ctx.lineWidth = 4;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(ox, size * 0.3);
            ctx.quadraticCurveTo(ox + bend * 0.5, size * 0.3 - h * 0.6, ox + bend, size * 0.3 - h);
            ctx.stroke();
        }
        // 顶部亮叶
        ctx.fillStyle = '#b6f06a';
        for (var j = 0; j < 4; j++) {
            var lx = (j - 1.5) * (size / 6);
            ctx.beginPath();
            ctx.ellipse(lx, size * 0.3 - size * 0.75, size * 0.06, size * 0.16, (j - 1.5) * 0.3, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.restore();
    }

    function drawChest(x, y, opened) {
        ctx.save();
        ctx.translate(x, y);
        ctx.fillStyle = 'rgba(0,0,0,0.28)';
        ctx.beginPath(); ctx.ellipse(0, 20, 30, 10, 0, 0, Math.PI * 2); ctx.fill();
        if (!opened) {
            var glow = ctx.createRadialGradient(0, -6, 4, 0, -6, 52);
            glow.addColorStop(0, 'rgba(255,220,120,0.55)');
            glow.addColorStop(1, 'rgba(255,220,120,0)');
            ctx.fillStyle = glow;
            ctx.beginPath(); ctx.arc(0, -6, 52, 0, Math.PI * 2); ctx.fill();
        }
        // 箱体
        var bodyGrad = ctx.createLinearGradient(0, -18, 0, 20);
        bodyGrad.addColorStop(0, '#b9793a');
        bodyGrad.addColorStop(1, '#7d4c1f');
        ctx.fillStyle = bodyGrad;
        roundRect(-28, -14, 56, 34, 6); ctx.fill();
        ctx.strokeStyle = '#5a3613'; ctx.lineWidth = 2.5; ctx.stroke();
        // 盖子
        var lidH = opened ? 10 : 16;
        var lidGrad = ctx.createLinearGradient(0, -30, 0, -14);
        lidGrad.addColorStop(0, '#d59a52');
        lidGrad.addColorStop(1, '#a86a2c');
        ctx.fillStyle = lidGrad;
        ctx.save();
        if (opened) { ctx.translate(0, -14); ctx.rotate(-0.5); ctx.translate(0, 14); }
        roundRect(-28, -14 - lidH, 56, lidH, 6); ctx.fill();
        ctx.strokeStyle = '#5a3613'; ctx.stroke();
        ctx.restore();
        // 金边
        ctx.fillStyle = '#f2c14e';
        ctx.fillRect(-28, -4, 56, 5);
        ctx.fillRect(-28, opened ? 6 : 12, 56, 5);
        ctx.fillRect(-5, -14, 10, 34);
        // 锁扣
        ctx.fillStyle = '#ffe08a';
        ctx.beginPath(); ctx.arc(0, 2, 5, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#7a4a12';
        ctx.beginPath(); ctx.arc(0, 2, 2.2, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }

    function drawPickup(x, y, level, t) {
        var bob = Math.sin(t * 3) * 5;
        ctx.save();
        ctx.translate(x, y + bob);
        var r = 19;
        var glow = ctx.createRadialGradient(0, 0, 2, 0, 0, r + 16);
        glow.addColorStop(0, levelGlow(level));
        glow.addColorStop(0.5, 'rgba(255,255,255,0.25)');
        glow.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = glow;
        ctx.beginPath(); ctx.arc(0, 0, r + 16, 0, Math.PI * 2); ctx.fill();
        // 白色玻璃球
        var ball = ctx.createRadialGradient(-6, -6, 2, 0, 0, r);
        ball.addColorStop(0, 'rgba(255,255,255,0.98)');
        ball.addColorStop(0.7, 'rgba(255,255,255,0.7)');
        ball.addColorStop(1, 'rgba(230,230,230,0.6)');
        ctx.fillStyle = ball;
        ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = levelColor(level); ctx.lineWidth = 3; ctx.stroke();
        ctx.strokeStyle = 'rgba(255,255,255,0.9)'; ctx.lineWidth = 1.5;
        ctx.beginPath(); ctx.arc(0, 0, r - 2.5, 0, Math.PI * 2); ctx.stroke();
        drawCrescentShape(0, 1, -0.5, levelColor(level), 13);
        // 高光
        ctx.fillStyle = 'rgba(255,255,255,0.9)';
        ctx.beginPath(); ctx.ellipse(-6, -7, 3.5, 2.4, -0.6, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }

    function drawCrescent(x, y, ang, color, glow, size) {
        ctx.save();
        ctx.translate(x, y);
        // 外层光晕
        ctx.save();
        ctx.rotate(ang);
        var g = ctx.createRadialGradient(0, 0, 4, 0, 0, size * 2.1);
        g.addColorStop(0, glow);
        g.addColorStop(0.55, hexA(color, 0.35));
        g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(0, 0, size * 2.1, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
        ctx.rotate(ang + 0.7);
        drawCrescentShape(0, 0, 0, color, size, true);
        ctx.restore();
    }

    function hexA(hex, a) {
        var h = hex.replace('#', '');
        if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
        var r = parseInt(h.slice(0, 2), 16), g = parseInt(h.slice(2, 4), 16), b = parseInt(h.slice(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
    }

    // 厚实的月牙：白色描边 + 颜色渐变
    function drawCrescentShape(x, y, rot, color, size, rim) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(rot);
        function moon() {
            ctx.beginPath();
            ctx.moveTo(0, -size);
            ctx.bezierCurveTo(size * 1.05, -size * 0.55, size * 1.02, size * 0.55, size * 0.12, size * 0.98);
            ctx.bezierCurveTo(size * 0.5, size * 0.5, size * 0.55, -size * 0.35, 0, -size);
            ctx.closePath();
        }
        var g = ctx.createLinearGradient(-size * 0.4, -size, size * 0.8, size);
        g.addColorStop(0, '#ffffff');
        g.addColorStop(0.35, color);
        g.addColorStop(1, color);
        ctx.fillStyle = g;
        if (rim) { ctx.lineWidth = 3; ctx.strokeStyle = '#ffffff'; }
        moon(); ctx.fill();
        if (rim) { ctx.stroke(); ctx.lineWidth = 1.5; ctx.strokeStyle = hexA(color, 0.6); }
        ctx.restore();
    }

    function drawPlayer(x, y) {
        ctx.save();
        ctx.translate(x, y);
        ctx.scale(1.28, 1.28);
        var f = state.player.face;
        // 影子
        ctx.fillStyle = 'rgba(0,0,0,0.28)';
        ctx.beginPath(); ctx.ellipse(0, 26, 24, 9, 0, 0, Math.PI * 2); ctx.fill();
        if (state.player.hurt > 0 && Math.floor(state.player.hurt * 20) % 2 === 0) { ctx.globalAlpha = 0.5; }
        // 剑（先画，在身后）
        ctx.save();
        ctx.scale(f, 1);
        ctx.strokeStyle = '#e8eef6'; ctx.lineWidth = 5; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(10, 6); ctx.lineTo(34, -16); ctx.stroke();
        ctx.strokeStyle = '#b9c3d2'; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(10, 6); ctx.lineTo(34, -16); ctx.stroke();
        ctx.fillStyle = '#8a5a2a';
        ctx.fillRect(4, 2, 8, 8);
        ctx.restore();
        // 袍子
        var robe = ctx.createLinearGradient(0, -8, 0, 24);
        robe.addColorStop(0, '#ffffff');
        robe.addColorStop(1, '#dbe3ef');
        ctx.fillStyle = robe;
        ctx.strokeStyle = '#8a99b5'; ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-15, -8); ctx.lineTo(15, -8);
        ctx.quadraticCurveTo(20, 8, 17, 24);
        ctx.lineTo(-17, 24);
        ctx.quadraticCurveTo(-20, 8, -15, -8);
        ctx.closePath();
        ctx.fill(); ctx.stroke();
        // 衣襟
        ctx.strokeStyle = '#c3cddd'; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(0, -8); ctx.lineTo(0, 22); ctx.stroke();
        // 腰带
        ctx.fillStyle = '#2f6bd0';
        ctx.fillRect(-17, 4, 34, 7);
        ctx.fillStyle = '#1c4fa8';
        ctx.fillRect(-17, 9, 34, 2);
        // 领口
        ctx.fillStyle = '#6a86c9';
        ctx.beginPath(); ctx.moveTo(-6, -8); ctx.lineTo(0, -2); ctx.lineTo(6, -8); ctx.closePath(); ctx.fill();
        // 头
        ctx.fillStyle = '#ffe0c2';
        ctx.beginPath(); ctx.arc(0, -18, 12.5, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = '#e0b48f'; ctx.lineWidth = 1.5; ctx.stroke();
        // 头发
        ctx.fillStyle = '#1d222b';
        ctx.beginPath(); ctx.arc(0, -21, 13, Math.PI * 0.95, Math.PI * 2.05); ctx.fill();
        ctx.beginPath(); ctx.arc(-11, -16, 4.5, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(11, -16, 4.5, 0, Math.PI * 2); ctx.fill();
        // 发髻
        ctx.beginPath(); ctx.arc(0, -31, 6, 0, Math.PI * 2); ctx.fill();
        // 红丝带
        ctx.strokeStyle = '#e5342b'; ctx.lineWidth = 3; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(4, -33); ctx.quadraticCurveTo(14, -38, 18, -30); ctx.stroke();
        // 眼睛
        ctx.fillStyle = '#2a2f38';
        ctx.beginPath(); ctx.arc(-4.5, -17, 1.8, 0, Math.PI * 2); ctx.arc(4.5, -17, 1.8, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }

    function drawPlayerBar(x, y) {
        var w = 66, h = 9, yy = y + 32;
        var ratio = clamp(state.player.hp / state.player.maxHp, 0, 1);
        ctx.fillStyle = 'rgba(0,0,0,0.45)';
        roundRect(x - w / 2 - 2, yy - 2, w + 4, h + 4, 5); ctx.fill();
        var g = ctx.createLinearGradient(0, yy, 0, yy + h);
        if (ratio > 0.5) { g.addColorStop(0, '#7dff8a'); g.addColorStop(1, '#1fb84a'); }
        else if (ratio > 0.25) { g.addColorStop(0, '#ffe07a'); g.addColorStop(1, '#f19a10'); }
        else { g.addColorStop(0, '#ff9b9b'); g.addColorStop(1, '#e02626'); }
        ctx.fillStyle = g;
        roundRect(x - w / 2, yy, w * ratio, h, 4); ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.lineWidth = 1;
        roundRect(x - w / 2, yy, w, h, 4); ctx.stroke();
    }

    function roundRect(x, y, w, h, r) {
        if (w < 2 * r) { r = w / 2; }
        if (h < 2 * r) { r = h / 2; }
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function drawEnemy(e, x, y) {
        ctx.save();
        ctx.translate(x, y);
        ctx.fillStyle = 'rgba(0,0,0,0.28)';
        ctx.beginPath(); ctx.ellipse(0, 22, e.r * 0.9, e.r * 0.36, 0, 0, Math.PI * 2); ctx.fill();
        // 身体（敌人：黑袍红纹）
        var body = ctx.createLinearGradient(0, -e.r, 0, e.r + 10);
        if (e.type === 'brute') { body.addColorStop(0, '#4a2030'); body.addColorStop(1, '#7a1f2b'); }
        else { body.addColorStop(0, '#2b303a'); body.addColorStop(1, '#171b22'); }
        ctx.fillStyle = body;
        ctx.strokeStyle = e.type === 'brute' ? '#c23a4b' : '#3f4754';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-e.r, 14);
        ctx.quadraticCurveTo(-e.r - 4, -e.r, 0, -e.r - 2);
        ctx.quadraticCurveTo(e.r + 4, -e.r, e.r, 14);
        ctx.closePath();
        ctx.fill(); ctx.stroke();
        // 头
        ctx.fillStyle = '#c9a07a';
        ctx.beginPath(); ctx.arc(0, -e.r - 2, e.r * 0.62, 0, Math.PI * 2); ctx.fill();
        // 头发
        ctx.fillStyle = '#1a1e26';
        ctx.beginPath(); ctx.arc(0, -e.r - 5, e.r * 0.62, Math.PI, Math.PI * 2); ctx.fill();
        // 红飘带
        ctx.strokeStyle = '#c23a4b'; ctx.lineWidth = 3; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(0, -e.r - 9); ctx.quadraticCurveTo(e.r, -e.r - 16, e.r * 1.2, -e.r - 4); ctx.stroke();
        // 眼睛（发光）
        ctx.fillStyle = e.type === 'brute' ? '#ff6b6b' : '#ffd34d';
        ctx.beginPath(); ctx.arc(-e.r * 0.25, -e.r - 2, 2.2, 0, Math.PI * 2); ctx.arc(e.r * 0.25, -e.r - 2, 2.2, 0, Math.PI * 2); ctx.fill();
        // 血条
        var w = e.r * 2, h = 6, yy = -e.r - 20;
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        roundRect(-w / 2 - 1, yy - 1, w + 2, h + 2, 3); ctx.fill();
        var ratio = clamp(e.hp / e.maxHp, 0, 1);
        var g = ctx.createLinearGradient(0, yy, 0, yy + h);
        if (e.type === 'brute') { g.addColorStop(0, '#ff8a8a'); g.addColorStop(1, '#d11f1f'); }
        else { g.addColorStop(0, '#ff6b6b'); g.addColorStop(1, '#c02020'); }
        ctx.fillStyle = g;
        roundRect(-w / 2, yy, w * ratio, h, 3); ctx.fill();
        ctx.restore();
    }

    function loop(ts) {
        if (!running && !paused) { return; }
        if (!lastT) { lastT = ts; }
        var dt = Math.min(0.05, (ts - lastT) / 1000);
        lastT = ts;
        if (!paused && !state.over) { update(dt); }
        draw();
        raf = requestAnimationFrame(loop);
    }

    function pause() { if (state.over) return; paused = true; pauseEl.hidden = false; canvas.style.filter = 'blur(2px)'; }
    function resume() { paused = false; pauseEl.hidden = true; canvas.style.filter = 'none'; lastT = 0; }
    function quit() { running = false; paused = false; cancelAnimationFrame(raf); window.location.href = '/index.php'; }

    document.getElementById('btnPause').addEventListener('click', function () { paused ? resume() : pause(); });
    document.getElementById('btnResume').addEventListener('click', resume);
    document.getElementById('btnQuit').addEventListener('click', quit);
    document.getElementById('btnRetry').addEventListener('click', newGame);
    document.getElementById('btnFull').addEventListener('click', function () {
        var el = document.documentElement;
        if (document.fullscreenElement) { document.exitFullscreen().catch(function () {}); }
        else if (el.requestFullscreen) { el.requestFullscreen().catch(function () {}); }
    });
    function syncSound() {
        if (!sndBtn) { return; }
        sndBtn.textContent = sound.enabled() ? '🔊' : '🔇';
        sndBtn.classList.toggle('is-muted', !sound.enabled());
    }
    syncSound();
    sndBtn.addEventListener('click', function () { sound.set(!sound.enabled()); syncSound(); if (sound.enabled()) sound.pickup(); });
    document.addEventListener('pointerdown', function once() { sound.resume(); document.removeEventListener('pointerdown', once); }, { once: true });

    var resizeT = 0;
    window.addEventListener('resize', function () { clearTimeout(resizeT); resizeT = setTimeout(resize, 120); });
    document.addEventListener('visibilitychange', function () { if (document.hidden && running && !state.over) { pause(); } });

    resize();
    newGame();
    setTimeout(function () { tipEl.hidden = true; }, 4500);
})();
