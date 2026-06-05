<?php
/*
 * iot_common.php
 * 공통 DB 접속/조회/HTML 출력 함수
 * 위치: /var/www/html/iot_common.php
 */
mysqli_report(MYSQLI_REPORT_OFF);

define('IOT_DB_HOST', 'localhost');
define('IOT_DB_USER', 'iot');
define('IOT_DB_PASS', 'pwiot');
define('IOT_DB_NAME', 'iotdb');

define('IOT_MAX_STOCK', 10);

function iot_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function iot_db_connect() {
    $conn = @mysqli_connect(IOT_DB_HOST, IOT_DB_USER, IOT_DB_PASS, IOT_DB_NAME);
    if (!$conn) {
        http_response_code(500);
        die('<pre>DB connect failed: '.iot_h(mysqli_connect_error()).'</pre>');
    }
    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}

function iot_fetch_all($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        http_response_code(500);
        die('<pre>SQL error: '.iot_h(mysqli_error($conn))."\n\n".iot_h($sql).'</pre>');
    }
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
    return $rows;
}

function iot_fetch_one($conn, $sql) {
    $rows = iot_fetch_all($conn, $sql);
    return count($rows) ? $rows[0] : array();
}

function iot_json($value) {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function iot_int($value) {
    return is_numeric($value) ? intval($value) : 0;
}

function iot_float($value) {
    return is_numeric($value) ? floatval($value) : 0.0;
}

function iot_page_begin($title, $refresh_sec = 30) {
    echo "<!DOCTYPE html>\n<html lang=\"ko\">\n<head>\n";
    echo "  <meta charset=\"UTF-8\">\n";
    echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    if ($refresh_sec > 0) {
        echo "  <meta http-equiv=\"refresh\" content=\"".intval($refresh_sec)."\">\n";
    }
    echo "  <title>".iot_h($title)."</title>\n";
    echo <<<'CSS'
  <style>
    body { font-family: Arial, 'Noto Sans KR', sans-serif; margin: 18px; background: #f6f8fb; color: #1f2937; }
    h1 { margin: 0 0 14px; font-size: 24px; }
    h2 { margin: 24px 0 10px; font-size: 18px; }
    .sub { color: #64748b; margin-bottom: 14px; }
    .nav { margin: 0 0 16px; display:flex; flex-wrap:wrap; gap:8px; }
    .nav a { text-decoration:none; color:#0f172a; background:#fff; border:1px solid #d8dee9; padding:8px 12px; border-radius:8px; }
    .nav a:hover { background:#eef6ff; }
    .card-wrap { display:flex; flex-wrap:wrap; gap:12px; margin: 14px 0 20px; }
    .card { background:#fff; border:1px solid #d8dee9; border-radius:12px; padding:14px 16px; min-width:160px; box-shadow:0 1px 2px rgba(15,23,42,0.05); }
    .card .label { color:#64748b; font-size:13px; margin-bottom:6px; }
    .card .value { font-size:24px; font-weight:bold; }
    .chart { width: 100%; min-height: 360px; background:#fff; border:1px solid #d8dee9; border-radius:12px; padding:8px; box-sizing:border-box; margin: 12px 0 22px; }
    table { border-collapse: collapse; width: 100%; background:#fff; border:1px solid #d8dee9; }
    th, td { border:1px solid #d8dee9; padding:8px 10px; text-align:center; font-size:14px; }
    th { background:#e9eef5; }
    tr:nth-child(even) td { background:#fbfcfe; }
    .left { text-align:left; }
    .ok { color:#047857; font-weight:bold; }
    .warn { color:#b45309; font-weight:bold; }
    .bad { color:#b91c1c; font-weight:bold; }
    code, pre { background:#0f172a; color:#e5e7eb; padding:10px; border-radius:8px; overflow:auto; display:block; }
  </style>
CSS;
    echo "\n</head>\n<body>\n";
    echo "<h1>".iot_h($title)."</h1>\n";
    echo "<div class=\"sub\">auto refresh: ".intval($refresh_sec)." sec · DB: ".iot_h(IOT_DB_NAME)."</div>\n";
    iot_nav();
}

function iot_page_end() {
    echo "</body>\n</html>\n";
}

function iot_nav() {
    echo '<div class="nav">';
    echo '<a href="iot_dashboard.php">Dashboard</a>';
    echo '<a href="iot_stockGraph.php">Stock Graph</a>';
    echo '<a href="iot_stockTable.php">Stock Table</a>';
    echo '<a href="iot_salesGraph.php">Sales Graph</a>';
    echo '<a href="iot_salesTable.php">Sales Table</a>';
    echo '<a href="iot_sensorGraph.php">Sensor Graph</a>';
    echo '<a href="iot_sensorTable.php">Sensor Table</a>';
    echo '<a href="iot_refillGraph.php">Refill Graph</a>';
    echo '<a href="iot_refillTable.php">Refill Table</a>';
    echo '<a href="iot_salesConditionGraph.php">Condition Sales</a>';
    echo '<a href="iot_salesConditionTable.php">Condition Table</a>';
    echo '<a href="iot_hourlyMonthlyGraph.php">Hourly Monthly</a>';
    echo '<a href="iot_hourlyMonthlyTable.php">Hourly Table</a>';
    echo '</div>';
}

function iot_print_table($rows, $columns) {
    echo "<table>\n<tr>";
    foreach ($columns as $key => $label) {
        echo '<th>'.iot_h($label).'</th>';
    }
    echo "</tr>\n";
    if (!count($rows)) {
        echo '<tr><td colspan="'.count($columns).'">조회된 데이터가 없습니다.</td></tr>';
    }
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($columns as $key => $label) {
            $class = is_numeric($row[$key] ?? null) ? '' : ' class="left"';
            echo '<td'.$class.'>'.iot_h($row[$key] ?? '').'</td>';
        }
        echo "</tr>\n";
    }
    echo "</table>\n";
}

function iot_chart_loader() {
    echo '<script src="https://www.gstatic.com/charts/loader.js"></script>' . "\n";
}
?>
