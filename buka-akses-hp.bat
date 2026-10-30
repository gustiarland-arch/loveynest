@echo off
rem Mengizinkan HP di WiFi yang sama membuka LoveyNest, lalu menampilkan alamatnya.
cd /d "%~dp0"
net session >nul 2>&1
if errorlevel 1 (
  echo Meminta izin Administrator untuk membuka firewall ...
  powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

echo === Membuka akses dari HP ===
netsh advfirewall firewall delete rule name="Us and Us" >nul 2>&1
netsh advfirewall firewall delete rule name="LoveyNest" >nul 2>&1
netsh advfirewall firewall add rule name="LoveyNest" dir=in action=allow protocol=TCP localport=8000,8080 profile=private,domain >nul
if errorlevel 1 (
  echo [GAGAL] Aturan firewall tidak bisa dibuat.
) else (
  echo Firewall: port 8000 dan 8080 dibuka untuk jaringan Pribadi.
)

echo.
powershell -NoProfile -Command "$p = Get-NetConnectionProfile | Where-Object { $_.IPv4Connectivity -ne 'Disconnected' }; foreach ($n in $p) { if ($n.NetworkCategory -eq 'Public') { Write-Host ('[PERHATIAN] Jaringan ' + $n.Name + ' masih Publik. Ubah jadi Pribadi: Settings > Network and internet > WiFi > nama WiFi > Private network.') } }"
echo.
echo Buka alamat ini di browser HP (HP harus di WiFi yang sama):
powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.PrefixOrigin -ne 'WellKnown' } | ForEach-Object { Write-Host ('   http://' + $_.IPAddress + ':8000   (' + $_.InterfaceAlias + ')') }"
echo.
echo Bila ada beberapa alamat, pakai yang baris WiFi atau Ethernet.
echo Server harus menyala dulu (jalankan.bat atau mulai otomatis).
echo.
pause
