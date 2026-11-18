Nama    : Ade Putri Amanda

NIM     : 10241002

# Read → Break → Fix → Build

## READ — Baca riwayat kelompok Anda (30 menit)

Tanpa AI, jawab di `docs/minggu-13-<nama>.md`:

### 1. Berapa _commit_ atas nama Anda? Bandingkan dengan anggota lain.

Jawab:

**Perintah yang dijalankan:**

```bash
git log --oneline --graph --all
git shortlog -sn
git log --pretty='%an' | sort | uniq -c
```

**Untuk pengecekan tambahan, saya juga menjalankan:**

```bash
git shortlog -sn --all
git log --format="%an | %ae" --all
```

**Keluaran `git shortlog -sn --all`:**

```text
116  AdeliaIsraE
 50  Renata_1003
 36  Andikaputra
 32  10241002-Putri
 28  adlrnta
 20  AndikaPutra
 13  ayya
  4  alyaurlia
  1  Achmad Zaki Zaidan
```

Untuk melihat jumlah _commit_ setiap anggota kelompok, saya menggunakan perintah `git shortlog -sn --all`. Dari hasil yang ditampilkan, terdapat beberapa anggota yang menggunakan nama _author_ berbeda meskipun email Git yang digunakan sama. Oleh karena itu, jumlah _commit_ tersebut saya gabungkan berdasarkan masing-masing anggota.

Hasilnya, saya memiliki 32 _commit_. Sementara itu, Adelia memiliki 116 _commit_, Renata sebanyak 78 _commit_, Andika sebanyak 56 _commit_, dan Alya sebanyak 17 _commit_. Jika dibandingkan dengan anggota lainnya, jumlah _commit_ saya berada di urutan keempat. Dari hasil tersebut, terlihat bahwa jumlah kontribusi _commit_ setiap anggota masih berbeda-beda.

### 2. Adakah minggu di mana Anda tidak punya _commit_ sama sekali?

Jawab:

Untuk mengetahui aktivitas _commit_ setiap minggunya, saya menjalankan perintah berikut:

```powershell
git log --all --no-merges --author="10241002-Putri" --date=format:"%Y-W%V" --pretty="%ad" | Group-Object | Sort-Object Name | Select-Object Name, Count
```

**Hasil pengecekan:**

```text
Name     Count
----     -----
2026-W36     5
2026-W37     3
2026-W38     3
2026-W40     2
2026-W41     3
2026-W43     1
2026-W44     1
2026-W46     3
```

**Dari hasil tersebut, terdapat tiga minggu yang tidak memiliki _commit_, yaitu:**

- Minggu ke-39 (21–27 September 2026).
- Minggu ke-42 (12–18 Oktober 2026).
- Minggu ke-45 (2–8 November 2026).

Alasan saya tidak melakukan _commit_ pada minggu tersebut salah satunya karena jadwal kerja kelompok yang bersamaan dengan kelas mata kuliah pilihan yang saya ikuti pada sesi 4. Hal ini membuat waktu saya untuk mengikuti kegiatan kerja kelompok menjadi terbatas. Ketika saya bisa mengikuti kerja kelompok setelah kelas selesai, pembagian tugas _front-end_ sudah dikerjakan dan sebagian besar pekerjaan lainnya telah dikerjakan oleh anggota kelompok. Akibatnya, pembagian pekerjaan menjadi kurang merata dan saya tidak memperoleh bagian tugas _front-end_ yang dapat saya kerjakan pada minggu-minggu tersebut.

### 3. Berapa persen pesan _commit_ yang mengikuti _Conventional Commits_?

Untuk mengetahui persentase pesan _commit_ yang mengikuti _Conventional Commits_, saya memeriksa riwayat _commit_ menggunakan perintah berikut:

**Perintah yang dijalankan:**

```powershell
git log --all --no-merges --author="10241002-Putri" --format="%s"
```

Selanjutnya, saya menggunakan perintah PowerShell untuk menghitung jumlah pesan _commit_ yang sesuai dengan format _Conventional Commits_.

