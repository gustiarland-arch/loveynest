// Hand-drawn pictures for the Arena, as SVG markup strings: a sticker set (64x64) and the two game characters.
// One look everywhere: a thick plum outline, flat colours, a small white shine and a kawaii face.
// The strings are static and written here (never user input), so they are safe to render with v-html.

export const INK = '#3b1d33';
const G = `stroke="${INK}" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"`;
const dot = (x, y, r = 2.3) => `<circle cx="${x}" cy="${y}" r="${r}" fill="${INK}" stroke="none"/><circle cx="${x + r * 0.35}" cy="${y - r * 0.4}" r="${r * 0.38}" fill="#fff" stroke="none"/>`;
const eyes = (cx, y, gap = 7, r = 2.3) => dot(cx - gap, y, r) + dot(cx + gap, y, r);
const smile = (cx, y, w = 3.2) => `<path d="M${cx - w} ${y}q${w} ${w * 1.1} ${w * 2} 0" fill="none" stroke-width="2.4"/>`;
const cheeks = (cx, y, gap = 12, c = '#ff8fa8') => `<ellipse cx="${cx - gap}" cy="${y}" rx="3.2" ry="2" fill="${c}" opacity=".7" stroke="none"/><ellipse cx="${cx + gap}" cy="${y}" rx="3.2" ry="2" fill="${c}" opacity=".7" stroke="none"/>`;
const shine = (d) => `<path d="${d}" fill="none" stroke="#fff" stroke-width="2.6" opacity=".75"/>`;

