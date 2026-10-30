const KEEP = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Shrinks a phone photo so it fits the server's upload limit (many hosts allow only 2 MB).
 * Files that already fit are returned untouched; if the browser cannot decode the image
 * the original comes back and the server decides.
 */
export async function fitImage(file, maxBytes = 1_800_000, maxSide = 2560) {
    if (!file || !file.type?.startsWith('image/')) return file;
    if (file.size <= maxBytes && KEEP.includes(file.type)) return file;

    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return file;
    }

    try {
        let scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        for (let round = 0; round < 6; round++) {
            const w = Math.max(1, Math.round(bitmap.width * scale));
            const h = Math.max(1, Math.round(bitmap.height * scale));
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(bitmap, 0, 0, w, h);
            for (const quality of [0.85, 0.72, 0.6]) {
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
                if (blob && blob.size <= maxBytes) {
                    return new File([blob], `${(file.name || 'foto').replace(/\.[^.]+$/, '')}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
                }
            }
            scale *= 0.75;
        }
    } catch {
        /* fall through to the original */
    } finally {
        bitmap.close?.();
    }

    return file;
}
