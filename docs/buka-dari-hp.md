# Menyalakan otomatis dan membuka dari HP

Panduan singkat agar LoveyNest selalu siap tanpa harus membuka jendela tiap hari, dan bisa dibuka dari HP pasangan.

## 1. Menyala sendiri saat komputer hidup

1. Pastikan `jalankan.bat` sudah pernah berhasil sekali (database `usandus` sudah ada).
2. Klik dua kali **`pasang-otomatis.bat`**. Jawab `Y` bila ingin langsung menyalakannya sekarang.
3. Mulai sekarang, setiap kali Windows dinyalakan dan kamu masuk ke akun Windows:
   - MySQL XAMPP dinyalakan bila belum jalan (XAMPP Control Panel tidak perlu dibuka).
   - Server LoveyNest menyala di jendela kecil di taskbar.
   - Pengingat dan notifikasi (`schedule:work`) ikut menyala di jendela kecil kedua.
4. Jangan tutup dua jendela kecil itu. Menutupnya berarti aplikasi berhenti sampai komputer dinyalakan ulang atau `mulai-otomatis.bat` diklik.

Untuk membatalkan: klik dua kali **`hapus-otomatis.bat`**.

Catatan:
- Server hanya menyala setelah kamu masuk (login) ke Windows, bukan di layar kunci awal.
- Bila komputer tertidur (sleep), HP tidak bisa membuka aplikasi. Atur di Settings > System > Power agar komputer tidak tidur saat dicolok.
- Bila folder `us-and-us` dipindah, jalankan `pasang-otomatis.bat` sekali lagi.

## 2. Membuka dari HP

1. Klik dua kali **`buka-akses-hp.bat`** dan pilih **Yes** saat Windows meminta izin Administrator. Cukup sekali.
   - Skrip ini membuka port 8000 (aplikasi) dan 8080 (chat langsung) di Windows Firewall, khusus jaringan Pribadi.
   - Bila WiFi rumah masih tercatat **Publik**, skrip memberi peringatan. Ubah di Settings > Network and internet > WiFi > nama WiFi > **Private network**.
2. Layar akan menampilkan alamat seperti `http://192.168.1.7:8000`. Catat alamat itu.
3. Sambungkan HP ke **WiFi yang sama** dengan komputer, lalu buka alamat itu di Chrome (Android) atau Safari (iPhone).
4. Agar mudah dibuka lagi: Chrome menu titik tiga > **Tambahkan ke layar utama**, atau Safari tombol bagikan > **Tambahkan ke Layar Utama**.

Alamat IP bisa berubah bila router dinyalakan ulang. Bila HP tiba-tiba tidak bisa membuka, jalankan `buka-akses-hp.bat` lagi untuk melihat alamat terbaru. Agar tetap, atur "DHCP reservation" untuk komputer ini di pengaturan router.

## 3. Bila HP tidak bisa membuka

| Yang terlihat di HP | Penyebab | Yang dilakukan |
|---|---|---|
| `ERR_CONNECTION_REFUSED` | Server belum menyala | Cek jendela "LoveyNest" di taskbar komputer, atau klik `mulai-otomatis.bat` |
| Lama lalu `ERR_CONNECTION_TIMED_OUT` | Firewall atau WiFi Publik | Jalankan `buka-akses-hp.bat`, ubah WiFi ke Private |
| "Sedang offline" | HP tidak sampai ke komputer | HP harus di WiFi yang sama, bukan data seluler |
| Membuka `localhost:8000` di HP | `localhost` artinya HP itu sendiri | Pakai alamat `192.168...` dari skrip |

## 4. Yang masih terbatas

Dibuka lewat `http://192.168...`, aplikasi bisa dipakai penuh (login, tugas, chat, album). Tetapi browser HP menganggap alamat ini tidak aman, jadi:
- Notifikasi push ke HP tidak bisa diaktifkan.
- "Tambahkan ke layar utama" hanya membuat pintasan, bukan aplikasi yang bisa dibuka tanpa koneksi.

Untuk notifikasi di HP dibutuhkan HTTPS. Caranya ada di `docs/pwa-push-jaringan-lokal.md` (jalur A mkcert atau jalur C Tailscale).
