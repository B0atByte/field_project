# Field Management System
> ระบบบริหารจัดการงานภาคสนาม v3.0

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
| **Security** | IP Whitelist, Rate limiting, CSRF, Remember me |

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

# 4. Import DB
docker exec -i field_mysql mysql -u root -p field_project < SQL/field_project.sql
```

เปิด `http://localhost` หรือ `http://YOUR_IP`

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

Schema อยู่ที่ `SQL/field_project.sql`

ตารางหลัก: `users`, `jobs`, `job_logs`, `departments`, `login_logs`, `job_deletion_logs`

---

## Bug Fixes (2026-03-16)

- เพิ่ม `ob_start()` ใน `session_config.php` ป้องกัน "headers already sent"
- เพิ่ม `isset($_SESSION['user'])` guard ครอบคลุมทุกไฟล์ (~38 files)
- เพิ่ม null coalescing guard สำหรับ `fetch_assoc()` chains

---

## License

Internal use — BPL Field Management Team
