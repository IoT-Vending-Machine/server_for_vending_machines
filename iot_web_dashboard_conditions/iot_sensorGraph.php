<?php
require_once __DIR__.'/iot_common.php';
$conn = iot_db_connect();
$machine_id = isset($_GET['machine_id']) ? intval($_GET['machine_id']) : 0;
$where = $machine_id > 0 ? 'WHERE sl.machine_id = '.$machine_id : '';

$sensorSql = <<<SQL
SELECT * FROM (
    SELECT
        sl.id,
        sl.machine_id,
        COALESCE(m.name, CONCAT('Machine ', sl.machine_id)) AS machine_name,
        COALESCE(m.location, '') AS location,
        TIMESTAMP(sl.date, sl.time) AS sensed_at,
        sl.temp,
        sl.humi,
        sl.cds
    FROM sensor_log sl
    LEFT JOIN machines m ON m.id = sl.machine_id
    $where
    ORDER BY sl.id DESC
    LIMIT 200
) x
ORDER BY id ASC
SQL;

$rows = iot_fetch_all($conn, $sensorSql);

/* 예전 sensor 테이블만 쓰던 환경을 위한 fallback */
if (!count($rows) && $machine_id === 0) {
    $fallbackSql = <<<SQL
SELECT * FROM (
    SELECT
        id,
        0 AS machine_id,
        name AS machine_name,
        '' AS location,
        CONCAT(date, ' ', time) AS sensed_at,
        temp,
        humi,
        illu AS cds
    FROM sensor
    ORDER BY id DESC
    LIMIT 200
) x
ORDER BY id ASC
SQL;
    $rows = iot_fetch_all($conn, $fallbackSql);
}
mysqli_close($conn);

$data = array(array('측정 시각', '온도', '습도', 'CDS'));
foreach ($rows as $r) {
    $label = $r['sensed_at'];
    if ($machine_id === 0 && $r['machine_name'] !== '') $label .= "\n".$r['machine_name'];
    $data[] = array($label, iot_float($r['temp']), iot_float($r['humi']), iot_int($r['cds']));
}
?>
<?php iot_page_begin('센서 그래프', 30); ?>
<?php iot_chart_loader(); ?>
<form method="get" style="margin:10px 0 16px">
  <label>machine_id: <input type="number" name="machine_id" value="<?=iot_h($machine_id)?>" min="0" style="width:80px"></label>
  <button type="submit">조회</button>
  <span class="sub">0은 전체 조회</span>
</form>
<div id="sensor_chart" class="chart"></div>
<script>
google.charts.load('current', {packages:['corechart']});
google.charts.setOnLoadCallback(drawCharts);
window.addEventListener('resize', drawCharts);
function drawCharts() {
  const data = google.visualization.arrayToDataTable(<?=iot_json($data)?>);
  new google.visualization.LineChart(document.getElementById('sensor_chart')).draw(data, {
    title:'온도 / 습도 / 조도(CDS) 변화',
    curveType:'function',
    hAxis:{slantedText:true, slantedTextAngle:30},
    vAxis:{title:'측정값'},
    legend:{position:'bottom'}
  });
}
</script>
<?php iot_page_end(); ?>
