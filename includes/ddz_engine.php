<?php
declare(strict_types=1);

function ddz_rank(int $id): int
{
    return intdiv($id, 10);
}

function ddz_suit(int $id): int
{
    return $id % 10;
}

function ddz_new_deck(): array
{
    $deck = [];
    for ($r = 3; $r <= 15; $r++) {
        for ($s = 0; $s < 4; $s++) {
            $deck[] = $r * 10 + $s;
        }
    }
    $deck[] = 160;
    $deck[] = 170;
    return $deck;
}

function ddz_deal(): array
{
    $deck = ddz_new_deck();
    shuffle($deck);
    $hands = [[], [], []];
    for ($i = 0; $i < 51; $i++) {
        $hands[$i % 3][] = $deck[$i];
    }
    $bottom = array_slice($deck, 51, 3);
    foreach ($hands as $k => $h) {
        sort($h);
        $hands[$k] = $h;
    }
    sort($bottom);
    return ['hands' => $hands, 'bottom' => $bottom];
}

function ddz_counts(array $cards): array
{
    $counts = [];
    foreach ($cards as $id) {
        $r = ddz_rank((int) $id);
        $counts[$r] = ($counts[$r] ?? 0) + 1;
    }
    return $counts;
}

function ddz_all_same(array $counts, int $value): bool
{
    foreach ($counts as $c) {
        if ($c !== $value) {
            return false;
        }
    }
    return true;
}

function ddz_has_count(array $counts, int $value): bool
{
    foreach ($counts as $c) {
        if ($c === $value) {
            return true;
        }
    }
    return false;
}

function ddz_rank_of_count(array $counts, int $value): int
{
    foreach ($counts as $r => $c) {
        if ($c === $value) {
            return (int) $r;
        }
    }
    return 0;
}

function ddz_parse(array $cards): ?array
{
    $ids = array_values(array_map('intval', $cards));
    $n = count($ids);
    if ($n === 0) {
        return null;
    }
    $ranks = array_map('ddz_rank', $ids);
    sort($ranks);
    $counts = ddz_counts($ids);

    if ($n === 2 && isset($counts[16]) && isset($counts[17])) {
        return ['type' => 'rocket', 'len' => 1, 'rank' => 17];
    }
    if ($n === 4 && count($counts) === 1) {
        return ['type' => 'bomb', 'len' => 1, 'rank' => $ranks[0]];
    }
    if (count($counts) === 1) {
        $r = $ranks[0];
        if ($n === 1) {
            return ['type' => 'single', 'len' => 1, 'rank' => $r];
        }
        if ($n === 2) {
            return ['type' => 'pair', 'len' => 1, 'rank' => $r];
        }
        if ($n === 3) {
            return ['type' => 'trio', 'len' => 1, 'rank' => $r];
        }
    }
    if ($n >= 5 && count($counts) === $n && $ranks[$n - 1] < 15 && $ranks[$n - 1] - $ranks[0] === $n - 1) {
        return ['type' => 'straight', 'len' => $n, 'rank' => $ranks[$n - 1]];
    }
    if ($n >= 6 && $n % 2 === 0 && count($counts) === intdiv($n, 2) && ddz_all_same($counts, 2)) {
        $keys = array_keys($counts);
        sort($keys);
        $last = $keys[count($keys) - 1];
        if ($last < 15 && $last - $keys[0] === count($keys) - 1) {
            return ['type' => 'tube', 'len' => count($keys), 'rank' => $last];
        }
    }
    if ($n >= 6 && $n % 3 === 0 && count($counts) === intdiv($n, 3) && ddz_all_same($counts, 3)) {
        $keys = array_keys($counts);
        sort($keys);
        $last = $keys[count($keys) - 1];
        if ($last < 15 && $last - $keys[0] === count($keys) - 1) {
            return ['type' => 'plane', 'len' => count($keys), 'rank' => $last];
        }
    }
    if ($n === 4 && ddz_has_count($counts, 3)) {
        return ['type' => 'trio1', 'len' => 1, 'rank' => ddz_rank_of_count($counts, 3)];
    }
    if ($n === 5 && ddz_has_count($counts, 3) && ddz_has_count($counts, 2)) {
        return ['type' => 'trio2', 'len' => 1, 'rank' => ddz_rank_of_count($counts, 3)];
    }
    if ($n === 6 && ddz_has_count($counts, 4)) {
        return ['type' => 'four1', 'len' => 1, 'rank' => ddz_rank_of_count($counts, 4)];
    }
    if ($n === 8 && ddz_has_count($counts, 4)) {
        $c4 = ddz_rank_of_count($counts, 4);
        $rest = $counts;
        unset($rest[$c4]);
        if (count($rest) === 2 && ddz_all_same($rest, 2)) {
            return ['type' => 'four2', 'len' => 1, 'rank' => $c4];
        }
    }
    if ($n % 4 === 0 && $n >= 8) {
        $k = intdiv($n, 4);
        $cands = ddz_find_planes($counts, $k);
        if ($cands) {
            return ['type' => 'plane1', 'len' => $k, 'rank' => $cands[0]['rank']];
        }
    }
    if ($n % 5 === 0 && $n >= 10) {
        $k = intdiv($n, 5);
        foreach (ddz_find_planes($counts, $k) as $cand) {
            if (count($cand['rest']) === $k && ddz_all_same($cand['rest'], 2)) {
                return ['type' => 'plane2', 'len' => $k, 'rank' => $cand['rank']];
            }
        }
    }
    return null;
}

