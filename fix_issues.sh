#!/bin/bash
# Fix permissions and database schema

echo "=== Fixing Field Project Issues ==="
echo ""

# 1. Fix database column size
echo "1. Fixing database column 'result' size..."
docker exec -i field_db mysql -uroot -proot field_project -e "ALTER TABLE job_logs MODIFY COLUMN result VARCHAR(255) NULL;"
if [ $? -eq 0 ]; then
    echo "   ✓ Database column fixed"
else
    echo "   ✗ Failed to fix database"
fi
echo ""

# 2. Fix upload directory permissions
echo "2. Fixing upload directory permissions..."
docker exec field_php chmod -R 777 /var/www/html/uploads
if [ $? -eq 0 ]; then
    echo "   ✓ Permissions fixed"
else
    echo "   ✗ Failed to fix permissions"
fi
echo ""

# 3. Create necessary directories
echo "3. Creating necessary directories..."
docker exec field_php mkdir -p /var/www/html/uploads/job_photos
docker exec field_php mkdir -p /var/www/html/uploads/temp
docker exec field_php chmod -R 777 /var/www/html/uploads
echo "   ✓ Directories created"
echo ""

echo "=== All fixes completed ==="
