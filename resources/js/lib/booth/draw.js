// Draws a finished photobooth picture: background, frames, photos, stickers and text.
// Everything is vector drawing on a 2D canvas, so previews, thumbnails and the saved file come from one code path.

import { LAYOUTS, themeByKey } from './themes';

import '@fontsource/caveat/latin-700.css';
import '@fontsource/pacifico/latin-400.css';
import '@fontsource/special-elite/latin-400.css';

const FONTS = {
    sans: '-apple-system, BlinkMacSystemFont, "SF Pro Display", "Inter Variable", "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
    script: '"Pacifico", "Brush Script MT", cursive',
    hand: '"Caveat", "Bradley Hand", cursive',
    type: '"Special Elite", "Courier New", monospace',
    serif: 'Georgia, "Times New Roman", serif',
};

let fontsReady = null;
/** Canvas draws with a fallback font if the web font is not loaded yet, so wait for them first. */
export function ensureFonts() {
    if (!fontsReady) {
        const load = (spec) => (document.fonts?.load ? document.fonts.load(spec).catch(() => null) : Promise.resolve());
        fontsReady = Promise.all([load('700 40px "Caveat"'), load('400 40px "Pacifico"'), load('400 40px "Special Elite"')]);
    }
    return fontsReady;
}

// ---------- helpers ----------

