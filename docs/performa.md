# Performa KampusLMS

**Kondisi uji:** seeder penuh (30 mahasiswa, 140 submission), lokal, Debugbar.
**Tanggal ukur sebelum:** 3 November 2026  |  **Tanggal ukur sesudah:** 4 November 2026

| Halaman | Query sebelum | Query sesudah | Waktu sebelum (ms) | Waktu sesudah (ms) | Memori sebelum | Memori sesudah |
|---|---|---|---|---|---|---|
| Daftar mata kuliah | 7 Queries | 7 QUeries | 1.100ms | 1.780ms | 25mb | 25mb |
| Detail mata kuliah | 10 Queries | 9 Queries | 1.140ms | 2.200ms | 25mb | 26mb |
| Daftar tugas | 8 Queries | 8 Queries | 1.150ms | 1.450ms | 25mb | 25mb |
| Daftar pengumpulan (dosen) | 10 Queries | 10 Queries | 1.130ms | 2.070ms | 25mb | 25mb |
| **Rekap nilai** | 7 Queries | 7 Queries | 515ms | 1.590ms | 26mb | 26mb |
| Dashboard mahasiswa | 5 Queries | 5 Queries | 465ms | 1.760ms | 25mb | 25mb |
| Dashboard dosen | 11 Queries | 12 Queries | 522ms | 1.050ms | 26mb | 25mb |

**Halaman terburuk:** Detail mata kuliah (diukur sebagai dosen).

**Alasan dipilih:** 10 query, salah satu yang terbanyak di antara halaman
utama. Memuat dua daftar mahasiswa penuh (33 model).

**Pengelompokan 10 query:**

| Kelompok | Jumlah | Keterangan |
|---|---|---|
| Framework (session, auth, route binding) | 4 | tidak bisa dihemat |
| Daftar mahasiswa (terdaftar, belum terdaftar) | 2 | tanpa pagination |
| Lazy load dosen (show.blade.php#65) | 1 | akan melanggar preventLazyLoading |
| Count di view (materials, assignments) | 2 | bisa digabung dengan loadCount |
| Badge notifikasi (JumlahNotifikasi#47) | 1 | jalan di setiap halaman dosen |

**Hasil EXPLAIN:**

| Query | type | rows | Kesimpulan |
|---|---|---|---|
| Badge notifikasi (JumlahNotifikasi#47): submissions JOIN assignments JOIN courses, not exists grades | ref / ref / ref / eq_ref | 1 / 3 / 14 / 1 | Seluruh tabel memakai index (`Using index`), tanpa full scan. Subquery `not exists` dioptimasi MySQL menjadi anti-join. Tidak perlu index tambahan pada ukuran data saat ini. |

## preventLazyLoading

Diaktifkan di `AppServiceProvider::boot()` dengan
`Model::preventLazyLoading(! $this->app->isProduction())`.

Seluruh halaman dijelajahi sebagai admin, dosen, dan mahasiswa dengan
seeder penuh. Hasil: tidak ditemukan pelanggaran (tidak ada lazy loading
pada koleksi). Relasi yang diakses di view sudah dimuat lewat
`with()` / `withCount()`.

## Bagian yang Sengaja Tidak Di-cache

Selain menerapkan cache pada statistik dashboard admin, beberapa bagian aplikasi sengaja tidak menggunakan cache agar informasi yang ditampilkan tetap sesuai dengan kondisi terbaru dan kebutuhan setiap pengguna.

| Bagian | Alasan Tidak Di-cache |
|---|---|
| Ringkasan mata kuliah pada dashboard admin | Menggunakan pagination, sehingga data perlu menyesuaikan halaman yang sedang dibuka. |
| Dashboard mahasiswa | Menampilkan informasi mata kuliah dan aktivitas berdasarkan akun mahasiswa yang sedang login. |
| Dashboard dosen | Menampilkan informasi yang berkaitan dengan mata kuliah dan tugas yang dikelola oleh dosen tersebut. |
| Daftar dan detail mata kuliah | Data dapat berubah ketika mata kuliah diperbarui atau mahasiswa melakukan enrollment. |
| Daftar tugas dan pengumpulan | Informasi tugas serta status pengumpulan dapat berubah sewaktu-waktu, sehingga perlu menampilkan kondisi terbaru. |
| Rekap nilai | Nilai dan umpan balik dapat berubah setelah dosen melakukan penilaian atau pembaruan. |
| Notifikasi | Jumlah notifikasi yang belum dibaca dan status keterbacaannya dapat berubah ketika pengguna menerima atau membaca notifikasi. |

Keputusan tersebut dilakukan agar aplikasi tidak menampilkan data yang sudah tidak sesuai, terutama pada bagian yang berkaitan dengan aktivitas pengguna dan perubahan data secara langsung. Dengan demikian, cache difokuskan pada statistik dashboard admin yang cenderung sering diminta tetapi tidak harus selalu dihitung ulang setiap kali halaman dibuka.