# Instalasi di XAMPP (Windows)

> Catatan: panduan Windows (php.ini, Apache, Task Scheduler) belum dicoba di XAMPP asli. Jika ada langkah yang gagal, catat pesan errornya.

## 1. Prasyarat

| Perangkat lunak | Versi | Catatan |
|---|---|---|
| XAMPP | 8.4.x atau 8.5.x | Berisi PHP ≥ 8.3, Apache, MariaDB. XAMPP 8.2.x **tidak** didukung. |
| Composer | 2.x | https://getcomposer.org/download/ — saat instalasi, arahkan ke `C:\xampp\php\php.exe` |
| Node.js | 22 LTS | https://nodejs.org/ — hanya dibutuhkan untuk membangun aset frontend |
| Git | opsional | Untuk mengambil kode dari repositori |

Cek di Command Prompt / PowerShell:

```powershell
php -v          # harus 8.3 atau lebih baru
composer -V
node -v         # v22.x
npm -v
```

Jika `php` tidak dikenali, tambahkan `C:\xampp\php` ke PATH Windows.

## 2. Aktifkan ekstensi PHP

Buka `C:\xampp\php\php.ini`, pastikan baris berikut **tidak** diawali `;`:

```ini
extension=curl
extension=exif
extension=fileinfo
extension=gd
extension=gmp
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=sodium
extension=zip
```

Simpan, lalu restart Apache dari XAMPP Control Panel. Cek dengan `php -m`.

`gd` dan `exif` dipakai untuk foto profil dan album: foto diperkecil, diputar sesuai kamera, dan metadata (termasuk lokasi GPS) dibuang. `gmp` direkomendasikan oleh library push untuk perhitungan enkripsi. Bila tidak aktif, library mencatat peringatan di log. Push hanya diuji dengan `gmp` aktif.

## 3. Siapkan database

1. Jalankan **MySQL** dari XAMPP Control Panel.
2. Buka http://localhost/phpmyadmin → tab **SQL**, jalankan (ganti password dengan milik Anda):

```sql
CREATE DATABASE usandus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'usandus'@'localhost' IDENTIFIED BY 'ganti-dengan-password-kuat';
GRANT ALL PRIVILEGES ON usandus.* TO 'usandus'@'localhost';
FLUSH PRIVILEGES;
```

Memakai user `root` tanpa password juga bisa untuk percobaan, tetapi tidak disarankan.

## 4. Pasang aplikasi

Letakkan folder proyek, misalnya di `C:\xampp\htdocs\us-and-us` (atau di luar `htdocs`, lihat §6).

```powershell
cd C:\xampp\htdocs\us-and-us
composer install
copy .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
APP_URL=http://localhost:8000
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=usandus
DB_USERNAME=usandus
DB_PASSWORD=ganti-dengan-password-kuat
```

Lalu:

```powershell
php artisan migrate
npm install
npm run build
```

**Tanpa `artisan migrate`:** folder `database/migrations` memang hanya berisi berkas `.php`, itu resep pembuat tabel yang dijalankan oleh `php artisan migrate`. Bila lebih suka lewat phpMyAdmin, impor `database/schema.sql` ke database kosong (tab Import). Berkas itu berisi seluruh struktur tabel tanpa data dan mengisi tabel `migrations`, jadi `php artisan migrate` sesudahnya akan menjawab "Nothing to migrate". Setelah menambah migrasi baru di masa depan, berkas ini perlu dibuat ulang; `migrate` tetap jalur utama.

## 5. Jalankan

Cara paling sederhana (untuk komputer ini saja):

```powershell
php artisan serve
```

Buka http://localhost:8000.

## 6. Menjalankan lewat Apache XAMPP (opsional)

Yang boleh diakses browser hanya folder `public/`. **Jangan** mengarahkan Apache ke folder proyek utama, karena `.env`, log, dan backup akan ikut terbuka.

Tambahkan ke `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName usandus.local
    DocumentRoot "C:/xampp/htdocs/us-and-us/public"
    <Directory "C:/xampp/htdocs/us-and-us/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Pastikan `LoadModule rewrite_module modules/mod_rewrite.so` aktif di `httpd.conf`, restart Apache, dan ubah `APP_URL` di `.env`.

## 7. Akses dari HP di jaringan yang sama

1. Cari IP komputer: `ipconfig` → "IPv4 Address", misalnya `192.168.1.10`.
2. Jalankan server agar menerima koneksi dari LAN:

   ```powershell
   php artisan serve --host=0.0.0.0 --port=8000
   ```

   Saat Windows Firewall bertanya, izinkan untuk jaringan **Private** saja.
3. Ubah `APP_URL=http://192.168.1.10:8000` di `.env`.
4. Di HP (Wi-Fi yang sama) buka `http://192.168.1.10:8000`.