function ddz_find_planes(array $counts, int $k): array
{
    $ranks = [];
    foreach ($counts as $r => $c) {
        if ($c >= 3 && (int) $r < 15) {
            $ranks[] = (int) $r;
        }
    }
    sort($ranks);
    $len = count($ranks);
    $out = [];
    for ($i = 0; $i + $k - 1 < $len; $i++) {
        $ok = true;
        for ($j = 1; $j < $k; $j++) {
            if ($ranks[$i + $j] !== $ranks[$i + $j - 1] + 1) {
                $ok = false;
                break;
            }
        }
        if (!$ok) {
            continue;
        }
        $seg = array_slice($ranks, $i, $k);
        $rest = $counts;
        foreach ($seg as $r) {
            $rest[$r] -= 3;
            if ($rest[$r] <= 0) {
                unset($rest[$r]);
            }
        }
        $out[] = ['rank' => $seg[count($seg) - 1], 'rest' => $rest];
    }
    return $out;
}

function ddz_beats(array $a, array $b): bool
{
    if ($a['type'] === 'rocket') {
        return true;
    }
    if ($b['type'] === 'rocket') {
        return false;
    }
    if ($a['type'] === 'bomb') {
        return $b['type'] === 'bomb' ? $a['rank'] > $b['rank'] : true;
    }
    if ($b['type'] === 'bomb') {
        return false;
    }
    return $a['type'] === $b['type'] && $a['len'] === $b['len'] && $a['rank'] > $b['rank'];
}

