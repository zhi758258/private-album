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
        // 地面
        ctx.fillStyle = '#6f6a5c';
        ctx.fillRect(0, 0, W, H);
        var tile = 96;
        var x0 = Math.floor(cam.x / tile) * tile;
        var y0 = Math.floor(cam.y / tile) * tile;
        for (var gx = x0; gx < cam.x + W + tile; gx += tile) {
            for (var gy = y0; gy < cam.y + H + tile; gy += tile) {
                var sx = gx - cam.x, sy = gy - cam.y;
                var even = ((Math.round(gx / tile) + Math.round(gy / tile)) % 2) === 0;
                ctx.fillStyle = even ? '#7c7666' : '#726c5e';
                ctx.fillRect(sx, sy, tile, tile);
                ctx.strokeStyle = 'rgba(0,0,0,0.08)';
                ctx.lineWidth = 1;
                ctx.strokeRect(sx + 0.5, sy + 0.5, tile - 1, tile - 1);
            }
        }

        function inView(x, y, pad) {
            pad = pad || 60;
            return x - cam.x > -pad && x - cam.x < W + pad && y - cam.y > -pad && y - cam.y < H + pad;
        }

        // 草丛
        state.grass.forEach(function (g) {
            if (g.cut || !inView(g.x, g.y, g.size)) { return; }
            drawGrass(g.x - cam.x, g.y - cam.y, g.size, state.t, g.sway);
        });
        // 宝箱
        state.chests.forEach(function (c) {
            if (!inView(c.x, c.y)) { return; }
            drawChest(c.x - cam.x, c.y - cam.y, c.opened);
        });
        // 掉落道具
        state.pickups.forEach(function (pu) {
            if (!inView(pu.x, pu.y)) { return; }
            drawPickup(pu.x - cam.x, pu.y - cam.y, pu.level, state.t + pu.bob);
        });

        // 敌人
        state.enemies.forEach(function (e) {
            if (!inView(e.x, e.y)) { return; }
            drawEnemy(e, e.x - cam.x, e.y - cam.y);
        });

        // 玩家 + 月牙
        var px = state.player.x - cam.x, py = state.player.y - cam.y;
        state.bladePos.forEach(function (bp) {
            drawCrescent(bp.x - cam.x, bp.y - cam.y, bp.ang, levelColor(bp.blade.level), levelGlow(bp.blade.level), 26);
        });
        drawPlayer(px, py);

        // 玩家血条 + 月牙数量
        drawPlayerBar(px, py);
        ctx.font = 'bold 20px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillStyle = '#2a2118';
        ctx.fillText(String(state.blades.length), px, py - 40);
        ctx.fillStyle = '#fff';
        ctx.fillText(String(state.blades.length), px, py - 42);

        // 飘字
        state.dmg.forEach(function (d) {
            var sx = d.x - cam.x, sy = d.y - cam.y;
            var a = Math.max(0, d.life / d.life0);
            ctx.globalAlpha = a;
            ctx.font = (d.player ? 'bold 18px' : 'bold 22px') + ' system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.lineWidth = 3;
            ctx.strokeStyle = 'rgba(0,0,0,0.5)';
            ctx.strokeText(String(d.val), sx, sy);
            ctx.fillStyle = d.color;
            ctx.fillText(String(d.val), sx, sy);
            ctx.globalAlpha = 1;
        });
        // 粒子
        state.particles.forEach(function (pt) {
            ctx.globalAlpha = Math.max(0, pt.life / 0.7);
            ctx.fillStyle = pt.color;
            ctx.fillRect(pt.x - cam.x - 2, pt.y - cam.y - 2, 4, 4);
            ctx.globalAlpha = 1;
        });

        // 拖拽指示
        if (dragging) {
            ctx.strokeStyle = 'rgba(255,255,255,0.35)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(dragStart.x, dragStart.y, 42, 0, Math.PI * 2);
            ctx.stroke();
            ctx.beginPath();
            ctx.arc(dragCur.x, dragCur.y, 20, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255,255,255,0.25)';
            ctx.fill();
        }
    }

    function drawGrass(x, y, size, t, sway) {
        var n = 9;
        ctx.save();
        ctx.strokeStyle = '#4caf3f';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        for (var i = 0; i < n; i++) {
            var ox = (i - (n - 1) / 2) * (size / n);
            var h = size * 0.9 + Math.sin(t * 2 + sway + i) * 4;
            ctx.beginPath();
            ctx.moveTo(x + ox, y + size * 0.35);
            ctx.quadraticCurveTo(x + ox + Math.sin(t * 2 + i) * 6, y + size * 0.35 - h * 0.6, x + ox + Math.sin(t * 2 + i) * 10, y + size * 0.35 - h);
            ctx.stroke();
        }
        ctx.restore();
    }

    function drawChest(x, y, opened) {
        ctx.save();
        ctx.translate(x, y);
        ctx.fillStyle = opened ? '#7a5a30' : '#8a5a22';
        ctx.strokeStyle = '#d4af37';
        ctx.lineWidth = 3;
        ctx.beginPath(); ctx.rect(-26, -18, 52, 36); ctx.fill(); ctx.stroke();
        ctx.fillStyle = '#d4af37';
        ctx.fillRect(-26, opened ? -26 : -6, 52, 6);
        ctx.beginPath(); ctx.arc(0, 2, 5, 0, Math.PI * 2); ctx.fillStyle = '#5a3d12'; ctx.fill();
        if (!opened) {
            var g = ctx.createRadialGradient(0, 0, 2, 0, 0, 40);
            g.addColorStop(0, 'rgba(255,220,120,0.5)');
            g.addColorStop(1, 'rgba(255,220,120,0)');
            ctx.fillStyle = g;
            ctx.beginPath(); ctx.arc(0, 0, 40, 0, Math.PI * 2); ctx.fill();
        }
        ctx.restore();
    }

    function drawPickup(x, y, level, t) {
        var bob = Math.sin(t * 3) * 4;
        ctx.save();
        ctx.translate(x, y + bob);
        var r = 20;
        var g = ctx.createRadialGradient(0, 0, 2, 0, 0, r + 10);
        g.addColorStop(0, levelGlow(level));
        g.addColorStop(0.6, 'rgba(255,255,255,0.15)');
        g.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(0, 0, r + 10, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,0.85)'; ctx.fill();
        ctx.strokeStyle = levelColor(level); ctx.lineWidth = 3; ctx.stroke();
        drawCrescentShape(0, 0, -0.6, levelColor(level), 14);
        ctx.restore();
    }

    function drawCrescent(x, y, ang, color, glow, size) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(ang);
        var g = ctx.createRadialGradient(0, 0, 2, 0, 0, size * 1.4);
        g.addColorStop(0, glow);
        g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(0, 0, size * 1.4, 0, Math.PI * 2); ctx.fill();
        drawCrescentShape(0, 0, Math.PI * 0.5, color, size);
        ctx.restore();
    }

    function drawCrescentShape(x, y, rot, color, size) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(rot);
        ctx.beginPath();
        ctx.moveTo(0, -size);
        ctx.quadraticCurveTo(size * 0.95, -size * 0.2, size * 0.5, size * 0.7);
        ctx.quadraticCurveTo(size * 0.15, size * 0.05, 0, -size * 0.25);
        ctx.quadraticCurveTo(-size * 0.1, -size * 0.75, 0, -size);
        ctx.closePath();
        var g = ctx.createLinearGradient(-size, -size, size, size);
        g.addColorStop(0, '#ffffff');
        g.addColorStop(0.4, color);
        g.addColorStop(1, color);
        ctx.fillStyle = g;
        ctx.fill();
        ctx.restore();
    }

    function drawPlayer(x, y) {
        ctx.save();
        ctx.translate(x, y);
        // 影子
        ctx.fillStyle = 'rgba(0,0,0,0.25)';
        ctx.beginPath(); ctx.ellipse(0, 24, 22, 8, 0, 0, Math.PI * 2); ctx.fill();
        // 身体
        ctx.fillStyle = '#f4f6fb';
        ctx.strokeStyle = '#5b6b8a';
        ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(-14, -6); ctx.lineTo(14, -6); ctx.lineTo(16, 20); ctx.lineTo(-16, 20); ctx.closePath();
        ctx.fill(); ctx.stroke();
        // 腰带
        ctx.fillStyle = '#3f6dc4'; ctx.fillRect(-16, 6, 32, 6);
        // 头
        ctx.fillStyle = '#ffd9b8';
        ctx.beginPath(); ctx.arc(0, -16, 12, 0, Math.PI * 2); ctx.fill();
        // 头发
        ctx.fillStyle = '#20242c';
        ctx.beginPath(); ctx.arc(0, -19, 12, Math.PI, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(0, -22, 5, 0, Math.PI * 2); ctx.fill();
        // 剑
        ctx.strokeStyle = '#cfd6e0'; ctx.lineWidth = 4;
        ctx.beginPath(); ctx.moveTo(12 * state.player.face, 4); ctx.lineTo(30 * state.player.face, -14); ctx.stroke();
        ctx.restore();
    }

    function drawPlayerBar(x, y) {
        var w = 64, h = 7;
        var ratio = clamp(state.player.hp / state.player.maxHp, 0, 1);
        ctx.fillStyle = 'rgba(0,0,0,0.4)';
        ctx.fillRect(x - w / 2 - 1, y + 30 - 1, w + 2, h + 2);
        ctx.fillStyle = ratio > 0.5 ? '#3ddc5a' : ratio > 0.25 ? '#ffb800' : '#ff3b3b';
        ctx.fillRect(x - w / 2, y + 30, w * ratio, h);
    }

    function drawEnemy(e, x, y) {
        ctx.save();
        ctx.translate(x, y);
        ctx.fillStyle = 'rgba(0,0,0,0.25)';
        ctx.beginPath(); ctx.ellipse(0, 20, 18, 7, 0, 0, Math.PI * 2); ctx.fill();
        if (e.type === 'brute') {
            ctx.fillStyle = '#7a1f2b';
            ctx.beginPath(); ctx.arc(0, 0, e.r + 2, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#c23a4b';
            ctx.beginPath(); ctx.arc(0, -2, e.r - 4, 0, Math.PI * 2); ctx.fill();
        } else {
            ctx.fillStyle = '#2a2f38';
            ctx.beginPath(); ctx.arc(0, 0, e.r, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#8e6a4a';
            ctx.beginPath(); ctx.arc(0, -3, e.r - 6, 0, Math.PI * 2); ctx.fill();
        }
        // 眼睛
        ctx.fillStyle = '#ffe07a';
        ctx.beginPath(); ctx.arc(-5, -2, 2.4, 0, Math.PI * 2); ctx.arc(5, -2, 2.4, 0, Math.PI * 2); ctx.fill();
        // 血条
        var w = 40, h = 5;
        var ratio = clamp(e.hp / e.maxHp, 0, 1);
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        ctx.fillRect(-w / 2 - 1, -e.r - 14, w + 2, h + 2);
        ctx.fillStyle = '#ff4d4d';
        ctx.fillRect(-w / 2, -e.r - 13, w * ratio, h);
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
