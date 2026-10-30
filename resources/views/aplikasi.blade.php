<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#b8446f">
        <title>Aplikasi Android · LoveyNest</title>
        <link rel="icon" href="/icons/icon-192.png" type="image/png">
        <style>
            :root { --bg: #fbf6f8; --fg: #3a2433; --muted: #7a6270; --card: #ffffff; --line: #ecdde4; --accent: #b8446f; --accent-fg: #ffffff; }
            @media (prefers-color-scheme: dark) { :root { --bg: #1d1219; --fg: #f4e6ee; --muted: #b79aab; --card: #2a1b25; --line: #40293a; --accent: #e07aa0; --accent-fg: #2a1020; } }
            * { box-sizing: border-box; }
            body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; padding: 28px max(16px, env(safe-area-inset-right)) 40px max(16px, env(safe-area-inset-left)); }
            main { max-width: 520px; margin: 0 auto; display: grid; gap: 16px; }
            img.logo { width: 84px; height: 84px; border-radius: 22px; }
            h1 { font: 700 1.7rem/1.2 Georgia, serif; margin: 8px 0 0; }
            p { margin: 0; }
            .muted { color: var(--muted); }
            .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; display: grid; gap: 10px; }
            .btn { display: inline-block; text-align: center; background: var(--accent); color: var(--accent-fg); text-decoration: none; font-weight: 700; padding: 14px 22px; border-radius: 14px; }
            ol { margin: 0; padding-left: 1.2em; display: grid; gap: 8px; }
        </style>
    </head>
    <body>
        <main>
            <img class="logo" src="/icons/icon-192.png" alt="LoveyNest">
            <h1>LoveyNest untuk Android</h1>
            <p class="muted">Aplikasi untuk berdua. Tampilannya ada di dalam aplikasi, sedangkan data, login, dan chat tetap sama dengan situs dan iPhone.</p>
            @if ($info)
                <div class="card">
                    <p><strong>Versi {{ $info['name'] }}</strong> · {{ number_format($info['size'] / 1048576, 1, ',', '.') }} MB</p>
                    <p class="muted">{{ $info['notes'] }}</p>
                    <a class="btn" href="/aplikasi/LoveyNest.apk">Unduh LoveyNest.apk</a>
                </div>
                <div class="card">
                    <strong>Cara memasang</strong>
                    <ol>
                        <li>Unduh berkasnya, lalu ketuk untuk membukanya.</li>
                        <li>Kalau diminta, izinkan "pasang dari sumber ini" untuk peramban yang kamu pakai.</li>
                        <li>Buka LoveyNest dan masuk dengan akunmu.</li>
                        <li>Aktifkan notifikasi di Pengaturan, Notifikasi.</li>
                    </ol>
                    <p class="muted">Sudah terpasang? Aplikasi memberi tahu sendiri kalau ada versi baru dan bisa diperbarui dari dalamnya.</p>
                </div>
            @else
                <div class="card"><p>Berkas aplikasi belum tersedia di server ini.</p></div>
            @endif
            <p class="muted"><a href="/" style="color: inherit;">Kembali ke LoveyNest</a></p>
        </main>
    </body>
</html>
