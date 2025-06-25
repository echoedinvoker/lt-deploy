FROM php:8.2-apache

# 安裝系統依賴和 PHP 擴展
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    pdo_mysql \
    mysqli \
    zip \
    intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 安装 Redis 扩展
RUN pecl install redis && docker-php-ext-enable redis

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 啟用 Apache 模組
RUN a2enmod rewrite

# 設定工作目錄
WORKDIR /var/www/html

# 複製並安裝 PHP 依賴
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 複製應用程式檔案
COPY . .

# 設定權限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/writable

# 複製 Apache 配置
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
