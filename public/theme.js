// Applies the saved appearance before the first paint. Kept as a tiny classic script
// (the page's CSP only allows scripts from our own origin).
(function () {
    try {
        var root = document.documentElement;
        var mode = localStorage.getItem('usandus-theme');
        if (mode === 'light' || mode === 'dark') root.setAttribute('data-theme', mode);
        // Liquid glass is the default; "soft" turns it off.
        root.setAttribute('data-glass', localStorage.getItem('usandus-glass') === 'soft' ? 'soft' : 'liquid');
        // Last backdrop of the signed-in user, so the page does not flash the default first.
        var bg = JSON.parse(localStorage.getItem('usandus-bg') || 'null');
        if (bg && bg.image) root.style.setProperty('--bg-image', bg.image);
        if (bg && bg.veil) root.style.setProperty('--veil-adjust', bg.veil + '%');
    } catch (e) {}
})();
