<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

render_header(nav_label('game'), $user, 'game', 'game-page');
?>
<link rel="stylesheet" href="/assets/css/game.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/game.css') ?>">
<div class="game" id="gameApp">
    <canvas id="gameCanvas"></canvas>

    <div class="game-hud">
        <div class="game-top">
            <button type="button" class="game-icon-btn" id="btnPause" aria-label="暂停">❚❚</button>
            <div class="game-stage" id="gameStage">第1关</div>
            <div class="game-top-right">
                <button type="button" class="game-icon-btn" id="btnSound" aria-label="音效">🔊</button>
                <button type="button" class="game-icon-btn" id="btnFull" aria-label="全屏">⛶</button>
            </div>
        </div>
        <div class="game-tip" id="gameTip">滑动屏幕移动，月牙自动攻击</div>
    </div>

    <div class="game-over" id="gameOver" hidden>
        <div class="game-over-card">
            <h2 id="gameOverTitle">游戏结束</h2>
            <p id="gameOverBody"></p>
            <div class="game-over-actions">
                <button type="button" class="game-btn game-btn-gold" id="btnRetry">再来一局</button>
            </div>
        </div>
    </div>

    <div class="game-pause" id="gamePause" hidden>
        <div class="game-over-card">
            <h2>已暂停</h2>
            <div class="game-over-actions">
                <button type="button" class="game-btn game-btn-gold" id="btnResume">继续</button>
                <button type="button" class="game-btn" id="btnQuit">返回大厅</button>
            </div>
        </div>
    </div>
</div>
<script>window.GAME = { uid: <?= (int) $user['id'] ?> };</script>
<script src="/assets/js/game.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/game.js') ?>"></script>
<?php render_footer();