const parts = {
    bear: `
        <circle cx="16" cy="19" r="8" fill="#b9733f"/><circle cx="48" cy="19" r="8" fill="#b9733f"/>
        <circle cx="16" cy="19" r="3.5" fill="#f3b98a" stroke="none"/><circle cx="48" cy="19" r="3.5" fill="#f3b98a" stroke="none"/>
        <circle cx="32" cy="35" r="21" fill="#cf8650"/>
        <ellipse cx="32" cy="42" rx="9.5" ry="7" fill="#f6d2ad"/>
        <ellipse cx="32" cy="39" rx="3.4" ry="2.4" fill="${INK}" stroke="none"/>
        ${eyes(32, 31, 9, 2.6)}${smile(32, 44.5, 3)}${cheeks(32, 39, 16)}
        ${shine('M20 24q3-5 8-6')}`,
    cat: `
        <path d="M11 30 13 8l15 9Z" fill="#ffb86b"/><path d="M53 30 51 8 36 17Z" fill="#ffb86b"/>
        <path d="M15 24l.8-9 7 4.3Z" fill="#ff8fa8" stroke="none"/><path d="M49 24l-.8-9-7 4.3Z" fill="#ff8fa8" stroke="none"/>
        <ellipse cx="32" cy="37" rx="22" ry="19" fill="#ffb86b"/>
        <path d="M24 20q1 5-1.5 8M32 18v8M40 20q-1 5 1.5 8" fill="none" stroke="#e58a2e" stroke-width="2.6"/>
        ${eyes(32, 36, 10, 2.6)}
        <path d="M29.5 41.5h5l-2.5 3Z" fill="#ff6f91" stroke-width="2"/>
        <path d="M32 44.5v2M32 46.5q-3 2.5-6 .5M32 46.5q3 2.5 6 .5" fill="none" stroke-width="2.2"/>
        <path d="M8 40l-6-1.5M8 44l-6 2M56 40l6-1.5M56 44l6 2" fill="none" stroke-width="2"/>
        ${cheeks(32, 42, 16)}${shine('M18 30q2-5 7-6')}`,
    bunny: `
        <ellipse cx="22" cy="17" rx="7" ry="15" transform="rotate(-8 22 17)" fill="#fff"/><ellipse cx="42" cy="17" rx="7" ry="15" transform="rotate(8 42 17)" fill="#fff"/>
        <ellipse cx="22" cy="18" rx="3" ry="10" transform="rotate(-8 22 18)" fill="#ffb3cb" stroke="none"/><ellipse cx="42" cy="18" rx="3" ry="10" transform="rotate(8 42 18)" fill="#ffb3cb" stroke="none"/>
        <ellipse cx="32" cy="42" rx="21" ry="17" fill="#fff"/>
        ${eyes(32, 40, 9, 2.6)}
        <path d="M29.6 44.5h4.8l-2.4 2.8Z" fill="#ff6f91" stroke-width="2"/>
        <path d="M32 47.3q-3 3-6 .5M32 47.3q3 3 6 .5" fill="none" stroke-width="2.2"/>
        ${cheeks(32, 45, 15)}`,
    cupcake: `
        <path d="M13 36h38l-5 22q-.5 2-3 2H21q-2.500 0-3-2Z" fill="#ff8fb8"/>
        <path d="M24 38l2 20M32 38v22M40 38l-2 20" fill="none" stroke-width="2.4" opacity=".55"/>
        <path d="M10 36q0-7 7-8 0-9 9-10 2-8 10-8 9 1 9 9 8 1 9 9 0 8-6 8Z" fill="#fff1d2"/>
        <path d="M14 30q4 3 8 0t8 0 8 0 8 0" fill="none" stroke-width="2.4" stroke="#f2c87a"/>
        <circle cx="33" cy="8" r="4.6" fill="#ff4f6d"/><path d="M33 4q1-3 4-4" fill="none" stroke-width="2.4"/>
        ${eyes(32, 46, 7, 2.2)}${smile(32, 50, 2.6)}${cheeks(32, 49, 12)}`,
    boba: `
        <path d="M30 4l4 14" fill="none" stroke="#ff6f91" stroke-width="5"/><path d="M30 4l4 14" fill="none" stroke="${INK}" stroke-width="1.5" opacity=".0"/>
        <path d="M12 18h40l-5 38q-.5 4-4 4H21q-3.500 0-4-4Z" fill="#fff0e0"/>
        <path d="M14.500 30h35l-2.700 26q-.5 4-4 4H21q-3.500 0-4-4Z" fill="#e0b184" stroke="none"/>
        <path d="M12 18h40l-5 38q-.5 4-4 4H21q-3.500 0-4-4Z" fill="none"/>
        <rect x="9" y="12" width="46" height="8" rx="4" fill="#ffd1e1"/>
        <circle cx="24" cy="55" r="3.500" fill="#4a2c2a"/><circle cx="32" cy="56" r="3.500" fill="#4a2c2a"/><circle cx="40" cy="55" r="3.500" fill="#4a2c2a"/><circle cx="28" cy="51" r="3.200" fill="#4a2c2a"/><circle cx="36" cy="51.500" r="3.200" fill="#4a2c2a"/>
        ${eyes(32, 37, 8, 2.4)}${smile(32, 41, 2.8)}${cheeks(32, 40, 13)}${shine('M17 24l2 14')}`,
    icecream: `
        <path d="M18 34l14 28 14-28Z" fill="#f1b266"/>
        <path d="M23 40l12 14M32 36l8 16M26 48l-3-6" fill="none" stroke-width="2" opacity=".5"/>
        <circle cx="32" cy="26" r="16" fill="#ff9fc4"/>
        <path d="M16 31q0 6 5 6 2 5 6 2 4 4 8 0 5 3 7-2 6 0 6-6 0-4-4-5-10 3-28 0-4 1-4 5Z" fill="#ffe0ec"/>
        <circle cx="32" cy="9" r="4.400" fill="#ff4f6d"/>
        ${eyes(32, 24, 7, 2.3)}${smile(32, 28, 2.8)}${cheeks(32, 28, 12)}${shine('M21 18q3-5 8-6')}`,
    flower: `
        <g fill="#ffd0e0"><circle cx="32" cy="14" r="10"/><circle cx="50" cy="27" r="10"/><circle cx="43" cy="49" r="10"/><circle cx="21" cy="49" r="10"/><circle cx="14" cy="27" r="10"/></g>
        <circle cx="32" cy="34" r="14" fill="#ffd54a"/>
        ${eyes(32, 32, 6, 2.2)}${smile(32, 37, 2.6)}${cheeks(32, 37, 10)}${shine('M24 28q2-4 6-4.500')}`,
    star: `
        <path d="m32 4 8.400 17 18.600 2.700-13.500 13.200 3.200 18.600L32 46.700 15.300 55.500l3.200-18.600L5 23.700 23.600 21Z" fill="#ffd54a"/>
        ${eyes(32, 32, 7, 2.4)}${smile(32, 38, 2.8)}${cheeks(32, 37, 12, '#ff9a6b')}${shine('M22 20l5-9')}`,
    moon: `
        <path d="M44 6A27 27 0 1 0 58 42 22 22 0 0 1 44 6Z" fill="#ffe08a"/>
        <path d="M22 18q-5 4-6 11" fill="none" stroke="#fff" stroke-width="2.6" opacity=".7"/>
        <path d="M20 34q3-3 6 0M30 41q3-3 6 0" fill="none" stroke-width="2.4"/>
        <path d="M22 44q3 3 6 0" fill="none" stroke-width="2.4"/><ellipse cx="17" cy="42" rx="3.200" ry="2" fill="#ff8fa8" opacity=".7" stroke="none"/>
        <path d="m50 14 1.800 3.700 4 .6-2.900 2.800.7 4-3.600-1.900-3.600 1.900.7-4-2.900-2.800 4-.6Z" fill="#fff" stroke-width="2"/>`,
    rocket: `
        <path d="M32 3q14 10 14 28v14H18V31Q18 13 32 3Z" fill="#fff"/>
        <path d="M32 3q14 10 14 28H32Z" fill="#ff6f91" stroke="none" opacity=".0"/>
        <path d="M32 3q9 6 12 17H20q3-11 12-17Z" fill="#ff6f91"/>
        <circle cx="32" cy="30" r="7" fill="#8ed8ff"/><path d="M28 28q1.500-3 4-3" fill="none" stroke="#fff" stroke-width="2.4"/>
        <path d="M18 34 7 46l11 1Z" fill="#a98bff"/><path d="M46 34l11 12-11 1Z" fill="#a98bff"/>
        <path d="M24 45h16l-3 6H27Z" fill="#ffd54a"/><path d="M32 52q-6 4-3 10 3-2 3-4 0 2 3 4 3-6-3-10Z" fill="#ff9a3d"/>`,
    donut: `
        <circle cx="32" cy="32" r="26" fill="#e6a964"/>
        <path d="M32 10c-13 0-22 9-22 21s9 21 22 21 22-9 22-21-9-21-22-21Z" fill="#ff9fc4"/>
        <path d="M13 30q2 5 5 3 2 5 6 2 3 4 6 1 4 3 8-1 5 2 8-3 3 1 4-3" fill="none" stroke="#fff" stroke-width="2.200" opacity=".6"/>
        <circle cx="32" cy="32" r="8" fill="#fff5e8"/>
        <path d="M20 20l4 2M42 18l3 3M16 36l4-1M46 38l4 2M26 47l2-4M38 47l-1-4" fill="none" stroke-width="3" stroke="#fff"/>
        <path d="M24 17l3 1.500M40 22l-1 3.500M21 43l3-2M44 31l3 .5" fill="none" stroke-width="3" stroke="#6fd0ff"/>`,
    frog: `
        <circle cx="19" cy="20" r="10" fill="#7ed957"/><circle cx="45" cy="20" r="10" fill="#7ed957"/>
        <circle cx="19" cy="20" r="5" fill="#fff" stroke-width="2.400"/><circle cx="45" cy="20" r="5" fill="#fff" stroke-width="2.400"/>
        <circle cx="20" cy="21" r="2.500" fill="${INK}" stroke="none"/><circle cx="46" cy="21" r="2.500" fill="${INK}" stroke="none"/>
        <ellipse cx="32" cy="40" rx="25" ry="19" fill="#7ed957"/>
        <path d="M18 44q14 11 28 0" fill="none"/>
        <ellipse cx="14" cy="42" rx="4" ry="2.400" fill="#ff8fa8" opacity=".7" stroke="none"/><ellipse cx="50" cy="42" rx="4" ry="2.400" fill="#ff8fa8" opacity=".7" stroke="none"/>
        <circle cx="27" cy="35" r="1.400" fill="${INK}" stroke="none"/><circle cx="37" cy="35" r="1.400" fill="${INK}" stroke="none"/>`,
    mushroom: `
        <path d="M22 36h20l2 18q0 6-12 6t-12-6Z" fill="#fff4de"/>
        <path d="M4 36C4 16 16 4 32 4s28 12 28 32c0 3-3 4-6 4H10c-3 0-6-1-6-4Z" fill="#ff5c6e"/>
        <circle cx="19" cy="22" r="5" fill="#fff" stroke-width="2.400"/><circle cx="36" cy="14" r="4" fill="#fff" stroke-width="2.400"/><circle cx="47" cy="27" r="5.500" fill="#fff" stroke-width="2.400"/><circle cx="30" cy="30" r="3" fill="#fff" stroke-width="2.400"/>
        ${eyes(32, 47, 6, 2.2)}${smile(32, 51, 2.4)}${cheeks(32, 50, 10)}`,
    rainbow: `
        <path d="M6 46a26 26 0 0 1 52 0" fill="none" stroke="${INK}" stroke-width="15"/>
        <path d="M6 46a26 26 0 0 1 52 0" fill="none" stroke="#ff6f91" stroke-width="9"/>
        <path d="M13 46a19 19 0 0 1 38 0" fill="none" stroke="#ffd54a" stroke-width="5.500"/>
        <path d="M18 46a14 14 0 0 1 28 0" fill="none" stroke="#6fd0ff" stroke-width="4"/>
        <path d="M2 54q-2-8 6-8 1-5 7-4 4-3 8 1 6 0 6 6 4 0 4 5Z" fill="#fff"/>
        <path d="M36 54q-2-6 4-7 1-4 6-3 3-2 6 1 6 0 6 5 4 1 3 4Z" fill="#fff"/>`,
    letter: `
        <path d="M5 16h54v38H5Z" fill="#fff3f7"/>
        <path d="M5 16l27 22 27-22" fill="none"/>
        <path d="M5 54l20-20M59 54 39 34" fill="none" stroke-width="2.400" opacity=".5"/>
        <path d="M32 50s-9-5.400-9-12a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 6.600-9 12-9 12Z" fill="#ff5c8a" stroke-width="2.600"/>
        <path d="M32 33l-1 5" opacity="0"/>`,
    berry: `
        <path d="M32 58C14 48 8 34 10 24c2-8 10-9 22-9s20 1 22 9c2 10-4 24-22 34Z" fill="#ff5c6e"/>
        <path d="M20 16c3-6 9-9 12-4 3-5 9-2 12 4-6 2-18 2-24 0Z" fill="#6fd66f"/><path d="M32 13V6" fill="none"/>
        <g fill="#ffe7a8" stroke="none"><circle cx="19" cy="30" r="1.600"/><circle cx="45" cy="30" r="1.600"/><circle cx="23" cy="43" r="1.600"/><circle cx="41" cy="43" r="1.600"/><circle cx="32" cy="52" r="1.600"/></g>
        ${eyes(32, 33, 8, 2.4)}${smile(32, 38, 2.8)}${cheeks(32, 38, 14, '#ffb0a8')}${shine('M17 24q2-5 7-6')}`,
};