function ddz_candidates(array $hand): array
{
    $hand = array_values(array_map('intval', $hand));
    sort($hand);
    $byRank = [];
    foreach ($hand as $id) {
        $byRank[ddz_rank($id)][] = $id;
    }
    ksort($byRank);
    $out = [];
    $add = function (array $cards) use (&$out): void {
        $t = ddz_parse($cards);
        if ($t !== null) {
            $t['cards'] = array_values($cards);
            $out[] = $t;
        }
    };

    foreach ($byRank as $ids) {
        $add([$ids[0]]);
        if (count($ids) >= 2) {
            $add(array_slice($ids, 0, 2));
        }
        if (count($ids) >= 3) {
            $add(array_slice($ids, 0, 3));
        }
        if (count($ids) >= 4) {
            $add(array_slice($ids, 0, 4));
        }
    }
    if (isset($byRank[16]) && isset($byRank[17])) {
        $add([$byRank[16][0], $byRank[17][0]]);
    }

    $lowRanks = [];
    foreach ($byRank as $r => $ids) {
        if ($r < 15) {
            $lowRanks[] = (int) $r;
        }
    }
    sort($lowRanks);
    $segCount = count($lowRanks);
    for ($i = 0; $i < $segCount; $i++) {
        for ($j = $i + 4; $j < $segCount; $j++) {
            if ($lowRanks[$j] !== $lowRanks[$j - 1] + 1) {
                break;
            }
            $seq = array_slice($lowRanks, $i, $j - $i + 1);
            $cards = array_map(fn ($r) => $byRank[$r][0], $seq);
            $add($cards);
        }
    }

    $pairRanks = [];
    $trioRanks = [];
    foreach ($byRank as $r => $ids) {
        if ((int) $r < 15 && count($ids) >= 2) {
            $pairRanks[] = (int) $r;
        }
        if ((int) $r < 15 && count($ids) >= 3) {
            $trioRanks[] = (int) $r;
        }
    }
    sort($pairRanks);
    sort($trioRanks);
    foreach ([['pair', $pairRanks, 3, 2], ['trio', $trioRanks, 2, 3]] as [$kind, $list, $minLen, $use]) {
        $cnt = count($list);
        for ($i = 0; $i < $cnt; $i++) {
            for ($j = $i + $minLen - 1; $j < $cnt; $j++) {
                if ($list[$j] !== $list[$j - 1] + 1) {
                    break;
                }
                $seq = array_slice($list, $i, $j - $i + 1);
                $cards = [];
                foreach ($seq as $r) {
                    $cards = array_merge($cards, array_slice($byRank[$r], 0, $use));
                }
                $add($cards);
            }
        }
    }

    foreach ($trioRanks as $r) {
        $attachSingle = ddz_smallest_attach($byRank, [$r], 1, 1);
        if ($attachSingle !== null) {
            $add(array_merge(array_slice($byRank[$r], 0, 3), $attachSingle));
        }
        $attachPair = ddz_smallest_attach($byRank, [$r], 1, 2);
        if ($attachPair !== null) {
            $add(array_merge(array_slice($byRank[$r], 0, 3), $attachPair));
        }
    }

    foreach ($byRank as $r => $ids) {
        if (count($ids) >= 4) {
            $single = ddz_smallest_attach($byRank, [$r], 2, 1);
            if ($single !== null) {
                $add(array_merge(array_slice($ids, 0, 4), $single));
            }
            $pair = ddz_smallest_attach($byRank, [$r], 2, 2);
            if ($pair !== null) {
                $add(array_merge(array_slice($ids, 0, 4), $pair));
            }
        }
    }

    $cnt = count($trioRanks);
    for ($i = 0; $i < $cnt; $i++) {
        for ($j = $i + 1; $j < $cnt; $j++) {
            if ($trioRanks[$j] !== $trioRanks[$j - 1] + 1) {
                break;
            }
            $seq = array_slice($trioRanks, $i, $j - $i + 1);
            $k = count($seq);
            $body = [];
            foreach ($seq as $r) {
                $body = array_merge($body, array_slice($byRank[$r], 0, 3));
            }
            $w1 = ddz_smallest_attach($byRank, $seq, $k, 1);
            if ($w1 !== null) {
                $add(array_merge($body, $w1));
            }
            $w2 = ddz_smallest_attach($byRank, $seq, $k, 2);
            if ($w2 !== null) {
                $add(array_merge($body, $w2));
            }
        }
    }
    return $out;
}

function ddz_smallest_attach(array $byRank, array $exclude, int $count, int $size): ?array
{
    $picked = [];
    foreach ($byRank as $r => $ids) {
        if ($size === 1) {
            if (in_array((int) $r, array_map('intval', $exclude), true) && count($ids) - 3 < 0) {
                continue;
            }
            if (count($ids) >= 1) {
                $avail = $ids;
                if (in_array((int) $r, array_map('intval', $exclude), true)) {
                    $avail = array_slice($ids, 3);
                }
                if (count($avail) >= 1) {
                    $picked[] = $avail[0];
                }
            }
        } else {
            if (count($ids) >= 2 && !in_array((int) $r, array_map('intval', $exclude), true)) {
                $picked = array_merge($picked, array_slice($ids, 0, 2));
            }
        }
        if (count($picked) >= $count * $size) {
            break;
        }
    }
    return count($picked) >= $count * $size ? array_slice($picked, 0, $count * $size) : null;
}

