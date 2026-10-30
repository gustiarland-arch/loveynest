# 05 — PWA & Push Notification di Jaringan Lokal

## 1. Fakta yang membatasi

1. Service Worker, instalasi PWA, dan Push API hanya aktif di **secure context**: `https://…` dengan sertifikat tepercaya, atau `http://localhost` / `http://127.0.0.1`.
2. `http://192.168.x.x:8000` dari HP **bukan** secure context. Halaman tetap bisa dibuka dan dipakai (login, tugas, chat polling), tetapi tombol "Aktifkan notifikasi" akan menampilkan penjelasan bahwa perangkat ini membutuhkan HTTPS.
3. Push tidak dikirim langsung dari server Anda ke HP. Server mengirim ke **push service** milik vendor browser, yang membutuhkan internet:
   - Chrome/Edge/Android → Firebase Cloud Messaging (`fcm.googleapis.com`)
   - Firefox → Mozilla autopush (`updates.push.services.mozilla.com`)
   - Safari macOS/iOS → Apple (`web.push.apple.com`)
   Jadi komputer server dan HP harus sama-sama terhubung internet, meski aplikasinya hanya di jaringan lokal.
4. iOS/iPadOS: Web Push hanya bekerja (iOS 16.4+) bila aplikasi **ditambahkan ke Layar Utama** lalu dibuka dari ikon itu. Di tab Safari biasa, push tidak tersedia.
5. Mode hemat baterai, mode fokus, atau pengaturan notifikasi OS dapat menunda atau menahan push. Service Worker yang terdaftar **tidak** membuktikan push sampai.

## 2. Tiga jalur pengujian (pilih salah satu)

### Jalur A — HTTPS lokal dengan mkcert (paling mendekati kondisi nyata, direkomendasikan)

