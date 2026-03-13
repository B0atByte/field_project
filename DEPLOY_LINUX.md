# คู่มือการ Deploy ระบบไปยัง Linux Server

## ขั้นตอนที่ 1: เตรียม Server

### ติดตั้ง Docker และ Docker Compose (ถ้ายังไม่มี)

```bash
# Update package list
sudo apt update

# ติดตั้ง Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# ติดตั้ง Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# เพิ่ม user ปัจจุบันเข้า docker group
sudo usermod -aG docker $USER

# Logout และ Login ใหม่เพื่อให้มีผล
```

## ขั้นตอนที่ 2: Upload โค้ดขึ้น Server

### วิธีที่ 1: ใช้ Git (แนะนำ)

```bash
# บน Server
cd /home/your_user
git clone https://github.com/your-repo/field_project.git
cd field_project
```

### วิธีที่ 2: ใช้ SCP/SFTP

```bash
# บน Windows (PowerShell)
# Compress โฟลเดอร์
Compress-Archive -Path "C:\Users\Administrator\Desktop\field_project" -DestinationPath "field_project.zip"

# Upload ไปยัง Server
scp field_project.zip user@your-server-ip:/home/user/

# บน Linux Server
cd /home/user
unzip field_project.zip
cd field_project
```

### วิธีที่ 3: ใช้ FTP Client (FileZilla, WinSCP)

1. เปิด FileZilla/WinSCP
2. เชื่อมต่อไปยัง Server (SFTP)
3. Upload โฟลเดอร์ `field_project` ทั้งหมด

## ขั้นตอนที่ 3: Deploy ระบบ

```bash
# เข้าไปในโฟลเดอร์โปรเจค
cd /path/to/field_project

# ให้สิทธิ์รัน deploy script
chmod +x deploy_linux.sh

# รัน deployment script
./deploy_linux.sh
```

## ขั้นตอนที่ 4: ตรวจสอบการทำงาน

```bash
# ดู logs
docker-compose logs -f

# ตรวจสอบ containers ที่ทำงานอยู่
docker ps

# ทดสอบเข้าเว็บ
curl http://localhost:8080
```

## การเข้าถึงระบบ

- **Web Application**: `http://YOUR_SERVER_IP:8080`
- **phpMyAdmin**: `http://YOUR_SERVER_IP:8081`

## คำสั่งที่มีประโยชน์

```bash
# ดู logs แบบ real-time
docker-compose logs -f

# Restart ระบบ
docker-compose restart

# Stop ระบบ
docker-compose down

# Start ระบบ
docker-compose up -d

# เข้าไปใน PHP container
docker exec -it field_php bash

# เข้าไปใน MySQL container
docker exec -it field_db mysql -uroot -proot field_project

# Backup database
docker exec field_db mysqldump -uroot -proot field_project > backup_$(date +%Y%m%d).sql

# Restore database
docker exec -i field_db mysql -uroot -proot field_project < backup_20260118.sql
```

## Troubleshooting

### ถ้า Port ชน (8080 หรือ 8081 ถูกใช้แล้ว)

แก้ไขไฟล์ `docker-compose.yml`:

```yaml
services:
  php:
    ports:
      - "8090:80"  # เปลี่ยนจาก 8080 เป็น 8090
  
  phpmyadmin:
    ports:
      - "8091:80"  # เปลี่ยนจาก 8081 เป็น 8091
```

### ถ้า Permission denied

```bash
sudo chmod -R 777 uploads/
sudo chown -R www-data:www-data uploads/
```

### ถ้า Database ไม่ทำงาน

```bash
# ลบ volume และสร้างใหม่
docker-compose down -v
docker-compose up -d

# Import database ใหม่
docker exec -i field_db mysql -uroot -proot field_project < SQL/field_project.sql
```

## การอัพเดทโค้ด

```bash
# ถ้าใช้ Git
git pull origin main

# ถ้าใช้ manual upload - upload ไฟล์ที่เปลี่ยนแปลงเท่านั้น

# Restart containers
docker-compose restart

# ถ้ามีการเปลี่ยน Dockerfile
docker-compose up -d --build
```

## Security Checklist

- [ ] เปลี่ยน MySQL root password ใน `docker-compose.yml`
- [ ] ตั้งค่า Firewall (ufw) เปิดเฉพาะ port ที่จำเป็น
- [ ] ใช้ HTTPS (ติดตั้ง SSL Certificate)
- [ ] เปลี่ยน default admin password
- [ ] Backup database เป็นประจำ
- [ ] Update Docker images เป็นประจำ

## ติดตั้ง SSL (HTTPS) - Optional

```bash
# ติดตั้ง Certbot
sudo apt install certbot

# สร้าง SSL Certificate
sudo certbot certonly --standalone -d yourdomain.com

# แก้ไข docker-compose.yml เพิ่ม SSL volume
```
