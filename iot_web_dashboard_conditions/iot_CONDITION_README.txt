IoT Vending 조건별 판매/월별 시간대 대시보드 추가 파일

복사 위치:
  /var/www/html/

추가된 화면:
  iot_salesConditionGraph.php  : 조도/온도 조건별 판매 그래프
  iot_salesConditionTable.php  : 조도/온도 조건별 판매 상세 표
  iot_hourlyMonthlyGraph.php   : 월별 + 1시간대 판매 그래프
  iot_hourlyMonthlyTable.php   : 월별 + 1시간대 판매 표
  iot_condition_queries.sql    : 직접 MariaDB에서 실행할 수 있는 SQL 모음

적용:
  sudo cp iot_* /var/www/html/
  sudo chown www-data:www-data /var/www/html/iot_*

접속:
  http://라즈베리파이IP/iot_index.html

중요:
  조건별 판매 조회는 sales_log 판매 시각에 대해 sensor_log의 같은 machine_id에서
  '판매 시각 직전 최신 센서값'을 찾아 붙입니다.
  sensor_log가 비어 있으면 조건별 판매 결과도 나오지 않습니다.
