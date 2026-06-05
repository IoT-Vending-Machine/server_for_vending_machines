<?php
require_once __DIR__.'/iot_common.php';
$conn = iot_db_connect();

/* 사용자 요청 쿼리: 자판기별 현재 재고 + 누적 판매 + 마지막 판매 시각 */
$stockSql = <<<'SQL'
SELECT
    m.id AS machine_id,
    m.name AS machine_name,
    m.location,
    p.name AS product_name,
    mp.stock AS current_stock,
    COALESCE(SUM(s.sold), 0) AS total_sold,
    MAX(CONCAT(s.date, ' ', s.time)) AS last_sold_time
FROM
    machines m
JOIN
    machine_products mp ON m.id = mp.machine_id
JOIN
    products p ON mp.product_id = p.id
LEFT JOIN
    sales_log s ON m.id = s.machine_id AND p.id = s.product_id
GROUP BY
    m.id, m.name, m.location, p.id, p.name, mp.stock
ORDER BY
    m.id, p.id
SQL;
$rows = iot_fetch_all($conn, $stockSql);
mysqli_close($conn);

$stockData = array(array('자판기 / 제품', '현재 재고', array('role' => 'annotation')));
$soldData  = array(array('자판기 / 제품', '누적 판매', array('role' => 'annotation')));
$machineAgg = array();
foreach ($rows as $r) {
    $label = $r['machine_name'].' / '.$r['product_name'];
    $stock = iot_int($r['current_stock']);
    $sold  = iot_int($r['total_sold']);
    $stockData[] = array($label, $stock, (string)$stock);
    $soldData[]  = array($label, $sold, (string)$sold);

    $mid = $r['machine_id'];
    if (!isset($machineAgg[$mid])) {
        $machineAgg[$mid] = array('label' => $r['machine_name'].'('.$r['location'].')', 'stock' => 0, 'sold' => 0);
    }
    $machineAgg[$mid]['stock'] += $stock;
    $machineAgg[$mid]['sold'] += $sold;
}
$machineData = array(array('자판기', '현재 총재고', '누적 판매'));
foreach ($machineAgg as $m) {
    $machineData[] = array($m['label'], $m['stock'], $m['sold']);
}
?>
<?php iot_page_begin('자판기 재고/판매 그래프', 30); ?>
<?php iot_chart_loader(); ?>
<div class="card-wrap">
  <div class="card"><div class="label">제품 행 수</div><div class="value"><?=count($rows)?></div></div>
  <div class="card"><div class="label">자판기 수</div><div class="value"><?=count($machineAgg)?></div></div>
</div>
<div id="stock_chart" class="chart"></div>
<div id="sold_chart" class="chart"></div>
<div id="machine_chart" class="chart"></div>

<h2>사용 SQL</h2>
<pre><?=iot_h($stockSql)?></pre>

<script>
google.charts.load('current', {packages:['corechart']});
google.charts.setOnLoadCallback(drawCharts);
window.addEventListener('resize', drawCharts);
function drawCharts() {
  const stockData = google.visualization.arrayToDataTable(<?=iot_json($stockData)?>);
  const soldData = google.visualization.arrayToDataTable(<?=iot_json($soldData)?>);
  const machineData = google.visualization.arrayToDataTable(<?=iot_json($machineData)?>);

  new google.visualization.ColumnChart(document.getElementById('stock_chart')).draw(stockData, {
    title:'자판기/제품별 현재 재고',
    legend: {position:'none'},
    vAxis: {title:'개수', viewWindow:{min:0, max:10}},
    hAxis: {slantedText:true, slantedTextAngle:30},
    annotations: {alwaysOutside:true}
  });

  new google.visualization.ColumnChart(document.getElementById('sold_chart')).draw(soldData, {
    title:'자판기/제품별 누적 판매량',
    legend: {position:'none'},
    vAxis: {title:'판매 수량'},
    hAxis: {slantedText:true, slantedTextAngle:30},
    annotations: {alwaysOutside:true}
  });

  new google.visualization.ComboChart(document.getElementById('machine_chart')).draw(machineData, {
    title:'자판기별 총재고와 누적 판매 비교',
    seriesType:'bars',
    vAxis: {title:'개수'},
    hAxis: {title:'자판기'}
  });
}
</script>
<?php iot_page_end(); ?>
