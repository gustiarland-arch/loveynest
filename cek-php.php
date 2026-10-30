<?php
// Dipanggil oleh jalankan.bat: memeriksa ekstensi PHP yang dibutuhkan aplikasi.
$wajib = ['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'curl', 'gd'];
$opsional = ['mysqli', 'exif', 'gmp', 'intl', 'zip'];
$gagal = false;
foreach (array_merge($wajib, $opsional) as $e) {
    $ok = extension_loaded($e);
    echo str_pad($e, 12), $ok ? 'ya' : 'TIDAK', PHP_EOL;
    if (!$ok && in_array($e, $wajib, true)) {
        $gagal = true;
    }
}
echo PHP_EOL, 'PHP ', PHP_VERSION, PHP_EOL, 'php.ini: ', php_ini_loaded_file() ?: '(tidak ada)', PHP_EOL, 'extension_dir: ', ini_get('extension_dir'), PHP_EOL;
exit($gagal ? 1 : 0);
