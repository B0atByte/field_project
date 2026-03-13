FROM php:8.2-apache

# อัปเดตและติดตั้ง library ที่จำเป็น
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql zip gd mbstring xml exif \
    && rm -rf /var/lib/apt/lists/*

# เปิดใช้งาน mod_rewrite
RUN a2enmod rewrite

# แก้ไขให้ Apache อ่านไฟล์ .htaccess ได้
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# สร้างโฟลเดอร์ tmp และให้สิทธิ์
RUN mkdir -p /var/www/html/tmp && \
    chown -R www-data:www-data /var/www/html/tmp && \
    chmod -R 775 /var/www/html/tmp

# ตั้งค่า permission สำหรับโฟลเดอร์หลัก
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# ตั้งค่า working directory
WORKDIR /var/www/html

# ตั้งค่า upload และ temp directory สำหรับ PHP
RUN mkdir -p /tmp/php && \
    chown -R www-data:www-data /tmp/php && \
    chmod -R 775 /tmp/php