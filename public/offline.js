// Kept outside offline.html so the page works under the app's CSP (no inline scripts).
document.getElementById('retry').addEventListener('click', () => window.location.reload());
window.addEventListener('online', () => window.location.reload());
