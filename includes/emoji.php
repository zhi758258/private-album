<?php
declare(strict_types=1);

function emoji_qq_names(): array
{
    return [
        '微笑', '撇嘴', '色', '发呆', '得意', '流泪', '害羞', '闭嘴', '睡', '大哭',
        '尴尬', '发怒', '调皮', '呲牙', '惊讶', '难过', '酷', '冷汗', '抓狂', '吐',
        '偷笑', '愉快', '白眼', '傲慢', '饥饿', '困', '惊恐', '流汗', '憨笑', '悠闲',
        '奋斗', '咒骂', '疑问', '嘘', '晕', '疯了', '衰', '骷髅', '敲打', '再见',
        '擦汗', '抠鼻', '鼓掌', '糗大了', '坏笑', '左哼哼', '右哼哼', '哈欠', '鄙视', '委屈',
        '快哭了', '阴险', '亲亲', '吓', '可怜', '菜刀', '西瓜', '啤酒', '篮球', '乒乓',
        '咖啡', '饭', '猪头', '玫瑰', '凋谢', '示爱', '爱心', '心碎', '蛋糕', '闪电',
        '炸弹', '刀', '足球', '瓢虫', '便便', '月亮', '太阳', '礼物', '拥抱', '强',
        '弱', '握手', '胜利', '抱拳', '勾引', '拳头', '差劲', '爱你', 'NO', 'OK',
        '爱情', '飞吻', '跳跳', '发抖', '怄火', '街舞', '磕头', '回头', '跳绳', '挥手',
        '激动', '转圈', '献吻', '左太极', '右太极', '双喜', '鞭炮', '灯笼', '发财', 'K歌',
        '购物', '邮件', '帅', '喝彩', '祈祷', '爆筋', '棒棒糖', '喝奶', '面条', '香蕉',
        '飞机', '开车',
    ];
}

function emoji_wx_names(): array
{
    return [
        '微笑', '撇嘴', '色', '发呆', '得意', '流泪', '害羞', '闭嘴', '睡', '大哭',
        '尴尬', '发怒', '调皮', '呲牙', '惊讶', '难过', '酷', '冷汗', '抓狂', '吐',
        '偷笑', '愉快', '白眼', '傲慢', '饥饿', '困', '惊恐', '流汗', '憨笑', '悠闲',
        '奋斗', '咒骂', '疑问', '嘘', '晕', '疯了', '衰', '骷髅', '敲打', '再见',
        '擦汗', '抠鼻', '鼓掌', '糗大了', '坏笑', '左哼哼', '右哼哼', '哈欠', '鄙视', '委屈',
        '快哭了', '阴险', '亲亲', '吓', '可怜', '菜刀', '西瓜', '啤酒', '篮球', '乒乓',
        '咖啡', '饭', '猪头', '玫瑰', '凋谢', '嘴唇', '爱心', '心碎', '蛋糕', '闪电',
        '炸弹', '刀', '足球', '瓢虫', '便便', '月亮', '太阳', '礼物', '拥抱', '强',
        '弱', '握手', '胜利', '抱拳', '勾引', '拳头', '差劲', '爱你', 'NO', 'OK',
        '爱情', '飞吻', '跳跳', '发抖', '怄火', '转圈', '磕头', '回头', '跳绳', '投降',
        '激动', '乱舞', '献吻', '左太极', '右太极',
    ];
}

function emoji_dy_names(): array
{
    return [
        '笑哭', '捂脸', '泣不成声', '奸笑', '嘿哈', '大笑', '微笑', '色', '发呆', '酷拽',
        '抠鼻', '流泪', '发怒', '呲牙', '害羞', '可怜', '打脸', '喷血', '吐血', '衰',
        '黑脸', '疑惑', '困', '口罩', '思考', '惊呆', '震惊', '无语', '裂开', '笑死',
        '绝了', 'emo', '破防', '摆烂', '杀我', '比心', '点赞', '鼓掌', '握手', '加一',
        'OK', '加油', '来看我', '灵机一动', '送心', '互粉', '亲吻', '玫瑰', '心', '心碎',
        '火', '满分', '爆炸', '闪电', '星星', '月亮', '太阳', '彩虹', '花', '庆祝',
        '钱', '皇冠', '钻石', '气球', '礼物', '生日', '音乐', '跳舞', '干杯', '吃饭',
        '晚安', '吃瓜', '摸鱼', '躺平', '狗头', '猪头', '猫', '兔', '熊', '青蛙',
        '便便', '鬼', '机器人', '祈祷', '耶', '抱拳', '弱', '胜利', '爱你', '飞吻',
        '拥抱', '哈欠', '睡', '奋斗', '疑问',
    ];
}

