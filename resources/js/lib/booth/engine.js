// Photo effects engine. Pure functions over RGBA pixel arrays: no DOM, no network, nothing leaves the phone.
// Order: tone and colour -> noise -> skin smoothing -> sharpen -> glow / soft focus -> light leak -> vignette -> grain.

export const NEUTRAL = Object.freeze({
    exposure: 0, brightness: 0, contrast: 0, saturation: 0, temperature: 0, tint: 0, highlights: 0, shadows: 0,
    fade: 0, grain: 0, vignette: 0, glow: 0, soften: 0, leak: 0,
    smooth: 0, tone: 0, denoise: 0, sharpen: 0,
});

// Sliders the editor shows, with their range. Everything else only comes from presets.
export const ADJUST_FIELDS = [
    { key: 'exposure', min: -100, max: 100 }, { key: 'brightness', min: -100, max: 100 }, { key: 'contrast', min: -100, max: 100 },
    { key: 'saturation', min: -100, max: 100 }, { key: 'temperature', min: -100, max: 100 }, { key: 'tint', min: -100, max: 100 },
    { key: 'highlights', min: -100, max: 100 }, { key: 'shadows', min: -100, max: 100 },
];
export const EFFECT_FIELDS = [
    { key: 'grain', min: 0, max: 100 }, { key: 'vignette', min: 0, max: 100 }, { key: 'glow', min: 0, max: 100 },
    { key: 'soften', min: 0, max: 100 }, { key: 'leak', min: 0, max: 100 },
];
export const SKIN_FIELDS = [
    { key: 'smooth', min: 0, max: 100 }, { key: 'tone', min: 0, max: 100 }, { key: 'denoise', min: 0, max: 100 }, { key: 'sharpen', min: 0, max: 100 },
];

// Preset values are the look at 100% intensity. `css` only approximates it for the live camera preview.
// split = [shadow rgb, highlight rgb] tints, each 0..255.
export const PRESETS = [
    { key: 'original', css: 'none', params: {} },
    { key: 'korean', css: 'brightness(1.06) contrast(0.92) saturate(0.95)', params: { exposure: 12, contrast: -12, saturation: -8, temperature: 6, tint: 5, highlights: -15, shadows: 25, fade: 8, soften: 22, glow: 18, smooth: 28 } },
    { key: 'warm', css: 'sepia(0.2) saturate(1.15) brightness(1.04)', params: { exposure: 8, temperature: 30, saturation: 10, glow: 30, vignette: 14, split: [[40, 20, 10], [255, 205, 150]] } },
    { key: 'dreamy', css: 'brightness(1.06) contrast(0.86) saturate(0.95)', params: { brightness: 8, contrast: -18, saturation: -5, tint: 10, fade: 14, soften: 52, glow: 42, leak: 14 } },
    { key: 'pastel', css: 'brightness(1.1) contrast(0.82) saturate(0.78)', params: { brightness: 14, contrast: -20, saturation: -25, temperature: -4, tint: 8, fade: 24 } },
    { key: 'y2k', css: 'contrast(1.25) saturate(1.2) brightness(1.1)', params: { exposure: 18, contrast: 28, saturation: 16, temperature: -10, tint: -6, highlights: 24, shadows: -10, grain: 12, vignette: 34, split: [[25, 10, 70], [255, 235, 245]] } },
    { key: 'vintage', css: 'sepia(0.45) contrast(1.05) saturate(0.8)', params: { saturation: -22, contrast: 10, temperature: 22, fade: 28, grain: 30, vignette: 30, leak: 25, split: [[10, 40, 45], [255, 215, 160]] } },
    { key: 'bw', css: 'grayscale(1) contrast(1.15)', params: { saturation: -100, contrast: 18, grain: 14, vignette: 15 } },
];

export const DEFAULT_SETTINGS = Object.freeze({
    preset: 'korean',
    intensity: 80,
    adjust: { ...NEUTRAL },
});

const clamp01 = (v) => (v < 0 ? 0 : v > 1 ? 1 : v);
const smoothstep = (a, b, x) => {
    const t = clamp01((x - a) / (b - a));
    return t * t * (3 - 2 * t);
};