function rng(seed) {
    let a = (seed >>> 0) || 1;
    return () => {
        a |= 0;
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function roundRectPath(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    if (r <= 0) {
        ctx.rect(x, y, w, h);
        return;
    }
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

export function fillTemplate(text, { names, date }) {
    const d = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
    return String(text ?? '').replaceAll('{names}', names.filter(Boolean).join(' & ')).replaceAll('{date}', d);
}

// ---------- stickers ----------

function heart(ctx, s) {
    const k = s / 2;
    ctx.beginPath();
    ctx.moveTo(0, k * 0.85);
    ctx.bezierCurveTo(-k * 1.5, -k * 0.05, -k * 0.75, -k * 1.3, 0, -k * 0.4);
    ctx.bezierCurveTo(k * 0.75, -k * 1.3, k * 1.5, -k * 0.05, 0, k * 0.85);
    ctx.closePath();
    ctx.fill();
}

function sparkle(ctx, s) {
    const k = s / 2;
    const c = k * 0.14;
    ctx.beginPath();
    ctx.moveTo(0, -k);
    ctx.quadraticCurveTo(c, -c, k, 0);
    ctx.quadraticCurveTo(c, c, 0, k);
    ctx.quadraticCurveTo(-c, c, -k, 0);
    ctx.quadraticCurveTo(-c, -c, 0, -k);
    ctx.closePath();
    ctx.fill();
}

function star(ctx, s) {
    const k = s / 2;
    ctx.beginPath();
    for (let i = 0; i < 10; i++) {
        const r = i % 2 ? k * 0.48 : k;
        const a = -Math.PI / 2 + (i * Math.PI) / 5;
        ctx[i ? 'lineTo' : 'moveTo'](Math.cos(a) * r, Math.sin(a) * r);
    }
    ctx.closePath();
    ctx.lineJoin = 'round';
    ctx.lineWidth = s * 0.1;
    ctx.stroke();
    ctx.fill();
}

function flower(ctx, s, color, center) {
    const k = s / 2;
    for (let i = 0; i < 5; i++) {
        const a = (i * Math.PI * 2) / 5 - Math.PI / 2;
        ctx.beginPath();
        ctx.arc(Math.cos(a) * k * 0.52, Math.sin(a) * k * 0.52, k * 0.44, 0, Math.PI * 2);
        ctx.fill();
    }
    ctx.fillStyle = center;
    ctx.beginPath();
    ctx.arc(0, 0, k * 0.28, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = color;
}

function bow(ctx, s) {
    const k = s / 2;
    for (const sign of [-1, 1]) {
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.bezierCurveTo(sign * k * 0.9, -k * 1.0, sign * k * 1.45, k * 0.75, 0, 0);
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(0, k * 0.1);
        ctx.lineTo(sign * k * 0.42, k * 0.95);
        ctx.lineTo(sign * k * 0.05, k * 0.8);
        ctx.closePath();
        ctx.fill();
    }
    ctx.beginPath();
    ctx.arc(0, 0, k * 0.22, 0, Math.PI * 2);
    ctx.fill();
}

function doodle(ctx, s, variant) {
    const k = s / 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = Math.max(3, s * 0.075);
    ctx.beginPath();
    if (variant === 0) {
        // squiggle
        ctx.moveTo(-k, 0);
        for (let i = 1; i <= 24; i++) {
            const t = i / 24;
            ctx.lineTo(-k + t * s, Math.sin(t * Math.PI * 4) * k * 0.28);
        }
    } else if (variant === 1) {
        // loop
        ctx.moveTo(-k, k * 0.3);
        ctx.bezierCurveTo(-k * 0.2, -k * 0.9, k * 0.5, -k * 0.7, k * 0.2, k * 0.1);
        ctx.bezierCurveTo(-k * 0.1, k * 0.6, -k * 0.5, -k * 0.1, k * 0.15, -k * 0.35);
        ctx.bezierCurveTo(k * 0.6, -k * 0.55, k * 0.8, k * 0.2, k, k * 0.1);
    } else if (variant === 2) {
        // curved arrow
        ctx.moveTo(-k, k * 0.4);
        ctx.quadraticCurveTo(0, -k * 0.9, k * 0.85, -k * 0.1);
        ctx.moveTo(k * 0.45, -k * 0.2);
        ctx.lineTo(k * 0.9, -k * 0.1);
        ctx.lineTo(k * 0.62, k * 0.32);
    } else {
        // underline swoosh
        ctx.moveTo(-k, k * 0.15);
        ctx.quadraticCurveTo(0, k * 0.65, k, -k * 0.1);
    }
    ctx.stroke();
}

function drawSticker(ctx, item) {
    ctx.save();
    ctx.translate(item.x, item.y);
    ctx.rotate(item.rot);
    ctx.fillStyle = item.color;
    ctx.strokeStyle = item.color;
    switch (item.kind) {
        case 'heart': heart(ctx, item.size); break;
        case 'sparkle': sparkle(ctx, item.size); break;
        case 'star': star(ctx, item.size); break;
        case 'flower': flower(ctx, item.size, item.color, item.center); break;
        case 'bow': bow(ctx, item.size); break;
        case 'doodle': doodle(ctx, item.size * 1.3, item.variant); break;
        default: break;
    }
    ctx.restore();
}

// ---------- backgrounds ----------

function paintBackground(ctx, w, h, bg, rand) {
    const grad = (c1, c2, angle = 160) => {
        const a = (angle * Math.PI) / 180;
        const dx = Math.sin(a) * w;
        const dy = -Math.cos(a) * h;
        const g = ctx.createLinearGradient(w / 2 - dx / 2, h / 2 - dy / 2, w / 2 + dx / 2, h / 2 + dy / 2);
        g.addColorStop(0, c1);
        g.addColorStop(1, c2);
        return g;
    };
    switch (bg.kind) {
        case 'gradient':
            ctx.fillStyle = grad(bg.c1, bg.c2, bg.angle);
            ctx.fillRect(0, 0, w, h);
            break;
        case 'dots': {
            ctx.fillStyle = grad(bg.c1, bg.c2, 170);
            ctx.fillRect(0, 0, w, h);
            ctx.fillStyle = bg.dot;
            for (let y = 20; y < h; y += 44) for (let x = ((y / 44) % 2 ? 42 : 20); x < w; x += 44) {
                ctx.beginPath();
                ctx.arc(x, y, 5, 0, Math.PI * 2);
                ctx.fill();
            }
            break;
        }
        case 'stripes': {
            ctx.fillStyle = bg.c1;
            ctx.fillRect(0, 0, w, h);
            ctx.fillStyle = bg.stripe;
            for (let x = -h; x < w + h; x += 64) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x + 32, 0);
                ctx.lineTo(x + 32 + h, h);
                ctx.lineTo(x + h, h);
                ctx.closePath();
                ctx.fill();
            }
            break;
        }
        case 'grid':
            ctx.fillStyle = grad(bg.c1, bg.c2, bg.angle);
            ctx.fillRect(0, 0, w, h);
            ctx.strokeStyle = bg.line;
            ctx.lineWidth = 2;
            for (let x = 0; x <= w; x += 48) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke(); }
            for (let y = 0; y <= h; y += 48) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke(); }
            break;
        case 'paper':
        case 'kraft': {
            ctx.fillStyle = grad(bg.c1, bg.c2, 150);
            ctx.fillRect(0, 0, w, h);
            // speckles and a few fibres
            for (let i = 0; i < (w * h) / 900; i++) {
                ctx.fillStyle = `rgba(${bg.kind === 'kraft' ? '90,60,30' : '120,90,50'},${0.04 + rand() * 0.07})`;
                ctx.fillRect(rand() * w, rand() * h, 1 + rand() * 2, 1 + rand() * 2);
            }
            ctx.strokeStyle = 'rgba(120,90,50,0.07)';
            ctx.lineWidth = 1;
            for (let i = 0; i < (w * h) / 22000; i++) {
                const x = rand() * w;
                const y = rand() * h;
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(x + (rand() - 0.5) * 40, y + (rand() - 0.5) * 40);
                ctx.stroke();
            }
            break;
        }
        case 'film':
            ctx.fillStyle = grad(bg.c1, bg.c2, 180);
            ctx.fillRect(0, 0, w, h);
            break;
        default:
            ctx.fillStyle = bg.c1;
            ctx.fillRect(0, 0, w, h);
    }
}

