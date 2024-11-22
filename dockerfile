# Sử dụng image PHP chính thức với phiên bản phù hợp
FROM php:8.2-fpm

# Cài đặt các dependencies cần thiết
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd

# Cài đặt Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy mã nguồn Laravel
COPY . /var/www

# Thiết lập thư mục làm việc
WORKDIR /var/www

# Thiết lập quyền cho thư mục storage và bootstrap/cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Expose cổng 9000
EXPOSE 9000

# Lệnh khởi chạy PHP-FPM
CMD ["php-fpm"]
