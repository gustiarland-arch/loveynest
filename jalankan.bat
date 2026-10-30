@echo off
cd /d "%~dp0"
title LoveyNest
echo === LoveyNest ===
echo.

rem Pakai PHP milik XAMPP bila ada (PHP lain di PATH sering tidak punya driver MySQL)
set "PHP=php"
if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if "%PHP%"=="php" if exist "D:\xampp\php\php.exe" set "PHP=D:\xampp\php\php.exe"
"%PHP%" -v >nul 2>&1
if errorlevel 1 (
  echo [GAGAL] PHP tidak ditemukan. Pasang XAMPP 8.4 atau 8.5, atau tambahkan folder php ke PATH.
  goto selesai
)
echo PHP yang dipakai: %PHP%

rem Buat php.ini khusus LoveyNest (mengaktifkan ekstensi yang dibutuhkan, php.ini XAMPP tidak diubah)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0siapkan-php.ps1" "%PHP%" "%~dp0storage\php-ini"
set "PHPRC=%~dp0storage\php-ini"

"%PHP%" "%~dp0cek-php.php"
if errorlevel 1 (
  echo.
  echo [GAGAL] Ada ekstensi PHP wajib yang tidak bisa dimuat ^(lihat daftar di atas^).
  echo Kirim foto layar ini ke pengembang.
  goto selesai
)

if not exist .env (
  copy .env.example .env >nul
)
findstr /b /c:"APP_KEY=base64" .env >nul
if errorlevel 1 "%PHP%" artisan key:generate --force

echo.
echo Membuat tabel database usandus ...
"%PHP%" artisan migrate --force
if errorlevel 1 (
  echo.
  echo [GAGAL] Tabel tidak bisa dibuat. Periksa:
  echo  - MySQL di XAMPP sudah Start
  echo  - Database kosong bernama usandus sudah dibuat di phpMyAdmin
  echo  - DB_USERNAME dan DB_PASSWORD di file .env sesuai
  goto selesai
)

if not exist public\storage "%PHP%" artisan storage:link
echo.
echo Buka di komputer ini : http://localhost:8000
echo Buka dari HP (satu WiFi): http://ALAMAT-IP-KOMPUTER:8000
echo Tutup jendela ini untuk menghentikan.
echo.
"%PHP%" artisan serve --host=0.0.0.0 --port=8000

:selesai
echo.
pause
