<?php
require_once __DIR__.'/iot_common.php';

function req_float2($key, $default) {
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? floatval($_GET[$key]) : $default;
}
function req_int2($key, $default) {
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? intval($_GET[$key]) : $default;
}
function req_choice2($key, $allow, $default) {
    $v = isset($_GET[$key]) ? $_GET[$key] : $default;
    return in_array($v, $allow, true) ? $v : $default;
}

$sensor = req_choice2('sensor', array('cds', 'temp'), 'cds');
$mode = req_choice2('mode', array('gte', 'lte', 'gt', 'lt'), 'gte');
$threshold = req_float2('threshold', $sensor === 'temp' ? 24.0 : 60.0);
$days = req_int2('days', 30);
$machine_id = req_int2('machine_id', 0);
$product_id = req_int2('product_id', 0);
if ($days < 0) $days = 0;
if ($days > 3650) $days = 3650;

$conn = iot_db_connect();
$where = array('1=1');
if ($days > 0) $where[] = 'sl.date >= DATE_SUB(CURDATE(), INTERVAL '.$days.' DAY)';
if ($machine_id > 0) $where[] = 'sl.machine_id = '.$machine_id;
if ($product_id > 0) $where[] = 'sl.product_id = '.$product_id;
$whereSql = implode("\n      AND ", $where);
$opMap = array('gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<');
$op = isset($opMap[$mode]) ? $opMap[$mode] : '>=';
$sensorValueExpr = ($sensor === 'cds') ? 'ss.cds' : 'ss.temp';
$conditionLabel = ($sensor === 'cds' ? '조도' : '온도') . ' ' . $op . ' ' . $threshold;

$baseSql = <<<SQL
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
        DATE_FORMAT(TIMESTAMP(sl.date, sl.time), '%Y-%m-%d %H:00:00') AS sale_hour,
        sl.sold,
        sl.remain,
        sl.price,
        sl.revenue,
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
SQL;

$rows = iot_fetch_all($conn, $baseSql."\nORDER BY sold_at DESC, sale_id DESC");
mysqli_close($conn);

$productAgg = array();
$machineAgg = array();
$hourAgg = array();
$totalSold = 0;
$totalRevenue = 0;
foreach ($rows as $r) {
    $p = $r['product_name'];
    $m = $r['machine_name'].'('.$r['location'].')';
    $h = $r['sale_hour'];
    $sold = iot_int($r['sold']);
    $rev = iot_int($r['revenue']);
    $totalSold += $sold;
    $totalRevenue += $rev;
    if (!isset($productAgg[$p])) $productAgg[$p] = 0;
    if (!isset($machineAgg[$m])) $machineAgg[$m] = 0;
    if (!isset($hourAgg[$h])) $hourAgg[$h] = 0;
    $productAgg[$p] += $sold;
    $machineAgg[$m] += $sold;
    $hourAgg[$h] += $sold;
}
ksort($hourAgg);
$productData = array(array('제품', '판매수량', array('role'=>'annotation')));
foreach ($productAgg as $k=>$v) $productData[] = array($k, $v, (string)$v);
$machineData = array(array('자판기', '판매수량', array('role'=>'annotation')));
foreach ($machineAgg as $k=>$v) $machineData[] = array($k, $v, (string)$v);
$hourData = array(array('시간대', '판매수량'));
foreach ($hourAgg as $k=>$v) $hourData[] = array($k, $v);
?>
<?php iot_page_begin('센서 조건별 판매 그래프', 30); ?>
<?php iot_chart_loader(); ?>
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
  <a href="iot_salesConditionTable.php?sensor=<?=iot_h($sensor)?>&mode=<?=iot_h($mode)?>&threshold=<?=iot_h($threshold)?>&days=<?=iot_h($days)?>&machine_id=<?=iot_h($machine_id)?>&product_id=<?=iot_h($product_id)?>">표로 보기</a>
</form>

<div class="card-wrap">
  <div class="card"><div class="label">조건</div><div class="value" style="font-size:18px"><?=iot_h($conditionLabel)?></div></div>
  <div class="card"><div class="label">판매 건수</div><div class="value"><?=count($rows)?></div></div>
  <div class="card"><div class="label">판매 수량</div><div class="value"><?=$totalSold?></div></div>
  <div class="card"><div class="label">매출 합계</div><div class="value"><?=number_format($totalRevenue)?></div></div>
</div>

<div id="product_chart" class="chart"></div>
<div id="machine_chart" class="chart"></div>
<div id="hour_chart" class="chart"></div>

<h2>사용 SQL</h2>
<pre><?=iot_h($baseSql)?></pre>

<script>
google.charts.load('current', {packages:['corechart']});
google.charts.setOnLoadCallback(drawCharts);
window.addEventListener('resize', drawCharts);
function drawCharts() {
  const productData = google.visualization.arrayToDataTable(<?=iot_json($productData)?>);
  const machineData = google.visualization.arrayToDataTable(<?=iot_json($machineData)?>);
  const hourData = google.visualization.arrayToDataTable(<?=iot_json($hourData)?>);

  new google.visualization.ColumnChart(document.getElementById('product_chart')).draw(productData, {
    title:'조건별 제품 판매수량', legend:{position:'none'}, vAxis:{title:'판매수량'}, annotations:{alwaysOutside:true}
  });
  new google.visualization.ColumnChart(document.getElementById('machine_chart')).draw(machineData, {
    title:'조건별 자판기 판매수량', legend:{position:'none'}, vAxis:{title:'판매수량'}, annotations:{alwaysOutside:true}
  });
  new google.visualization.LineChart(document.getElementById('hour_chart')).draw(hourData, {
    title:'조건별 시간대 판매 흐름', legend:{position:'none'}, vAxis:{title:'판매수량'}, hAxis:{slantedText:true, slantedTextAngle:30}
  });
}
</script>
<?php iot_page_end(); ?>