function emoji_aliases(): array
{
    return [
        '微笑' => ['哈哈', '笑', '嘻嘻'],
        '憨笑' => ['哈哈', '大笑', '嘿嘿'],
        '偷笑' => ['哈哈', '嘿嘿'],
        '呲牙' => ['哈哈', '嘻嘻', '嘿嘿'],
        '调皮' => ['哈哈', '吐舌'],
        '愉快' => ['哈哈', '开心'],
        '大笑' => ['哈哈', '笑死'],
        '嘿哈' => ['哈哈', '嘿嘿'],
        '笑哭' => ['哈哈', '笑死', '哈哈哈'],
        '笑死' => ['哈哈', '哈哈哈'],
        '流泪' => ['哭', '呜呜'],
        '大哭' => ['哭', '呜呜', '泪'],
        '快哭了' => ['哭'],
        '泣不成声' => ['哭', '泪崩'],
        '爱心' => ['心', '比心', '爱你'],
        '送心' => ['心', '比心'],
        '心' => ['比心', '爱心'],
        '比心' => ['心', '爱你'],
        '亲亲' => ['吻', '么么'],
        '飞吻' => ['吻', '么么'],
        '玫瑰' => ['花'],
        '强' => ['赞', '点赞', '牛'],
        '点赞' => ['赞', '牛'],
        'OK' => ['好的', 'ok', '好'],
        '疑问' => ['问号', '啥', '啊'],
        '困' => ['睡觉', '晚安'],
        '睡' => ['睡觉', '晚安'],
        '晚安' => ['睡觉'],
        '狗头' => ['狗', 'doge'],
        '便便' => ['屎'],
        '吃瓜' => ['看戏'],
        'emo' => ['难过', '抑郁'],
        '破防' => ['哭', '泪崩'],
        '摆烂' => ['躺平'],
        '躺平' => ['摆烂'],
    ];
}

function emoji_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }
    $aliases = emoji_aliases();
    $qq = [];
    foreach (emoji_qq_names() as $i => $name) {
        $qq[] = [
            'pack' => 'qq',
            'name' => $name,
            'token' => '[qq:' . $name . ']',
            'src' => '/assets/emoji/qq/' . $i . '.gif',
            'kind' => 'face',
            'aliases' => $aliases[$name] ?? [],
        ];
    }
    $wx = [];
    foreach (emoji_wx_names() as $i => $name) {
        $wx[] = [
            'pack' => 'wx',
            'name' => $name,
            'token' => '[wx:' . $name . ']',
            'src' => '/assets/emoji/wx/' . $i . '.gif',
            'kind' => 'face',
            'aliases' => $aliases[$name] ?? [],
        ];
    }
    $dy = [];
    foreach (emoji_dy_names() as $i => $name) {
        $dy[] = [
            'pack' => 'dy',
            'name' => $name,
            'token' => '[dy:' . $name . ']',
            'src' => '/assets/emoji/dy/' . $i . '.png',
            'kind' => 'face',
            'aliases' => $aliases[$name] ?? [],
        ];
    }
    $catalog = ['qq' => $qq, 'wx' => $wx, 'dy' => $dy];
    return $catalog;
}

function emoji_lookup(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    foreach (emoji_catalog() as $pack => $items) {
        foreach ($items as $item) {
            $map[$pack][$item['name']] = $item;
        }
    }
    return $map;
}

function emoji_is_public_https(string $url): bool
{
    if (strlen($url) > 480 || strlen($url) < 12) {
        return false;
    }
    if (!str_starts_with($url, 'https://') || preg_match('/[\s<>"\']/', $url)) {
        return false;
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '' || str_contains($host, ':') || !str_contains($host, '.')) {
        return false;
    }
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    return $scheme === 'https';
}

function emoji_img_tag(array $item, string $cls = 'emoji-face'): string
{
    $name = h($item['name']);
    $src = h($item['src'] ?? '');
    return '<img class="' . h($cls) . '" src="' . $src . '" alt="' . $name . '" title="' . $name . '" loading="lazy">';
}

function emoji_net_tag(string $url): string
{
    if (!emoji_is_public_https($url)) {
        return h($url);
    }
    $src = h($url);
    return '<img class="emoji-sticker" src="' . $src . '" alt="表情" loading="lazy" referrerpolicy="no-referrer">';
}

function render_message_html(string $content): string
{
    $lookup = emoji_lookup();
    $parts = preg_split(
        '/(\[(?:qq|wx|dy):[^\[\]]{1,32}\]|\[net:https:\/\/[^\[\s<>"\']{8,480}\])/u',
        $content,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );
    if (!is_array($parts)) {
        return nl2br(h($content));
    }
    $out = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match('/^\[(qq|wx|dy):([^\[\]]{1,32})\]$/u', $part, $m)) {
            $item = $lookup[$m[1]][$m[2]] ?? null;
            $out .= $item ? emoji_img_tag($item) : h($part);
            continue;
        }
        if (preg_match('/^\[net:(https:\/\/[^\[\s<>"\']{8,480})\]$/u', $part, $m)) {
            $out .= emoji_net_tag($m[1]);
            continue;
        }
        $out .= nl2br(h($part));
    }
    return $out;
}