export const STICKER_NAMES = Object.keys(parts);

// Inner markup for one sticker; wrap it in <svg viewBox="0 0 64 64">.
export function sticker(name) {
    if (name === 'heart') return bits.heart();
    if (name === 'storm' || name === 'ball' || name === 'glove') return bits[name];
    return `<g ${G} fill="none">${parts[name] ?? parts.star}</g>`;
}

// The two characters of the Arena: a pink and a blue blob. mood: happy | cheer | sad | wink | think | oops
const TONES = {
    pink: { body: '#ffb0cb', dark: '#f27ca6', cheek: '#ff6f9c' },
    blue: { body: '#a5d8ff', dark: '#6cb4f0', cheek: '#ff8fb8' },
    mint: { body: '#a8efc9', dark: '#5fd49d', cheek: '#ff8fb8' },
    lilac: { body: '#cdbdff', dark: '#a38df0', cheek: '#ff8fb8' },
};
const mouth = {
    happy: `<path d="M42 64q8 9 16 0" fill="none" stroke-width="3"/>`,
    cheer: `<path d="M39 61h22c0 10-4 16-11 16s-11-6-11-16Z" fill="${INK}"/><path d="M44 72q6-4 12 0" fill="#ff6f91" stroke="none"/>`,
    sad: `<path d="M43 71q7-7 14 0" fill="none" stroke-width="3"/>`,
    wink: `<path d="M42 64q8 9 16 0" fill="none" stroke-width="3"/>`,
    think: `<path d="M44 68h12" fill="none" stroke-width="3"/>`,
    oops: `<ellipse cx="50" cy="70" rx="5" ry="6" fill="${INK}"/>`,
};

