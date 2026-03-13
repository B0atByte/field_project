# รายการไฟล์ที่แก้ไขแล้ว - ต้อง Upload

## ไฟล์ที่แก้ไข (Modified Files)

1. ✅ `.htaccess` - แก้ไข hotlink protection ให้รองรับ IP ranges
2. ✅ `SQL/update_schema.sql` - เพิ่ม tables และ columns ใหม่
3. ✅ `dashboard/view_job.php` - แก้ htmlspecialchars line 516
4. ✅ `dashboard/job_result.php` - แก้ htmlspecialchars line 298 + session validation
5. ✅ `admin/edit_job.php` - แก้ htmlspecialchars line 426
6. ✅ `admin/export_jobs.php` - แก้ buffer handling (ob_start, ob_end_clean)
7. ✅ `includes/image_optimizer.php` - เพิ่ม error logging และ chmod 777
8. ✅ `admin/api/export_word_zip.php` - Revert to original
9. ✅ `admin/api/process_excel_for_word.php` - Revert to original

## ไฟล์ใหม่ (New Files)

10. ✅ `deploy_linux.sh` - Script deploy สำหรับ Linux
11. ✅ `fix_issues.sh` - Script แก้ไขปัญหา
12. ✅ `DEPLOY_LINUX.md` - คู่มือ deploy
13. ✅ `CHANGES.md` - สรุปการเปลี่ยนแปลง
14. ✅ `SQL/fix_result_column.sql` - SQL แก้ไข column

## ไฟล์ที่ไม่ต้อง Upload (ลบทิ้งได้)

- ❌ `fix_issues.bat` - สำหรับ Windows
- ❌ `FIX_UPLOAD_MANUAL.txt` - คู่มือ manual
- ❌ `admin/api/test_access.php` - ไฟล์ทดสอบ
- ❌ `admin/run_migration.php` - ไฟล์ทดสอบ (ถ้ามี)

## วิธี Upload แบบง่าย

### วิธีที่ 1: Upload ทั้งโฟลเดอร์ (แนะนำ)
```bash
# Compress ทั้งโฟลเดอร์
tar -czf field_project.tar.gz field_project/

# Upload ไป server
scp field_project.tar.gz user@server-ip:/home/user/

# บน server
tar -xzf field_project.tar.gz
cd field_project
chmod +x deploy_linux.sh
./deploy_linux.sh
```

### วิธีที่ 2: Upload เฉพาะไฟล์ที่แก้ไข
```bash
# Upload ไฟล์ที่แก้ไขเท่านั้น
scp .htaccess user@server:/path/to/field_project/
scp SQL/update_schema.sql user@server:/path/to/field_project/SQL/
scp dashboard/view_job.php user@server:/path/to/field_project/dashboard/
scp dashboard/job_result.php user@server:/path/to/field_project/dashboard/
scp admin/edit_job.php user@server:/path/to/field_project/admin/
scp admin/export_jobs.php user@server:/path/to/field_project/admin/
scp includes/image_optimizer.php user@server:/path/to/field_project/includes/
scp admin/api/export_word_zip.php user@server:/path/to/field_project/admin/api/
scp admin/api/process_excel_for_word.php user@server:/path/to/field_project/admin/api/
scp deploy_linux.sh user@server:/path/to/field_project/
```

### วิธีที่ 3: ใช้ Git (ถ้ามี repo)
```bash
# บน Windows
git add .
git commit -m "Fix bugs and add deployment scripts"
git push origin main

# บน Linux Server
git pull origin main
chmod +x deploy_linux.sh
./deploy_linux.sh
```

## สรุป: ไฟล์ที่ต้อง Upload (14 ไฟล์)

1. `.htaccess`
2. `SQL/update_schema.sql`
3. `SQL/fix_result_column.sql`
4. `dashboard/view_job.php`
5. `dashboard/job_result.php`
6. `admin/edit_job.php`
7. `admin/export_jobs.php`
8. `includes/image_optimizer.php`
9. `admin/api/export_word_zip.php`
10. `admin/api/process_excel_for_word.php`
11. `deploy_linux.sh`
12. `fix_issues.sh`
13. `DEPLOY_LINUX.md`
14. `CHANGES.md`