/** Preset at the chosen intensity plus the person's own sliders. */
export function resolveParams(settings) {
    const preset = PRESETS.find((p) => p.key === settings.preset) ?? PRESETS[0];
    const k = clamp01((settings.intensity ?? 100) / 100);
    const out = { ...NEUTRAL, split: null, splitK: 0 };
    for (const [key, value] of Object.entries(preset.params)) {
        if (key === 'split') {
            out.split = value;
            out.splitK = k;
        } else {
            out[key] = value * k;
        }
    }
    for (const [key, value] of Object.entries(settings.adjust ?? {})) {
        if (key in NEUTRAL) out[key] += Number(value) || 0;
    }
    return out;
}

/** True when the settings would leave the picture unchanged. */
export function isNeutral(settings) {
    const p = resolveParams(settings);
    return Object.keys(NEUTRAL).every((k) => Math.abs(p[k]) < 0.5) && !(p.split && p.splitK > 0);
}

// ---------- box blur and guided filter (O(n), edge aware) ----------

export function boxBlur(src, w, h, r, out = new Float32Array(w * h), tmp = new Float32Array(w * h)) {
    if (r < 1) {
        out.set(src);
        return out;
    }
    const d = 2 * r + 1;
    for (let y = 0; y < h; y++) {
        const base = y * w;
        let acc = 0;
        for (let i = -r; i <= r; i++) acc += src[base + Math.min(w - 1, Math.max(0, i))];
        for (let x = 0; x < w; x++) {
            tmp[base + x] = acc / d;
            acc += src[base + Math.min(w - 1, x + r + 1)] - src[base + Math.max(0, x - r)];
        }
    }
    for (let x = 0; x < w; x++) {
        let acc = 0;
        for (let i = -r; i <= r; i++) acc += tmp[Math.min(h - 1, Math.max(0, i)) * w + x];
        for (let y = 0; y < h; y++) {
            out[y * w + x] = acc / d;
            acc += tmp[Math.min(h - 1, y + r + 1) * w + x] - tmp[Math.max(0, y - r) * w + x];
        }
    }
    return out;
}

/** Guided filter (He et al.): smooths flat areas, keeps strong edges. guide and p are 0..1 planes. */
export function guidedFilter(guide, p, w, h, r, eps) {
    const n = w * h;
    const tmp = new Float32Array(n);
    const meanI = boxBlur(guide, w, h, r, new Float32Array(n), tmp);
    const meanP = boxBlur(p, w, h, r, new Float32Array(n), tmp);
    const ii = new Float32Array(n);
    const ip = new Float32Array(n);
    for (let i = 0; i < n; i++) {
        ii[i] = guide[i] * guide[i];
        ip[i] = guide[i] * p[i];
    }
    const corrI = boxBlur(ii, w, h, r, new Float32Array(n), tmp);
    const corrIp = boxBlur(ip, w, h, r, new Float32Array(n), tmp);
    const a = ii; // reuse
    const b = ip;
    for (let i = 0; i < n; i++) {
        const varI = corrI[i] - meanI[i] * meanI[i];
        const cov = corrIp[i] - meanI[i] * meanP[i];
        const ai = cov / (varI + eps);
        a[i] = ai;
        b[i] = meanP[i] - ai * meanI[i];
    }
    const meanA = boxBlur(a, w, h, r, new Float32Array(n), tmp);
    const meanB = boxBlur(b, w, h, r, new Float32Array(n), tmp);
    const out = new Float32Array(n);
    for (let i = 0; i < n; i++) out[i] = meanA[i] * guide[i] + meanB[i];
    return out;
}

// ---------- planes ----------

function toPlanes(data, n) {
    const r = new Float32Array(n);
    const g = new Float32Array(n);
    const b = new Float32Array(n);
    for (let i = 0, j = 0; i < n; i++, j += 4) {
        r[i] = data[j] / 255;
        g[i] = data[j + 1] / 255;
        b[i] = data[j + 2] / 255;
    }
    return [r, g, b];
}

function fromPlanes(data, n, [r, g, b]) {
    for (let i = 0, j = 0; i < n; i++, j += 4) {
        data[j] = Math.round(clamp01(r[i]) * 255);
        data[j + 1] = Math.round(clamp01(g[i]) * 255);
        data[j + 2] = Math.round(clamp01(b[i]) * 255);
    }
}

const luma = (r, g, b) => 0.299 * r + 0.587 * g + 0.114 * b;

// ---------- tone and colour (per pixel) ----------

