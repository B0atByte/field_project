@echo off
REM Fix permissions and database schema for Windows

echo === Fixing Field Project Issues ===
echo.

REM 1. Fix database columns
echo 1. Fixing database columns...
docker exec -i field_db mysql -uroot -proot field_project -e "ALTER TABLE job_logs MODIFY COLUMN result VARCHAR(255) NULL;"
docker exec -i field_db mysql -uroot -proot field_project -e "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_manage_departments TINYINT(1) NOT NULL DEFAULT 0;"
if %errorlevel% equ 0 (
    echo    [OK] Database columns fixed
) else (
    echo    [ERROR] Failed to fix database
)
echo.

REM 2. Fix upload directory permissions
echo 2. Fixing upload directory permissions...
docker exec field_php chmod -R 777 /var/www/html/uploads
if %errorlevel% equ 0 (
    echo    [OK] Permissions fixed
) else (
    echo    [ERROR] Failed to fix permissions
)
echo.

REM 3. Create necessary directories
echo 3. Creating necessary directories...
docker exec field_php mkdir -p /var/www/html/uploads/job_photos
docker exec field_php mkdir -p /var/www/html/uploads/temp
docker exec field_php chmod -R 777 /var/www/html/uploads
echo    [OK] Directories created
echo.

echo === All fixes completed ===
pause