```powershell
$commits = @(git log --all --no-merges --author="10241002-Putri" --format="%s")

$total = $commits.Count

$sesuai = @($commits | Where-Object {
    $_ -match '^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([^)]+\))?!?:\s'
}).Count

$persentase = [math]::Round(($sesuai / $total) * 100, 2)

Write-Host "Total commit       : $total"
Write-Host "Sesuai format      : $sesuai"
Write-Host "Tidak sesuai format: $($total - $sesuai)"
Write-Host "Persentase         : $persentase%"
```

**Hasil perhitungan:**

```text
Total commit       : 21
Sesuai format      : 5
Tidak sesuai format: 16
Persentase         : 23.81%
```

Berdasarkan hasil pemeriksaan terhadap 21 pesan _commit_, terdapat 5 pesan yang sudah mengikuti format _Conventional Commits_, sedangkan 16 pesan lainnya belum menggunakan format tersebut.

**Perhitungan persentasenya:**

5 / 21 × 100% = 23,81%

Dengan demikian, persentase pesan _commit_ saya yang mengikuti _Conventional Commits_ adalah **23,81%**. Sebagian besar pesan _commit_ yang sudah sesuai menggunakan tipe `style`, sedangkan beberapa pesan lainnya masih menggunakan deskripsi langsung tanpa mencantumkan tipe _commit_ di awal pesan.

### 4. Temukan satu _commit_ Anda sendiri yang pesannya buruk. Tulis ulang seharusnya bagaimana.

Untuk menemukan pesan _commit_ yang kurang jelas, saya memeriksa riwayat _commit_ dan detail perubahan menggunakan perintah berikut:

**Perintah yang dijalankan:**

```powershell
git log --all --no-merges --author="10241002-Putri" --format="%h | %s"
git show --stat --oneline 2a08ba1
git show --format=fuller 2a08ba1
```

**Pesan _commit_ sebelumnya:**

```text
Hash  : 2a08ba1
Pesan : frontend dashboard admin
```

**Perbaikan pesan _commit_:**

```text
feat(admin): tambahkan fitur dashboard admin
```

Pesan _commit_ tersebut menurut saya masih kurang jelas karena hanya menuliskan "_front-end dashboard_ admin", sehingga orang yang membaca riwayat _commit_ belum bisa mengetahui apa saja yang dikerjakan. Padahal, dalam _commit_ tersebut saya melakukan beberapa perubahan, mulai dari membuat _controller_, mengatur tampilan _dashboard_, menambahkan CSS, hingga menyesuaikan _routing_. Karena itu, pesan _commit_ sebaiknya dibuat lebih spesifik agar perubahan yang dilakukan bisa langsung dipahami tanpa harus membuka detail _commit_ terlebih dahulu.

### 5. Temukan _commit_ terbesar Anda menggunakan `git log --stat`. Apakah seharusnya dipecah?

Untuk mengetahui _commit_ dengan perubahan terbesar, saya menjalankan perintah berikut di terminal:

**Perintah yang dijalankan:**

```powershell
git log --all --no-merges --author="10241002-Putri" --stat
```

Untuk memeriksa jumlah perubahan pada _commit_ yang dipilih, saya menggunakan perintah:

```powershell
git show --shortstat --oneline 2a08ba1
```

**Hasil pemeriksaan:**

```text
2a08ba1 frontend dashboard admin

4 files changed, 4134 insertions(+), 294 deletions(-)
```

Menurut saya, _commit_ ini sebaiknya dipecah menjadi beberapa bagian karena perubahan yang dikerjakan tidak semuanya berada pada bagian yang sama. _Controller_ dan _routing_ digunakan untuk mengatur proses serta akses ke _dashboard_, sementara file Blade dan CSS digunakan untuk membangun tampilan _dashboard_ admin. Walaupun semuanya dibutuhkan untuk menghasilkan _dashboard_ yang utuh, pengerjaannya bisa disimpan dalam _commit_ yang berbeda sesuai bagian yang diselesaikan. Dengan begitu, riwayat _commit_ tidak hanya menunjukkan hasil akhir dari pembuatan _dashboard_, tetapi juga menggambarkan tahapan pengembangannya. Selain itu, setiap _commit_ bisa memiliki pesan yang lebih spesifik sesuai perubahan yang dilakukan.