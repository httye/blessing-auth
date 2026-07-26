FROM php:8.2-fpm-alpine

WORKDIR /app

# 系统依赖
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    unzip \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install gd mbstring pdo_mysql pdo_sqlite zip bcmath

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 项目文件
COPY . .

# 生产依赖
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# 入口脚本
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
