# Server-for-Vending-Machines
Server managing Vending Machines(Arduino) and Stock Manager(STM32F411RE)

[자판기 1 (VM_01 - 서울)]          [중앙 서버 (라즈베리파이)]          [물류/재고 창고 (STM32)]
- Arduino Uno + ESP-01     (WiFi)   - C Socket Server          (BT)    - STM32F411
- 서보모터 (상품 투출)  ---------->   - MariaDB (RDBMS)      <---------- - Blue Button (수동 리필)
- DHT11 / CDS (환경)       (WiFi)   - PHP Web Dashboard        (BT)    - Stepper Motor (리필 동작)
                                         |
[자판기 2 (VM_02 - 부산)]                | (데이터 동기화/명령 하달)
- Arduino Uno + ESP-01  -----------------+
- 서보모터 / 온습도 센서


# 기본 대시보드
https://drive.google.com/file/d/1rZnEsVqgKbrguSjN3WotICKnsmeBlmU0/view?usp=drive_link
<img width="1920" height="911" alt="기본대시보드" src="https://github.com/user-attachments/assets/35c6dd51-00af-4908-bd3f-63e5a5f7cd15" />


# 판매량 그래프 
<img width="1678" height="894" alt="판매량 그래프" src="https://github.com/user-attachments/assets/58a4016d-7398-48f0-8ac7-3a413ffdf489" />

# 온도에 따른 쿼리(계절 전략) 
<img width="1683" height="834" alt="온도에따른 판매량" src="https://github.com/user-attachments/assets/ea804e69-4c58-4b1d-994e-7a27832cbb32" />


# 조도에 따른 쿼리(날씨(맑음/흐림)전략)
<img width="1730" height="834" alt="조도에따른 판매량" src="https://github.com/user-attachments/assets/bb188002-ce43-4807-93d0-e32395a18262" />


# 시간대에 따른 쿼리 
<img width="1632" height="375" alt="월별시간대 판매량" src="https://github.com/user-attachments/assets/4a0f194b-f84b-4803-8987-1331812e7e0e" />


# 리필그래프
<img width="1672" height="898" alt="리필그래프" src="https://github.com/user-attachments/assets/82f82971-bddf-44d7-9158-6ffed847f462" />