export function character(tone = 'pink', mood = 'happy') {
    const t = TONES[tone] ?? TONES.pink;
    const eyeL = mood === 'sad' ? `<path d="M28 52q6 4 12 0" fill="none" stroke-width="3"/>` : mood === 'cheer' ? `<path d="M28 54q6-9 12 0" fill="none" stroke-width="3"/>` : dot(34, 52, 4.200);
    const eyeR = mood === 'wink' ? `<path d="M60 54q6-9 12 0" fill="none" stroke-width="3"/>` : mood === 'sad' ? `<path d="M60 52q6 4 12 0" fill="none" stroke-width="3"/>` : mood === 'cheer' ? `<path d="M60 54q6-9 12 0" fill="none" stroke-width="3"/>` : dot(66, 52, 4.200);
    const arms = mood === 'cheer'
        ? `<path d="M12 56Q2 40 6 28" fill="none" stroke="${INK}" stroke-width="13"/><path d="M12 56Q2 40 6 28" fill="none" stroke="${t.dark}" stroke-width="7"/><path d="M88 56q10-16 6-28" fill="none" stroke="${INK}" stroke-width="13"/><path d="M88 56q10-16 6-28" fill="none" stroke="${t.dark}" stroke-width="7"/>`
        : `<ellipse cx="9" cy="70" rx="8" ry="13" fill="${t.dark}"/><ellipse cx="91" cy="70" rx="8" ry="13" fill="${t.dark}"/>`;
    return `<g ${G}>
        <ellipse cx="50" cy="104" rx="30" ry="5" fill="${INK}" opacity=".15" stroke="none"/>
        ${arms}
        <ellipse cx="34" cy="100" rx="11" ry="7" fill="${t.dark}"/><ellipse cx="66" cy="100" rx="11" ry="7" fill="${t.dark}"/>
        <path d="M8 66C8 28 28 8 50 8s42 20 42 58c0 24-17 34-42 34S8 90 8 66Z" fill="${t.body}"/>
        <path d="M21 36c6-11 16-16 27-17" fill="none" stroke="#fff" stroke-width="5" opacity=".7"/>
        <ellipse cx="22" cy="64" rx="7" ry="4.500" fill="${t.cheek}" opacity=".6" stroke="none"/><ellipse cx="78" cy="64" rx="7" ry="4.500" fill="${t.cheek}" opacity=".6" stroke="none"/>
        ${eyeL}${eyeR}${mouth[mood] ?? mouth.happy}
        ${mood === 'think' ? `<path d="M80 22q4-8 12-4" fill="none" stroke-width="3"/><circle cx="94" cy="14" r="2.500" fill="${INK}"/>` : ''}
        ${mood === 'sad' ? `<path d="M78 60c2 4 4 6 4 9a4 4 0 1 1-8 0c0-3 2-5 4-9Z" fill="#8fd3ff" stroke-width="2"/>` : ''}
    </g>`;
}

