<?php
require_once __DIR__.'/iot_common.php';
$conn = iot_db_connect();

$dailySql = <<<SQL
SELECT
    DATE_FORMAT(TIMESTAMP(sl.date, sl.time), '%Y-%m-%d') AS sale_date,
    SUM(sl.sold) AS sold_count,
    SUM(sl.revenue) AS revenue_sum
FROM sales_log sl
GROUP BY DATE_FORMAT(TIMESTAMP(sl.date, sl.time), '%Y-%m-%d')
ORDER BY sale_date
SQL;

$productSql = <<<SQL
SELECT
    p.id AS product_id,
    p.name AS product_name,
    SUM(sl.sold) AS sold_count,
    SUM(sl.revenue) AS revenue_sum
FROM sales_log sl
JOIN products p ON p.id = sl.product_id
GROUP BY p.id, p.name
ORDER BY p.id
SQL;

$machineSql = <<<SQL
SELECT
    m.id AS machine_id,
    CONCAT(m.name, '(', m.location, ')') AS machine_label,
    SUM(sl.sold) AS sold_count,
    SUM(sl.revenue) AS revenue_sum
FROM sales_log sl
JOIN machines m ON m.id = sl.machine_id
GROUP BY m.id, m.name, m.location
ORDER BY m.id
SQL;

$dailyRows = iot_fetch_all($conn, $dailySql);
$productRows = iot_fetch_all($conn, $productSql);
$machineRows = iot_fetch_all($conn, $machineSql);
mysqli_close($conn);

$dailyData = array(array('날짜', '판매 수량', '매출'));
foreach ($dailyRows as $r) $dailyData[] = array($r['sale_date'], iot_int($r['sold_count']), iot_int($r['revenue_sum']));

$productData = array(array('제품', '판매 수량'));
foreach ($productRows as $r) $productData[] = array($r['product_name'], iot_int($r['sold_count']));

$machineData = array(array('자판기', '판매 수량', '매출'));
foreach ($machineRows as $r) $machineData[] = array($r['machine_label'], iot_int($r['sold_count']), iot_int($r['revenue_sum']));
?>
<?php iot_page_begin('판매 그래프', 30); ?>
<?php iot_chart_loader(); ?>
<div id="daily_chart" class="chart"></div>
<div id="product_chart" class="chart"></div>
<div id="machine_chart" class="chart"></div>
<script>
google.charts.load('current', {packages:['corechart']});
google.charts.setOnLoadCallback(drawCharts);
window.addEventListener('resize', drawCharts);
function drawCharts() {
  const dailyData = google.visualization.arrayToDataTable(<?=iot_json($dailyData)?>);
  const productData = google.visualization.arrayToDataTable(<?=iot_json($productData)?>);
  const machineData = google.visualization.arrayToDataTable(<?=iot_json($machineData)?>);

  new google.visualization.ComboChart(document.getElementById('daily_chart')).draw(dailyData, {
    title:'일자별 판매 수량 / 매출',
    seriesType:'bars',
    series:{1:{type:'line', targetAxisIndex:1}},
    vAxes:{0:{title:'판매 수량'}, 1:{title:'매출'}},
    hAxis:{slantedText:true, slantedTextAngle:30}
  });

  new google.visualization.PieChart(document.getElementById('product_chart')).draw(productData, {
    title:'제품별 누적 판매 비중',
    pieHole:0.35
  });

  new google.visualization.ComboChart(document.getElementById('machine_chart')).draw(machineData, {
    title:'자판기별 누적 판매 수량 / 매출',
    seriesType:'bars',
    series:{1:{type:'line', targetAxisIndex:1}},
    vAxes:{0:{title:'판매 수량'}, 1:{title:'매출'}}
  });
}
</script>
<?php iot_page_end(); ?>
