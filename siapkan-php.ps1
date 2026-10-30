# Membuat php.ini khusus untuk LoveyNest dari php.ini XAMPP, tanpa mengubah berkas aslinya.
param([string]$PhpExe, [string]$OutDir)
if (-not [IO.Path]::IsPathRooted($PhpExe)) { $PhpExe = (Get-Command $PhpExe).Source }
$phpDir = Split-Path -Parent $PhpExe
$src = Join-Path $phpDir 'php.ini'
if (-not (Test-Path $src)) { $src = Join-Path $phpDir 'php.ini-development' }
$s = Get-Content $src -Raw
$need = 'curl','fileinfo','gd','gmp','intl','mbstring','exif','mysqli','openssl','pdo_mysql','zip'
foreach ($e in $need) {
  $pat = '(?m)^[ \t]*;?[ \t]*extension[ \t]*=[ \t]*(php_)?' + $e + '(\.dll)?[ \t]*(;[^\r\n]*)?(?=\r?$)'
  if ($s -match $pat) { $s = [regex]::Replace($s, $pat, 'extension=' + $e) }
  else { $s += "`r`nextension=$e`r`n" }
}
# Buang baris extension ganda (mis. mysqli dua kali) agar PHP tidak memberi peringatan
$seen = @{}
$out = foreach ($l in ($s -split "`r?`n")) {
  if ($l -match '^\s*extension\s*=\s*"?(?:php_)?([A-Za-z0-9_]+)(?:\.dll)?"?\s*(;.*)?$') {
    $n = $Matches[1].ToLower()
    if ($seen[$n]) { continue }
    $seen[$n] = $true
  }
  $l
}
$s = $out -join "`r`n"
$s = 'extension_dir="' + (Join-Path $phpDir 'ext') + "`"`r`n" + $s
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
[IO.File]::WriteAllText((Join-Path $OutDir 'php.ini'), $s, (New-Object Text.UTF8Encoding($false)))