// Little extras used inside scenes and games.
export const bits = {
    heart: (c = '#ff5c8a') => `<g ${G}><path d="M32 56S6 40 6 22a13 13 0 0 1 26-5 13 13 0 0 1 26 5c0 18-26 34-26 34Z" fill="${c}"/><path d="M16 18q2-6 8-7" fill="none" stroke="#fff" stroke-width="3" opacity=".8"/></g>`,
    starBig: (c = '#ffd54a') => `<g ${G}><path d="m32 4 8.400 17 18.600 2.700-13.500 13.200 3.200 18.600L32 46.700 15.300 55.500l3.200-18.600L5 23.700 23.600 21Z" fill="${c}"/></g>`,
    // An angry little storm cloud (the thing to dodge in Tangkap Hati).
    storm: `<g ${G}><path d="M14 44q-10 0-10-9t9-10q2-11 14-11 8 0 12 7 4-3 9-1 8 3 7 12 9 0 9 8t-9 4Z" fill="#7d86a8"/><path d="M16 22q3-5 9-6" fill="none" stroke="#fff" stroke-width="2.600" opacity=".5"/><path d="M22 30l8 3M46 30l-8 3" fill="none" stroke-width="3"/><circle cx="26" cy="37" r="2.200" fill="${INK}" stroke="none"/><circle cx="42" cy="37" r="2.200" fill="${INK}" stroke="none"/><path d="M28 44q6-4 12 0" fill="none" stroke-width="2.600"/><path d="M34 46l-5 9h6l-4 8 10-12h-6l3-5Z" fill="#ffd54a" stroke-width="2"/></g>`,
    ball: `<g ${G}><circle cx="32" cy="32" r="26" fill="#fff"/><path d="m32 20 11 8-4 13H25l-4-13Z" fill="${INK}"/><path d="M32 6v14M43 28l15-5M39 41l8 13M25 41l-8 13M21 28 6 23" fill="none" stroke-width="2.600"/></g>`,
    glove: `<g ${G}><path d="M16 60V30q0-6 5-6t5 6V16q0-6 5-6t5 6v-2q0-6 5-6t5 6v8q0-4 5-4t5 5v22q0 14-14 14H28q-12 0-12-14Z" fill="#ffd54a"/><path d="M20 52h28" fill="none" stroke-width="2.600"/></g>`,
};

