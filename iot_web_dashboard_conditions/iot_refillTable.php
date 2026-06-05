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
ORDER BY r.refill_at DESC, r.id DESC
SQL;

$rows = iot_fetch_all($conn, $refillSql);
mysqli_close($conn);

iot_page_begin('리필 이력 표', 30);
iot_print_table($rows, array(
    'id' => 'ID',
    'machine_id' => 'Machine ID',
    'machine_name' => '자판기명',
    'location' => '지역',
    'reason' => '사유',
    'before_p1' => 'P1 이전재고',
    'before_p2' => 'P2 이전재고',
    'before_p3' => 'P3 이전재고',
    'filled_total' => '채운 수량',
    'temp' => '온도',
    'humi' => '습도',
    'refill_at' => '리필 시각'
));
echo '<h2>사용 SQL</h2><pre>'.iot_h($refillSql).'</pre>';
iot_page_end();
?>
