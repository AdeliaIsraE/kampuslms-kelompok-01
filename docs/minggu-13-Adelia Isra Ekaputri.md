## 13.3 - READ - Baca riwayat kelompok Anda

Nama: Adelia Isra Ekaputri
NIM: 10241004

#### 1. Berapa commit atas nama Anda? Bandingkan dengan anggota lain.    
Jawaban:
<img src="image/Bukti Commit 1004.png">

Berdasarkan hasil perintah ```git shortlog -sn```, terdapat 115 commit atas nama saya, yaitu ```AdeliaIsraE```. Jika dibandingkan dengan anggota kelompok yang lain, saya memiliki jumlah commit terbanyak. Hal ini disebabkan karena saya menggarap role frontend dan backend secara bersamaan.

#### 2. Adakah minggu di mana Anda tidak punya commit sama sekali?   
Jawaban: Ada minggu dimana saya tidak memiliki commit sama sekali, yaitu pada Minggu 8. Hal ini disebabkan karena minggu tersebut merupakan minggu UTS.

#### 3. Berapa persen pesan commit yang mengikuti Conventional Commits.
Jawaban: Dari total 156 commits yang ada pada repository github, ada 98 atau 62,8% commit yang tidak mengikuti Conventional Commits.

#### 4. Temukan satu commit Anda sendiri yang pesannya buruk. Tulis ulang seharusnya bagaimana.
Jawaban: Commit terburuk pada akun AdeliaIsraE adalah ```5e789b3```, yang di buat pada tanggal 9 September 2026, yang berjudul ```jujur aku lupa apa yang ku modified```
<img src="image/Commit terburuk 1004.png">
Commit ini sebenarnya berisi penghapusan method tentang() di app/Http/Controllers/Coursecontroller.php, yaitu 6 baris yang mengembalikan view('tentang'). Jadi, commit seharusnya tertulis ```refactor: hapus method tentang() dari CourseController```

#### 5. ```git log --stat``` — temukan commit terbesar Anda. Apakah seharusnya dipecah?
Jawaban: Commit terbesar pada akun AdeliaIsraE merupakan ```8fb9b64```, yang di buat pada tanggal 8 Oktober 2026, yang berjudul ```css```. Commit ini mengubah 28 file dengan 1.366 baris ditambah dan 981 baris dihapus, total sekitar 2.350 baris. Saya mengukur dari jumlah baris yang berubah, hanya untuk commit non-merge.    
Menurut saya commit ini masih bisa di pecah karena commit ini hanya memisahkan style dari file-file blade ke folder-folder dan file-file css.