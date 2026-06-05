<?php
require_once __DIR__.'/iot_common.php';
$conn = iot_db_connect();

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

$stockRows = iot_fetch_all($conn, $stockSql);
$today = iot_fetch_one($conn, "SELECT COALESCE(SUM(sold),0) AS sold, COALESCE(SUM(revenue),0) AS revenue FROM sales_log WHERE date = CURDATE()");
$total = iot_fetch_one($conn, "SELECT COALESCE(SUM(sold),0) AS sold, COALESCE(SUM(revenue),0) AS revenue FROM sales_log");
$latestSale = iot_fetch_one($conn, "SELECT MAX(TIMESTAMP(date, time)) AS sold_at FROM sales_log");
$latestRefill = iot_fetch_one($conn, "SELECT MAX(refill_at) AS refill_at FROM refill_log");
$latestSensor = iot_fetch_one($conn, "SELECT TIMESTAMP(date, time) AS sensed_at, temp, humi, cds FROM sensor_log ORDER BY id DESC LIMIT 1");
mysqli_close($conn);

$totalStock = 0;
$lowCount = 0;
foreach ($stockRows as $r) {
    $s = iot_int($r['current_stock']);
    $totalStock += $s;
    if ($s <= 3) $lowCount++;
}

iot_page_begin('IoT 자판기 통합 대시보드', 30);
?>
<div class="card-wrap">
  <div class="card"><div class="label">현재 총 재고</div><div class="value"><?=iot_h($totalStock)?></div></div>
  <div class="card"><div class="label">부족 제품 수(3개 이하)</div><div class="value"><?=iot_h($lowCount)?></div></div>
  <div class="card"><div class="label">오늘 판매</div><div class="value"><?=iot_h($today['sold'] ?? 0)?></div></div>
  <div class="card"><div class="label">오늘 매출</div><div class="value"><?=number_format(iot_int($today['revenue'] ?? 0))?></div></div>
  <div class="card"><div class="label">누적 판매</div><div class="value"><?=iot_h($total['sold'] ?? 0)?></div></div>
  <div class="card"><div class="label">누적 매출</div><div class="value"><?=number_format(iot_int($total['revenue'] ?? 0))?></div></div>
</div>
<div class="card-wrap">
  <div class="card"><div class="label">마지막 판매</div><div class="value" style="font-size:16px"><?=iot_h($latestSale['sold_at'] ?? '')?></div></div>
  <div class="card"><div class="label">마지막 리필</div><div class="value" style="font-size:16px"><?=iot_h($latestRefill['refill_at'] ?? '')?></div></div>
  <div class="card"><div class="label">마지막 센서</div><div class="value" style="font-size:16px"><?=iot_h($latestSensor['sensed_at'] ?? '')?></div></div>
  <div class="card"><div class="label">온습도/CDS</div><div class="value" style="font-size:16px"><?=iot_h(($latestSensor['temp'] ?? '').' / '.($latestSensor['humi'] ?? '').' / '.($latestSensor['cds'] ?? ''))?></div></div>
</div>
<h2>현재 재고 요약</h2>
<?php
iot_print_table($stockRows, array(
    'machine_id' => 'Machine ID',
    'machine_name' => '자판기명',
    'location' => '지역',
    'product_name' => '제품명',
    'current_stock' => '현재 재고',
    'total_sold' => '누적 판매',
    'last_sold_time' => '마지막 판매 시각'
));
iot_page_end();
?>
