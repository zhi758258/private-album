<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));

render_header(nav_label('ddz'), $user, 'ddz');
?>
<style><?= file_get_contents(__DIR__ . '/assets/css/ddz.css') ?></style>
<div class="ddz" id="ddzApp" data-code="<?= h($code) ?>" data-csrf="<?= h(csrf_token()) ?>" data-uid="<?= (int) $user['id'] ?>" data-name="<?= h($user['display_name']) ?>">

    <section class="ddz-lobby" id="ddzLobby">
        <div class="ddz-hero">
            <div class="ddz-hero-title">欢乐斗地主</div>
            <div class="ddz-hero-sub">快速开始匹配 AI，或创建房间邀请好友一起玩</div>
        </div>
        <div class="ddz-lobby-panel">
            <button type="button" class="ddz-btn ddz-btn-gold" id="btnQuick">快速开始</button>
            <button type="button" class="ddz-btn ddz-btn-green" id="btnCreate">创建房间</button>
            <div class="ddz-join">
                <input id="ddzCodeInput" placeholder="输入房间号" maxlength="8" autocomplete="off">
                <button type="button" class="ddz-btn" id="btnJoin">加入</button>
            </div>
            <div class="ddz-score" id="ddzScore">积分：--</div>
        </div>
        <div class="ddz-rooms-panel">
            <div class="ddz-block-head">
                <span>等待中的房间</span>
                <button type="button" class="ddz-mini" id="btnRefreshRooms">刷新</button>
            </div>
            <div class="ddz-room-list" id="ddzRoomList"></div>
        </div>
        <div class="ddz-flash" id="ddzFlash" hidden></div>
    </section>

    <section class="ddz-table" id="ddzTable" hidden>
        <div class="ddz-topbar">
            <span class="ddz-chip" id="ddzRoomCode"></span>
            <span class="ddz-chip" id="ddzMult"></span>
            <span class="ddz-chip" id="ddzPhaseTip"></span>
            <button type="button" class="ddz-mini" id="btnLeave">退出房间</button>
        </div>
        <div class="ddz-arena">
            <div class="ddz-felt">
                <div class="ddz-felt-ring"></div>
                <div class="ddz-felt-logo">欢乐斗地主</div>
                <div class="ddz-bottom-cards" id="ddzBottom"></div>
                <div class="ddz-turn" id="ddzTurn"></div>
            </div>
            <div class="ddz-seat ddz-seat-left" id="seatLeft"></div>
            <div class="ddz-seat ddz-seat-right" id="seatRight"></div>
            <div class="ddz-play ddz-play-left" id="playLeft"></div>
            <div class="ddz-play ddz-play-right" id="playRight"></div>
            <div class="ddz-play ddz-play-me" id="playMe"></div>
            <div class="ddz-seat ddz-seat-me" id="meInfo"></div>
        </div>
        <div class="ddz-dock">
            <div class="ddz-actions" id="myActions"></div>
            <div class="ddz-myhand" id="myHand"></div>
        </div>
    </section>

    <div class="ddz-over-mask" id="ddzOver" hidden>
        <div class="ddz-over-card">
            <h2 id="ddzOverTitle">对局结束</h2>
            <div class="ddz-over-body" id="ddzOverBody"></div>
            <div class="ddz-over-actions">
                <button type="button" class="ddz-btn ddz-btn-gold" id="btnAgain">再来一局</button>
                <button type="button" class="ddz-btn" id="btnBackLobby">返回大厅</button>
            </div>
        </div>
    </div>
</div>
<script>window.DDZ = {
    csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>,
    code: <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>,
    uid: <?= (int) $user['id'] ?>
};</script>
<script><?= file_get_contents(__DIR__ . '/assets/js/ddz.js') ?></script>
<?php render_footer();