function applyTone(planes, n, p) {
    const [R, G, B] = planes;
    const exposure = Math.pow(2, (p.exposure / 100) * 0.9);
    const bright = (p.brightness / 100) * 0.22;
    const temp = p.temperature / 100;
    const tint = p.tint / 100;
    const contrast = 1 + p.contrast / 100 * 0.85;
    const sat = 1 + p.saturation / 100;
    const sh = p.shadows / 100;
    const hi = p.highlights / 100;
    const fade = p.fade / 255;
    const split = p.split && p.splitK > 0 ? p.split : null;
    const sk = p.splitK * 0.22;

    for (let i = 0; i < n; i++) {
        let r = R[i] * exposure + bright;
        let g = G[i] * exposure + bright;
        let b = B[i] * exposure + bright;

        r += temp * 0.09;
        b -= temp * 0.09;
        g -= tint * 0.06;
        r += tint * 0.03;
        b += tint * 0.03;

        const y = luma(r, g, b);
        if (sh) {
            const wS = (1 - clamp01(y)) ** 2 * sh * 0.32;
            r += wS;
            g += wS;
            b += wS;
        }
        if (hi) {
            const wH = clamp01(y) ** 2 * hi * 0.28;
            r += wH;
            g += wH;
            b += wH;
        }

        r = (r - 0.5) * contrast + 0.5;
        g = (g - 0.5) * contrast + 0.5;
        b = (b - 0.5) * contrast + 0.5;

        const y2 = luma(r, g, b);
        r = y2 + (r - y2) * sat;
        g = y2 + (g - y2) * sat;
        b = y2 + (b - y2) * sat;

        if (split) {
            const t = clamp01(y2);
            const ws = (1 - t) * (1 - t) * sk;
            const wh = t * t * sk;
            r += ((split[0][0] / 255) - r) * ws + ((split[1][0] / 255) - r) * wh;
            g += ((split[0][1] / 255) - g) * ws + ((split[1][1] / 255) - g) * wh;
            b += ((split[0][2] / 255) - b) * ws + ((split[1][2] / 255) - b) * wh;
        }

        if (fade) {
            r = fade + r * (1 - fade);
            g = fade + g * (1 - fade);
            b = fade + b * (1 - fade);
        }

        R[i] = clamp01(r);
        G[i] = clamp01(g);
        B[i] = clamp01(b);
    }
}

// ---------- skin ----------

/**
 * 0..1 likelihood that a pixel is skin, from its chroma (YCbCr). Dark pixels (eyes, brows, hair)
 * and strongly red ones (lips) are excluded so those details stay crisp.
 * `faces` ([{x,y,w,h}] in pixels) narrows it to face areas when the browser can detect them.
 */
export function skinMask(planes, w, h, faces = null) {
    const n = w * h;
    const [R, G, B] = planes;
    const mask = new Float32Array(n);
    for (let i = 0; i < n; i++) {
        const r = R[i] * 255;
        const g = G[i] * 255;
        const b = B[i] * 255;
        const y = 0.299 * r + 0.587 * g + 0.114 * b;
        const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
        const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
        const sCb = smoothstep(71, 79, cb) * (1 - smoothstep(125, 131, cb));
        const sCr = smoothstep(127, 135, cr) * (1 - smoothstep(158, 168, cr));
        const sY = smoothstep(55, 95, y) * (1 - smoothstep(245, 254, y));
        mask[i] = sCb * sCr * sY;
    }

    if (faces && faces.length) {
        const area = new Float32Array(n);
        for (const f of faces) {
            const cx = f.x + f.w / 2;
            const cy = f.y + f.h / 2;
            const rx = (f.w / 2) * 1.25;
            const ry = (f.h / 2) * 1.35;
            const x0 = Math.max(0, Math.floor(cx - rx * 1.2));
            const x1 = Math.min(w - 1, Math.ceil(cx + rx * 1.2));
            const y0 = Math.max(0, Math.floor(cy - ry * 1.2));
            const y1 = Math.min(h - 1, Math.ceil(cy + ry * 1.2));
            for (let y = y0; y <= y1; y++) {
                for (let x = x0; x <= x1; x++) {
                    const d = Math.hypot((x - cx) / rx, (y - cy) / ry);
                    const v = 1 - smoothstep(0.85, 1.15, d);
                    if (v > area[y * w + x]) area[y * w + x] = v;
                }
            }
        }
        for (let i = 0; i < n; i++) mask[i] *= area[i];
    }

    // Feather, so smoothing fades out instead of ending in a visible edge.
    return boxBlur(mask, w, h, Math.max(2, Math.round(Math.min(w, h) * 0.012)));
}