1. Pasang [mkcert](https://github.com/FiloSottile/mkcert) di komputer XAMPP, jalankan `mkcert -install`.
2. Buat sertifikat untuk IP LAN dan nama lokal: `mkcert 192.168.1.10 usandus.local localhost 127.0.0.1`.
3. Pasang sertifikat di Apache XAMPP (**belum diverifikasi di XAMPP**; konfigurasi standar Apache 2.4):
   - Salin dua file hasil mkcert ke `C:\xampp\apache\conf\ssl\usandus.pem` dan `usandus-key.pem`.
   - Pastikan di `httpd.conf` baris `LoadModule ssl_module`, `LoadModule rewrite_module`, dan `Include conf/extra/httpd-ssl.conf` aktif (bawaan XAMPP sudah aktif).
   - Tambahkan ke `conf/extra/httpd-vhosts.conf`:

     ```apache
     <VirtualHost *:443>
         ServerName 192.168.1.10
         ServerAlias usandus.local localhost
         DocumentRoot "C:/xampp/htdocs/us-and-us/public"
         SSLEngine on
         SSLCertificateFile "conf/ssl/usandus.pem"
         SSLCertificateKeyFile "conf/ssl/usandus-key.pem"
         <Directory "C:/xampp/htdocs/us-and-us/public">
             AllowOverride All
             Require all granted
         </Directory>
     </VirtualHost>
     ```
   - Ubah `.env`: `APP_URL=https://192.168.1.10` dan `SESSION_SECURE_COOKIE=true`, lalu restart Apache. Izinkan port 443 di Windows Firewall untuk jaringan Private.
4. Pasang **root CA** mkcert (`mkcert -CAROOT` → `rootCA.pem`) di HP:
   - Android: Pengaturan → Keamanan → Enkripsi & kredensial → Pasang sertifikat → Sertifikat CA. Chrome Android memercayai CA pengguna.
   - iOS: kirim `rootCA.pem` ke HP, pasang profilnya, lalu aktifkan di Pengaturan → Umum → Mengenai → Pengaturan Kepercayaan Sertifikat.
5. Buka `https://192.168.1.10` di HP.

Risiko: root CA mkcert dapat menerbitkan sertifikat untuk domain apa pun dan dipercaya HP Anda. Jangan bagikan file `rootCA-key.pem`, dan hapus CA dari HP setelah selesai bila tidak diperlukan.

### Jalur B — Flag Chrome (cepat, hanya untuk uji di Android/desktop Chrome)

Di Chrome Android buka `chrome://flags/#unsafely-treat-insecure-origin-as-secure`, isi `http://192.168.1.10:8000`, aktifkan, restart Chrome. Origin tersebut diperlakukan sebagai secure context. Cocok untuk uji cepat, tidak mewakili kondisi pengguna nyata, dan tidak tersedia di iOS.

### Jalur C — Tailscale (HTTPS asli tanpa membuka ke internet publik)

`tailscale serve` memberikan sertifikat valid untuk `nama-mesin.tailnet.ts.net` yang hanya dapat diakses perangkat di tailnet Anda. Butuh akun Tailscale dan aplikasi Tailscale di HP. Ini bukan deployment publik, tetapi lalu lintas keluar dari LAN, jadi hanya dipakai bila Anda menyetujuinya.

## 3. Matriks uji perangkat (diisi oleh Anda; tidak akan diisi dengan karangan)

| Perangkat / Browser | Jalur | Instal PWA | Izin diberikan | Push "uji" diterima saat app terbuka | Push diterima saat app ditutup | Klik notifikasi membuka halaman benar | Status |
|---|---|---|---|---|---|---|---|
| Android – Chrome | A/B | | | | | | Belum diuji |
| Android – Samsung Internet | A | | | | | | Belum diuji |
| Android – Firefox | A | | | | | | Belum diuji |
| iPhone – Safari (Layar Utama, iOS ≥ 16.4) | A/C | | | | | | Belum diuji |
| Windows – Chrome/Edge (`localhost`) | — | | | | | | Belum diuji |
| Windows – Firefox (`localhost`) | — | | | | | | Belum diuji |

Prosedur per baris: login → Profil → Notifikasi → "Aktifkan notifikasi" → izinkan → "Kirim notifikasi uji" → tutup aplikasi/tab → minta pasangan mengirim pesan chat → catat apakah notifikasi muncul dan berapa lama jedanya.

Perilaku yang perlu diketahui saat menguji:
- Push pesan chat dikirim paling banyak **sekali per menit** per penerima; pesan berikutnya dalam rentang itu tidak memicu push baru (notifikasi lama diganti, bukan ditumpuk).
- Isi pesan **tidak** ditampilkan di notifikasi kecuali penerima menyalakan "Tampilkan isi pesan di notifikasi".
- Bila halaman chat sedang terbuka dan terlihat, push pesan baru tidak ditampilkan (pesan sudah terlihat lewat polling).
- Logout menghapus langganan perangkat itu, sehingga akun berikutnya di HP yang sama tidak menerima notifikasi akun sebelumnya.

## 4. Yang harus berjalan agar push & pengingat terkirim

Di komputer XAMPP, selain Apache & MySQL, cukup **satu** proses:

```bash
php artisan schedule:work
```

Setiap menit proses ini menjalankan `reminders:dispatch` (kirim pengingat yang jatuh tempo) dan `queue:work --stop-when-empty` (kirim push yang mengantre). Alternatifnya Windows Task Scheduler menjalankan `php artisan schedule:run` tiap menit (lihat panduan instalasi §8).

Tanpa proses ini, notifikasi in-app untuk aktivitas pasangan tetap muncul, tetapi push dan pengingat tidak terkirim. Halaman Pengaturan menampilkan kapan penjadwal terakhir berjalan, dan berubah menjadi peringatan bila lebih dari 5 menit.

Aturan pengiriman pengingat:
- Setiap baris pengingat diklaim dengan `UPDATE … WHERE status='pending'`, sehingga dua penjadwal yang berjalan bersamaan tetap mengirim sekali (diuji dengan 3 proses paralel).
- Pengingat yang terlambat lebih dari 6 jam (misalnya komputer mati semalaman) dilewati, tidak dikirim beruntun.
- Sebelum mengirim, akses dicek ulang: anggota yang sudah keluar dari ruang dan tugas yang sudah selesai tidak diingatkan.
