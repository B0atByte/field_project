# Field Project Management System

ระบบบริหารจัดการงานภาคสนาม (Field Job Management System) สำหรับติดตามและจัดการงานภาคสนามแบบ Real-time พร้อมระบบรายงาน GPS และการ Export เอกสาร

---

## Features

### Role-Based Dashboards
- **Admin** — ภาพรวมระบบ สถิติ 14 วัน อัตราการทำงานสำเร็จ
- **Manager** — ติดตามงานทีม และประสิทธิภาพรายบุคคล
- **Field Worker** — รับงาน บันทึกผล GPS และอัปโหลดรูปภาพ

### Job Management
- สร้าง แก้ไข มอบหมาย และติดตามงาน
- นำเข้าข้อมูลงานจาก Excel จำนวนมาก (Bulk Import)
- ระดับความสำคัญ: ปกติ / สูง / เร่งด่วน
- Workflow การคืนงานกลับให้ Field Worker แก้ไข

### Reporting & Export
- Export เป็น PDF, Word (เดี่ยว / Bulk), Excel
- แปลงไฟล์ Excel เป็นเอกสาร Word
- Preview ก่อน Export

### Security
- IP Whitelist สำหรับควบคุมการเข้าถึง
- Rate Limiting ป้องกัน Brute Force
- CSRF Token validation
- Session Hijacking protection
- Audit Log ทุกการลบงาน และ Login

### Other
- แผนที่ GPS แสดงตำแหน่งปิดงาน
- ระบบ Comment บนงาน
- Image Optimizer อัตโนมัติ
- PWA (Service Worker) รองรับ Offline บางส่วน

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 (Apache) |
| Database | MySQL 8.0 |
| Frontend | Tailwind CSS, SweetAlert2 |
| PDF | mPDF 8.2 |
| Word/Excel | PhpSpreadsheet 2.x, PhpWord 1.x |
| Proxy | Caddy (HTTPS) |
| Container | Docker & Docker Compose |

---

## Project Structure

```
field_project/
├── index.php                  # Login page
├── config/
│   ├── db.php                 # Database connection
│   └── env.php                # .env loader
├── includes/                  # Shared utilities (CSRF, session, rate limiter)
├── auth/                      # Login / Logout handlers
├── dashboard/                 # Role dashboards & job workflow
│   └── api/                   # Realtime API endpoints
├── admin/                     # Job management, users, export, logs
│   └── api/                   # Admin API endpoints
├── components/                # Reusable UI (header, sidebar, footer)
├── cron/                      # Scheduled cleanup jobs
├── SQL/                       # Database schema
├── assets/                    # Icons, images
├── Dockerfile
├── docker-compose.yml
└── Caddyfile
```

---

## Getting Started

### Requirements
- Docker & Docker Compose

### 1. Clone Repository

```bash
git clone https://github.com/B0atByte/field_project.git
cd field_project
```

### 2. Setup Environment

สร้างไฟล์ `.env` ที่ root ของ project:

```env
DB_HOST=db
DB_USER=user
DB_PASS=your_password
DB_NAME=field_project
DB_ROOT_PASS=your_root_password
APP_DEBUG=false
SESSION_LIFETIME=2592000
```

### 3. Setup SSL Certificate (สำหรับ HTTPS)

วาง SSL certificate ที่ `config/`:
```
config/ip.crt
config/ip.key
```

### 4. Start Containers

```bash
docker compose up -d
```

| Service | URL |
|---|---|
| Web App (HTTPS) | https://localhost:7080 |
| PHPMyAdmin | http://localhost:8081 |

### 5. Import Database Schema

เข้า PHPMyAdmin แล้ว import ไฟล์ `SQL/field_project.sql`

หรือผ่าน CLI:
```bash
docker exec -i field_project-db-1 mysql -u root -p field_project < SQL/field_project.sql
```

### 6. Install PHP Dependencies

```bash
docker exec field_project-php-1 composer install
```

---

## Default Roles

| Role | สิทธิ์ |
|---|---|
| `admin` | จัดการทุกอย่าง: งาน, ผู้ใช้, ตั้งค่า, log |
| `manager` | ดูงานและทีม, Export รายงาน |
| `field` | รับงาน, บันทึกผล, อัปโหลดรูป |

---

## Production Deployment (Linux)

```bash
chmod +x deploy_linux.sh
./deploy_linux.sh
```

---

## Notes

- **.env** และ SSL Certificate ไม่ได้อยู่ใน repository — ต้องสร้างเองก่อน deploy
- `vendor/` ไม่ได้อยู่ใน repo — ต้องรัน `composer install` หลัง clone
- Database dump ไม่ได้อยู่ใน repo — ขอรับจากผู้ดูแลระบบ
