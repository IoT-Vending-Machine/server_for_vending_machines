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
$rows = iot_fetch_all($conn, $stockSql);
mysqli_close($conn);

iot_page_begin('자판기별 현재 재고/누적 판매 표', 30);
iot_print_table($rows, array(
    'machine_id' => 'Machine ID',
    'machine_name' => '자판기명',
    'location' => '지역',
    'product_name' => '제품명',
    'current_stock' => '현재 재고',
    'total_sold' => '누적 판매',
    'last_sold_time' => '마지막 판매 시각'
));
echo '<h2>사용 SQL</h2><pre>'.iot_h($stockSql).'</pre>';
iot_page_end();
?>
