<?php
require_once __DIR__.'/iot_common.php';

function rqi($key, $default) {
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? intval($_GET[$key]) : $default;
}
function rqm($key) {
    $v = isset($_GET[$key]) ? trim($_GET[$key]) : '';
    return preg_match('/^\d{4}-\d{2}$/', $v) ? $v : '';
}

$month = rqm('month');
$hour_start = rqi('hour_start', 0);
$hour_end = rqi('hour_end', 23);
$machine_id = rqi('machine_id', 0);
$product_id = rqi('product_id', 0);
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
    CONCAT(DATE_FORMAT(sl.date, '%Y-%m'), ' ', LPAD(HOUR(sl.time), 2, '0'), '시') AS month_hour,
    SUM(sl.sold) AS total_sold,
    COUNT(*) AS sale_count,
    SUM(sl.revenue) AS total_revenue
FROM sales_log sl
WHERE {$whereSql}
GROUP BY DATE_FORMAT(sl.date, '%Y-%m'), HOUR(sl.time)
ORDER BY sale_month ASC, sale_hour ASC
SQL;
$rows = iot_fetch_all($conn, $sql);

$productSql = <<<SQL
SELECT
    p.name AS product_name,
    SUM(sl.sold) AS total_sold,
    SUM(sl.revenue) AS total_revenue
FROM sales_log sl
JOIN products p ON p.id = sl.product_id
WHERE {$whereSql}
GROUP BY p.id, p.name
ORDER BY p.id
SQL;
$productRows = iot_fetch_all($conn, $productSql);

$machineSql = <<<SQL
SELECT
    m.name AS machine_name,
    m.location,
    SUM(sl.sold) AS total_sold,
    SUM(sl.revenue) AS total_revenue
FROM sales_log sl
JOIN machines m ON m.id = sl.machine_id
WHERE {$whereSql}
GROUP BY m.id, m.name, m.location
ORDER BY m.id
SQL;
$machineRows = iot_fetch_all($conn, $machineSql);
mysqli_close($conn);

$totalSold = 0;
$totalRevenue = 0;
$hourData = array(array('월/시', '판매수량', '매출'));
foreach ($rows as $r) {
    $sold = iot_int($r['total_sold']);
    $rev = iot_int($r['total_revenue']);
    $totalSold += $sold;
    $totalRevenue += $rev;
    $hourData[] = array($r['month_hour'], $sold, $rev);
}
$productData = array(array('제품', '판매수량', array('role'=>'annotation')));
foreach ($productRows as $r) $productData[] = array($r['product_name'], iot_int($r['total_sold']), (string)iot_int($r['total_sold']));
$machineData = array(array('자판기', '판매수량', array('role'=>'annotation')));
foreach ($machineRows as $r) $machineData[] = array($r['machine_name'].'('.$r['location'].')', iot_int($r['total_sold']), (string)iot_int($r['total_sold']));
?>
<?php iot_page_begin('월별/1시간대 판매 그래프', 30); ?>
<?php iot_chart_loader(); ?>
<form method="get" style="background:#fff;border:1px solid #d8dee9;border-radius:12px;padding:12px;margin-bottom:14px;">
  <label>월 <input type="month" name="month" value="<?=iot_h($month)?>"> 비우면 전체 월</label>
  <label>시작시 <input type="number" name="hour_start" value="<?=iot_h($hour_start)?>" min="0" max="23" style="width:60px"></label>
  <label>종료시 <input type="number" name="hour_end" value="<?=iot_h($hour_end)?>" min="0" max="23" style="width:60px"></label>
  <label>machine_id <input type="number" name="machine_id" value="<?=iot_h($machine_id)?>" style="width:70px">, 0=전체</label>
  <label>product_id <input type="number" name="product_id" value="<?=iot_h($product_id)?>" style="width:70px">, 0=전체</label>
  <button type="submit">조회</button>
  <a href="iot_hourlyMonthlyTable.php?month=<?=iot_h($month)?>&hour_start=<?=iot_h($hour_start)?>&hour_end=<?=iot_h($hour_end)?>&machine_id=<?=iot_h($machine_id)?>&product_id=<?=iot_h($product_id)?>">표로 보기</a>
</form>

<div class="card-wrap">
  <div class="card"><div class="label">1시간대 행 수</div><div class="value"><?=count($rows)?></div></div>
  <div class="card"><div class="label">판매 수량 합계</div><div class="value"><?=$totalSold?></div></div>
  <div class="card"><div class="label">매출 합계</div><div class="value"><?=number_format($totalRevenue)?></div></div>
  <div class="card"><div class="label">시간대</div><div class="value" style="font-size:18px"><?=iot_h($hour_start)?>시~<?=iot_h($hour_end)?>시</div></div>
</div>

<div id="hour_chart" class="chart"></div>
<div id="product_chart" class="chart"></div>
<div id="machine_chart" class="chart"></div>

<h2>사용 SQL</h2>
<pre><?=iot_h($sql)?></pre>

<script>
google.charts.load('current', {packages:['corechart']});
google.charts.setOnLoadCallback(drawCharts);
window.addEventListener('resize', drawCharts);
function drawCharts() {
  const hourData = google.visualization.arrayToDataTable(<?=iot_json($hourData)?>);
  const productData = google.visualization.arrayToDataTable(<?=iot_json($productData)?>);
  const machineData = google.visualization.arrayToDataTable(<?=iot_json($machineData)?>);
  new google.visualization.ComboChart(document.getElementById('hour_chart')).draw(hourData, {
    title:'월별 1시간대 판매수량/매출',
    seriesType:'bars',
    series:{1:{type:'line', targetAxisIndex:1}},
    vAxes:{0:{title:'판매수량'}, 1:{title:'매출'}},
    hAxis:{slantedText:true, slantedTextAngle:45}
  });
  new google.visualization.ColumnChart(document.getElementById('product_chart')).draw(productData, {
    title:'선택 시간대 제품별 판매수량', legend:{position:'none'}, vAxis:{title:'판매수량'}, annotations:{alwaysOutside:true}
  });
  new google.visualization.ColumnChart(document.getElementById('machine_chart')).draw(machineData, {
    title:'선택 시간대 자판기별 판매수량', legend:{position:'none'}, vAxis:{title:'판매수량'}, annotations:{alwaysOutside:true}
  });
}
</script>
<?php iot_page_end(); ?>
