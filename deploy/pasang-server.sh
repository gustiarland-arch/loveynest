#!/usr/bin/env bash
# Pasang LoveyNest di server Ubuntu (22.04 atau 24.04) dengan HTTPS otomatis.
# Pakai: sudo bash deploy/pasang-server.sh [domain-kamu]
# Tanpa domain, dipakai alamat gratis IP-ip-ip-ip.sslip.io.
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Jalankan dengan sudo: sudo bash deploy/pasang-server.sh"; exit 1; }

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN="${1:-}"
if [ -z "$DOMAIN" ]; then
    IP="$(curl -fsS https://api.ipify.org)"
    DOMAIN="${IP//./-}.sslip.io"
fi
echo "==> Folder aplikasi: $APP_DIR"
echo "==> Alamat: https://$DOMAIN"

export DEBIAN_FRONTEND=noninteractive

# Server kecil (1 GB RAM) butuh swap supaya composer dan PHP tidak kehabisan memori.
if ! swapon --show | grep -q .; then
    echo "==> Membuat swap 1 GB"
    fallocate -l 1G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Memasang paket"
apt-get update -y
apt-get install -y software-properties-common curl gpg unzip ca-certificates apt-transport-https
if ! apt-cache policy php8.3-cli | grep -q 'Candidate: [0-9]'; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi
apt-get install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-gd php8.3-zip php8.3-bcmath php8.3-intl php8.3-gmp mariadb-server

if ! command -v caddy >/dev/null; then
    curl -fsSL 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -fsSL 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -y
    apt-get install -y caddy
fi

echo "==> Menyiapkan database"
DB_NAME=usandus
DB_USER=loveynest
if [ -f "$APP_DIR/.env" ] && grep -q '^DB_PASSWORD=.\+' "$APP_DIR/.env"; then
    DB_PASS="$(grep '^DB_PASSWORD=' "$APP_DIR/.env" | cut -d= -f2-)"
else
    DB_PASS="$(openssl rand -hex 16)"
fi
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Menulis .env"
cd "$APP_DIR"
[ -f .env ] || cp .env.example .env
setenv() { # setenv KEY VALUE
    if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
setenv APP_ENV production
setenv APP_DEBUG false
setenv LOG_LEVEL warning
setenv APP_URL "https://$DOMAIN"
setenv DB_HOST 127.0.0.1
setenv DB_DATABASE "$DB_NAME"
setenv DB_USERNAME "$DB_USER"
setenv DB_PASSWORD "$DB_PASS"
setenv SESSION_SECURE_COOKIE true
grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

echo "==> Izin folder dan unggahan besar"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data "$APP_DIR"
chmod -R u+rwX,g+rwX storage bootstrap/cache
# Folder induk harus bisa dilewati www-data (mis. /home/user).
d="$APP_DIR"; while [ "$d" != "/" ]; do chmod o+x "$d" 2>/dev/null || true; d="$(dirname "$d")"; done
printf 'upload_max_filesize=20M\npost_max_size=25M\nmemory_limit=256M\n' > /etc/php/8.3/fpm/conf.d/99-loveynest.ini

echo "==> Membuat tabel"
sudo -u www-data php artisan migrate --force
[ -e public/storage ] || sudo -u www-data php artisan storage:link

echo "==> Pengingat otomatis (scheduler) sebagai layanan"
cat > /etc/systemd/system/loveynest-scheduler.service <<UNIT
[Unit]
Description=LoveyNest scheduler
After=mariadb.service

[Service]
User=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now loveynest-scheduler

echo "==> Web server dengan HTTPS"
cat > /etc/caddy/Caddyfile <<CADDY
$DOMAIN {
    root * $APP_DIR/public
    encode gzip
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
    request_body {
        max_size 25MB
    }
}
CADDY
systemctl restart php8.3-fpm
systemctl enable --now caddy
systemctl reload caddy || systemctl restart caddy

echo
echo "Selesai. Buka: https://$DOMAIN"
echo "Sertifikat HTTPS butuh sekitar satu menit pertama kali. Kalau belum terbuka, tunggu lalu muat ulang."
