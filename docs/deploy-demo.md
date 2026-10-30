# Deploy untuk demo

Dua jalur. Pilih **A** bila ingin tercepat, **B** bila ingin alamat tetap di internet.

Dalam kedua jalur, akun demo dibuat sendiri lewat halaman Daftar (dua akun, lalu kode undangan). `DevSeeder` sengaja hanya jalan di `APP_ENV=local`, jadi tidak ikut terbawa ke server.

## A. Dari laptop sendiri + HTTPS gratis (±15 menit)

Aplikasi tetap jalan di XAMPP (ikuti [instalasi-xampp.md](instalasi-xampp.md) sampai bagian 5), lalu diberi alamat HTTPS publik sementara lewat Cloudflare Tunnel.

Yang perlu disiapkan: laptop yang tetap menyala selama demo, akun Cloudflare gratis, dan `cloudflared` terpasang.

```powershell
php artisan serve --port=8000
cloudflared tunnel --url http://localhost:8000
```

Salin alamat `https://xxxx.trycloudflare.com` yang muncul, isi `APP_URL=` di `.env` dengan alamat itu, lalu `php artisan config:clear`. Karena HTTPS, PWA bisa dipasang dan push notification bisa dicoba. Alamat berganti tiap tunnel dijalankan ulang.

Chat tetap memakai mode berkala di HTTPS (lihat bagian 9 di instalasi-xampp.md).

## B. VPS kecil (alamat tetap, ±1 jam)

Yang perlu disiapkan:
1. VPS Ubuntu 22.04/24.04, 1 GB RAM cukup (sekitar Rp50–100 ribu per bulan).
2. Nama domain, dengan record A ke IP VPS.
3. Akses SSH ke VPS.

Langkah di VPS:

```bash
sudo apt update && sudo apt install -y caddy mariadb-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-gd php8.3-gmp php8.3-intl php8.3-zip php8.3-bcmath composer nodejs npm unzip
sudo mysql -e "CREATE DATABASE usandus CHARACTER SET utf8mb4; CREATE USER 'usandus'@'localhost' IDENTIFIED BY 'GANTI-PASSWORD-KUAT'; GRANT ALL ON usandus.* TO 'usandus'@'localhost';"
```

Unggah dan ekstrak `us-and-us.zip` ke `/var/www/us-and-us`, lalu:

```bash
cd /var/www/us-and-us
composer install --no-dev --optimize-autoloader
npm install && npm run build
cp .env.example .env && php artisan key:generate
```

Edit `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domainmu`, `DB_*` sesuai di atas. Lalu:

```bash
php artisan migrate --force
php artisan usandus:vapid
php artisan config:cache && php artisan route:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Caddy (`/etc/caddy/Caddyfile`) memberi sertifikat HTTPS otomatis:

```
domainmu {
    root * /var/www/us-and-us/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

`sudo systemctl reload caddy`. Agar pengingat dan push berjalan, tambahkan cron: `* * * * * cd /var/www/us-and-us && php artisan schedule:run >> /dev/null 2>&1`.

## Soal Vercel dan hosting gratis lain

- **Vercel: tidak cocok.** PHP tidak didukung resmi (hanya lewat runtime komunitas), filesystem hanya-baca sehingga foto, sesi, dan log tidak bisa disimpan, tidak ada MariaDB, tidak ada cron untuk pengingat, dan tidak ada proses panjang untuk chat langsung.
- **Render/Railway/Fly (Docker gratis):** disk sementara, foto hilang saat restart; database gratis umumnya Postgres atau kedaluwarsa, sedangkan aplikasi ini memakai MariaDB; layanan gratis tidur bila sepi. Bisa untuk melihat-lihat, kurang pas untuk demo yang andal. Dockerfile belum saya buat karena tidak bisa saya uji di sini.
- **Oracle Cloud Always Free:** VM gratis permanen yang bisa dipakai persis seperti jalur B. Syaratnya kartu untuk verifikasi, dan kapasitas kadang penuh.
- **Paling realistis untuk demo: jalur A** (laptop + Cloudflare Tunnel), gratis dan memakai database serta foto di laptop sendiri.

## Batas yang jujur

- Panduan ini belum pernah dijalankan di VPS atau tunnel nyata; saya tidak punya akses ke hosting maupun akun Anda. Bila ada langkah yang gagal, kirim pesan galatnya.
- Hosting bersama murah (cPanel) sering tidak cocok: butuh PHP 8.3, akses terminal, dan cron tiap menit.
- Push ke HP nyata belum pernah diuji; coba di demo dan laporkan hasilnya.
- Data ada di server itu sendiri; atur backup dengan `php artisan usandus:backup`.
