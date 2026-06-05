<?php
require_once __DIR__.'/iot_common.php';

function req_float($key, $default) {
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? floatval($_GET[$key]) : $default;
}
function req_int($key, $default) {
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? intval($_GET[$key]) : $default;
}
function req_choice($key, $allow, $default) {
    $v = isset($_GET[$key]) ? $_GET[$key] : $default;
    return in_array($v, $allow, true) ? $v : $default;
}

$sensor = req_choice('sensor', array('cds', 'temp'), 'cds');
$mode = req_choice('mode', array('gte', 'lte', 'gt', 'lt'), 'gte');
$threshold = req_float('threshold', $sensor === 'temp' ? 24.0 : 60.0);
$days = req_int('days', 30);
$machine_id = req_int('machine_id', 0);
$product_id = req_int('product_id', 0);

if ($days < 0) $days = 0;
if ($days > 3650) $days = 3650;

$conn = iot_db_connect();

$where = array('1=1');
if ($days > 0) {
    $where[] = 'sl.date >= DATE_SUB(CURDATE(), INTERVAL '.$days.' DAY)';
}
if ($machine_id > 0) {
    $where[] = 'sl.machine_id = '.$machine_id;
}
if ($product_id > 0) {
    $where[] = 'sl.product_id = '.$product_id;
}
$whereSql = implode("\n      AND ", $where);

$opMap = array('gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<');
$op = isset($opMap[$mode]) ? $opMap[$mode] : '>=';
$conditionLabel = ($sensor === 'cds' ? '조도' : '온도') . ' ' . $op . ' ' . $threshold;
$sensorValueExpr = ($sensor === 'cds') ? 'ss.cds' : 'ss.temp';

$sql = <<<SQL
SELECT *
FROM (
    SELECT
        sl.id AS sale_id,
        sl.machine_id,
        m.name AS machine_name,
        m.location,
        sl.product_id,
        p.name AS product_name,
        TIMESTAMP(sl.date, sl.time) AS sold_at,
        sl.date AS sold_date,
        sl.time AS sold_time,
        sl.sold,
        sl.remain,
        sl.price,
        sl.revenue,
        (
            SELECT sg.id
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS sensor_id,
        (
            SELECT TIMESTAMP(sg.date, sg.time)
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS sensor_at,
        (
            SELECT sg.cds
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS cds,
        (
            SELECT sg.temp
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS temp,
        (
            SELECT sg.humi
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS humi
    FROM sales_log sl
    JOIN machines m ON m.id = sl.machine_id
    JOIN products p ON p.id = sl.product_id
    WHERE {$whereSql}
) ss
WHERE {$sensorValueExpr} IS NOT NULL
  AND {$sensorValueExpr} {$op} {$threshold}
ORDER BY sold_at DESC, sale_id DESC
SQL;

$rows = iot_fetch_all($conn, $sql);
mysqli_close($conn);

$totalSold = 0;
$totalRevenue = 0;
foreach ($rows as $r) {
    $totalSold += iot_int($r['sold']);
    $totalRevenue += iot_int($r['revenue']);
}

$columns = array(
    'sale_id' => '판매ID',
    'machine_name' => '자판기',
    'location' => '지역',
    'product_name' => '제품',
    'sold_at' => '판매시각',
    'sold' => '판매수량',
    'remain' => '판매후 재고',
    'price' => '가격',
    'revenue' => '매출',
    'sensor_at' => '센서시각',
    'cds' => '조도',
    'temp' => '온도',
    'humi' => '습도'
);
?>
<?php iot_page_begin('센서 조건별 판매 조회 표', 30); ?>
<form method="get" style="background:#fff;border:1px solid #d8dee9;border-radius:12px;padding:12px;margin-bottom:14px;">
  <label>센서
    <select name="sensor">
      <option value="cds" <?=$sensor==='cds'?'selected':''?>>조도(cds)</option>
      <option value="temp" <?=$sensor==='temp'?'selected':''?>>온도(temp)</option>
    </select>
  </label>
  <label>조건
    <select name="mode">
      <option value="gte" <?=$mode==='gte'?'selected':''?>>이상(&gt;=)</option>
      <option value="lte" <?=$mode==='lte'?'selected':''?>>이하(&lt;=)</option>
      <option value="gt" <?=$mode==='gt'?'selected':''?>>초과(&gt;)</option>
      <option value="lt" <?=$mode==='lt'?'selected':''?>>미만(&lt;)</option>
    </select>
  </label>
  <label>기준값 <input type="number" step="0.1" name="threshold" value="<?=iot_h($threshold)?>" style="width:80px"></label>
  <label>최근 일수 <input type="number" name="days" value="<?=iot_h($days)?>" style="width:70px">일, 0=전체</label>
  <label>machine_id <input type="number" name="machine_id" value="<?=iot_h($machine_id)?>" style="width:70px">, 0=전체</label>
  <label>product_id <input type="number" name="product_id" value="<?=iot_h($product_id)?>" style="width:70px">, 0=전체</label>
  <button type="submit">조회</button>
</form>

<div class="card-wrap">
  <div class="card"><div class="label">조건</div><div class="value" style="font-size:18px"><?=iot_h($conditionLabel)?></div></div>
  <div class="card"><div class="label">판매 건수</div><div class="value"><?=count($rows)?></div></div>
  <div class="card"><div class="label">판매 수량</div><div class="value"><?=$totalSold?></div></div>
  <div class="card"><div class="label">매출 합계</div><div class="value"><?=number_format($totalRevenue)?></div></div>
</div>

<?php iot_print_table($rows, $columns); ?>

<h2>사용 SQL</h2>
<pre><?=iot_h($sql)?></pre>
<?php iot_page_end(); ?>
