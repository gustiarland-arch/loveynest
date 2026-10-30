import { LockedError, e2e, sealFile } from './index';
import { fitImage } from '@/lib/image';

const DISPLAY_EDGE = 2560;
const THUMB = 480;

function canvasBlob(canvas, quality) {
    return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new LockedError('Foto tidak bisa diproses.'))), 'image/jpeg', quality));
}

/**
 * With E2E on, the server can no longer shrink photos or cut thumbnails, so this browser does it:
 * a display JPEG (at most 2560 px, about 1.8 MB) and a 480 px square thumbnail, both locked.
 * Drawing on a canvas also drops the photo's metadata (EXIF, GPS), as the server did before.
 *
 * @returns {Promise<{full: File, thumb: File, width: number, height: number}|null>} null with E2E off
 */
export async function sealPhoto(file) {
    if (!e2e.enabled) return null;

    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        throw new LockedError('Foto ini tidak bisa dibuka di perangkat ini. Coba foto JPG atau PNG.');
    }

    try {
        const scale = Math.min(1, DISPLAY_EDGE / Math.max(bitmap.width, bitmap.height));
        const w = Math.max(1, Math.round(bitmap.width * scale));
        const h = Math.max(1, Math.round(bitmap.height * scale));
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(bitmap, 0, 0, w, h);
        let display = new File([await canvasBlob(canvas, 0.85)], 'foto.jpg', { type: 'image/jpeg' });
        // Same size budget as before, for hosts that accept only 2 MB per upload.
        display = await fitImage(display);
        const dims = await sizeOf(display, w, h);

        const side = Math.min(bitmap.width, bitmap.height);
        const thumbCanvas = document.createElement('canvas');
        thumbCanvas.width = THUMB;
        thumbCanvas.height = THUMB;
        const tctx = thumbCanvas.getContext('2d');
        tctx.fillStyle = '#fff';
        tctx.fillRect(0, 0, THUMB, THUMB);
        tctx.drawImage(bitmap, (bitmap.width - side) / 2, (bitmap.height - side) / 2, side, side, 0, 0, THUMB, THUMB);
        const thumb = await canvasBlob(thumbCanvas, 0.8);

        return { full: await sealFile(display, 'foto'), thumb: await sealFile(thumb, 'thumb'), width: dims.w, height: dims.h };
    } finally {
        bitmap.close?.();
    }
}

async function sizeOf(file, w, h) {
    try {
        const b = await createImageBitmap(file);
        const out = { w: b.width, h: b.height };
        b.close?.();
        return out;
    } catch {
        return { w, h };
    }
}