function filmHoles(ctx, w, h, pad) {
    ctx.fillStyle = '#efe6d2';
    const holeW = 26;
    const holeH = 36;
    const x1 = (pad - holeW) / 2;
    for (let y = 24; y < h - 24; y += 62) {
        roundRectPath(ctx, x1, y, holeW, holeH, 6);
        ctx.fill();
        roundRectPath(ctx, w - x1 - holeW, y, holeW, holeH, 6);
        ctx.fill();
    }
}

// ---------- geometry ----------

const BASE_CELL = { strip: 560, trio: 560, duo: 600, grid: 540, collage: 500 };

export function geometry(theme, layoutKey) {
    const L = LAYOUTS[layoutKey] ?? LAYOUTS.strip;
    const f = theme.frame;
    const pad = theme.pad;
    const gap = f.card ? 30 : 22;
    const s = BASE_CELL[layoutKey] ?? 560;
    const bpX = f.card ?? 0;
    const bpTop = f.card ?? 0;
    const bpBottom = f.cardBottom ?? f.card ?? 0;
    const boxW = s + 2 * bpX;
    const boxH = s + bpTop + bpBottom;
    const rows = Math.ceil(L.count / L.cols);
    const footer = theme.bg.kind === 'film' ? 150 : 176;
    const w = 2 * pad + L.cols * boxW + (L.cols - 1) * gap;
    const h = pad + rows * boxH + (rows - 1) * gap + footer;
    const tilt = f.tilt ?? 0;
    const pattern = [-1, 1, -0.55, 0.8, -0.9, 0.65];
    const cells = Array.from({ length: L.count }, (_, i) => {
        const col = i % L.cols;
        const row = Math.floor(i / L.cols);
        const x = pad + col * (boxW + gap);
        const y = pad + row * (boxH + gap);
        return { x, y, w: boxW, h: boxH, ix: x + bpX, iy: y + bpTop, s, rot: ((pattern[i % pattern.length] * tilt) * Math.PI) / 180 };
    });
    return { w, h, pad, gap, footer, footerY: h - footer, cells, count: L.count, cols: L.cols };
}

// ---------- placeholder photos for theme previews ----------