function ddz_ai_bid(array $hand): bool
{
    $score = 0;
    $byRank = [];
    foreach ($hand as $id) {
        $r = ddz_rank((int) $id);
        $byRank[$r] = ($byRank[$r] ?? 0) + 1;
        if ($r === 17) {
            $score += 6;
        } elseif ($r === 16) {
            $score += 4;
        } elseif ($r === 15) {
            $score += 2;
        } elseif ($r === 14) {
            $score += 1;
        }
    }
    foreach ($byRank as $c) {
        if ($c === 4) {
            $score += 6;
        }
    }
    return $score >= 5;
}

function ddz_ai_lead(array $hand, array $state): array
{
    $hand = array_values(array_map('intval', $hand));
    if (ddz_parse($hand) !== null) {
        return $hand;
    }
    $cands = ddz_candidates($hand);
    $preferred = [];
    foreach ($cands as $c) {
        if (in_array($c['type'], ['straight', 'tube', 'plane', 'plane1', 'plane2'], true)) {
            $preferred[] = $c;
        }
    }
    if ($preferred) {
        usort($preferred, fn ($a, $b) => count($b['cards']) <=> count($a['cards']));
        return $preferred[0]['cards'];
    }
    $plain = array_values(array_filter($cands, fn ($c) => $c['type'] !== 'bomb' && $c['type'] !== 'rocket'));
    if ($plain) {
        usort($plain, function ($a, $b) {
            $c = count($b['cards']) <=> count($a['cards']);
            return $c !== 0 ? $c : $a['rank'] <=> $b['rank'];
        });
        return $plain[0]['cards'];
    }
    return [$hand[0]];
}

function ddz_ai_follow(array $hand, array $last, array $state): ?array
{
    $hand = array_values(array_map('intval', $hand));
    $cands = ddz_candidates($hand);
    $usable = [];
    foreach ($cands as $c) {
        if ($c['type'] === 'bomb' || $c['type'] === 'rocket') {
            continue;
        }
        if (ddz_beats($c, $last)) {
            $usable[] = $c;
        }
    }
    if ($usable) {
        usort($usable, fn ($a, $b) => $a['rank'] !== $b['rank'] ? $a['rank'] <=> $b['rank'] : count($a['cards']) <=> count($b['cards']));
        return $usable[0]['cards'];
    }
    $bombs = [];
    foreach ($cands as $c) {
        if (($c['type'] === 'bomb' || $c['type'] === 'rocket') && ddz_beats($c, $last)) {
            $bombs[] = $c;
        }
    }
    if ($bombs) {
        $opponentLow = false;
        foreach ([0, 1, 2] as $s) {
            $p = $state['players'][$s] ?? null;
            if ($p && empty($p['is_ai']) && count($state['hands'][$s]) <= 3) {
                $opponentLow = true;
            }
        }
        if ($opponentLow || count($hand) <= 4) {
            usort($bombs, fn ($a, $b) => $a['rank'] <=> $b['rank']);
            return $bombs[0]['cards'];
        }
    }
    return null;
}

function ddz_new_state(array $players): array
{
    return [
        'phase' => 'waiting',
        'players' => $players,
        'hands' => [[], [], []],
        'bottom' => [],
        'landlord' => -1,
        'turn' => 0,
        'bidTurn' => 0,
        'startSeat' => 0,
        'bidCount' => 0,
        'lastPlay' => null,
        'lastSeat' => -1,
        'mult' => 1,
        'winner' => -1,
        'landlordWin' => false,
        'spring' => false,
        'played' => [0, 0, 0],
        'passed' => [false, false, false],
        'events' => [],
    ];
}

