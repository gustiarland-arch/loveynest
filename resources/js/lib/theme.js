const KEY = 'usandus-theme';

export function currentTheme() {
    try {
        const saved = localStorage.getItem(KEY);
        return saved === 'light' || saved === 'dark' ? saved : 'auto';
    } catch {
        return 'auto';
    }
}

export function setTheme(mode) {
    const root = document.documentElement;
    if (mode === 'light' || mode === 'dark') root.setAttribute('data-theme', mode);
    else root.removeAttribute('data-theme');
    try {
        if (mode === 'auto') localStorage.removeItem(KEY);
        else localStorage.setItem(KEY, mode);
    } catch {
        // Private mode: the choice just lasts for this visit.
    }
}

const GLASS_KEY = 'usandus-glass';

export function currentGlass() {
    try {
        return localStorage.getItem(GLASS_KEY) === 'soft' ? 'soft' : 'liquid';
    } catch {
        return 'liquid';
    }
}

export function setGlass(mode) {
    document.documentElement.setAttribute('data-glass', mode);
    try {
        if (mode === 'soft') localStorage.setItem(GLASS_KEY, 'soft');
        else localStorage.removeItem(GLASS_KEY);
    } catch {
        // Private mode: the choice just lasts for this visit.
    }
}

// Built-in backdrops besides the default photo. Soft so the veil keeps text readable.
export const BACKGROUND_PRESETS = {
    blossom: 'linear-gradient(160deg, #f7b9cf 0%, #fbd9c9 45%, #f3c6e0 100%)',
    lavender: 'linear-gradient(160deg, #cbbdf3 0%, #e7dcfb 50%, #f5c9de 100%)',
    peach: 'linear-gradient(160deg, #ffd0b0 0%, #ffe6cf 45%, #f9bfcf 100%)',
    mint: 'linear-gradient(160deg, #bfe8d6 0%, #e4f5ea 50%, #cfe0f7 100%)',
    night: 'radial-gradient(120% 80% at 20% 10%, #6b4a8f 0%, #2d2147 55%, #160f24 100%)',
};

/** Paints the signed-in user's backdrop; null puts the default photo back (guest pages). */
export function applyBackground(bg) {
    const root = document.documentElement;
    let image = null;
    if (bg?.preset === 'custom' && bg.url) image = `url("${bg.url}")`;
    else if (bg && BACKGROUND_PRESETS[bg.preset]) image = BACKGROUND_PRESETS[bg.preset];
    if (image) root.style.setProperty('--bg-image', image);
    else root.style.removeProperty('--bg-image');
    if (bg?.veil) root.style.setProperty('--veil-adjust', `${bg.veil}%`);
    else root.style.removeProperty('--veil-adjust');
    try {
        if (image || bg?.veil) localStorage.setItem('usandus-bg', JSON.stringify({ image, veil: bg?.veil ?? 0 }));
        else localStorage.removeItem('usandus-bg');
    } catch {
        // Only a first-paint cache.
    }
}
