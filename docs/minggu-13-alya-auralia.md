# Catatan Minggu 13
## Nama: Alya Auralia
## NIM: 10241008
## Mata Kuliah: Pemrograman Web B

# Read
### 1. Berapa commit atas nama Anda? Bandingkan dengan anggota lain.
Jawaban=<Br>

```
C:\laragon\www\kampuslms-kelompok-01>git shortlog -sn
   115  AdeliaIsraE
    50  Renata_1003
    35  Andikaputra
    30  10241002-Putri
    28  adlrnta
    20  AndikaPutra
    13  ayya
     4  alyaurlia
     1  Achmad Zaki Zaidan
```

Berdasarkan hasil perintah git shortlog -sn, terdapat 17 commit atas nama saya, yang tercatat menggunakan dua identitas Git, yaitu ayya sebanyak 13 commit dan alyaurlia sebanyak 4 commit.

Jika dibandingkan dengan anggota kelompok lainnya, jumlah commit saya masih lebih sedikit. AdeliaIsraE memiliki jumlah commit terbanyak, yaitu 115 commit, disusul Renata_1003 sebanyak 50 commit dan Andikaputra sebanyak 35 commit.

Menurut saya, perbedaan jumlah commit menunjukkan bahwa kontribusi setiap anggota dalam riwayat Git tidak sama. Namun, jumlah commit saja belum cukup untuk menentukan besar kecilnya kontribusi karena perlu mempertimbangkan isi perubahan, kendala selama pengerjaan dan tanggung jawab yang dikerjakan setiap anggota.

### 2. Adakah minggu di mana Anda tidak punya commit sama sekali?
Jawaban=<Br>

```
C:\laragon\www\kampuslms-kelompok-01>git log --all --author="ayya" --format="%ad %h %s" --date=short
2026-11-04 1a255d8 perf: optimalkan cache dashboard, query rekap nilai, dan penggunaan config
2026-10-11 aab52fb Update Tabel IDOR menjadi terbaru
2026-10-11 6f1fe8c skrip pengujian otorisasi
2026-10-10 af53191 Tabel Titik Rawan IDOR Terbaru
2026-10-08 7f1ba46 Build no.4 Week 7
2026-10-07 8e707ac file scripts/test-api.sh
2026-10-07 f39a374 dokumentasi API pada docs/api.md
2026-10-06 4dd5c7b Pengerjaan Build no.2 Endpoint 11-15
2026-10-06 0673305 Endpoint no. 12
2026-09-15 ce04b9b break week-03-alyaaurlia
2026-09-09 9c9f75a WIP on main: 4275ed5 Merge branch 'main' of https://github.com/alyaurlia/kampuslms-k
elompok-01
2026-09-09 e21f903 index on main: 4275ed5 Merge branch 'main' of https://github.com/alyaurlia/kampuslms
-kelompok-01
2026-09-02 4275ed5 Merge branch 'main' of https://github.com/alyaurlia/kampuslms-kelompok-01
2026-09-02 d8abba1 Merge branch 'main' of https://github.com/alyaurlia/kampuslms-kelompok-01
2026-09-02 8b5ecc3 commit pertama: setup laravel
```
```
C:\laragon\www\kampuslms-kelompok-01>git log --all --author="alyaurlia" --format="%ad %h %s" --date=short
2026-10-06 59a0bbc Merge pull request #52 from alyaurlia/Adel
2026-09-09 db27197 Add files via upload
2026-09-09 8739417 minggu-02-alya-auralia
2026-09-07 cd6c210 Merge pull request #1 from alyaurlia/andika
```
Ya, terdapat beberapa minggu ketika saya tidak melakukan commit sama sekali. Berdasarkan riwayat Git, minggu2 tersebut terdapat di tanggal 21-27 september, 28 September–4 Oktober, 12–18 Oktober, 19–25 Oktober, 26 Oktober–1 November. Aktivitas commit saya terlihat pada awal September, pertengahan September, Oktober, dan awal November. Hal ini menunjukkan bahwa aktivitas commit saya belum dilakukan secara konsisten setiap minggu.


### 3. Berapa persen pesan commit yang mengikuti Conventional Commits?
Jawaban=<Br>
Berdasarkan hasil pemeriksaan 17 pesan commit, terdapat 1 commit yang mengikuti format Conventional Commits, yaitu perf: optimalkan cache dashboard, query rekap nilai, dan penggunaan config. Sementara itu, 16 commit lainnya belum mengikuti format tersebut. Dengan demikian, persentase pesan commit yang sesuai Conventional Commits adalah 5,88%. Hal ini menunjukkan bahwa penggunaan format penamaan commit saya masih perlu ditingkatkan agar riwayat perubahan proyek lebih terstruktur dan mudah dipahami. 

### 4. Temukan satu commit Anda sendiri yang pesannya buruk. Tulis ulang seharusnya bagaimana.
Jawaban=<Br>
Salah satu pesan commit saya yang kurang baik adalah "Update Tabel IDOR menjadi terbaru". Pesan tersebut belum mengikuti format Conventional Commits dan kurang menjelaskan perubahan yang dilakukan. Seharusnya pesan tersebut ditulis menjadi "docs: update tabel IDOR pada dokumentasi keamanan". Dengan demikian, jenis perubahan dan bagian yang diperbarui dapat diketahui dengan lebih jelas.

### 5. `git log --stat` — temukan commit terbesar Anda. Apakah seharusnya dipecah?
Jawaban=<Br>
Berdasarkan hasil pemeriksaan menggunakan perintah git log --stat, commit terbesar saya adalah f39a374 dengan pesan "dokumentasi API pada docs/api.md". Commit tersebut mengubah 3 file dengan 1.059 baris penambahan dan 55 baris penghapusan. Menurut saya, commit tersebut sebaiknya dipecah karena menggabungkan perubahan kode program dengan penambahan dokumentasi API. Pemisahan commit akan membuat riwayat perubahan lebih terstruktur dan memudahkan proses peninjauan kode.  Akan lebih baik jika dipisah menjadi beberapa commit, misalnya:
fix: update course controller and grade model
docs: add API documentation
