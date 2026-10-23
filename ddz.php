<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));

render_header(nav_label('ddz'), $user, 'ddz');
?>
<link rel="stylesheet" href="/assets/css/ddz.css">
<div class="ddz" id="ddzApp" data-code="<?= h($code) ?>" data-csrf="<?= h(csrf_token()) ?>" data-uid="<?= (int) $user['id'] ?>" data-name="<?= h($user['display_name']) ?>">

    <section class="ddz-lobby" id="ddzLobby">
        <div class="ddz-lobby-head">
            <div>
                <h1>斗地主大厅</h1>
                <p>快速开始匹配两名 AI，或创建房间邀请好友一起玩。</p>
            </div>
            <div class="ddz-score" id="ddzScore">积分：--</div>
        </div>
        <div class="ddz-lobby-actions">
            <button type="button" class="btn ddz-big" id="btnQuick">快速开始</button>
            <button type="button" class="btn ghost-btn ddz-big" id="btnCreate">创建房间</button>
        </div>
        <div class="ddz-join">
            <input id="ddzCodeInput" placeholder="输入房间号" maxlength="8" autocomplete="off">
            <button type="button" class="btn" id="btnJoin">加入房间</button>
        </div>
        <div class="ddz-rooms">
            <div class="block-head">
                <h2>等待中的房间</h2>
                <button type="button" class="btn ghost-btn" id="btnRefreshRooms">刷新</button>
            </div>
            <div class="ddz-room-list" id="ddzRoomList"></div>
        </div>
        <div class="ddz-flash" id="ddzFlash" hidden></div>
    </section>

    <section class="ddz-table" id="ddzTable" hidden>
        <div class="ddz-table-head">
            <span class="ddz-chip" id="ddzRoomCode"></span>
            <span class="ddz-chip" id="ddzMult"></span>
            <span class="ddz-chip" id="ddzPhaseTip"></span>
            <button type="button" class="btn ghost-btn" id="btnLeave">退出房间</button>
        </div>
        <div class="ddz-board">
            <div class="ddz-seat ddz-seat-top" id="seatTop"></div>
            <div class="ddz-seat ddz-seat-left" id="seatLeft"></div>
            <div class="ddz-seat ddz-seat-right" id="seatRight"></div>
            <div class="ddz-center">
                <div class="ddz-bottom-cards" id="ddzBottom"></div>
                <div class="ddz-turn" id="ddzTurn"></div>
            </div>
        </div>
        <div class="ddz-myhand-wrap">
            <div class="ddz-myhand" id="myHand"></div>
        </div>
        <div class="ddz-actions" id="myActions"></div>
    </section>

    <div class="ddz-over-mask" id="ddzOver" hidden>
        <div class="ddz-over-card">
            <h2 id="ddzOverTitle">对局结束</h2>
            <div class="ddz-over-body" id="ddzOverBody"></div>
            <div class="ddz-over-actions">
                <button type="button" class="btn" id="btnAgain">再来一局</button>
                <button type="button" class="btn ghost-btn" id="btnBackLobby">返回大厅</button>
            </div>
        </div>
    </div>
</div>
<script>window.DDZ = {
    csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>,
    code: <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>,
    uid: <?= (int) $user['id'] ?>
};</script>
<script src="/assets/js/ddz.js"></script>
<?php render_footer();