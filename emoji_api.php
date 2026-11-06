<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => '请先登录。'], JSON_UNESCAPED_UNICODE));
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'search');

if ($action === 'catalog') {
    echo json_encode(['ok' => true, 'packs' => emoji_catalog()], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
if (mb_strlen($q) > 24) {
    $q = mb_substr($q, 0, 24);
}

    $faces = $q === '' ? [] : emoji_local_search($q, 12);
    $stickers = [];
    $wantNet = ($_GET['net'] ?? $_POST['net'] ?? '1') !== '0';
    if ($wantNet) {
        $stickers = emoji_net_search($q, 36);
    }

echo json_encode([
    'ok' => true,
    'q' => $q,
    'faces' => $faces,
    'stickers' => $stickers,
], JSON_UNESCAPED_UNICODE);
