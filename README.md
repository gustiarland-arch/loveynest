# LoveyNest

*A little space for two hearts.*

Aplikasi web progresif (PWA) untuk dua orang pasangan: tugas pribadi dan bersama, kalender, chat langsung, pengingat, mood harian, jurnal dan surat, album foto, dan halaman ringkasan, dalam ruang privat yang dijaga oleh backend.

Stack: PHP 8.3+ (XAMPP 8.4/8.5), Laravel 13, MariaDB, Inertia 3, Vue 3, Tailwind CSS 4, Vite 8.

## Menjalankan

Panduan lengkap untuk XAMPP: [docs/instalasi-xampp.md](docs/instalasi-xampp.md). Ringkasnya:

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # lalu isi DB_* di .env
php artisan migrate
php artisan usandus:vapid        # kunci push, sekali saja
php artisan serve                # http://localhost:8000
php artisan schedule:work        # jendela kedua: pengingat, push, backup harian
# opsional, chat langsung: php artisan usandus:realtime, lalu di jendela ketiga: php artisan reverb:start
```

Data contoh (hanya untuk lokal): `USANDUS_DEMO_PASSWORD=pilih-sendiri php artisan db:seed --class=DevSeeder`.

Lupa password: `php artisan usandus:reset-password` (belum ada email).

## Dokumentasi

- [docs/instalasi-xampp.md](docs/instalasi-xampp.md): instalasi, akses dari HP, push, backup dan restore.
- [docs/pwa-push-jaringan-lokal.md](docs/pwa-push-jaringan-lokal.md): HTTPS lokal dan pengujian push di perangkat.

## Fitur kenangan

- **Mood**: satu pilihan per hari (5 tingkat + catatan). Hanya kamu dan pasangan yang aktif yang bisa melihatnya.
- **Jurnal dan surat**: jurnal pribadi (My Space) atau berdua (Our Space). Surat selalu terkirim ke pasangan, isinya tidak muncul di layar kunci notifikasi, dan hanya penulisnya yang boleh mengubah atau menghapusnya.
- **Album foto**: album pribadi atau berdua; unggah beberapa foto sekaligus, tampilan penuh dengan geser. Foto disimpan privat, diubah ulang tanpa metadata, dan diberikan hanya lewat rute yang mengecek akses.
- **Ringkasan**: kartu di beranda dan halaman `/widget` (mood, foto terbaru, surat, acara berikutnya, chat). Ini halaman di dalam aplikasi yang bisa dijadikan pintasan, bukan widget bawaan layar utama ponsel; PWA tidak bisa membuatnya.
- **Photobooth** (`/photobooth`, juga ada di Kenangan): 11 tema siap pakai, 5 susunan (strip 4, trio 3, duo 2, kotak 4, kolase 6), foto persegi diambil gantian dari HP masing-masing (atau satu HP berdua) dengan hitung mundur. Foto asli disimpan privat dan efeknya (filter, cahaya, haluskan kulit, stiker, teks) diproses di browser, tidak dikirim ke layanan luar. Hasil disimpan di album bersama "Photobooth" dan bisa diedit ulang 24 jam; foto asli lalu dibersihkan otomatis oleh `php artisan photobooth:prune` (sudah terjadwal lewat `schedule:run`). Kamera langsung butuh HTTPS; tanpa itu tersedia tombol ambil foto lewat kamera HP.
- **Chat langsung**: lewat Reverb (WebSocket) bila diaktifkan; polling tetap jadi cadangan.

## Fitur berdua

Semua ada di menu **Berdua** (`/berdua`) dan ringkasannya di beranda:

- **Hari jadian dan momen**: hitung hari bersama, hitung mundur ulang tahun dan tanggal penting, serta hitungan hari beruntun saat keduanya aktif. Tanggal jadian diisi di Pengaturan atau halaman momen.
- **Pertanyaan harian**: satu pertanyaan untuk berdua; jawaban pasangan terbuka setelah kamu menjawab (tidak dikirim ke browser sebelumnya).
- **Bucket list dan wishlist**: pribadi atau berdua, bisa dicoret.
- **Kapsul waktu**: pesan yang disegel sampai tanggal tertentu; isinya tidak dikirim ke siapa pun sebelum tanggal itu.
- **Voting berdua** dan **ide kencan acak** (bisa disimpan ke bucket list).
- **Kirim rasa**: peluk, kangen, cium, atau semangat dengan satu ketukan, lengkap dengan notifikasi untuk pasangan.
- **Chat**: balas pesan dan reaksi emoji.

Data fitur ini selalu terikat ke ruang pasangan; akun lain mendapat 404.

## Fitur tampilan

- Foto profil (Pengaturan → Ganti foto): diunggah ke penyimpanan privat, diubah ulang jadi JPEG persegi tanpa metadata, dan hanya bisa dilihat pemilik dan pasangannya.
- Tema terang, gelap, atau ikut HP (Pengaturan → Tampilan).
- Pemilih tanggal dan jam bergaya lembar bawah.

## Catatan

Push notification ke HP nyata dan jalannya di XAMPP Windows belum pernah diuji. Chat langsung hanya jalan saat `reverb:start` aktif dan halaman dibuka lewat HTTP; selain itu chat memakai polling. Pesan tersimpan sebagai teks biasa di database.
