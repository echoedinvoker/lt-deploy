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

# 修改 Apache DocumentRoot 到 CodeIgniter 入口點
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# 調整 Apache 配置文件
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

# 設定工作目錄（非必要，但乾淨）
WORKDIR /var/www/html/public


# 複製並安裝 PHP 依賴
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 複製應用程式檔案
COPY . .

# 複製 Apache 配置
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