/** Soft two-tone pictures with simple figures. Used only for theme thumbnails and empty slots. */
export function placeholderCell(index, size = 240) {
    const c = document.createElement('canvas');
    c.width = c.height = size;
    const x = c.getContext('2d');
    const hues = [['#ffd6e5', '#e7d4ff'], ['#d9ecff', '#ffe3ef'], ['#ffe9d2', '#ffd3e0'], ['#e6dcff', '#cfe9ff'], ['#ffdce8', '#fff0d2'], ['#d7f1e4', '#e9dcff']][index % 6];
    const g = x.createLinearGradient(0, 0, size, size);
    g.addColorStop(0, hues[0]);
    g.addColorStop(1, hues[1]);
    x.fillStyle = g;
    x.fillRect(0, 0, size, size);
    x.fillStyle = 'rgba(255,255,255,0.55)';
    const person = (cx, tone, r) => {
        x.fillStyle = tone;
        x.beginPath();
        x.arc(cx, size * 0.44, r, 0, Math.PI * 2);
        x.fill();
        x.beginPath();
        x.ellipse(cx, size * 1.02, r * 1.9, r * 1.55, 0, Math.PI, 0);
        x.fill();
    };
    person(size * 0.36, 'rgba(255,255,255,0.7)', size * 0.12);
    person(size * 0.64, 'rgba(255,255,255,0.5)', size * 0.115);
    return c;
}

// ---------- compose ----------

function pickZones(geo) {
    const { w, h, pad, gap, footerY, cells, cols } = geo;
    const zones = [];
    zones.push({ x: 0, y: 0, w, h: pad });
    zones.push({ x: 0, y: footerY, w: w * 0.22, h: h - footerY });
    zones.push({ x: w * 0.78, y: footerY, w: w * 0.22, h: h - footerY });
    zones.push({ x: 0, y: pad, w: pad, h: footerY - pad });
    zones.push({ x: w - pad, y: pad, w: pad, h: footerY - pad });
    // gaps between rows and columns
    const rowsY = [...new Set(cells.map((c) => c.y))];
    rowsY.slice(0, -1).forEach((y, i) => zones.push({ x: pad, y: y + cells[0].h, w: w - 2 * pad, h: gap }));
    if (cols > 1) zones.push({ x: cells[0].x + cells[0].w, y: pad, w: gap, h: footerY - pad });
    return zones.filter((z) => z.w > 10 && z.h > 10);
}

function placeStickers(geo, theme, stickers, rand) {
    const sets = (stickers?.sets?.length ? stickers.sets : theme.ornaments.sets) ?? [];
    if (!sets.length) return { back: [], front: [] };
    const density = stickers?.density ?? theme.ornaments.density;
    if (density <= 0) return { back: [], front: [] };
    const colors = theme.ornaments.colors.length ? theme.ornaments.colors : [theme.palette.accent, theme.palette.accent2];
    const zones = pickZones(geo);
    const area = zones.reduce((s, z) => s + z.w * z.h, 0);
    const count = Math.round((5 + 15 * (density / 100)) * Math.sqrt(area / 450000));
    const make = (zone, minS, maxS) => {
        const kind = sets[Math.floor(rand() * sets.length)];
        const cap = Math.max(14, Math.min(zone.w, zone.h) * 1.0);
        const size = Math.min(minS + rand() * (maxS - minS), cap);
        return {
            kind,
            size,
            x: zone.x + size / 2 + rand() * Math.max(0, zone.w - size),
            y: zone.y + size / 2 + rand() * Math.max(0, zone.h - size),
            rot: (rand() - 0.5) * (kind === 'doodle' ? 0.9 : 0.7),
            color: colors[Math.floor(rand() * colors.length)],
            center: '#fff6c9',
            variant: Math.floor(rand() * 4),
        };
    };
    const back = [];
    for (let i = 0; i < count; i++) {
        const weighted = zones[Math.floor(rand() * zones.length)];
        back.push(make(weighted, 22, 46));
    }
    const front = [];
    const frontCount = Math.round(2 + 4 * (density / 100));
    for (let i = 0; i < frontCount; i++) {
        const c = geo.cells[Math.floor(rand() * geo.cells.length)];
        const corner = Math.floor(rand() * 4);
        const sticker = make({ x: 0, y: 0, w: 100, h: 100 }, 40, 62);
        sticker.x = c.x + (corner % 2 ? c.w : 0) + (rand() - 0.5) * 12;
        sticker.y = c.y + (corner > 1 ? c.h : 0) + (rand() - 0.5) * 12;
        front.push(sticker);
    }
    return { back, front };
}