// ───────────── Scenes: full-screen backdrops, drawn on a 400x800 canvas and cropped to fit the phone ─────────────
const cloud = (x, y, s = 1, c = '#fff') => `<g transform="translate(${x} ${y}) scale(${s})" fill="${c}" stroke="none"><ellipse cx="30" cy="30" rx="30" ry="18"/><ellipse cx="58" cy="22" rx="24" ry="20"/><ellipse cx="84" cy="32" rx="26" ry="16"/><rect x="10" y="30" width="90" height="18" rx="9"/></g>`;
const bunting = (y, colors) => {
    let flags = '';
    for (let i = 0; i < 9; i++) {
        const x = i * 46 - 4;
        const sag = Math.sin((i / 8) * Math.PI) * 18;
        flags += `<path d="M${x + 6} ${y + sag - 1}l17 0-8.500 22Z" fill="${colors[i % colors.length]}" stroke="${INK}" stroke-width="2.400" stroke-linejoin="round"/>`;
    }
    return `<path d="M-4 ${y - 1}Q200 ${y + 22} 404 ${y - 1}" fill="none" stroke="${INK}" stroke-width="2.400"/>${flags}`;
};
const dots = (id, c1, c2, size = 22) => `<pattern id="${id}" width="${size}" height="${size}" patternUnits="userSpaceOnUse"><rect width="${size}" height="${size}" fill="${c1}"/><circle cx="${size / 2}" cy="${size / 2}" r="${size * 0.14}" fill="${c2}"/></pattern>`;
const tinyStar = (x, y, s, c = '#fff') => `<path transform="translate(${x} ${y}) scale(${s})" d="m0-6 1.600 4.200 4.400.400-3.400 3 1 4.400L0 3.600-3.600 6.400l1-4.400-3.400-3 4.400-.4Z" fill="${c}" stroke="none"/>`;

