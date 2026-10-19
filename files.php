<?php
declare(strict_types=1);

@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '0');
@ini_set('memory_limit', '-1');
set_time_limit(0);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();

function files_safe_name(string $name): string
{
    $name = str_replace(["\0", '/', '\\'], '', $name);
    $name = basename($name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        return '';
    }
    return $name;
}

function files_unique_path(string $dir, string $name): string
{
    $dest = $dir . '/' . $name;
    if (!file_exists($dest)) {
        return $dest;
    }
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $i = 1;
    do {
        $candidate = $ext === '' ? $base . '_' . $i : $base . '_' . $i . '.' . $ext;
        $dest = $dir . '/' . $candidate;
        $i++;
    } while (file_exists($dest));
    return $dest;
}

function files_human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

if (isset($_GET['download'])) {
    $name = files_safe_name((string) $_GET['download']);
    $path = FILES_DIR . '/' . $name;
    $realDir = realpath(FILES_DIR);
    $realFile = $name !== '' ? realpath($path) : false;
    if ($name === '' || $realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
        flash_set('err', '文件不存在。');
        redirect('/files.php');
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . (string) filesize($realFile));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($realFile);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        flash_set('err', '请选择要上传的文件。');
        redirect('/files.php');
    }
    $names = $_FILES['file']['name'];
    $tmps = $_FILES['file']['tmp_name'];
    $errors = $_FILES['file']['error'];
    if (!is_array($names)) {
        $names = [$names];
        $tmps = [$tmps];
        $errors = [$errors];
    }
    $ok = 0;
    $fail = 0;
    foreach ($names as $i => $orig) {
        $err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string) ($tmps[$i] ?? '');
        if ($err !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            $fail++;
            continue;
        }
        $safe = files_safe_name((string) $orig);
        if ($safe === '') {
            $safe = 'file_' . date('YmdHis') . '_' . $i;
        }
        $dest = files_unique_path(FILES_DIR, $safe);
        if (!move_uploaded_file($tmp, $dest)) {
            $fail++;
            continue;
        }
        $ok++;
    }
    if ($ok > 0 && $fail === 0) {
        flash_set('ok', '已上传 ' . $ok . ' 个文件。');
    } elseif ($ok > 0) {
        flash_set('ok', '成功 ' . $ok . ' 个，失败 ' . $fail . ' 个。');
    } else {
        flash_set('err', '上传失败。若文件很大，请在宝塔把 upload_max_filesize 和 post_max_size 调成足够大。');
    }
    redirect('/files.php');
}

$items = [];
foreach (scandir(FILES_DIR) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === '.gitkeep') {
        continue;
    }
    $path = FILES_DIR . '/' . $entry;
    if (!is_file($path)) {
        continue;
    }
    $items[] = [
        'name' => $entry,
        'size' => filesize($path),
        'mtime' => date('Y-m-d H:i:s', (int) filemtime($path)),
    ];
}
usort($items, static fn(array $a, array $b): int => strcmp($b['mtime'], $a['mtime']));

render_header('文件管理', $admin, 'files');
?>
<section class="page-head">
    <h1>文件管理</h1>
</section>
<form class="panel" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="file">选择文件</label>
    <input id="file" name="file[]" type="file" multiple required>
    <button type="submit">上传</button>
</form>
<?php if (!$items): ?>
    <div class="empty">还没有文件。</div>
<?php else: ?>
    <div class="panel">
        <table class="table">
            <thead>
                <tr>
                    <th>文件名</th>
                    <th>大小</th>
                    <th>上传时间</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['name']) ?></td>
                    <td><?= h(files_human_size((int) $item['size'])) ?></td>
                    <td><?= h($item['mtime']) ?></td>
                    <td><a class="btn" href="/files.php?download=<?= rawurlencode($item['name']) ?>">下载</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php render_footer();
