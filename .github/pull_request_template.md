## Apa yang diubah
(Jelaskan fitur, perbaikan, atau perubahan kode yang dilakukan)


## Kenapa
(Jelaskan alasan perubahan ini diperlukan dan masalah yang ingin diselesaikan)

## Cara menguji
1.
2.
(Tuliskan langkah-langkah untuk menguji perubahan)

## Daftar periksa
- [ ] Validasi ada di server-side
- [ ] Otorisasi diperiksa (`Gate::authorize()` dipanggil di controller)
- [ ] Tidak ada N+1 (diperiksa dengan Debugbar)
- [ ] Ada feature test untuk jalur sukses dan jalur ditolak
- [ ] Tidak ada `dd()`, `dump()`, atau `console.log` tertinggal
- [ ] `.env` tidak tersentuh

## Bagian yang dibantu AI
(sebutkan bagian mana dan apa yang Anda ubah dari keluaran aslinya)