function fitText(ctx, text, maxW, size, fontFor) {
    let s = size;
    ctx.font = fontFor(s);
    while (ctx.measureText(text).width > maxW && s > 18) {
        s -= 2;
        ctx.font = fontFor(s);
    }
    return s;
}

/**
 * @param {object} o
 * @param {string} o.themeKey
 * @param {string} o.layout   strip | trio | duo | grid | collage
 * @param {Array<CanvasImageSource|null>} o.cells  processed photos; missing ones show a placeholder
 * @param {string[]} o.names
 * @param {Date} o.date
 * @param {{title?: string, sub?: string}} o.text  empty strings fall back to the theme's text
 * @param {{sets?: string[]|null, density?: number, seed?: number}} o.stickers
 * @param {number} o.maxEdge  longest edge of the result in px
 */
export function composeBooth({ themeKey, layout, cells, names, date, text = {}, stickers = {}, maxEdge = 2560 }) {
    const theme = themeByKey(themeKey);
    const geo = geometry(theme, layout);
    const scale = Math.min(1, maxEdge / Math.max(geo.w, geo.h));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(geo.w * scale));
    canvas.height = Math.max(1, Math.round(geo.h * scale));
    const ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);
    ctx.imageSmoothingQuality = 'high';

    const seed = stickers.seed ?? 11;
    const rand = rng(seed);
    const { palette, frame: f, bg } = theme;
    paintBackground(ctx, geo.w, geo.h, bg, rng(seed + 5));
    if (bg.kind === 'film') filmHoles(ctx, geo.w, geo.h, geo.pad);

    const layer = placeStickers(geo, theme, stickers, rand);
    layer.back.forEach((item) => drawSticker(ctx, item));

    // photos
    geo.cells.forEach((cell, i) => {
        ctx.save();
        const cx = cell.x + cell.w / 2;
        const cy = cell.y + cell.h / 2;
        ctx.translate(cx, cy);
        ctx.rotate(cell.rot);
        ctx.translate(-cx, -cy);

        const hasCard = !!f.card;
        if (hasCard) {
            ctx.save();
            if (f.shadow) {
                ctx.shadowColor = 'rgba(40,25,15,0.28)';
                ctx.shadowBlur = 26;
                ctx.shadowOffsetY = 10;
            }
            ctx.fillStyle = f.cardColor ?? '#fff';
            roundRectPath(ctx, cell.x, cell.y, cell.w, cell.h, f.radius + 2);
            ctx.fill();
            ctx.restore();
        } else if (f.shadowSolid) {
            ctx.fillStyle = f.shadowSolid;
            roundRectPath(ctx, cell.ix + 9, cell.iy + 9, cell.s, cell.s, f.radius);
            ctx.fill();
        } else if (f.shadow) {
            ctx.save();
            ctx.shadowColor = 'rgba(60,30,50,0.22)';
            ctx.shadowBlur = 24;
            ctx.shadowOffsetY = 9;
            ctx.fillStyle = f.borderColor ?? '#fff';
            roundRectPath(ctx, cell.ix, cell.iy, cell.s, cell.s, f.radius);
            ctx.fill();
            ctx.restore();
        }

        const b = hasCard ? 0 : f.border ?? 0;
        if (b) {
            ctx.fillStyle = f.borderColor;
            roundRectPath(ctx, cell.ix, cell.iy, cell.s, cell.s, f.radius);
            ctx.fill();
        }
        const ix = cell.ix + b;
        const iy = cell.iy + b;
        const is = cell.s - 2 * b;
        const src = cells[i];
        ctx.save();
        roundRectPath(ctx, ix, iy, is, is, Math.max(0, f.radius - b * 0.6));
        ctx.clip();
        if (src) ctx.drawImage(src, ix, iy, is, is);
        else ctx.drawImage(placeholderCell(i), ix, iy, is, is);
        ctx.restore();

        if (f.outline) {
            ctx.strokeStyle = f.outline;
            ctx.lineWidth = f.outlineW ?? 5;
            roundRectPath(ctx, cell.ix, cell.iy, cell.s, cell.s, f.radius);
            ctx.stroke();
        }
        if (theme.captions && hasCard) {
            ctx.fillStyle = palette.ink;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = `700 34px ${FONTS.hand}`;
            const line = fillTemplate(theme.text.sub, { names, date });
            ctx.fillText(line, cell.x + cell.w / 2, cell.y + cell.h - (f.cardBottom ?? 60) / 2 + 4, cell.w - 24);
        }
        if (f.tape) {
            ctx.save();
            ctx.translate(cell.x + cell.w / 2, cell.y + 2);
            ctx.rotate(((i % 2 ? 1 : -1) * 4 * Math.PI) / 180);
            ctx.fillStyle = i % 3 === 0 ? 'rgba(255,214,120,0.78)' : i % 3 === 1 ? 'rgba(255,170,190,0.75)' : 'rgba(160,210,240,0.75)';
            ctx.fillRect(-52, -16, 104, 32);
            ctx.restore();
        }
        if (f.frameNo) {
            ctx.fillStyle = palette.accent;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'alphabetic';
            ctx.font = `400 17px ${FONTS.type}`;
            ctx.fillText(`▶ ${i + 1}A`, cell.ix + 2, cell.iy - 7);
            ctx.textAlign = 'right';
            ctx.fillText(`KODAK 400 · ${i + 1}`, cell.ix + cell.s - 2, cell.iy - 7);
        }
        ctx.restore();
    });

    layer.front.forEach((item) => drawSticker(ctx, item));

    // text
    const tpl = { names, date };
    const title = fillTemplate(text.title || theme.text.title, tpl);
    const sub = fillTemplate(text.sub || theme.text.sub, tpl);
    const fontKey = theme.font.title;
    const fontFor = (size) => `${theme.font.italic ? 'italic ' : ''}${theme.font.weight} ${size}px ${FONTS[fontKey]}`;
    const midY = geo.footerY + (theme.bg.kind === 'film' ? 62 : 68);
    const maxW = geo.w - 2 * geo.pad - (theme.footerHearts ? 120 : 0);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = palette.ink;
    if ('letterSpacing' in ctx && theme.font.track) ctx.letterSpacing = `${theme.font.track}px`;
    const shownTitle = theme.font.caps ? title.toUpperCase() : title;
    const size = fitText(ctx, shownTitle, maxW, theme.font.size, fontFor);
    ctx.fillText(shownTitle, geo.w / 2, midY, maxW);
    if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';

    ctx.fillStyle = palette.soft;
    ctx.font = `500 ${Math.round(Math.min(28, size * 0.62))}px ${FONTS[fontKey === 'script' ? 'sans' : fontKey === 'hand' ? 'hand' : fontKey]}`;
    ctx.fillText(sub, geo.w / 2, midY + 44, geo.w - 2 * geo.pad);

    if (theme.footerHearts) {
        ctx.fillStyle = palette.accent;
        ctx.save();
        ctx.translate(geo.pad + 26, midY - size * 0.3);
        heart(ctx, 34);
        ctx.restore();
        ctx.save();
        ctx.translate(geo.w - geo.pad - 26, midY - size * 0.3);
        heart(ctx, 34);
        ctx.restore();
    }

    ctx.fillStyle = palette.soft;
    ctx.globalAlpha = 0.8;
    ctx.font = `600 17px ${FONTS.sans}`;
    if ('letterSpacing' in ctx) ctx.letterSpacing = '3px';
    ctx.fillText('LOVEYNEST', geo.w / 2, geo.h - 22);
    if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';
    ctx.globalAlpha = 1;

    return canvas;
}
