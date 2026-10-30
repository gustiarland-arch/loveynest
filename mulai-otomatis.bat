@echo off
rem Dipanggil saat Windows menyala (lihat pasang-otomatis.bat).
rem Menyalakan MySQL XAMPP bila belum jalan, lalu server LoveyNest dan pengingat.
cd /d "%~dp0"

set "XAMPP="
if exist "C:\xampp\mysql\bin\mysqld.exe" set "XAMPP=C:\xampp"
if not defined XAMPP if exist "D:\xampp\mysql\bin\mysqld.exe" set "XAMPP=D:\xampp"

call :mysql_siap
if not errorlevel 1 goto mysql_ok
if not defined XAMPP goto mysql_tunggu
tasklist /fi "imagename eq mysqld.exe" 2>nul | find /i "mysqld.exe" >nul
if errorlevel 1 start "MySQL" /min "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini" --standalone

:mysql_tunggu
rem Tunggu sampai 60 detik sampai MySQL menerima koneksi
for /l %%i in (1,1,30) do (
  call :mysql_siap
  if not errorlevel 1 goto mysql_ok
  timeout /t 2 /nobreak >nul
)
rem Tetap lanjut: jalankan.bat akan menampilkan pesan bila MySQL belum siap

:mysql_ok
start "LoveyNest" /min cmd /c "%~dp0jalankan.bat"

rem Pengingat dan push butuh schedule:work. Tunggu php.ini khusus dibuat oleh jalankan.bat.
set "PHP=php"
if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if "%PHP%"=="php" if exist "D:\xampp\php\php.exe" set "PHP=D:\xampp\php\php.exe"
for /l %%i in (1,1,30) do (
  if exist "%~dp0storage\php-ini\php.ini" goto php_ok
  timeout /t 2 /nobreak >nul
)
:php_ok
timeout /t 5 /nobreak >nul
set "PHPRC=%~dp0storage\php-ini"
start "LoveyNest pengingat" /min "%PHP%" "%~dp0artisan" schedule:work
exit /b 0

:mysql_siap
powershell -NoProfile -Command "$c=New-Object Net.Sockets.TcpClient; try { $c.Connect('127.0.0.1',3306); exit 0 } catch { exit 1 } finally { $c.Close() }" >nul 2>&1
exit /b %errorlevel%
