# QuizHell

**Quiz serius, gangguannya tidak.**

QuizHell adalah aplikasi web untuk membuat dan membagikan quiz dengan sentuhan prank. Creator menyiapkan soal, memilih tingkat Rage, lalu membagikan link. Player cukup memasukkan nickname untuk bermain tanpa membuat akun.

Di tengah pengerjaan, Rage System menghadirkan tombol yang menghindar, countdown palsu, pertanyaan jebakan, serta gambar dan suara acak. Efek tersebut terpisah dari jawaban, perhitungan skor, dan timer asli. Quiz tetap dapat diselesaikan.

## Fitur utama

- **Akun creator** — registrasi, login, dan pengelolaan quiz milik sendiri.
- **Quiz builder** — soal Multiple Choice dan True / False, urutan soal, timer opsional, serta pilihan Rage Level.
- **Publish dan berbagi** — simpan draft, publish quiz, bagikan link, atau hentikan akses melalui unpublish.
- **Dashboard visual** — tampilan bento dengan statistik quiz, attempt selesai, serta filter status dan Rage Level.
- **Pengerjaan tanpa akun** — player menggunakan nickname, menjawab soal berurutan, dan melihat hasil setelah selesai.
- **Results & Analytics** — daftar hasil, rata-rata skor, completion rate, distribusi kategori skor, dan soal tersulit.
- **Export Excel** — unduh hasil player dan ringkasan quiz dalam workbook `.xlsx`.
- **Tema gelap dan terang** — tampilan responsif untuk desktop dan mobile dengan preferensi tema yang tersimpan.

## Rage System

Creator dapat memilih **Mild**, **Annoying**, atau **Hell**. Level menentukan frekuensi gangguan: setiap empat, dua, atau satu soal, dimulai dari soal pertama. Jenis event yang sama tidak dipilih pada dua giliran event berturut-turut.

| Efek | Pengalaman player |
|---|---|
| Moving Next / Submit Button | Tombol melompat menjauhi pointer hingga tiga kali, lalu dapat diklik. |
| Random Confirmation Taunt | Pesan iseng muncul setelah Next/Submit dan menunggu tombol **Lewati**. |
| Fake Countdown | Hitung mundur palsu selama 3, 5, atau 10 detik dengan efek merah dan suara, lalu mengungkap prank. |
| One Quick Question | Pertanyaan jebakan menunggu jawaban, kemudian memutar suara sebelum melanjutkan. Jawabannya tidak dinilai. |
| Fake Loading + Random Picture | Gambar acak tampil dalam popup besar sampai player menekan **Lewati**. |
| Random Question Background | Gambar latar acak bertahan selama soal tersebut aktif. |
| Dramatic Score Result | Hasil akhir mendapat reaksi visual dan suara sesuai kategori skor. |

Gambar dan sound effect dapat diganti melalui folder asset dan konfigurasi project. Media yang kosong atau gagal dimuat tidak menjadi syarat untuk melanjutkan quiz. Timer asli tetap berjalan selama efek berlangsung.

## Alur penggunaan

**Creator:** daftar atau login → buat quiz → isi soal dan pengaturan → simpan dan publish → bagikan link → lihat analytics atau export hasil.

**Player:** buka link → masukkan nickname → jawab soal dan hadapi Rage Events → lihat skor akhir.

Player wajib menjawab sebelum menekan Next atau Submit. Jika timer asli habis, attempt diselesaikan dengan jawaban kosong dihitung salah.

## Penilaian

Skor dihitung dari jumlah jawaban benar pada soal sungguhan:

```text
skor = max(1, round(jumlah benar / jumlah soal × 10))
```

| Kategori | Skor |
|---|---|
| Low | 1–4 |
| Mid | 5–7 |
| Good | 8–10 |

Pertanyaan prank dan interaksi Rage tidak memengaruhi skor. Statistik peserta menghitung attempt selesai; satu orang dapat bermain lebih dari sekali.

## Teknologi

QuizHell menggunakan **PHP native**, **MySQL**, **HTML**, **JavaScript ES modules**, dan **CSS lokal**. Halaman dirender di server, sedangkan interaksi builder, tema, audio, dan Rage System berjalan di browser.

Aplikasi tidak memerlukan framework, package Composer/npm saat runtime, atau proses build frontend. Export Excel dibuat menggunakan PHP `ZipArchive`. Playwright digunakan untuk pengujian browser.