const scenes = {
    hub: () => `
        <rect width="400" height="800" fill="#bfe8ff"/>
        <defs><linearGradient id="hg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d9f2ff" stop-opacity="0"/><stop offset="1" stop-color="#d9f2ff"/></linearGradient></defs><rect y="120" width="400" height="400" fill="url(#hg)"/><rect y="520" width="400" height="280" fill="#d9f2ff"/>
        <circle cx="330" cy="120" r="48" fill="#ffe27a"/><circle cx="330" cy="120" r="62" fill="#ffe27a" opacity=".3"/>
        ${cloud(20, 90, 1)}${cloud(220, 190, .8)}${cloud(-20, 300, .9, '#f2fbff')}
        <path d="M-10 560Q80 480 190 540T410 500V800H-10Z" fill="#9be58f"/>
        <path d="M-10 640Q120 560 250 620T410 600V800H-10Z" fill="#7bd67f"/>
        <path d="M-10 720Q100 670 220 710T410 690V800H-10Z" fill="#5fc76e"/>
        ${bunting(14, ['#ff7aa2', '#ffd54a', '#6fd0ff', '#a98bff'])}
        <g fill="#fff" stroke="none"><circle cx="60" cy="600" r="5"/><circle cx="320" cy="660" r="5"/><circle cx="150" cy="740" r="5"/><circle cx="360" cy="560" r="4"/></g>
        <g fill="#ffd54a" stroke="none"><circle cx="60" cy="600" r="2"/><circle cx="320" cy="660" r="2"/><circle cx="150" cy="740" r="2"/><circle cx="360" cy="560" r="1.600"/></g>`,
    kartu: () => `
        <defs>
            <pattern id="pk" width="64" height="64" patternUnits="userSpaceOnUse"><rect width="64" height="64" fill="#fff4f6"/><rect width="32" height="32" fill="#ffd0de"/><rect x="32" y="32" width="32" height="32" fill="#ffd0de"/><rect x="0" y="32" width="32" height="32" fill="#ffe6ec"/><rect x="32" y="0" width="32" height="32" fill="#ffe6ec"/></pattern>
        </defs>
        <rect width="400" height="800" fill="url(#pk)"/>
        <rect width="400" height="800" fill="#ffb6cf" opacity=".12"/>
        <g opacity=".0"></g>
        <path d="M-4 0H404V34Q380 46 356 34T308 34 260 34 212 34 164 34 116 34 68 34 20 34 -4 34Z" fill="#fff" stroke="${INK}" stroke-width="2.400" stroke-linejoin="round" opacity=".0"/>`,
    puzzle: () => `
        <defs>
            <pattern id="pp" width="32" height="32" patternUnits="userSpaceOnUse"><rect width="32" height="32" fill="#fbfdff"/><path d="M32 0H0V32" fill="none" stroke="#c9e2fb" stroke-width="1.600"/></pattern>
        </defs>
        <rect width="400" height="800" fill="url(#pp)"/>
        <path d="M44 0V800" stroke="#ff9db8" stroke-width="2" opacity=".7"/>
        <g fill="#dbeeff" stroke="none"><circle cx="18" cy="120" r="7"/><circle cx="18" cy="360" r="7"/><circle cx="18" cy="600" r="7"/></g>
        <rect x="300" y="14" width="86" height="26" rx="4" fill="#ffd54a" opacity=".85" transform="rotate(6 343 27)"/>
        <g transform="translate(332 706) rotate(24)"><rect x="-6" y="-60" width="12" height="96" rx="3" fill="#ff8fb8" stroke="${INK}" stroke-width="2.400"/><path d="M-6 36h12l-6 14Z" fill="#ffe0b8" stroke="${INK}" stroke-width="2.400" stroke-linejoin="round"/><path d="M-3 46h6l-3 4Z" fill="${INK}"/><rect x="-6" y="-66" width="12" height="9" rx="3" fill="#cfd8e3" stroke="${INK}" stroke-width="2.400"/></g>
        ${tinyStar(70, 90, 2.600, '#ffd54a')}${tinyStar(350, 300, 2, '#ffb0cb')}${tinyStar(80, 700, 2.200, '#8ed8ff')}`,
    tangkap: () => `
        <rect width="400" height="800" fill="#7fd3ff"/>
        <defs><linearGradient id="tg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6bc7fb"/><stop offset="1" stop-color="#6bc7fb" stop-opacity="0"/></linearGradient></defs><rect width="400" height="420" fill="url(#tg)"/>
        <circle cx="70" cy="110" r="38" fill="#fff2a0"/><circle cx="70" cy="110" r="54" fill="#fff2a0" opacity=".3"/>
        <g class="ar-drift-a">${cloud(150, 70, 1)}</g><g class="ar-drift-b">${cloud(-30, 220, .85, '#f4fbff')}</g><g class="ar-drift-a">${cloud(250, 340, .7, '#f4fbff')}</g>
        <path d="M-10 640Q70 560 170 620T400 590V800H-10Z" fill="#8fdf8f"/>
        <path d="M-10 700Q130 640 260 690T410 680V800H-10Z" fill="#6bcf74"/>
        <path d="M-10 770Q120 730 240 760T410 750V800H-10Z" fill="#53bf63"/>
        <g stroke="#3da356" stroke-width="3" stroke-linecap="round" fill="none"><path d="M40 720v-18M46 724l4-16M34 724l-4-14M300 760v-16M306 762l3-14M294 762l-3-14"/></g>`,
    ngobrol: () => `
        <rect width="400" height="800" fill="#4a2a6e"/>
        <defs><linearGradient id="ng" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5a3482" stop-opacity="0"/><stop offset="1" stop-color="#5a3482"/></linearGradient></defs><rect y="260" width="400" height="300" fill="url(#ng)"/><rect y="560" width="400" height="240" fill="#5a3482"/>
        <g>${[[40, 70], [120, 150], [330, 90], [260, 220], [60, 320], [350, 360], [190, 60], [300, 40], [20, 200]].map(([x, y], i) => tinyStar(x, y, 1.600 + (i % 3) * .5, i % 2 ? '#ffe9a8' : '#fff')).join('')}</g>
        <path d="M-4 34Q100 80 200 40T404 36" fill="none" stroke="${INK}" stroke-width="2.400"/>
        ${[24, 84, 150, 214, 280, 346].map((x, i) => { const y = 34 + Math.sin(((x + 8) / 400) * Math.PI * 2 + 3.800) * 7 + (x > 200 ? 18 : 18); const col = ['#ff7aa2', '#ffd54a', '#6fd0ff', '#a8efc9', '#ff9a6b', '#cdbdff'][i]; return `<circle cx="${x}" cy="${y + 12}" r="9" fill="${col}" stroke="${INK}" stroke-width="2.400"/><circle cx="${x}" cy="${y + 12}" r="16" fill="${col}" opacity=".18"/>`; }).join('')}
        <path d="M-10 640Q100 560 220 620T410 590V800H-10Z" fill="#3d2260"/>
        <g transform="translate(40 540)"><path d="M0 70 40 0l40 70Z" fill="#ff9a6b" stroke="${INK}" stroke-width="3" stroke-linejoin="round"/><path d="M40 0v70M40 70 22 70 40 24 58 70Z" fill="#ffd9b8" stroke="${INK}" stroke-width="2.400" stroke-linejoin="round"/></g>
        <g transform="translate(300 600)"><path d="M0 40q20-60 40 0Z" fill="#ff7a3d"/><path d="M8 40q12-34 24 0Z" fill="#ffd54a"/><path d="M-6 44l52 6M46 44l-52 6" stroke="#8a5a3a" stroke-width="5" stroke-linecap="round"/></g>`,
    penalti: () => `
        <rect width="400" height="800" fill="#9ad9ff"/>
        <rect y="150" width="400" height="190" fill="#6a4a8c"/>
        <g>${Array.from({ length: 3 }, (_, row) => Array.from({ length: 14 }, (_, i) => { const x = i * 30 + (row % 2) * 14 - 4; const y = 170 + row * 46; const cols = ['#ff7aa2', '#ffd54a', '#6fd0ff', '#a8efc9', '#cdbdff', '#ff9a6b']; const c = cols[(i * 3 + row * 5) % 6]; return `<circle cx="${x + 14}" cy="${y + 8}" r="11" fill="#ffd9b8" stroke="${INK}" stroke-width="2"/><path d="M${x + 3} ${y + 40}q0-18 11-18t11 18Z" fill="${c}" stroke="${INK}" stroke-width="2"/>`; }).join('')).join('')}</g>
        <rect y="320" width="400" height="26" fill="#ff7aa2"/><rect y="320" width="400" height="26" fill="url(#pa)" opacity=".0"/>
        <g fill="#fff" stroke="none" font-family="Lilita One, sans-serif" font-size="16"><text x="14" y="338">LOVEYNEST ♥ ARENA ♥ LOVEYNEST ♥ ARENA ♥ LOVEYNEST</text></g>
        <rect y="346" width="400" height="454" fill="#58c76b"/>
        ${Array.from({ length: 8 }, (_, i) => `<rect y="${346 + i * 60}" width="400" height="30" fill="#4fbd62"/>`).join('')}
        <g transform="translate(300 40)">${cloud(0, 0, .7)}</g>${cloud(10, 60, .6)}`,
};

