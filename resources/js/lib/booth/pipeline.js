// Turns the stored square shots into processed photos for the editor and for the saved picture.
// A worker does the pixel work; if workers are unavailable the same engine runs on the main thread.

import { processPixels, needsFaces, isNeutral } from './engine';
import { detectFaces } from './faces';

let workers = null;
let next = 0;
let seq = 0;
const waiting = new Map();

function startWorkers() {
    if (workers) return workers;
    workers = [];
    try {
        const count = Math.max(1, Math.min(3, (navigator.hardwareConcurrency ?? 2) - 1));
        for (let i = 0; i < count; i++) {
            const w = new Worker(new URL('./worker.js', import.meta.url), { type: 'module' });
            w.onmessage = ({ data }) => {
                const job = waiting.get(data.id);
                waiting.delete(data.id);
                if (!job) return;
                if (data.error) job.reject(new Error(data.error));
                else job.resolve(new Uint8ClampedArray(data.buffer));
            };
            w.onerror = () => {
                // One broken worker must not leave jobs hanging: fail them so the caller falls back.
                for (const [id, job] of waiting) {
                    waiting.delete(id);
                    job.reject(new Error('worker'));
                }
                workers = [];
            };
            workers.push(w);
        }
    } catch {
        workers = [];
    }
    return workers;
}

async function run(imageData, settings, faces, seed) {
    const pool = startWorkers();
    if (pool.length) {
        try {
            const id = ++seq;
            const copy = new Uint8ClampedArray(imageData.data);
            const result = await new Promise((resolve, reject) => {
                waiting.set(id, { resolve, reject });
                pool[next++ % pool.length].postMessage({ id, buffer: copy.buffer, width: imageData.width, height: imageData.height, settings, faces, seed }, [copy.buffer]);
            });
            return new ImageData(result, imageData.width, imageData.height);
        } catch {
            /* fall through to the main thread */
        }
    }
    const copy = new ImageData(new Uint8ClampedArray(imageData.data), imageData.width, imageData.height);
    processPixels(copy, settings, { faces, seed });
    return copy;
}

export const DEFAULT_TRANSFORM = Object.freeze({ zoom: 1, x: 0, y: 0, rot: 0, mirror: false });

/** Crops, zooms, rotates and mirrors a square shot into a `size` canvas. */
export function transformShot(raw, t = DEFAULT_TRANSFORM, size) {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.imageSmoothingQuality = 'high';
    const a = (Math.abs(t.rot) * Math.PI) / 180;
    // Rotating exposes corners: zoom in just enough to hide them.
    const cover = Math.cos(a) + Math.sin(a);
    const zoom = Math.max(t.zoom, cover);
    const free = (size * (zoom - 1)) / 2;
    ctx.translate(size / 2 + t.x * free, size / 2 + t.y * free);
    ctx.rotate((t.rot * Math.PI) / 180);
    ctx.scale(t.mirror ? -zoom : zoom, zoom);
    ctx.drawImage(raw, -size / 2, -size / 2, size, size);
    return canvas;
}

const cache = new Map();
const faceCache = new Map();

function remember(key, value) {
    cache.set(key, value);
    if (cache.size > 40) cache.delete(cache.keys().next().value);
}

/**
 * One processed photo. `settings` are the editor's look settings, `transform` this photo's crop.
 * Results are cached by their inputs, so moving a slider only recomputes what changed.
 */
export async function renderPhoto(raw, index, transform, settings, size, { useFaces = true } = {}) {
    const looks = JSON.stringify([settings.preset, settings.intensity, settings.adjust]);
    const key = `${raw.__id}|${index}|${size}|${JSON.stringify(transform)}|${looks}`;
    if (cache.has(key)) return cache.get(key);

    const base = transformShot(raw, transform, size);
    if (isNeutral(settings)) {
        remember(key, base);
        return base;
    }
    const ctx = base.getContext('2d', { willReadFrequently: true });
    const image = ctx.getImageData(0, 0, size, size);

    let faces = null;
    if (useFaces && needsFaces(settings)) {
        const faceKey = `${raw.__id}|${index}|${size}|${JSON.stringify(transform)}`;
        if (!faceCache.has(faceKey)) faceCache.set(faceKey, detectFaces(base));
        const found = await faceCache.get(faceKey);
        faces = found.length ? found : null;
    }

    const out = await run(image, settings, faces, index + 1);
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    canvas.getContext('2d').putImageData(out, 0, 0);
    remember(key, canvas);
    return canvas;
}

let rawId = 0;
/** Loads a stored shot into a canvas the pipeline can use. */
export async function loadShot(url) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`shot ${response.status}`);
    const bitmap = await createImageBitmap(await response.blob());
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0);
    bitmap.close?.();
    canvas.__id = ++rawId;
    return canvas;
}

/** A camera or file picture, centre-cropped to a square. */
export function rawFromSource(source, width, height, { mirror = false, edge = 1080 } = {}) {
    const side = Math.min(width, height);
    const size = Math.min(edge, Math.round(side));
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    if (mirror) {
        ctx.translate(size, 0);
        ctx.scale(-1, 1);
    }
    ctx.drawImage(source, (width - side) / 2, (height - side) / 2, side, side, 0, 0, size, size);
    canvas.__id = ++rawId;
    return canvas;
}

export function toJpeg(canvas, quality = 0.9) {
    return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('encode'))), 'image/jpeg', quality));
}