function ddz_start_game(array &$state): void
{
    $deal = ddz_deal();
    $state['hands'] = $deal['hands'];
    $state['bottom'] = $deal['bottom'];
    $state['phase'] = 'bid';
    $state['landlord'] = -1;
    $state['startSeat'] = random_int(0, 2);
    $state['bidTurn'] = $state['startSeat'];
    $state['turn'] = $state['startSeat'];
    $state['bidCount'] = 0;
    $state['lastPlay'] = null;
    $state['lastSeat'] = -1;
    $state['mult'] = 1;
    $state['winner'] = -1;
    $state['landlordWin'] = false;
    $state['spring'] = false;
    $state['played'] = [0, 0, 0];
    $state['passed'] = [false, false, false];
    $state['events'] = [['t' => 'start']];
}

function ddz_finish_bid(array &$state): void
{
    $landlord = (int) $state['landlord'];
    if ($landlord < 0) {
        $landlord = (int) $state['startSeat'];
        $state['landlord'] = $landlord;
    }
    $state['phase'] = 'play';
    $state['turn'] = $landlord;
    foreach ($state['bottom'] as $b) {
        $state['hands'][$landlord][] = $b;
    }
    sort($state['hands'][$landlord]);
    $state['events'][] = ['t' => 'landlord', 'seat' => $landlord];
}

function ddz_apply_bid(array &$state, int $seat, bool $rob): void
{
    if ($state['phase'] !== 'bid' || $seat !== (int) $state['bidTurn']) {
        return;
    }
    $state['bidCount'] = (int) $state['bidCount'] + 1;
    if ($rob) {
        $state['landlord'] = $seat;
    }
    $state['events'][] = ['t' => 'bid', 'seat' => $seat, 'rob' => $rob];
    if ((int) $state['bidCount'] >= 3) {
        ddz_finish_bid($state);
        return;
    }
    $state['bidTurn'] = ($seat + 1) % 3;
    $state['turn'] = (int) $state['bidTurn'];
}

function ddz_apply_play(array &$state, int $seat, array $cards): array
{
    if ($state['phase'] !== 'play' || $seat !== (int) $state['turn']) {
        return ['ok' => false, 'error' => '还没轮到你。'];
    }
    $cards = array_values(array_map('intval', $cards));
    $hand = $state['hands'][$seat];
    $remain = $hand;
    foreach ($cards as $c) {
        $i = array_search($c, $remain, true);
        if ($i === false) {
            return ['ok' => false, 'error' => '你没有这些牌。'];
        }
        unset($remain[$i]);
    }
    $type = ddz_parse($cards);
    if ($type === null) {
        return ['ok' => false, 'error' => '牌型不合法。'];
    }
    $last = $state['lastPlay'];
    if ($last !== null && (int) $last['seat'] !== $seat) {
        if (!ddz_beats($type, ['type' => $last['type'], 'len' => $last['len'], 'rank' => $last['rank']])) {
            return ['ok' => false, 'error' => '管不上上家的牌。'];
        }
    }
    $state['hands'][$seat] = array_values($remain);
    $state['played'][$seat] = (int) $state['played'][$seat] + 1;
    $state['lastPlay'] = ['seat' => $seat, 'cards' => $cards, 'type' => $type['type'], 'len' => $type['len'], 'rank' => $type['rank']];
    $state['lastSeat'] = $seat;
    if ($type['type'] === 'bomb' || $type['type'] === 'rocket') {
        $state['mult'] = (int) $state['mult'] * 2;
    }
    $state['passed'] = [false, false, false];
    $state['events'][] = ['t' => 'play', 'seat' => $seat, 'cards' => $cards, 'ctype' => $type['type']];
    if (count($state['events']) > 40) {
        $state['events'] = array_slice($state['events'], -40);
    }
    if (count($state['hands'][$seat]) === 0) {
        $state['winner'] = $seat;
        ddz_settle($state);
        return ['ok' => true];
    }
    $state['turn'] = ($seat + 1) % 3;
    return ['ok' => true];
}