export function scene(name) {
    return (scenes[name] ?? scenes.hub)();
}

// A full picture (600x800) for Puzzle Momen when there is no photo: the two of you on a hill. `variant` picks the weather.
export function puzzlePicture(variant = 0) {
    const v = ((variant % 3) + 3) % 3;
    const skies = ['#9fdcff', '#ffc2d6', '#c9b8ff'];
    const hills = [['#9be58f', '#7bd67f', '#5fc76e'], ['#ffd88a', '#ffc16b', '#ffa94d'], ['#8fe0c0', '#6acfa6', '#4fbf8f']][v];
    const sun = ['#ffe27a', '#fff2a0', '#ffe9a8'][v];
    const left = ['pink', 'blue', 'mint'][v];
    const right = ['blue', 'lilac', 'pink'][v];
    const stick = [['rainbow', 'cupcake', 'star'], ['moon', 'boba', 'flower'], ['rocket', 'donut', 'cat']][v];
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 800" width="600" height="800">
        <rect width="600" height="800" fill="${skies[v]}"/>
        <rect y="380" width="600" height="420" fill="#fff" opacity=".16"/>
        <circle cx="470" cy="150" r="78" fill="${sun}"/><circle cx="470" cy="150" r="104" fill="${sun}" opacity=".3"/>
        ${cloud(40, 110, 1.4)}${cloud(300, 290, 1.1)}${cloud(-30, 420, 1)}
        <path d="M-10 560Q150 440 320 540T620 500V800H-10Z" fill="${hills[0]}"/>
        <path d="M-10 650Q200 560 400 640T620 610V800H-10Z" fill="${hills[1]}"/>
        <path d="M-10 740Q150 690 330 735T620 715V800H-10Z" fill="${hills[2]}"/>
        <g transform="translate(60 40) scale(1.1)">${`<svg x="0" y="0" width="64" height="64" viewBox="0 0 64 64">${sticker(stick[0])}</svg>`}</g>
        <g transform="translate(150 480) scale(2.5)">${character(left, 'cheer')}</g>
        <g transform="translate(320 500) scale(2.5)"><g transform="translate(100 0) scale(-1 1)">${character(right, 'happy')}</g></g>
        <g transform="translate(262 330) scale(2.4)">${bits.heart()}</g>
        <g transform="translate(395 392) scale(1.2)">${bits.heart('#ffb0cb')}</g>
        <g transform="translate(36 650) scale(2)"><svg width="64" height="64" viewBox="0 0 64 64">${sticker(stick[1])}</svg></g>
        <g transform="translate(470 660) scale(1.9)"><svg width="64" height="64" viewBox="0 0 64 64">${sticker(stick[2])}</svg></g>
    </svg>`;
}
