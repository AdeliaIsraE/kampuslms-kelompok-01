# Debugging KampusLMS Kelompok-01

Dokumen ini berisi tiga bug yang ditemukan selama pengerjaan proyek KampusLMS, mulai dari masalah yang muncul, penyebabnya, hingga cara memperbaikinya. Setiap bug juga dilengkapi dengan pengujian untuk memastikan perbaikannya berhasil. Adapun bug yang dibahas dalam dokumen ini merupakan beberapa masalah yang ditemukan selama proses pengembangan aplikasi KampusLMS Kelompok-01, antara lain sebagai berikut:

## 1. Upload File Gagal di Server (PR #130)

**Gejala:**

File tidak bisa diunggah ketika aplikasi sudah dijalankan di server.

**Cara Menemukan Penyebab:**

Masalah ditemukan saat mencoba mengunggah file secara langsung di server setelah aplikasi di-deploy. Dari pemeriksaan konfigurasi, diketahui bahwa Dockerfile belum disesuaikan dengan kebutuhan aplikasi.

**Penyebab dan Perbaikan:**

Masalah terjadi karena konfigurasi Dockerfile belum mendukung kebutuhan proses upload file di server. Perbaikannya dilakukan dengan menyesuaikan konfigurasi Dockerfile agar proses upload dapat berjalan dengan baik.

**Pengujian Setelah Perbaikan:**

Pengujian dilakukan secara manual di server dengan mencoba mengunggah file kembali. Pengujian ini memastikan file berhasil diunggah setelah konfigurasi Dockerfile diperbaiki.

## 2. Status Notifikasi Tidak Berubah (PR #131)

**Gejala:**

Notifikasi yang sudah dibaca masih terlihat seperti belum dibaca, sehingga statusnya tidak sesuai dengan kondisi sebenarnya.

**Cara Menemukan Penyebab:**

Masalah ditemukan saat mencoba fitur notifikasi dan melihat bahwa statusnya tidak berubah meskipun notifikasi sudah dibaca. Setelah diperiksa, diketahui bahwa data notifikasi dari seeder memiliki tanggal di masa depan sehingga memengaruhi logika waktu pada fitur notifikasi.

**Penyebab dan Perbaikan:**

Masalah terjadi karena tanggal pada data notifikasi dari seeder tidak sesuai dengan waktu yang seharusnya. Untuk mengatasinya, logika waktu pada fitur notifikasi diperbaiki agar status notifikasi dapat diperbarui dengan benar.

**Pengujian Setelah Perbaikan:**

Fitur diuji secara manual di server lokal dengan menekan tombol "Tandai semua dibaca". Setelah tombol ditekan, status notifikasi diperiksa untuk memastikan seluruh notifikasi berubah menjadi sudah dibaca.

## 3. Notifikasi Admin Tidak Muncul (PR #133)

**Gejala:**

Notifikasi di halaman admin tidak muncul meskipun ada aktivitas yang seharusnya menghasilkan notifikasi.

**Cara Menemukan Penyebab:**

Masalah ditemukan ketika memeriksa halaman admin dan melihat bahwa notifikasi tidak muncul. Setelah ditelusuri, diketahui bahwa Queue Worker belum berjalan secara otomatis di server sehingga proses pengiriman notifikasi tidak dijalankan.

**Penyebab dan Perbaikan:**

Masalah terjadi karena Queue Worker belum dikonfigurasi agar berjalan secara otomatis di server. Untuk mengatasinya, ditambahkan Supervisor agar Queue Worker dapat berjalan secara otomatis dan memproses antrean notifikasi.

**Pengujian Setelah Perbaikan:**

Pengujian dilakukan dengan memastikan Supervisor dan Queue Worker berjalan, kemudian melakukan aktivitas yang menghasilkan notifikasi. Selanjutnya, halaman admin diperiksa untuk memastikan notifikasi berhasil diproses dan ditampilkan.
