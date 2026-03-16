#!/bin/bash
# Deploy Script for Linux Server
# รันสคริปต์นี้หลังจาก upload โค้ดขึ้น server แล้ว

set -e  # หยุดทันทีถ้ามี error

echo "=========================================="
echo "  Field Project - Linux Deployment"
echo "=========================================="
echo ""

# 1. ตรวจสอบ Docker
echo "1. Checking Docker..."
if ! command -v docker &> /dev/null; then
    echo "   [ERROR] Docker is not installed!"
    exit 1
fi
echo "   [OK] Docker is installed"
echo ""

# 2. ตรวจสอบ docker-compose
echo "2. Checking docker-compose..."
if ! command -v docker-compose &> /dev/null; then
    echo "   [ERROR] docker-compose is not installed!"
    exit 1
fi
echo "   [OK] docker-compose is installed"
echo ""

# 3. Stop containers (ถ้ามี)
echo "3. Stopping existing containers..."
docker-compose down || true
echo "   [OK] Containers stopped"
echo ""

# 4. Build and start containers
echo "4. Building and starting containers..."
docker-compose up -d --build
echo "   [OK] Containers started"
echo ""

# 5. Wait for database to be ready
echo "5. Waiting for database to be ready..."
sleep 10
echo "   [OK] Database should be ready"
echo ""

# 6. Run database migrations
echo "6. Running database migrations..."
docker exec -i field_db mysql -uroot -proot field_project < SQL/update_schema.sql 2>&1 | grep -v "Duplicate" || true
echo "   [OK] Migrations completed"
echo ""

# 7. Fix database columns
echo "7. Fixing database columns..."
docker exec -i field_db mysql -uroot -proot field_project -e "ALTER TABLE job_logs MODIFY COLUMN result VARCHAR(255) NULL;" 2>&1 | grep -v "Duplicate" || true
docker exec -i field_db mysql -uroot -proot field_project -e "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_manage_departments TINYINT(1) NOT NULL DEFAULT 0;" 2>&1 | grep -v "Duplicate" || true
echo "   [OK] Database columns fixed"
echo ""

# 8. Fix permissions
echo "8. Fixing file permissions..."
docker exec field_php chmod -R 777 /var/www/html/uploads
docker exec field_php chown -R www-data:www-data /var/www/html/uploads
echo "   [OK] Permissions fixed"
echo ""

# 9. Create necessary directories
echo "9. Creating necessary directories..."
docker exec field_php mkdir -p /var/www/html/uploads/job_photos
docker exec field_php mkdir -p /var/www/html/uploads/temp
docker exec field_php chmod -R 777 /var/www/html/uploads
echo "   [OK] Directories created"
echo ""

# 10. Restart Apache
echo "10. Restarting Apache..."
docker exec field_php apachectl -k graceful
echo "   [OK] Apache restarted"
echo ""

echo "=========================================="
echo "  Deployment completed successfully!"
echo "=========================================="
echo ""
echo "Application is now running at:"
echo "  - Web: http://YOUR_SERVER_IP:8080"
echo "  - phpMyAdmin: http://YOUR_SERVER_IP:8081"
echo ""
echo "Default admin credentials:"
echo "  - Username: admin"
echo "  - Password: (check your database)"
echo ""