function emoji_local_search(string $q, int $limit = 24): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $qLower = mb_strtolower($q);
    $hits = [];
    foreach (emoji_catalog() as $items) {
        foreach ($items as $item) {
            $name = mb_strtolower((string) $item['name']);
            $score = 0;
            if ($name === $qLower) {
                $score = 100;
            } elseif (mb_strpos($name, $qLower) !== false) {
                $score = 80 - (int) mb_strpos($name, $qLower);
            } elseif (mb_strpos($qLower, $name) !== false) {
                $score = 60;
            } else {
                foreach ($item['aliases'] ?? [] as $alias) {
                    $al = mb_strtolower((string) $alias);
                    if ($al === $qLower) {
                        $score = 90;
                        break;
                    }
                    if (mb_strpos($al, $qLower) !== false || mb_strpos($qLower, $al) !== false) {
                        $score = 70;
                        break;
                    }
                }
            }
            if ($score > 0) {
                $row = $item;
                $row['score'] = $score;
                $hits[] = $row;
            }
        }
    }
    usort($hits, static function ($a, $b) {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });
    return array_slice($hits, 0, $limit);
}

function emoji_http_get(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json,text/javascript,*/*'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : '';
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

function emoji_https_url(string $url): string
{
    $url = trim($url);
    if (str_starts_with($url, 'http://')) {
        $url = 'https://' . substr($url, 7);
    }
    return $url;
}

function emoji_looks_gif(string $url, string $type = ''): bool
{
    $u = strtolower($url);
    $t = strtolower($type);
    return str_contains($u, '.gif') || $t === 'gif' || $t === 'other';
}

function emoji_sticker_score(array $row, string $url): int
{
    $w = (int) ($row['width'] ?? 0);
    $h = (int) ($row['height'] ?? 0);
    $score = 10;
    if (emoji_looks_gif($url, (string) ($row['imgtype'] ?? ''))) {
        $score += 40;
    }
    if ($w > 0 && $h > 0) {
        if ($w >= 80 && $h >= 80 && $w <= 480 && $h <= 480) {
            $score += 25;
        }
        $ratio = $w / max($h, 1);
        if ($ratio >= 0.7 && $ratio <= 1.4) {
            $score += 15;
        }
        if ($w > 800 || $h > 800) {
            $score -= 20;
        }
    }
    $title = mb_strtolower((string) ($row['title'] ?? ''));
    foreach (['表情包', '表情', 'gif', '动图'] as $hit) {
        if (str_contains($title, $hit)) {
            $score += 8;
            break;
        }
    }
    return $score;
}

function emoji_net_item(string $url, string $title, string $fallback): array
{
    $title = mb_substr(trim($title), 0, 24);
    if ($title === '') {
        $title = $fallback;
    }
    return [
        'pack' => 'net',
        'name' => $title,
        'token' => '[net:' . $url . ']',
        'src' => $url,
        'kind' => 'sticker',
    ];
}

function emoji_net_hot(int $limit = 24): array
{
    $raw = emoji_http_get('https://www.soogif.com/hotGif?start=0&json=1');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    $rows = $data['data']['result'] ?? null;
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $url = emoji_https_url((string) ($row['gifurl'] ?? ''));
        if ($url === '' || isset($seen[$url]) || !emoji_is_public_https($url)) {
            continue;
        }
        $seen[$url] = true;
        $out[] = emoji_net_item($url, (string) ($row['title'] ?? $row['label'] ?? '热门斗图'), '热门斗图');
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

function emoji_net_search(string $q, int $limit = 36): array
{
    $q = trim($q);
    if ($q === '') {
        return emoji_net_hot($limit);
    }
    $query = $q;
    if (!preg_match('/gif|表情包|斗图|动图/iu', $q)) {
        $query = $q . ' gif 表情包';
    }
    $url = 'https://image.so.com/j?' . http_build_query([
        'q' => $query,
        'src' => 'srp',
        'pn' => max($limit * 2, 48),
        'sn' => 0,
    ]);
    $raw = emoji_http_get($url);
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['list']) || !is_array($data['list'])) {
        return [];
    }
    $ranked = [];
    $seen = [];
    foreach ($data['list'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $candidates = [];
        foreach (['img', 'thumb'] as $key) {
            $u = emoji_https_url((string) ($row[$key] ?? ''));
            if ($u !== '') {
                $candidates[] = $u;
            }
        }
        usort($candidates, static function (string $a, string $b): int {
            return (int) emoji_looks_gif($b) <=> (int) emoji_looks_gif($a);
        });
        $picked = '';
        foreach ($candidates as $u) {
            if (emoji_is_public_https($u)) {
                $picked = $u;
                break;
            }
        }
        if ($picked === '' || isset($seen[$picked])) {
            continue;
        }
        $seen[$picked] = true;
        $title = trim((string) ($row['title'] ?? $q));
        $title = mb_substr($title, 0, 24);
        $ranked[] = [
            'pack' => 'net',
            'name' => $title !== '' ? $title : $q,
            'token' => '[net:' . $picked . ']',
            'src' => $picked,
            'kind' => 'sticker',
            'score' => emoji_sticker_score($row, $picked),
        ];
    }
    usort($ranked, static function ($a, $b) {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });
    $out = [];
    foreach ($ranked as $item) {
        unset($item['score']);
        $out[] = $item;
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}
