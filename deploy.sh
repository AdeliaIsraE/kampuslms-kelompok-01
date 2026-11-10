#!/usr/bin/env bash

# Menghentikan skrip ketika ada perintah yang gagal.
set -e

# Menentukan branch Git yang digunakan untuk deployment.
DEPLOY_BRANCH="${DEPLOY_BRANCH:-andika}"

# URL aplikasi produksi, diisi melalui environment server.
: "${DEPLOY_URL:?DEPLOY_URL harus diatur}"

# Secret untuk mengakses aplikasi ketika maintenance.
: "${MAINTENANCE_SECRET:?MAINTENANCE_SECRET harus diatur}"

# Menampilkan informasi awal deployment.
echo "Memulai deployment KampusLMS..."

# Mengaktifkan maintenance mode agar pengguna tidak mengakses
# aplikasi selama proses pembaruan berlangsung.
php artisan down --with-secret="$MAINTENANCE_SECRET"

# Mengambil kode terbaru dari branch GitHub.
git pull origin "$DEPLOY_BRANCH"

# Memasang dependency PHP untuk lingkungan produksi.
composer install --no-dev --optimize-autoloader

# Memasang dependency frontend dan membangun aset produksi.
npm ci
npm run build

# Memperbarui struktur database sesuai migrasi terbaru.
php artisan migrate --force

# Membersihkan cache lama sebelum membuat cache produksi.
php artisan optimize:clear

# Membuat cache konfigurasi, route, view, dan event.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Meminta queue worker memuat ulang kode terbaru.
php artisan queue:restart

# Menyimpan cookie bypass maintenance untuk pemeriksaan aplikasi.
COOKIE_FILE="$(mktemp)"
trap 'rm -f "$COOKIE_FILE"' EXIT

# Mengakses URL rahasia untuk mendapatkan cookie bypass.
curl --fail --silent --show-error \
    --cookie-jar "$COOKIE_FILE" \
    --output /dev/null \
    "$DEPLOY_URL/$MAINTENANCE_SECRET"

# Memastikan aplikasi memberikan respons HTTP sukses.
curl --fail --silent --show-error \
    --cookie "$COOKIE_FILE" \
    --output /dev/null \
    "$DEPLOY_URL"

# Membuka kembali aplikasi setelah pemeriksaan berhasil.
php artisan up

# Menampilkan pesan keberhasilan deployment.
echo "Deployment KampusLMS berhasil!"
