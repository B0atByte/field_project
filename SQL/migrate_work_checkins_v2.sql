-- Migration: work_checkins v1 → v2
-- เปลี่ยนจาก 1 event = 1 row เป็น 1 session = 1 row (checkin_at + checkout_at)
-- รัน: docker exec -i field_db mysql -uroot -proot field_project < SQL/migrate_work_checkins_v2.sql

-- 1. สำรองข้อมูลเดิม
CREATE TABLE IF NOT EXISTS work_checkins_backup_v1 AS SELECT * FROM work_checkins;

-- 2. สร้างตารางใหม่
CREATE TABLE IF NOT EXISTS work_checkins_v2 (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  checkin_at       DATETIME NOT NULL,
  checkout_at      DATETIME DEFAULT NULL,
  checkin_lat      DECIMAL(10,8) DEFAULT NULL,
  checkin_lng      DECIMAL(11,8) DEFAULT NULL,
  checkin_address  TEXT DEFAULT NULL,
  checkout_lat     DECIMAL(10,8) DEFAULT NULL,
  checkout_lng     DECIMAL(11,8) DEFAULT NULL,
  checkout_address TEXT DEFAULT NULL,
  note             TEXT DEFAULT NULL,
  KEY idx_user_id    (user_id),
  KEY idx_checkin_at (checkin_at),
  CONSTRAINT fk_wc_user_v2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Migrate ข้อมูลเดิม: จับคู่ checkin กับ checkout ลำดับที่ตรงกันในวันเดียวกัน
INSERT INTO work_checkins_v2
  (user_id, checkin_at, checkout_at,
   checkin_lat, checkin_lng, checkin_address,
   checkout_lat, checkout_lng, checkout_address, note)
SELECT
  ci.user_id,
  ci.checked_at,
  co.checked_at,
  ci.latitude,
  ci.longitude,
  ci.address,
  co.latitude,
  co.longitude,
  co.address,
  ci.note
FROM (
  SELECT *, ROW_NUMBER() OVER (PARTITION BY user_id, DATE(checked_at) ORDER BY checked_at) AS rn
  FROM work_checkins WHERE type = 'checkin'
) ci
LEFT JOIN (
  SELECT *, ROW_NUMBER() OVER (PARTITION BY user_id, DATE(checked_at) ORDER BY checked_at) AS rn
  FROM work_checkins WHERE type = 'checkout'
) co ON ci.user_id = co.user_id
     AND DATE(ci.checked_at) = DATE(co.checked_at)
     AND ci.rn = co.rn;

-- 4. สลับตาราง
DROP TABLE work_checkins;
RENAME TABLE work_checkins_v2 TO work_checkins;
