# Field Management System
> ระบบบริหารจัดการงานภาคสนาม v3.2

ระบบจัดการงานสำหรับทีมภาคสนาม รองรับการมอบหมายงาน ติดตามสถานะ บันทึกผล และสร้างรายงาน

---

## Features

| Module | คำอธิบาย |
|--------|-----------|
| **Job Management** | สร้าง/แก้ไข/มอบหมาย/นำเข้างาน (Excel) |
| **Field Dashboard** | รับงาน บันทึกผล GPS ถ่ายรูป |
| **Manager Dashboard** | ติดตามงานของแผนก ตีงานกลับ |
| **Admin Dashboard** | ภาพรวมระบบ สถิติ กราฟ Ranking |
| **Map View** | แผนที่ตำแหน่งงาน (Leaflet) |
| **Export** | Excel / PDF / Word ทั้งรายเดียวและ Bulk |
| **Logs** | Login log, Job edit log, Deletion log |
| **Security** | IP Whitelist, Device Registration, Invite Link, Rate limiting, CSRF, Remember me |

---

## Tech Stack

- **Backend:** PHP 8+ (no framework), MySQL 8
- **Frontend:** Tailwind CSS, Chart.js, SweetAlert2, Leaflet
- **Libraries:** PhpSpreadsheet, PhpWord, mPDF
- **Deploy:** Docker / Linux (Apache/Caddy)

---

## Roles

| Role | สิทธิ์ |
|------|--------|
| `admin` | เต็มรูปแบบ — จัดการ user, งาน, export, ดู log ทุกอย่าง |
| `manager` | ดูงานของแผนก, มอบหมายงาน, ตีงานกลับ, export |
| `field` | รับงาน, บันทึกผล, ดูประวัติของตัวเอง |

---

## Quick Start (Docker)

```bash
# 1. Clone
git clone https://github.com/B0atByte/field_project.git
cd field_project

# 2. Config
cp .env.example .env
# แก้ไข .env ตามสภาพแวดล้อม

# 3. Run
docker compose up -d

# 4. Install dependencies
docker run --rm -v "$(pwd):/app" composer:latest install --no-dev --ignore-platform-reqs

# 5. Import DB
docker exec -i field_db mysql -uroot -proot field_project < SQL/field_db_YYYYMMDD.sql
```

เปิด `https://localhost:7080`

---

## Quick Start (Linux / Apache)

ดู [DEPLOY_LINUX.md](DEPLOY_LINUX.md) สำหรับขั้นตอนละเอียด

---

## Environment Variables

สร้างไฟล์ `.env` จาก `.env.example`:

```env
DB_HOST=db
DB_USER=root
DB_PASS=your_password
DB_NAME=field_project

APP_DEBUG=false
SESSION_LIFETIME=2592000
MAX_LOGIN_ATTEMPTS=5
```

---

## Project Structure

```
field_project/
├── admin/              # หน้า Admin + API endpoints
│   ├── api/            # JSON API (export, comments, map)
│   └── logs_partials/  # Log partial views
├── auth/               # Login / Logout
├── components/         # Shared UI (header, sidebar, footer)
├── config/             # DB config, env loader
├── cron/               # Cron jobs (cleanup)
├── dashboard/          # Role dashboards
│   └── api/            # Dashboard real-time API
├── includes/           # Session, CSRF, rate limiter, IP security
├── SQL/                # Database schema
├── image/              # Static assets (logo)
├── Dockerfile
├── docker-compose.yml
└── .env.example
```

---

## Database

ตารางหลัก: `users`, `jobs`, `job_logs`, `departments`, `login_logs`, `job_deletion_logs`, `allowed_ips`, `allowed_devices`, `device_invites`

---

## Changelog

### v3.3 (2026-03-20)
- เปลี่ยน schema `work_checkins`: จาก 1 event = 1 row เป็น **1 session = 1 row** (checkin_at + checkout_at ในแถวเดียว)
- Check-in สร้าง row ใหม่, Check-out UPDATE row เดิม — ป้องกัน checkin ซ้ำถ้ายังไม่ได้ checkout
- อัปเดต `checkin.php`, `checkin_status.php`, `attendance.php`, `export_attendance.php` ให้ใช้ schema ใหม่
- ลบ Timeline section ที่ซ้ำซ้อนออกจาก attendance.php
- เพิ่ม `SQL/migrate_work_checkins_v2.sql` สำหรับ migrate ข้อมูลเดิม

### v3.2 (2026-03-20)
- แก้ layout `admin/logs.php` และ `admin/admin_delete_jobs.php` — ใช้ `header.php`/`footer.php` ถูกต้อง ไม่มี duplicate DOCTYPE, sidebar ไม่ทับ content
- เพิ่ม SweetAlert2 + AJAX submit ใน `dashboard/view_job.php` — confirm → loading → success/error แทนหน้าขาว
- แก้ `dashboard/save_job.php` ให้ตอบกลับ JSON ทุก case (เพิ่ม `jsonDie()`, JSON-friendly CSRF check)
- แก้ `admin/export_job_detail_word.php` — ลบ `ProofErr` class ที่ไม่มีใน PhpWord version ที่ติดตั้ง, เพิ่ม null guard สำหรับ `os`, `log_time`, `json_decode`, sanitize ชื่อไฟล์
- แก้ `includes/ip_security.php` — เพิ่ม Docker bridge IP range `172.16.0.0/12` ใน localhost whitelist
- แก้ `setup_ip_security.php` — เปลี่ยน font จาก Sarabun เป็น Prompt ให้ตรงกับ design system

### v3.1 (2026-03-17)
- เพิ่มหน้า `setup_ip_security.php` — จัดการ IP Whitelist, Device Registration, Invite Link
- เพิ่ม `docker/php-custom.ini` — ปิด `expose_php`, `display_errors`
- จำกัด port MySQL และ phpMyAdmin เฉพาะ localhost
- อัปเดต SQL backup ล่าสุด

### v3.0 (2026-03-16)
- เพิ่ม `ob_start()` ใน `session_config.php` ป้องกัน "headers already sent"
- เพิ่ม `isset($_SESSION['user'])` guard ครอบคลุมทุกไฟล์ (~38 files)
- เพิ่ม null coalescing guard สำหรับ `fetch_assoc()` chains

---

## License

Internal use — BPL Field Management Team