function applySkin(planes, w, h, p, faces) {
    const n = w * h;
    const wantSmooth = p.smooth > 0.5;
    const wantTone = p.tone > 0.5;
    const wantDenoise = p.denoise > 0.5;
    if (!wantSmooth && !wantTone && !wantDenoise) return;

    const [R, G, B] = planes;
    const side = Math.min(w, h);
    const guide = new Float32Array(n);
    for (let i = 0; i < n; i++) guide[i] = luma(R[i], G[i], B[i]);

    // Noise reduction everywhere, tiny radius: only flat areas change.
    if (wantDenoise) {
        const k = clamp01(p.denoise / 100);
        const eps = 0.0009 + (1 - k) * 0.004;
        const r = Math.max(1, Math.round(side * 0.004));
        const smoothed = planes.map((pl) => guidedFilter(guide, pl, w, h, r, eps));
        const a = k * 0.85;
        for (let c = 0; c < 3; c++) for (let i = 0; i < n; i++) planes[c][i] += (smoothed[c][i] - planes[c][i]) * a;
        for (let i = 0; i < n; i++) guide[i] = luma(R[i], G[i], B[i]);
    }

    if (!wantSmooth && !wantTone) return;
    const mask = skinMask(planes, w, h, faces);

    if (wantSmooth) {
        const k = clamp01(p.smooth / 100);
        const r = Math.max(2, Math.round(side * (0.010 + k * 0.012)));
        const eps = 0.0016 + k * 0.006; // edges stronger than about 0.04..0.08 in luma survive
        const smoothed = planes.map((pl) => guidedFilter(guide, pl, w, h, r, eps));
        // Keep a little of the original texture so skin does not turn into plastic.
        const keep = 0.30 - k * 0.12;
        const strength = 0.92 * k;
        for (let c = 0; c < 3; c++) {
            const pl = planes[c];
            const sm = smoothed[c];
            for (let i = 0; i < n; i++) {
                const target = sm[i] + (pl[i] - sm[i]) * keep;
                pl[i] += (target - pl[i]) * mask[i] * strength;
            }
        }
    }

    if (wantTone) {
        // Even out blotches and redness: pull the colour (not the brightness) towards its wide local average.
        const k = clamp01(p.tone / 100);
        const r = Math.max(3, Math.round(side * 0.05));
        const tmp = new Float32Array(n);
        const avg = planes.map((pl) => boxBlur(pl, w, h, r, new Float32Array(n), tmp));
        const a = k * 0.6;
        for (let i = 0; i < n; i++) {
            const m = mask[i] * a;
            if (m < 0.002) continue;
            const y = luma(R[i], G[i], B[i]);
            const ya = luma(avg[0][i], avg[1][i], avg[2][i]);
            // chroma = colour minus brightness
            const dr = (avg[0][i] - ya) - (R[i] - y);
            const dg = (avg[1][i] - ya) - (G[i] - y);
            const db = (avg[2][i] - ya) - (B[i] - y);
            R[i] += dr * m;
            G[i] += dg * m;
            B[i] += db * m;
            // a touch of brightness for a fresher look
            const lift = k * 0.012 * mask[i];
            R[i] += lift;
            G[i] += lift;
            B[i] += lift;
        }
    }
}

// ---------- spatial effects ----------

function applySharpen(planes, w, h, amount) {
    const n = w * h;
    const r = Math.max(1, Math.round(Math.min(w, h) * 0.006));
    const tmp = new Float32Array(n);
    const k = clamp01(amount / 100) * 0.7;
    for (const pl of planes) {
        const blur = boxBlur(pl, w, h, r, new Float32Array(n), tmp);
        for (let i = 0; i < n; i++) {
            const d = pl[i] - blur[i];
            // Ignore tiny differences (noise) so sharpening does not add grit.
            const gate = smoothstep(0.004, 0.03, Math.abs(d));
            pl[i] = clamp01(pl[i] + d * k * gate);
        }
    }
}

