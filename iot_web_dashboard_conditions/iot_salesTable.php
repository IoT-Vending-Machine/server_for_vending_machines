<?php
require_once __DIR__.'/iot_common.php';
$conn = iot_db_connect();

/* 이전에 제시한 판매 시점 상세 조회 쿼리 */
$salesSql = <<<SQL
SELECT
    sl.id AS sale_id,
    sl.machine_id,
    m.name AS machine_name,
    m.location,
    sl.product_id,
    p.name AS product_name,
    TIMESTAMP(sl.date, sl.time) AS sold_at,
    sl.sold,
    sl.remain,
    sl.price,
    sl.revenue
FROM sales_log sl
JOIN machines m ON m.id = sl.machine_id
JOIN products p ON p.id = sl.product_id
ORDER BY sold_at DESC, sl.id DESC
SQL;

$rows = iot_fetch_all($conn, $salesSql);
mysqli_close($conn);

iot_page_begin('판매 시점 상세 정보', 30);
iot_print_table($rows, array(
    'sale_id' => '판매 ID',
    'machine_id' => 'Machine ID',
    'machine_name' => '자판기명',
    'location' => '지역',
    'product_id' => '제품 ID',
    'product_name' => '제품명',
    'sold_at' => '판매 시각',
    'sold' => '판매 수량',
    'remain' => '판매 후 재고',
    'price' => '가격',
    'revenue' => '매출'
));
echo '<h2>사용 SQL</h2><pre>'.iot_h($salesSql).'</pre>';
iot_page_end();
?>
