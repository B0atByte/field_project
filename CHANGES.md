# รายการไฟล์ที่แก้ไข - สำหรับ Deploy

## ไฟล์ที่แก้ไขแล้ว (ต้อง upload ไป server)

### 1. Database & Configuration
- `SQL/update_schema.sql` - เพิ่ม tables และ columns ใหม่
- `SQL/fix_result_column.sql` - แก้ไข column size
- `.htaccess` - แก้ไข hotlink protection

### 2. PHP Files - Bug Fixes
- `dashboard/view_job.php` - แก้ htmlspecialchars deprecation (line 516)
- `dashboard/job_result.php` - แก้ htmlspecialchars deprecation (line 298)
- `admin/edit_job.php` - แก้ htmlspecialchars deprecation (line 426)
- `admin/export_jobs.php` - แก้ buffer handling
- `includes/image_optimizer.php` - เพิ่ม error logging และ chmod 777

### 3. API Files (ถ้ายังต้องการ Export Word)
- `admin/api/export_word_zip.php` - กลับไปใช้ original code
- `admin/api/process_excel_for_word.php` - กลับไปใช้ original code

### 4. Deployment Scripts (ใหม่)
- `deploy_linux.sh` - Script สำหรับ deploy บน Linux
- `fix_issues.sh` - Script แก้ไขปัญหาทั่วไป
- `DEPLOY_LINUX.md` - คู่มือการ deploy

## ไฟล์ที่ไม่ต้อง upload

### ไฟล์ Windows-specific
- `fix_issues.bat` - สำหรับ Windows เท่านั้น
- `FIX_UPLOAD_MANUAL.txt` - คู่มือ manual

### ไฟล์ทดสอบ
- `admin/api/test_access.php` - ไฟล์ทดสอบ (ลบได้)
- `admin/run_migration.php` - ไฟล์ทดสอบ (ลบได้)

### ไฟล์ที่ไม่ควร upload
- `uploads/*` - ไฟล์ที่ upload จาก user (ถ้ามี)
- `vendor/*` - จะถูกสร้างใหม่จาก composer
- `.env` - ต้องสร้างใหม่บน server

## Quick Deploy Checklist

1. [ ] Upload ไฟล์ทั้งหมดไปยัง server
2. [ ] ลบไฟล์ทดสอบ (`admin/api/test_access.php`, `admin/run_migration.php`)
3. [ ] สร้างไฟล์ `.env` บน server
4. [ ] รัน `chmod +x deploy_linux.sh`
5. [ ] รัน `./deploy_linux.sh`
6. [ ] ทดสอบการทำงาน
7. [ ] เปลี่ยน admin password
8. [ ] Setup backup schedule

## คำสั่งย่อสำหรับ Deploy

```bash
# 1. Upload โค้ด (เลือกวิธีใดวิธีหนึ่ง)
scp -r field_project user@server:/path/to/

# 2. SSH เข้า server
ssh user@server

# 3. เข้าโฟลเดอร์
cd /path/to/field_project

# 4. Deploy
chmod +x deploy_linux.sh
./deploy_linux.sh

# 5. ตรวจสอบ
docker ps
curl http://localhost:8080
```

## หมายเหตุสำคัญ

- ⚠️ **Database**: ต้องรัน `SQL/update_schema.sql` ก่อนใช้งาน
- ⚠️ **Permissions**: โฟลเดอร์ `uploads/` ต้องมีสิทธิ์ 777
- ⚠️ **Environment**: ตรวจสอบ `.env` ให้ตรงกับ server
- ⚠️ **Security**: เปลี่ยน password ทั้งหมดก่อน production
