USE iotdb;

/* -------------------------------------------------------------------------
   1) 조도 60 이상 판매 조회: 판매 시각 직전의 최신 sensor_log 값을 붙여 판단
   ------------------------------------------------------------------------- */
SELECT *
FROM (
    SELECT
        sl.id AS sale_id,
        sl.machine_id,
        m.name AS machine_name,
        m.location,
        sl.product_id,
        p.name AS product_name,
        TIMESTAMP(sl.date, sl.time) AS sold_at,
        sl.date AS sold_date,
        sl.time AS sold_time,
        sl.sold,
        sl.remain,
        sl.price,
        sl.revenue,
        (
            SELECT TIMESTAMP(sg.date, sg.time)
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS sensor_at,
        (
            SELECT sg.cds
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS cds,
        (
            SELECT sg.temp
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS temp,
        (
            SELECT sg.humi
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS humi
    FROM sales_log sl
    JOIN machines m ON m.id = sl.machine_id
    JOIN products p ON p.id = sl.product_id
) ss
WHERE ss.cds >= 60
ORDER BY sold_at DESC, sale_id DESC;

/* -------------------------------------------------------------------------
   2) 조도 60 이하 판매 조회
   ------------------------------------------------------------------------- */
SELECT *
FROM (
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
        sl.revenue,
        (
            SELECT TIMESTAMP(sg.date, sg.time)
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS sensor_at,
        (
            SELECT sg.cds
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS cds,
        (
            SELECT sg.temp
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS temp,
        (
            SELECT sg.humi
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS humi
    FROM sales_log sl
    JOIN machines m ON m.id = sl.machine_id
    JOIN products p ON p.id = sl.product_id
) ss
WHERE ss.cds <= 60
ORDER BY sold_at DESC, sale_id DESC;

/* -------------------------------------------------------------------------
   3) 온도 24도 이상 판매 조회
   ------------------------------------------------------------------------- */
SELECT *
FROM (
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
        sl.revenue,
        (
            SELECT TIMESTAMP(sg.date, sg.time)
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS sensor_at,
        (
            SELECT sg.cds
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS cds,
        (
            SELECT sg.temp
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS temp,
        (
            SELECT sg.humi
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS humi
    FROM sales_log sl
    JOIN machines m ON m.id = sl.machine_id
    JOIN products p ON p.id = sl.product_id
) ss
WHERE ss.temp >= 24
ORDER BY sold_at DESC, sale_id DESC;

/* -------------------------------------------------------------------------
   4) 온도 24도 이하 판매 조회
   ------------------------------------------------------------------------- */
SELECT *
FROM (
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
        sl.revenue,
        (
            SELECT TIMESTAMP(sg.date, sg.time)
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS sensor_at,
        (
            SELECT sg.cds
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS cds,
        (
            SELECT sg.temp
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS temp,
        (
            SELECT sg.humi
            FROM sensor_log sg
            WHERE sg.machine_id = sl.machine_id
              AND TIMESTAMP(sg.date, sg.time) <= TIMESTAMP(sl.date, sl.time)
            ORDER BY TIMESTAMP(sg.date, sg.time) DESC, sg.id DESC
            LIMIT 1
        ) AS humi
    FROM sales_log sl
    JOIN machines m ON m.id = sl.machine_id
    JOIN products p ON p.id = sl.product_id
) ss
WHERE ss.temp <= 24
ORDER BY sold_at DESC, sale_id DESC;

/* -------------------------------------------------------------------------
   5) 월별 합산 + 1시간대별 판매 수치
      특정 월/시간대를 보려면 WHERE 조건을 수정합니다.
      예: 2026-06월 08시~20시만 조회
          WHERE DATE_FORMAT(sl.date, '%Y-%m') = '2026-06'
            AND HOUR(sl.time) BETWEEN 8 AND 20
   ------------------------------------------------------------------------- */
SELECT
    DATE_FORMAT(sl.date, '%Y-%m') AS sale_month,
    HOUR(sl.time) AS sale_hour,
    CONCAT(LPAD(HOUR(sl.time), 2, '0'), ':00~', LPAD(HOUR(sl.time), 2, '0'), ':59') AS hour_band,
    sl.machine_id,
    m.name AS machine_name,
    m.location,
    sl.product_id,
    p.name AS product_name,
    SUM(sl.sold) AS total_sold,
    COUNT(*) AS sale_count,
    SUM(sl.revenue) AS total_revenue,
    MIN(TIMESTAMP(sl.date, sl.time)) AS first_sold_at,
    MAX(TIMESTAMP(sl.date, sl.time)) AS last_sold_at
FROM sales_log sl
JOIN machines m ON m.id = sl.machine_id
JOIN products p ON p.id = sl.product_id
WHERE HOUR(sl.time) BETWEEN 0 AND 23
GROUP BY
    DATE_FORMAT(sl.date, '%Y-%m'),
    HOUR(sl.time),
    sl.machine_id,
    m.name,
    m.location,
    sl.product_id,
    p.name
ORDER BY sale_month DESC, sale_hour ASC, sl.machine_id ASC, sl.product_id ASC;

/* -------------------------------------------------------------------------
   6) 월별/1시간대 전체 합산만 보기: 제품/자판기 구분 없이 합산
   ------------------------------------------------------------------------- */
SELECT
    DATE_FORMAT(sl.date, '%Y-%m') AS sale_month,
    HOUR(sl.time) AS sale_hour,
    CONCAT(DATE_FORMAT(sl.date, '%Y-%m'), ' ', LPAD(HOUR(sl.time), 2, '0'), '시') AS month_hour,
    SUM(sl.sold) AS total_sold,
    COUNT(*) AS sale_count,
    SUM(sl.revenue) AS total_revenue
FROM sales_log sl
WHERE HOUR(sl.time) BETWEEN 0 AND 23
GROUP BY DATE_FORMAT(sl.date, '%Y-%m'), HOUR(sl.time)
ORDER BY sale_month ASC, sale_hour ASC;