function applyGlowSoften(planes, w, h, p) {
    const n = w * h;
    const soften = clamp01(p.soften / 100);
    const glow = clamp01(p.glow / 100);
    if (soften < 0.01 && glow < 0.01) return;
    const side = Math.min(w, h);
    const r = Math.max(2, Math.round(side * (0.018 + soften * 0.02)));
    const tmp = new Float32Array(n);
    const blurred = planes.map((pl) => boxBlur(boxBlur(pl, w, h, r, new Float32Array(n), tmp), w, h, r, new Float32Array(n), tmp));
    for (let c = 0; c < 3; c++) {
        const pl = planes[c];
        const bl = blurred[c];
        for (let i = 0; i < n; i++) {
            let v = pl[i] + (bl[i] - pl[i]) * soften * 0.65;
            if (glow > 0.01) {
                // Screen the blurred highlights back in: a warm bloom around bright areas.
                const bloom = clamp01((bl[i] - 0.35) / 0.65) * glow * 0.55;
                v = 1 - (1 - v) * (1 - bloom * bl[i]);
            }
            pl[i] = clamp01(v);
        }
    }
}

function applyLeak(planes, w, h, amount) {
    const k = clamp01(amount / 100);
    if (k < 0.01) return;
    const [R, G, B] = planes;
    const reach = Math.max(w, h) * 0.75;
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            // A leak from the upper-left corner and a fainter one from the lower-right.
            const d1 = Math.hypot(x - w * 0.02, y - h * 0.18) / reach;
            const d2 = Math.hypot(x - w * 1.0, y - h * 0.95) / (reach * 1.1);
            const a1 = (1 - smoothstep(0, 1, d1)) * k * 0.55;
            const a2 = (1 - smoothstep(0, 1, d2)) * k * 0.3;
            const i = y * w + x;
            // screen with orange and magenta
            R[i] = 1 - (1 - R[i]) * (1 - a1 * 1.0) * (1 - a2 * 0.9);
            G[i] = 1 - (1 - G[i]) * (1 - a1 * 0.45) * (1 - a2 * 0.25);
            B[i] = 1 - (1 - B[i]) * (1 - a1 * 0.12) * (1 - a2 * 0.55);
        }
    }
}

function applyVignette(planes, w, h, amount) {
    const k = clamp01(amount / 100);
    if (k < 0.01) return;
    const [R, G, B] = planes;
    const cx = w / 2;
    const cy = h / 2;
    const maxD = Math.hypot(cx, cy);
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const d = Math.hypot(x - cx, y - cy) / maxD;
            const f = 1 - k * 0.7 * smoothstep(0.35, 1.05, d);
            const i = y * w + x;
            R[i] *= f;
            G[i] *= f;
            B[i] *= f;
        }
    }
}

// Same seed, same grain: the preview, the before/after and the saved file agree.
function applyGrain(planes, n, amount, seed = 1) {
    const k = clamp01(amount / 100) * 0.16;
    if (k < 0.001) return;
    const [R, G, B] = planes;
    let s = (seed * 2654435761) >>> 0;
    for (let i = 0; i < n; i++) {
        s ^= s << 13; s >>>= 0;
        s ^= s >>> 17;
        s ^= s << 5; s >>>= 0;
        const noise = ((s & 0xffff) / 65535 - 0.5) * 2 * k;
        const y = luma(R[i], G[i], B[i]);
        const mid = 1 - Math.abs(y - 0.5) * 1.2; // film grain shows most in the mid-tones
        const v = noise * Math.max(0.25, mid);
        R[i] = clamp01(R[i] + v);
        G[i] = clamp01(G[i] + v);
        B[i] = clamp01(B[i] + v);
    }
}

/**
 * Applies the settings to RGBA pixels in place.
 * @param {{data: Uint8ClampedArray, width: number, height: number}} image
 * @param {object} settings { preset, intensity, adjust }
 * @param {{faces?: Array, seed?: number}} options
 */
export function processPixels(image, settings, options = {}) {
    const { data, width: w, height: h } = image;
    const n = w * h;
    const p = resolveParams(settings);
    if (Object.keys(NEUTRAL).every((k) => Math.abs(p[k]) < 0.5) && !(p.split && p.splitK > 0)) return image;

    const planes = toPlanes(data, n);
    applyTone(planes, n, p);
    applySkin(planes, w, h, p, options.faces ?? null);
    if (p.sharpen > 0.5) applySharpen(planes, w, h, p.sharpen);
    applyGlowSoften(planes, w, h, p);
    applyLeak(planes, w, h, p.leak);
    applyVignette(planes, w, h, p.vignette);
    applyGrain(planes, n, p.grain, options.seed ?? 1);
    fromPlanes(data, n, planes);
    return image;
}

/** Only the parts that need a face detector's help. */
export function needsFaces(settings) {
    const p = resolveParams(settings);
    return p.smooth > 0.5 || p.tone > 0.5;
}