function ddz_apply_pass(array &$state, int $seat): array
{
    if ($state['phase'] !== 'play' || $seat !== (int) $state['turn']) {
        return ['ok' => false, 'error' => '还没轮到你。'];
    }
    if ($state['lastPlay'] === null || (int) $state['lastPlay']['seat'] === $seat) {
        return ['ok' => false, 'error' => '你是本轮首个出牌者，必须出牌。'];
    }
    $state['passed'][$seat] = true;
    $state['events'][] = ['t' => 'pass', 'seat' => $seat];
    if (count($state['events']) > 40) {
        $state['events'] = array_slice($state['events'], -40);
    }
    $next = ($seat + 1) % 3;
    if ((int) $state['lastSeat'] === $next) {
        $state['lastPlay'] = null;
        $state['passed'] = [false, false, false];
    }
    $state['turn'] = $next;
    return ['ok' => true];
}

function ddz_settle(array &$state): void
{
    $state['phase'] = 'over';
    $landlord = (int) $state['landlord'];
    $winner = (int) $state['winner'];
    $landlordWin = $winner === $landlord;
    $spring = false;
    if ($landlordWin) {
        $farmers = 0;
        foreach ([0, 1, 2] as $s) {
            if ($s !== $landlord) {
                $farmers += (int) $state['played'][$s];
            }
        }
        if ($farmers === 0) {
            $spring = true;
        }
    } elseif ((int) $state['played'][$landlord] <= 1) {
        $spring = true;
    }
    if ($spring) {
        $state['mult'] = (int) $state['mult'] * 2;
    }
    $stake = (int) $state['mult'];
    foreach ([0, 1, 2] as $s) {
        if ($s === $landlord) {
            $delta = $landlordWin ? 2 * $stake : -2 * $stake;
        } else {
            $delta = $landlordWin ? -$stake : $stake;
        }
        $state['players'][$s]['score'] = (int) ($state['players'][$s]['score'] ?? 0) + $delta;
        $state['players'][$s]['last_delta'] = $delta;
    }
    $state['spring'] = $spring;
    $state['landlordWin'] = $landlordWin;
    $state['events'][] = ['t' => 'over', 'winner' => $winner, 'landlord' => $landlord, 'mult' => $state['mult'], 'spring' => $spring];
}

function ddz_advance(array &$state): void
{
    $guard = 0;
    while (($state['phase'] === 'bid' || $state['phase'] === 'play') && $guard++ < 300) {
        $seat = $state['phase'] === 'bid' ? (int) $state['bidTurn'] : (int) $state['turn'];
        $p = $state['players'][$seat] ?? null;
        if (!$p || empty($p['is_ai'])) {
            break;
        }
        if ($state['phase'] === 'bid') {
            ddz_apply_bid($state, $seat, ddz_ai_bid($state['hands'][$seat]));
            continue;
        }
        $hand = $state['hands'][$seat];
        $last = $state['lastPlay'];
        if ($last !== null && (int) $last['seat'] === $seat) {
            $last = null;
        }
        if ($last === null) {
            $cards = ddz_ai_lead($hand, $state);
        } else {
            $cards = ddz_ai_follow($hand, ['type' => $last['type'], 'len' => $last['len'], 'rank' => $last['rank']], $state);
        }
        if ($cards === null || count($cards) === 0) {
            $r = ddz_apply_pass($state, $seat);
            if (!$r['ok']) {
                ddz_apply_play($state, $seat, [$hand[0]]);
            }
        } else {
            $r = ddz_apply_play($state, $seat, $cards);
            if (!$r['ok']) {
                ddz_apply_play($state, $seat, [$hand[0]]);
            }
        }
    }
}

function ddz_public_state(array $state, int $viewerSeat): array
{
    $out = $state;
    $counts = [];
    foreach ([0, 1, 2] as $s) {
        $counts[$s] = count($state['hands'][$s]);
    }
    $out['handCounts'] = $counts;
    $out['mySeat'] = $viewerSeat;
    $out['myHand'] = $viewerSeat >= 0 && isset($state['hands'][$viewerSeat]) ? $state['hands'][$viewerSeat] : [];
    unset($out['hands']);
    if ($state['phase'] !== 'over') {
        $out['bottom'] = [];
    }
    return $out;
}