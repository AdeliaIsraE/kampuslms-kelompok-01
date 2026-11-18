Nama: Adelia Cyntia Renata

NIM: 10241003

# READ — Baca riwayat kelompok Anda

### 1. Berapa commit atas nama Anda? Bandingkan dengan anggota lain.

Berikut adalah hasil pengecekan riwayat kontribusi menggunakan perintah `git shortlog`:

![Output git shortlog -sn --all](images/shortlog-nama.png)
![Terminal output from git shortlog -sn --all, listing commit counts by author name. AdeliaIsraE leads with 116 commits, followed by Renata_1003 with 50; the output also lists adlrnta with 28, Andikaputra with 36, AndikaPutra with 21, Putri with 32, Alya's three author names with 20 commits combined, and Achmad Zaki Zaidan with 1. The wider view is a command-line contribution summary for the group repository.](images/shortlog-nama.png)

![Terminal output from git shortlog -sne --all, listing commit counts alongside author names and email addresses. The entries show Renata_1003 and adlrnta associated with 10241003@student.itk.ac.id, accounting for 50 and 28 commits respectively. The wider view is a command-line contribution summary for the group repository, with authors distinguished by name and email.](images/shortlog-email.png)

Total commit atas nama aku adalah 78 commit dari keseluruhan 304 commit di repositori ini, atau sekitar 25,66%. Angka itu terbagi ke dalam dua nama penulis yang berbeda, yaitu `Renata_1003` sebanyak 50 commit dan `adlrnta` sebanyak 28 commit. Dengan jumlah tersebut, aku berada di urutan kedua terbanyak di kelompok. Kedua akun itu sebenarnya memakai alamat email yang sama (`10241003@student.itk.ac.id`), tetapi tercatat terpisah karena ada perbedaan pengaturan `user.name` pada Git lokal di perangkat yang aku gunakan atau sempat berubah saat konfigurasi ulang.

Jika dibandingkan dengan anggota kelompok lain, jumlah commit terbanyak dipegang oleh `AdeliaIsraE` dengan total 116 commit (38,16%), sehingga selisihnya denganku adalah 38 commit. Di bawahku ada Andika dengan total 57 commit, Putri dengan 32 commit, Alya dengan 20 commit, serta Achmad Zaki Zaidan dengan 1 commit. Perlu dicatat bahwa beberapa anggota juga tercatat dengan lebih dari satu nama di riwayat Git—seperti Andika yang terpecah menjadi `Andikaputra` (36) dan `AndikaPutra` (21), serta Alya yang tercatat di tiga nama berbeda (`ayya`, `alyaurlia`, dan `Alya Auralia_10241008`)—sehingga angka mentahnya harus digabung terlebih dahulu agar perbandingannya akurat.

Menurutku, angka 78 commit ini sudah cukup menggambarkan kontribusiku secara teknis di bagian penulisan kode, meskipun belum sepenuhnya mencerminkan keseluruhan proses kerja kelompok. Masih banyak pekerjaan lain yang tidak tercatat di log commit, seperti diskusi pembagian tugas, perancangan alur fitur, pengujian manual aplikasi, hingga membantu anggota lain saat mengalami kendala *merge* atau *debugging*. Ke depannya, satu hal yang pasti akan aku ubah adalah memastikan konfigurasi `git config user.name` dan `git config user.email` diatur secara konsisten sejak awal proyek agar seluruh riwayat kontribusiku tercatat rapi dalam satu identitas saja.