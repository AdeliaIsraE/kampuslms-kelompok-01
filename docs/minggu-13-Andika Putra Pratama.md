# Minggu 13 — READ: Riwayat Git Kelompok

**Nama:** Andika
**NIM:** 10241010
**Repo:** kampuslms-kelompok-01

---

## 1. Berapa commit atas nama saya? Bandingkan dengan anggota lain.

Perintah yang dijalankan:

```
git shortlog -sne --all --no-merges
```

Keluaran:

```
    (base) PS C:\Users\User\Documents\proweb\kampuslms-kelompok-01> git shortlog -sne --all --no-merges
    54  AdeliaIsraE <10241004@student.itk.ac.id>
    32  Andikaputra <andikaxxcold@gmail.com>
    26  adlrnta <10241003@student.itk.ac.id>
    20  10241002-Putri <10241002@student.itk.ac.id>
    11  ayya <alyaauralia05@gmail.com>
     3  AndikaPutra <111578739+andikaputra9@users.noreply.github.com>
     3  Renata_1003 <10241003@student.itk.ac.id>
     2  alyaurlia <10241008@student.itk.ac.id>
     1  Achmad Zaki Zaidan <ahmadzakizaidan@gmail.com>
```

**Jawaban saya:**

Berdasarkan hasil git shortlog, saya memiliki total 35 commit yang terdiri dari 32 commit dengan nama Andikaputra dan 3 commit dengan nama AndikaPutra. Jika dibandingkan dengan anggota lain, jumlah commit saya berada di posisi kedua setelah AdeliaIsraE yang memiliki 54 commit.

---

## 2. Adakah minggu di mana saya tidak punya commit sama sekali?

Perintah (per minggu kalender ISO):

```
git log --all --no-merges --author="Andikaputra" --author="AndikaPutra" --date=format:"%Y-W%V" --pretty="%ad" | Group-Object | Select-Object Name, Count
```

Keluaran:

```
(base) PS C:\Users\User\Documents\proweb\kampuslms-kelompok-01> git log --all --no-merges --author="Andikaputra" --author="AndikaPutra" --date=format:"%Y-W%V" --pretty="%ad" | Group-Object | Select-Object Name, Count

Name     Count
----     -----
2026-W46     2
2026-W45     2
2026-W44     1
2026-W43     1
2026-W41    12
2026-W40     1
2026-W39     2
2026-W38     1
2026-W37    11
2026-W36     2
```

Total 35 commit sama dengan hasil no 1. Minggu kalender yang tidak muncul: W42.

**Jawaban saya:**

Berdasarkan riwayat commit, terdapat satu minggu ketika saya tidak melakukan commit, yaitu minggu kalender ISO ke-42 (12–18 Oktober 2026). Pada minggu tersebut tidak terdapat perubahan yang tercatat atas nama saya di Git. Hal ini di karenakan adanya persiapan untuk UTS untuk persiapan UTS saya hanya menyiapkan jawaban untuk mempersiapkan presentasi.

---

## 3. Berapa persen pesan commit yang mengikuti Conventional Commits?

Perintah (PowerShell):

```
$pesan = git log --all --no-merges --author="Andikaputra" --author="AndikaPutra" --pretty="%s"; $pola = '^(feat|fix|refactor|docs|test|chore|style|perf|build|ci)(\(.+\))?!?: .+'; $sesuai = ($pesan | Where-Object { $_ -cmatch $pola }).Count; "{0} dari {1} = {2:N1}%" -f $sesuai, $pesan.Count, (100 * $sesuai / $pesan.Count)
```

Keluaran:

```
(base) PS C:\Users\User\Documents\proweb\kampuslms-kelompok-01> $pesan = git log --all --no-merges --author="Andikaputra" --author="AndikaPutra" --pretty="%s"; $pola = '^(feat|fix|refactor|docs|test|chore|style|perf|build|ci)(\(.+\))?!?: .+'; $sesuai = ($pesan | Where-Object { $_ -cmatch $pola }).Count; "{0} dari {1} = {2:N1}%" -f $sesuai, $pesan.Count, (100 * $sesuai / $pesan.Count)
14 dari 35 = 40.0%
```