Lewat HTTP biasa semua fitur bisa dipakai, **kecuali** instalasi PWA dan push notification, karena browser hanya mengizinkannya di HTTPS. Cara menyiapkan HTTPS lokal ada di [05-pwa-push-jaringan-lokal.md](pwa-push-jaringan-lokal.md).

## 8. Push notification dan pengingat

1. Buat kunci VAPID sekali saja (tersimpan di `.env`, jangan dibagikan):

   ```powershell
   php artisan usandus:vapid
   ```

   Mengganti kunci (`--force`) membuat semua perangkat harus mengaktifkan notifikasi ulang.
2. Biarkan satu jendela terminal menjalankan penjadwal:

   ```powershell
   php artisan schedule:work
   ```

   Setiap menit penjadwal mengirim pengingat yang jatuh tempo dan mengosongkan antrean push. Tidak perlu `queue:work` terpisah.
3. Agar berjalan otomatis setelah komputer dinyalakan, buat tugas di **Windows Task Scheduler**: pemicu "At startup", ulangi setiap 1 menit tanpa batas, aksi `C:\xampp\php\php.exe` dengan argumen `artisan schedule:run` dan "Start in" folder proyek.

Halaman Pengaturan → Notifikasi menampilkan kapan penjadwal terakhir berjalan. Bila lebih dari 5 menit, tulisan berubah menjadi peringatan.

## 9. Chat langsung (realtime, opsional)

Tanpa langkah ini chat tetap jalan: aplikasi memeriksa pesan baru tiap beberapa detik (lencana "Berkala" di atas chat). Dengan langkah ini pesan muncul seketika (lencana "Langsung").

1. Sekali saja, buat kunci dan aktifkan:

   ```powershell
   php artisan usandus:realtime
   ```

2. Jalankan server socket di jendela terminal sendiri, selain `php artisan serve` dan `schedule:work`:

   ```powershell
   php artisan reverb:start
   ```

3. Bila diakses dari HP, izinkan port **8080** di Windows Firewall (jaringan Private saja), sama seperti port 8000.
4. Muat ulang halaman chat. Socket hanya membawa tanda "ada pesan baru"; isi pesan tetap diambil lewat jalur biasa yang sudah dijaga login.

Bila `reverb:start` mati atau terblokir, chat otomatis kembali ke mode berkala dan tidak ada pesan yang hilang. Halaman yang dibuka lewat `https://` tidak bisa memakai socket `ws://` biasa, jadi akan tetap di mode berkala. Untuk mematikan: `php artisan usandus:realtime --off`.

## 10. Backup dan restore

Atur lokasi program MySQL XAMPP di `.env`:

```env
BACKUP_MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"
BACKUP_MYSQL_PATH="C:\xampp\mysql\bin\mysql.exe"
BACKUP_KEEP=14
```

| Tindakan | Perintah |
|---|---|
| Buat backup sekarang | `php artisan usandus:backup` |
| Lihat daftar backup | `php artisan usandus:backup --list` |
| Periksa keutuhan backup | `php artisan usandus:backup --verify=NAMA_FILE.sql.gz` |
| Pulihkan | `php artisan usandus:restore NAMA_FILE.sql.gz` |

- File tersimpan di `storage/app/private/backups/` (di luar folder `public`), bernama `usandus-TANGGAL-JAM-*.sql.gz`, disertai file `.json` berisi checksum SHA-256 dan daftar tabel.
- Backup dinyatakan berhasil hanya bila dump berakhir dengan penanda "Dump completed" dan semua tabel ada. Bila gagal, file setengah jadi dihapus dan perintah keluar dengan kode error.
- Restore menolak file yang checksum-nya tidak cocok, meminta konfirmasi, dan **selalu membuat backup pengaman** (`…-sebelum-restore.sql.gz`) dulu. Setelah itu `migrate` dijalankan agar backup lama mengikuti skema terbaru.
- Bila `schedule:work` atau Task Scheduler berjalan, backup otomatis dibuat setiap hari pukul 02.00 WIB dan hanya `BACKUP_KEEP` file terbaru yang disimpan.
- Isi backup mencakup pesan chat dan hash password. Simpan salinannya di tempat yang hanya bisa diakses kalian (misalnya flashdisk terenkripsi), bukan folder yang dibagikan.
- **File `.env` tidak ikut di-backup.** Salin manual ke tempat aman yang sama. Tanpa `APP_KEY` yang sama, session lama dan langganan push (endpoint disimpan terenkripsi) tidak bisa dibaca, sehingga perangkat perlu mengaktifkan notifikasi ulang.

Prosedur ini teruji di container (`Backup/BackupRestoreTest`: backup → data diubah → restore → data kembali persis, termasuk emoji), **belum di XAMPP Windows**.
