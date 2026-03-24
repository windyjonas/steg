<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── CONFIG via .env ───────────────────────────────────────────────────
$env = [];
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

$cache_file  = $env['CACHE_FILE']    ?? '/var/lib/garmin-cache/steps.json';
$goal_per_day = (int)($env['GOAL_PER_DAY'] ?? 10000);

if (!file_exists($cache_file)) {
    http_response_code(503);
    echo json_encode(['error' => 'Ingen data tillgänglig ännu.']);
    exit;
}

$raw = json_decode(file_get_contents($cache_file), true);
if (!$raw) {
    http_response_code(503);
    echo json_encode(['error' => 'Kunde inte läsa cachen.']);
    exit;
}

$year = (int)(new DateTime('now', new DateTimeZone('Europe/Stockholm')))->format('Y');

$months     = [];
$daily      = [];
$best_day   = ['date' => '', 'steps' => 0];

// Find Monday of ISO week 1 of current year (may be in Dec previous year)
$isoWeek1Monday = new DateTime();
$isoWeek1Monday->setISODate($year, 1, 1);
$isoWeek1Start = $isoWeek1Monday->format('Y-m-d');

foreach ($raw as $date => $steps) {
    $is_current_year     = (int)substr($date, 0, 4) === $year;
    $is_prev_year_iso_w1 = !$is_current_year && $date >= $isoWeek1Start;

    if (!$is_current_year && !$is_prev_year_iso_w1) continue;

    $steps = (int)$steps;
    $daily[$date] = $steps;

    if ($is_current_year) {
        $month = (int)substr($date, 5, 2);
        if (!isset($months[$month])) $months[$month] = 0;
        $months[$month] += $steps;

        if ($steps > $best_day['steps']) {
            $best_day = ['date' => $date, 'steps' => $steps];
        }
    }
}

// Group by ISO week
$weeks = [];
foreach ($daily as $date => $steps) {
    $dt  = new DateTime($date);
    $wk  = (int)$dt->format('W');
    $yr  = (int)$dt->format('o');
    $key = sprintf('%04d-W%02d', $yr, $wk);
    if (!isset($weeks[$key])) {
        $mon = clone $dt;
        $mon->modify('monday this week');
        $sun = clone $mon;
        $sun->modify('+6 days');
        $weeks[$key] = [
            'key'   => $key,
            'week'  => $wk,
            'label' => 'V.' . $wk,
            'from'  => $mon->format('d/m'),
            'to'    => $sun->format('d/m'),
            'steps' => 0,
            'days'  => 0,
        ];
    }
    $weeks[$key]['steps'] += $steps;
    if ($steps > 0) $weeks[$key]['days']++;
}
ksort($weeks);
$weeks = array_values($weeks);

// Build month data with days info
$sv_months  = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];
$today_dt   = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
$cur_m      = (int)$today_dt->format('m');
$cur_day    = (int)$today_dt->format('d');
$month_data = [];
for ($m = 1; $m <= 12; $m++) {
    $days_in_month = (int)(new DateTime("$year-$m-01"))->format('t');
    $days_elapsed  = ($m < $cur_m) ? $days_in_month : ($m === $cur_m ? $cur_day : 0);
    $month_data[] = [
        'label'         => $sv_months[$m],
        'steps'         => $months[$m] ?? 0,
        'month'         => $m,
        'days_in_month' => $days_in_month,
        'days_elapsed'  => $days_elapsed,
    ];
}

// Stats (current year only)
$year_steps  = array_filter($daily, fn($s, $d) => substr($d, 0, 4) == $year, ARRAY_FILTER_USE_BOTH);
$active_days = array_filter($year_steps, fn($s) => $s > 0);
$total       = array_sum($year_steps);
$avg         = count($active_days) > 0 ? (int)round($total / count($active_days)) : 0;

// Rolling 365-day average
$all_active = array_filter(array_values($raw), fn($s) => (int)$s > 0);
$avg_365    = count($all_active) > 0 ? (int)round(array_sum($all_active) / count($all_active)) : 0;

// Current month daily breakdown
$cur_month_daily = [];
foreach ($daily as $date => $steps) {
    if (substr($date, 0, 4) == $year && (int)substr($date, 5, 2) === $cur_m) {
        $cur_month_daily[] = ['date' => $date, 'steps' => $steps, 'day' => (int)substr($date, 8, 2)];
    }
}
usort($cur_month_daily, fn($a, $b) => strcmp($a['date'], $b['date']));

echo json_encode([
    'year'          => $year,
    'total'         => $total,
    'daily_avg'     => $avg,
    'avg_365'       => $avg_365,
    'days_tracked'  => count($year_steps),
    'active_days'   => count($active_days),
    'best_day'      => $best_day,
    'goal_per_day'  => $goal_per_day,
    'months'        => $month_data,
    'weeks'         => $weeks,
    'current_month' => $cur_month_daily,
    'cache_updated' => date('Y-m-d H:i', filemtime($cache_file)),
], JSON_UNESCAPED_UNICODE);
