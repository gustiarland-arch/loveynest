@echo off
set "LNK=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\LoveyNest.lnk"
set "OLD=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\Us and Us.lnk"
if exist "%OLD%" del "%OLD%"
if exist "%LNK%" (
  del "%LNK%"
  echo Selesai. LoveyNest tidak lagi menyala sendiri saat komputer dinyalakan.
) else (
  echo LoveyNest memang belum diatur menyala sendiri.
)
echo.
pause
