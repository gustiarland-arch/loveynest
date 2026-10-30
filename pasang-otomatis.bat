@echo off
rem Membuat LoveyNest menyala sendiri setiap kali Windows dinyalakan dan masuk.
cd /d "%~dp0"
set "LNK=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\LoveyNest.lnk"
if exist "%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\Us and Us.lnk" del "%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\Us and Us.lnk"
powershell -NoProfile -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut($env:LNK); $s.TargetPath='%~dp0mulai-otomatis.bat'; $s.WorkingDirectory='%~dp0'; $s.WindowStyle=7; $s.Description='LoveyNest'; $s.Save()"
if errorlevel 1 (
  echo [GAGAL] Pintasan tidak bisa dibuat.
  pause
  exit /b 1
)
echo Selesai. LoveyNest akan menyala sendiri setiap komputer dinyalakan.
echo Jendelanya muncul kecil di taskbar. Jangan ditutup bila ingin aplikasi tetap bisa dibuka.
echo Untuk membatalkan: klik dua kali hapus-otomatis.bat
echo.
choice /m "Nyalakan sekarang juga"
if errorlevel 2 goto akhir
start "" /min "%~dp0mulai-otomatis.bat"
:akhir
echo.
pause
