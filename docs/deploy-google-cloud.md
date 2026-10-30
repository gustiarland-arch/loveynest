# Deploy LoveyNest di Google Cloud (VM gratis)

Bisa dikerjakan dari HP lewat browser.

## Syarat dan biaya
- Akun Google dan **kartu debit/kredit untuk verifikasi**. Kartu tidak dikenai biaya selama kamu tetap di batas gratis.
- Batas gratis ("Always Free"): 1 VM e2-micro di region **us-west1, us-central1, atau us-east1**, disk standar sampai 30 GB, trafik keluar sampai 1 GB per bulan. Di luar itu ditagih. Pasang anggaran peringatan di menu Billing.
- RAM e2-micro hanya 1 GB. Cukup untuk dua orang. Skrip membuat swap 1 GB.

## Langkah
1. Buka console.cloud.google.com, buat project, lalu aktifkan billing (isi kartu).
2. Menu **Compute Engine > VM instances > Create instance**:
   - Machine type: **e2-micro**, region salah satu dari tiga di atas.
   - Boot disk: **Ubuntu 24.04 LTS**, tipe **Standard persistent disk**, 30 GB.
   - Firewall: centang **Allow HTTP traffic** dan **Allow HTTPS traffic**.
3. Setelah VM jalan, klik **SSH** di baris VM. Terminal terbuka di browser.
4. Di jendela SSH, klik ikon roda gigi (kanan atas), pilih **Upload file**, lalu unggah `us-and-us-siap-pakai.zip`.
5. Di terminal:
   ```
   sudo apt-get install -y unzip
   unzip us-and-us-siap-pakai.zip -d ~/
   sudo mv ~/us-and-us /var/www-loveynest
   sudo bash /var/www-loveynest/deploy/pasang-server.sh
   ```
6. Tunggu sampai muncul alamat `https://...sslip.io`. Buka dari HP dan pilih "Tambahkan ke layar utama".

Punya domain sendiri? Arahkan A record ke IP VM, lalu jalankan skrip dengan `sudo bash .../pasang-server.sh domainmu.com`.

## Catatan
- Aman dijalankan ulang untuk pembaruan setelah file baru diunggah.
- Alamat sslip.io berubah kalau IP VM berubah. Pesan alamat IP tetap (Static external IP) agar tidak berubah. IP tetap yang terpasang ke VM yang menyala gratis.
- Cadangan: `php artisan usandus:backup` (jalan otomatis oleh scheduler).
