# Deployment KampusLMS — Kelompok 01

## 1. Spesifikasi server
(Railway, region, service app + MySQL, image php:8.4-apache, versi PHP/Node)

## 2. Langkah dari nol
## 3. Cara deployment ulang
## 4. Cara membaca log
## 5. Daftar periksa keamanan produksi
Diuji pada: <tanggal, jam> — domain: https://...

### 5.1 APP_DEBUG=false & APP_ENV=production
Perintah: `php artisan about`

<img src="image/butir-1dan2.png">

Kesimpulan: ✅ / ❌

### 5.2 /.env tidak bisa diakses
Perintah: `curl.exe -I https://DOMAIN/.env`

<img src="image/butir 3.png">

### 5.3 /
perintah: `curl.exe -I https://DOMAIN/storage/logs/laravel.log`

<img src="image/butir 4.png">

### 5.4
perintah :`curl.exe -I https://DOMAIN/composer.json`

<img src="image/butirmodul.png">

### 5.5
perintah: `curl.exe -I http://DOMAIN/ lalu curl.exe -I https://DOMAIN/`

<img src="image/butir 5.png">

### 5.6
perintah: ` curl.exe -i https://DOMAIN/login `

<img src="image/butir 6.png">

### 5.7
perintah: ` BASE_URL=https://DOMAIN ... ./scripts/test-authz.sh `


### 5.8
perintah: ` curl.exe -I https://DOMAIN/pengumpulan/1/unduh `

<img src="image/butir 100.png">
... (ulangi untuk 12 butir)

## 6. Hasil test-authz.sh terhadap produksi
## 7. Pengujian dari HP (siapa, perangkat, hasil)
## 8. Masalah yang ditemui dan cara mengatasinya
| Gejala | Cara menemukan penyebab | Penyebab | Solusi |