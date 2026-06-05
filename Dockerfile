FROM php:8.4-fpm

# Встановлюємо системні залежності
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Встановлюємо та увімкнемо розширення MongoDB та Redis через PECL
RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

# Встановлюємо Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
