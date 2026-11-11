#!/bin/sh
set -e

# Port dinamis Railway untuk Apache
export PORT="${PORT:-8080}"
sed -i "s/Listen .*/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*

cd /var/www/html

# Migrasi dulu: worker butuh tabel jobs. Tanpa "|| true" agar kegagalan terlihat.
php artisan migrate --force && php artisan db:seed --force

# Cache dibuat saat runtime karena variabel Railway baru tersedia di sini
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link || true

# Perintah di atas dijalankan sebagai root; kembalikan kepemilikan agar
# worker (www-data) bisa menulis log dan cache
chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf