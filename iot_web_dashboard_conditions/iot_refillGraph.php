<?php
require_once __DIR__.'/iot_common.php';
$conn = iot_db_connect();

$refillSql = <<<SQL
SELECT
    r.id,
    r.machine_id,
    COALESCE(m.name, CONCAT('Machine ', r.machine_id)) AS machine_name,
    COALESCE(m.location, '') AS location,
    r.reason,
    r.before_p1,
    r.before_p2,
    r.before_p3,
    r.filled_total,
    r.temp,
    r.humi,
    r.refill_at
FROM refill_log r
LEFT JOIN machines m ON m.id = r.machine_id
ORDER BY r.refill_at ASC, r.id ASC
SQL;

$rows = iot_fetch_all($conn, $refillSql);
mysqli_close($conn);

$filledData = array(array('리필 시각 / 자판기', '채운 총 수량', array('role' => 'annotation')));
$beforeData = array(array('리필 시각 / 자판기', 'P1 이전재고', 'P2 이전재고', 'P3 이전재고'));
foreach ($rows as $r) {
    $label = $r['refill_at']."\n".$r['machine_name'];
    $filled = iot_int($r['filled_total']);
    $filledData[] = array($label, $filled, (string)$filled);
    $beforeData[] = array($label, iot_int($r['before_p1']), iot_int($r['before_p2']), iot_int($r['before_p3']));
}
?>
<?php iot_page_begin('리필 그래프', 30); ?>
<?php iot_chart_loader(); ?>
<div id="filled_chart" class="chart"></div>
<div id="before_chart" class="chart"></div>
<script>
google.charts.load('current', {packages:['corechart']});
google.charts.setOnLoadCallback(drawCharts);
window.addEventListener('resize', drawCharts);
function drawCharts() {
  const filledData = google.visualization.arrayToDataTable(<?=iot_json($filledData)?>);
  const beforeData = google.visualization.arrayToDataTable(<?=iot_json($beforeData)?>);

  new google.visualization.ColumnChart(document.getElementById('filled_chart')).draw(filledData, {
    title:'리필 이벤트별 채운 수량',
    legend:{position:'none'},
    hAxis:{slantedText:true, slantedTextAngle:30},
    vAxis:{title:'채운 개수'},
    annotations:{alwaysOutside:true}
  });

  new google.visualization.LineChart(document.getElementById('before_chart')).draw(beforeData, {
    title:'리필 직전 제품별 재고',
    curveType:'function',
    hAxis:{slantedText:true, slantedTextAngle:30},
    vAxis:{title:'리필 전 재고', viewWindow:{min:0, max:10}},
    legend:{position:'bottom'}
  });
}
</script>
<?php iot_page_end(); ?>
