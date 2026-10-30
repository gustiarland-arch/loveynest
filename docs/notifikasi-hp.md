# Notifikasi di HP (muncul walau web tertutup)

Notifikasi memakai Web Push. Syaratnya ada lima, dan semuanya satu kali pengaturan.

## Di server (cPanel)
1. **HTTPS aktif** dan `APP_URL=https://alamat-kamu` di file `.env`.
2. **Kunci VAPID.** Di Terminal cPanel, dari folder aplikasi:
   ```
   php artisan usandus:vapid
   php artisan config:clear
   ```
3. **Kirim langsung.** Di `.env` isi `QUEUE_CONNECTION=sync`, lalu `php artisan config:clear`. Pesan chat lalu terkirim sebagai notifikasi seketika, tanpa menunggu antrean.
4. **Cron tiap menit** (cPanel > Cron Jobs, pilih "Once Per Minute"), untuk pengingat acara dan tugas:
   ```
   /usr/local/bin/php /home/loveynes/us-and-us/artisan schedule:run >> /dev/null 2>&1
   ```
   Kalau jalur PHP berbeda, pakai hasil `which php` di Terminal.
5. Periksa semuanya sekaligus: `php artisan usandus:cek-notifikasi`. Tiap baris berawalan [OK] atau [GAGAL] dengan petunjuk perbaikannya.

## Di tiap HP
- **iPhone (iOS 16.4 ke atas):** buka lewat Safari, ketuk Bagikan, **Tambah ke Layar Utama**. Buka LoveyNest dari ikon itu (bukan dari Safari), lalu Pengaturan > Notifikasi > **Aktifkan**. Di iPhone, notifikasi hanya jalan setelah dipasang seperti ini.
- **Android (Chrome):** Pengaturan > Notifikasi > **Aktifkan**. Memasang ke layar utama membantu tapi tidak wajib.
- Izinkan notifikasi saat browser bertanya. Pasangan juga harus mengaktifkannya di HP-nya sendiri.
- Tombol "Kirim notifikasi uji" di Pengaturan memastikan HP menerimanya.

## Kalau tidak muncul
- Jalankan `php artisan usandus:cek-notifikasi` dan ikuti baris [GAGAL].
- Jam tenang di Pengaturan menahan notifikasi pada jam yang kamu pilih.
- Chat yang dibisukan tidak mengirim notifikasi.
- Hemat daya iPhone atau Android yang mematikan aplikasi latar bisa menunda notifikasi.
