<?php
require_once __DIR__.'/iot_common.php';

function rq_int($key, $default) {
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? intval($_GET[$key]) : $default;
}
function rq_month($key) {
    $v = isset($_GET[$key]) ? trim($_GET[$key]) : '';
    return preg_match('/^\d{4}-\d{2}$/', $v) ? $v : '';
}

$month = rq_month('month');
$hour_start = rq_int('hour_start', 0);
$hour_end = rq_int('hour_end', 23);
$machine_id = rq_int('machine_id', 0);
$product_id = rq_int('product_id', 0);
if ($hour_start < 0) $hour_start = 0;
if ($hour_start > 23) $hour_start = 23;
if ($hour_end < 0) $hour_end = 0;
if ($hour_end > 23) $hour_end = 23;
if ($hour_start > $hour_end) { $tmp = $hour_start; $hour_start = $hour_end; $hour_end = $tmp; }

$conn = iot_db_connect();
$where = array('HOUR(sl.time) BETWEEN '.$hour_start.' AND '.$hour_end);
if ($month !== '') $where[] = "DATE_FORMAT(sl.date, '%Y-%m') = '".mysqli_real_escape_string($conn, $month)."'";
if ($machine_id > 0) $where[] = 'sl.machine_id = '.$machine_id;
if ($product_id > 0) $where[] = 'sl.product_id = '.$product_id;
$whereSql = implode("\n  AND ", $where);

$sql = <<<SQL
SELECT
    DATE_FORMAT(sl.date, '%Y-%m') AS sale_month,
    HOUR(sl.time) AS sale_hour,
    CONCAT(LPAD(HOUR(sl.time), 2, '0'), ':00~', LPAD(HOUR(sl.time), 2, '0'), ':59') AS hour_band,
    sl.machine_id,
    m.name AS machine_name,
    m.location,
    sl.product_id,
    p.name AS product_name,
    SUM(sl.sold) AS total_sold,
    COUNT(*) AS sale_count,
    SUM(sl.revenue) AS total_revenue,
    MIN(TIMESTAMP(sl.date, sl.time)) AS first_sold_at,
    MAX(TIMESTAMP(sl.date, sl.time)) AS last_sold_at
FROM sales_log sl
JOIN machines m ON m.id = sl.machine_id
JOIN products p ON p.id = sl.product_id
WHERE {$whereSql}
GROUP BY
    DATE_FORMAT(sl.date, '%Y-%m'),
    HOUR(sl.time),
    sl.machine_id,
    m.name,
    m.location,
    sl.product_id,
    p.name
ORDER BY sale_month DESC, sale_hour ASC, sl.machine_id ASC, sl.product_id ASC
SQL;
$rows = iot_fetch_all($conn, $sql);
mysqli_close($conn);

$totalSold = 0;
$totalRevenue = 0;
foreach ($rows as $r) {
    $totalSold += iot_int($r['total_sold']);
    $totalRevenue += iot_int($r['total_revenue']);
}

$columns = array(
    'sale_month' => '월',
    'hour_band' => '1시간대',
    'machine_name' => '자판기',
    'location' => '지역',
    'product_name' => '제품',
    'total_sold' => '판매수량 합계',
    'sale_count' => '판매건수',
    'total_revenue' => '매출합계',
    'first_sold_at' => '첫 판매시각',
    'last_sold_at' => '마지막 판매시각'
);
?>
<?php iot_page_begin('월별/1시간대 판매 집계 표', 30); ?>
<form method="get" style="background:#fff;border:1px solid #d8dee9;border-radius:12px;padding:12px;margin-bottom:14px;">
  <label>월 <input type="month" name="month" value="<?=iot_h($month)?>"> 비우면 전체 월</label>
  <label>시작시 <input type="number" name="hour_start" value="<?=iot_h($hour_start)?>" min="0" max="23" style="width:60px"></label>
  <label>종료시 <input type="number" name="hour_end" value="<?=iot_h($hour_end)?>" min="0" max="23" style="width:60px"></label>
  <label>machine_id <input type="number" name="machine_id" value="<?=iot_h($machine_id)?>" style="width:70px">, 0=전체</label>
  <label>product_id <input type="number" name="product_id" value="<?=iot_h($product_id)?>" style="width:70px">, 0=전체</label>
  <button type="submit">조회</button>
</form>

<div class="card-wrap">
  <div class="card"><div class="label">조회 행 수</div><div class="value"><?=count($rows)?></div></div>
  <div class="card"><div class="label">판매 수량 합계</div><div class="value"><?=$totalSold?></div></div>
  <div class="card"><div class="label">매출 합계</div><div class="value"><?=number_format($totalRevenue)?></div></div>
  <div class="card"><div class="label">시간대</div><div class="value" style="font-size:18px"><?=iot_h($hour_start)?>시~<?=iot_h($hour_end)?>시</div></div>
</div>

<?php iot_print_table($rows, $columns); ?>

<h2>사용 SQL</h2>
<pre><?=iot_h($sql)?></pre>
<?php iot_page_end(); ?>