Pesan yang tidak sesuai pola (21 commit):

```
(base) PS C:\Users\User\Documents\proweb\kampuslms-kelompok-01> $pesan | Where-Object { $_ -cnotmatch $pola }
assignmentpolicy dan coursepolicy
fix demo account seeder
mengerjakan build no 4 week 6
mengerjakan build 1 dan 2 hanya endpoint 1 sampai 6 di week 6
mengerjakan build no 1 sampai 3
Form tambah/edit mata kuliah dengan  @error
update build minggu 4 bagian matakuliah
database
anu backend2
anu backend
Add files via upload
Add files via upload
Add files via upload
update /tentang
md baru
nambah env example
update minggu 1
update week1
update nama asset menjadi image dan perbaiki minggu 1
Update README dan tambah route /tentang untuk info kelompok
Submit tugas minggu 1 - Andika Putra Pratama
```

Catatan: pola hanya mengecek bentuk pesan (awalan jenis + titik dua), bukan apakah isinya informatif.

**Jawaban saya:**

Berdasarkan hasil pemeriksaan, terdapat 14 dari 35 commit yang mengikuti pola Conventional Commits, sehingga persentasenya sebesar 40%. Sementara itu, 21 commit lainnya belum mengikuti format tersebut. Kesalahan yang paling sering saya lakukan adalah penggunaan pesan commit yang terlalu umum, seperti update, database, anu backend, dan Add files via upload. Pesan seperti ini kurang menjelaskan perubahan yang dilakukan. Hasil 40% ini menunjukkan bahwa saya masih perlu memperbaiki kebiasaan dalam menulis pesan commit agar riwayat perubahan proyek lebih mudah dipahami.

---

## 4. Satu commit saya yang pesannya buruk, dan seharusnya bagaimana

Perintah (PowerShell):

```
git log --all --no-merges --author="Andikaputra" --author="AndikaPutra" --format="%h | %s"
git show --stat --oneline 74210bd
git show --format=fuller 74210bd
```

**Commit asli:**

```
Hash: 74210bd
Pesan: assignmentpolicy dan coursepolicy
```

**Seharusnya:**

```
feat(auth): perbarui otorisasi Course dan Assignment
```

Pesan commit asli kurang jelas karena hanya menyebutkan nama policy tanpa menjelaskan perubahan yang dilakukan. Seharusnya pesan commit menggunakan format Conventional Commits dan menjelaskan bahwa perubahan tersebut berkaitan dengan pembaruan aturan otorisasi pada Course dan Assignment.

---

## 5. Commit terbesar saya (`git log --stat`). Apakah seharusnya dipecah?

Perintah (PowerShell):

```
git log --all --no-merges --author="Andikaputra" --author="AndikaPutra" --shortstat
git show --name-status --format=fuller 11fff174
```

Keluaran:

```
commit 11fff17
database

25 files changed, 824 insertions(+), 105 deletions(-)
```

**Jawaban saya:**

Berdasarkan hasil pemeriksaan `git log --stat`, commit terbesar saya adalah commit dengan hash 11fff17 dan pesan database. Commit tersebut mengubah 25 berkas, dengan 824 baris penambahan dan 105 baris penghapusan.

Perubahan tersebut mencakup beberapa bagian, yaitu Model, Factory, Migration, dan Seeder untuk fitur seperti User, Course, Assignment, Submission, dan Grade. Menurut saya, commit ini sebaiknya dipecah menjadi beberapa commit berdasarkan bagian atau fungsinya. Misalnya, perubahan Migration dipisahkan dari Model dan Factory. Dengan begitu, riwayat Git menjadi lebih rapi, mudah dipahami, dan lebih mudah diperiksa apabila terjadi kesalahan.