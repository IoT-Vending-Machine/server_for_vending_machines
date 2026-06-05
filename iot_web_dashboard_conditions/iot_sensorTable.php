<?php
require_once __DIR__.'/iot_common.php';
$conn = iot_db_connect();
$machine_id = isset($_GET['machine_id']) ? intval($_GET['machine_id']) : 0;
$where = $machine_id > 0 ? 'WHERE sl.machine_id = '.$machine_id : '';

$sensorSql = <<<SQL
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
SQL;

$rows = iot_fetch_all($conn, $sensorSql);

/* 예전 sensor 테이블만 데이터가 있는 경우 fallback */
if (!count($rows) && $machine_id === 0) {
    $sensorSql = <<<SQL
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
SQL;
    $rows = iot_fetch_all($conn, $sensorSql);
}
mysqli_close($conn);

iot_page_begin('센서 로그 표', 30);
echo '<form method="get" style="margin:10px 0 16px"><label>machine_id: <input type="number" name="machine_id" value="'.iot_h($machine_id).'" min="0" style="width:80px"></label> <button type="submit">조회</button> <span class="sub">0은 전체 조회</span></form>';
iot_print_table($rows, array(
    'id' => 'ID',
    'machine_id' => 'Machine ID',
    'machine_name' => '자판기명',
    'location' => '지역',
    'sensed_at' => '측정 시각',
    'temp' => '온도',
    'humi' => '습도',
    'cds' => 'CDS'
));
echo '<h2>사용 SQL</h2><pre>'.iot_h($sensorSql).'</pre>';
iot_page_end();
?>
