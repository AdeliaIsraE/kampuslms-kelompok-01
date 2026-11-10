# syntax=docker/dockerfile:1

# ============================================================
# Tahap 1 - Build aset frontend (Vite + Tailwind)
# Folder public/build di-.gitignore, jadi harus dibuat saat build.
# Tanpanya, layout.blade.php (@vite) error "Vite manifest not found".
# ============================================================
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build


# ============================================================
# Tahap 2 - Aplikasi Laravel 12 (PHP 8.4 + Apache)
# ============================================================
FROM php:8.4-apache

# 1. Paket sistem + ekstensi PHP
#    pdo_mysql -> MySQL Railway | bcmath -> dipakai Laravel
#    (mbstring, tokenizer, ctype, fileinfo, dom sudah bawaan image)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 1b. Batas upload PHP (default image hanya 2M, sedangkan validasi app = 10 MB)
RUN { \
      echo "upload_max_filesize = 20M"; \
      echo "post_max_size = 25M"; \
      echo "memory_limit = 256M"; \
      echo "max_execution_time = 120"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# 2. Apache: mod_rewrite, satu MPM saja, .htaccess aktif,
#    document root ke /public, port dibaca dari variabel PORT
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite \
    && a2dismod -f mpm_event mpm_worker || true \
    && a2enmod mpm_prefork \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -ri -e 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri -e 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# 3. Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 4. Dependensi PHP (layer terpisah agar cache build awet)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# 5. Kode aplikasi (file yang tidak perlu disaring oleh .dockerignore)
COPY . .

# 6. Hasil build Vite dari tahap 1
COPY --from=assets /app/public/build ./public/build

# 7. Autoloader produksi + package:discover
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/private storage/app/public \
                storage/framework/cache/data storage/framework/sessions \
                storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# 8. Script startup: pastikan hanya mpm_prefork aktif, atur PORT dinamis Railway, clear cache, jalankan migrasi & seed, lalu jalankan Apache
EXPOSE 80 8080

CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* && export PORT=\"${PORT:-8080}\" && sed -i \"s/Listen .*/Listen $PORT/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:.*>/<VirtualHost \\*:$PORT>/\" /etc/apache2/sites-available/000-default.conf && php artisan config:clear && php artisan route:clear && php artisan view:clear && (php artisan migrate --force || true) && (php artisan db:seed --force || true) && exec apache2-foreground"]