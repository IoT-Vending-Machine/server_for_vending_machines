USE iotdb;

/* 사용자 요청 쿼리: 재고 + 누적 판매 + 마지막 판매 시각 */
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
    m.id, p.id;

/* VIEW로 저장해서 브라우저/PHP/CLI에서 공통 사용 */
CREATE OR REPLACE VIEW v_iot_stock_sales_summary AS
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
    m.id, p.id;

CREATE OR REPLACE VIEW v_iot_sales_detail AS
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
JOIN products p ON p.id = sl.product_id;

CREATE OR REPLACE VIEW v_iot_refill_detail AS
SELECT
    r.id,
    r.machine_id,
    m.name AS machine_name,
    m.location,
    r.reason,
    r.before_p1,
    r.before_p2,
    r.before_p3,
    r.filled_total,
    r.temp,
    r.humi,
    r.refill_at
FROM refill_log r
LEFT JOIN machines m ON m.id = r.machine_id;

CREATE OR REPLACE VIEW v_iot_sensor_detail AS
SELECT
    sl.id,
    sl.machine_id,
    m.name AS machine_name,
    m.location,
    TIMESTAMP(sl.date, sl.time) AS sensed_at,
    sl.temp,
    sl.humi,
    sl.cds
FROM sensor_log sl
LEFT JOIN machines m ON m.id = sl.machine_id;
