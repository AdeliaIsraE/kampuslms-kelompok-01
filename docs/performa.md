# Performa KampusLMS

**Kondisi uji:** seeder penuh (30 mahasiswa, 140 submission), lokal, Debugbar.
**Tanggal ukur sebelum:** 3 November 2026  |  **Tanggal ukur sesudah:** 3 November 2026

| Halaman | Query sebelum | Query sesudah | Waktu sebelum (ms) | Waktu sesudah (ms) | Memori sebelum | Memori sesudah |
|---|---|---|---|---|---|---|
| Daftar mata kuliah | 7 Queries | | 1.100 ms | | 25mb | |
| Detail mata kuliah | 10 Queries | | 1.140 ms | | 25mb | |
| Daftar tugas | 8 Queries | | 1.150 ms | | 25mb | |
| Daftar pengumpulan (dosen) | 10 Queries | | 1.130 ms | | 25mb | |
| **Rekap nilai** | 7 Queries | | 515 ms | | 26mb | |
| Dashboard mahasiswa | 5 Queries | | 465 ms | | 25mb | |
| Dashboard dosen | 11 Queries | | 522 ms | | 26mb | |

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