// Profile frames: animated SVGs in resources/frames, bundled by Vite so they ship with the build.
const files = import.meta.glob('../../frames/border-*.svg', { eager: true, query: '?url', import: 'default' });

export const FRAMES = ['aurora', 'emas', 'manis', 'daun', 'abstrak', 'neon', 'kristal', 'awan', 'grafiti', 'malam'];

// e: half-width of the picture in hole radii x100 (hole = the opening that holds the photo);
// p: how much of the avatar box the photo takes (the rest is the frame hugging it, nothing sticks out far).
const META = {
    default: { e: 125, p: 0.92 },
    neon: { e: 139.8, p: 0.89 },
    kristal: { e: 153.3, p: 0.812 },
    awan: { e: 149.3, p: 0.833 },
    grafiti: { e: 140.5, p: 0.886 },
    malam: { e: 149.7, p: 0.831 },
};

const urls = {};
for (const [path, url] of Object.entries(files)) {
    const id = path.match(/border-([a-z]+)\.svg$/)?.[1];
    if (id) urls[id] = url;
}

export function frameUrl(id) {
    return (id && urls[id]) || null;
}

/** Sizes for the avatar: photo share of the box, and picture width as a share of the box (photo sits 2% under the ring). */
export function frameFit(id) {
    const m = META[id] ?? META.default;
    return { photo: m.p * 100, width: (m.e / 102) * m.p * 100 };
}
