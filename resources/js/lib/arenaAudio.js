// Music and sound effects for the Arena, made live with the Web Audio API: there are no audio files to download.
// The tunes are short original loops. Browsers only start audio after a tap, so every game begins with a button.

const KEY = 'arena.music';
const SFX_KEY = 'arena.sfx';

let ctx = null;
let master = null;
let musicBus = null;
let sfxBus = null;
let noiseBuffer = null;
let timer = null;
let playing = null; // { track, step, nextTime }

function read(key) {
    try {
        return localStorage.getItem(key) !== 'off';
    } catch {
        return true;
    }
}

function write(key, on) {
    try {
        localStorage.setItem(key, on ? 'on' : 'off');
    } catch {
        /* private mode: the choice just is not remembered */
    }
}

export const musicOn = () => read(KEY);
export const sfxOn = () => read(SFX_KEY);

function context() {
    if (ctx) return ctx;
    const Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return null;
    try {
        ctx = new Ctor();
    } catch {
        return null;
    }
    master = ctx.createGain();
    master.gain.value = 0.9;
    master.connect(ctx.destination);
    musicBus = ctx.createGain();
    musicBus.gain.value = 0.2;
    musicBus.connect(master);
    sfxBus = ctx.createGain();
    sfxBus.gain.value = 0.5;
    sfxBus.connect(master);
    const length = ctx.sampleRate * 0.5;
    noiseBuffer = ctx.createBuffer(1, length, ctx.sampleRate);
    const data = noiseBuffer.getChannelData(0);
    for (let i = 0; i < length; i++) data[i] = Math.random() * 2 - 1;
    return ctx;
}

/** Call from a tap or click: lets the browser start sound. */
export function unlock() {
    const c = context();
    if (c && c.state === 'suspended') c.resume().catch(() => {});
    return !!c;
}

const NAMES = { c: 0, d: 2, e: 4, f: 5, g: 7, a: 9, b: 11 };
function midi(token) {
    const m = /^([a-g])([#b]?)(\d)$/.exec(token);
    if (!m) return null;
    return 12 * (Number(m[3]) + 1) + NAMES[m[1]] + (m[2] === '#' ? 1 : m[2] === 'b' ? -1 : 0);
}
const hz = (n) => 440 * Math.pow(2, (n - 69) / 12);
const row = (text) => text.trim().split(/\s+/).map((t) => (t === '.' ? null : midi(t)));

function voice(freq, when, length, { type = 'triangle', volume = 0.5, bus = musicBus, slideTo = null, attack = 0.01 } = {}) {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, when);
    if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, when + length);
    gain.gain.setValueAtTime(0.0001, when);
    gain.gain.exponentialRampToValueAtTime(volume, when + attack);
    gain.gain.exponentialRampToValueAtTime(0.0001, when + length);
    osc.connect(gain).connect(bus);
    osc.start(when);
    osc.stop(when + length + 0.05);
}

function hat(when, volume = 0.25, length = 0.05) {
    const src = ctx.createBufferSource();
    const gain = ctx.createGain();
    const filter = ctx.createBiquadFilter();
    filter.type = 'highpass';
    filter.frequency.value = 6000;
    src.buffer = noiseBuffer;
    gain.gain.setValueAtTime(volume, when);
    gain.gain.exponentialRampToValueAtTime(0.0001, when + length);
    src.connect(filter).connect(gain).connect(musicBus);
    src.start(when);
    src.stop(when + length + 0.02);
}

// Each tune: 16 steps per bar, bars in order. mel = tune, bass = low notes, '.' is a rest.
const TRACKS = {
    hub: {
        bpm: 112, swing: 0, lead: 'triangle',
        bars: [
            { mel: 'e5 . g5 . c6 . g5 . e5 . d5 . c5 . . .', bass: 'c3 . . . c3 . . . g2 . . . g2 . . .' },
            { mel: 'd5 . f5 . a5 . f5 . d5 . c5 . b4 . . .', bass: 'd3 . . . d3 . . . a2 . . . a2 . . .' },
            { mel: 'e5 . g5 . c6 . d6 . e6 . d6 . c6 . g5 .', bass: 'c3 . . . e3 . . . g3 . . . e3 . . .' },
            { mel: 'a5 . g5 . f5 . e5 . d5 . . . c5 . . .', bass: 'f2 . . . g2 . . . c3 . . . c3 . . .' },
        ],
    },
    kartu: {
        bpm: 124, swing: 0.1, lead: 'square',
        bars: [
            { mel: 'g5 . e5 g5 . c6 . g5 a5 . g5 . e5 . . .', bass: 'c3 . c3 . g2 . g2 . c3 . c3 . g2 . g2 .' },
            { mel: 'f5 . a5 f5 . d6 . a5 g5 . f5 . d5 . . .', bass: 'f2 . f2 . c3 . c3 . f2 . f2 . c3 . c3 .' },
            { mel: 'e5 g5 c6 . e6 . c6 g5 a5 . c6 . a5 g5 e5 .', bass: 'a2 . a2 . e3 . e3 . a2 . a2 . e3 . e3 .' },
            { mel: 'd5 . f5 . g5 . b5 . c6 . . . . . . .', bass: 'g2 . g2 . g2 . g2 . c3 . . . c3 . . .' },
        ],
    },
    puzzle: {
        bpm: 92, swing: 0, lead: 'sine',
        bars: [
            { mel: 'c5 . . e5 . . g5 . . e5 . . d5 . . .', bass: 'c3 . . . . . g2 . . . . . . . . .' },
            { mel: 'a4 . . c5 . . e5 . . c5 . . b4 . . .', bass: 'a2 . . . . . e3 . . . . . . . . .' },
            { mel: 'f4 . . a4 . . c5 . . a4 . . g4 . . .', bass: 'f2 . . . . . c3 . . . . . . . . .' },
            { mel: 'g4 . . b4 . . d5 . . g5 . . e5 . . .', bass: 'g2 . . . . . d3 . . . . . c3 . . .' },
        ],
    },
    tangkap: {
        bpm: 150, swing: 0, lead: 'square',
        bars: [
            { mel: 'c6 . g5 . c6 . e6 . d6 . g5 . d6 . f6 .', bass: 'c3 . c3 c3 c3 . c3 c3 g2 . g2 g2 g2 . g2 g2' },
            { mel: 'e6 . c6 . e6 . g6 . f6 . d6 . f6 . a6 .', bass: 'a2 . a2 a2 a2 . a2 a2 f2 . f2 f2 f2 . f2 f2' },
            { mel: 'g6 . e6 . c6 . e6 . d6 . b5 . g5 . b5 .', bass: 'c3 . c3 c3 c3 . c3 c3 g2 . g2 g2 g2 . g2 g2' },
            { mel: 'a5 . c6 . f6 . e6 . d6 . . . c6 . . .', bass: 'f2 . f2 f2 g2 . g2 g2 c3 . . . c3 . . .' },
        ],
    },
    ngobrol: {
        bpm: 80, swing: 0, lead: 'sine',
        bars: [
            { mel: 'e5 . . . g5 . . . a5 . . . g5 . . .', bass: 'c3 . . . . . . . a2 . . . . . . .' },
            { mel: 'f5 . . . a5 . . . c6 . . . a5 . . .', bass: 'f2 . . . . . . . c3 . . . . . . .' },
            { mel: 'g5 . . . e5 . . . d5 . . . e5 . . .', bass: 'e3 . . . . . . . g2 . . . . . . .' },
            { mel: 'd5 . . . e5 . . . c5 . . . . . . .', bass: 'g2 . . . . . . . c3 . . . . . . .' },
        ],
    },
    penalti: {
        bpm: 132, swing: 0, lead: 'square',
        bars: [
            { mel: 'c5 . c5 . g5 . g5 . a5 . a5 . g5 . . .', bass: 'c3 . . c3 . . c3 . f2 . . f2 . . f2 .' },
            { mel: 'f5 . f5 . e5 . e5 . d5 . d5 . g5 . . .', bass: 'f2 . . f2 . . f2 . g2 . . g2 . . g2 .' },
            { mel: 'c5 . c5 . g5 . g5 . a5 . c6 . a5 . g5 .', bass: 'c3 . . c3 . . c3 . a2 . . a2 . . a2 .' },
            { mel: 'g5 . f5 . e5 . d5 . c5 . . . . . . .', bass: 'g2 . . g2 . . g2 . c3 . . . c3 . . .' },
        ],
    },
};

function schedule() {
    if (!playing || !ctx) return;
    const track = TRACKS[playing.track];
    const stepLength = 60 / track.bpm / 4;
    while (playing.nextTime < ctx.currentTime + 0.25) {
        const bar = track.bars[Math.floor(playing.step / 16) % track.bars.length];
        const at = playing.step % 16;
        const mel = bar.mel_parsed ?? (bar.mel_parsed = row(bar.mel));
        const bass = bar.bass_parsed ?? (bar.bass_parsed = row(bar.bass));
        const swing = at % 2 === 1 ? track.swing * stepLength : 0;
        const when = playing.nextTime + swing;
        if (mel[at] != null) voice(hz(mel[at]), when, stepLength * 1.8, { type: track.lead, volume: track.lead === 'square' ? 0.22 : 0.5 });
        if (bass[at] != null) voice(hz(bass[at]), when, stepLength * 2.4, { type: 'triangle', volume: 0.55 });
        if (at % 4 === 2) hat(when, 0.18);
        playing.nextTime += stepLength;
        playing.step++;
    }
}

export function startMusic(name) {
    if (!TRACKS[name]) return;
    if (playing?.track === name) return;
    stopMusic();
    if (!musicOn() || !unlock()) return;
    playing = { track: name, step: 0, nextTime: ctx.currentTime + 0.1 };
    schedule();
    timer = setInterval(schedule, 80);
}

export function stopMusic() {
    clearInterval(timer);
    timer = null;
    playing = null;
}

export function setMusic(on, resumeTrack) {
    write(KEY, on);
    if (!on) stopMusic();
    else if (resumeTrack) startMusic(resumeTrack);
}

export function setSfx(on) {
    write(SFX_KEY, on);
}

const EFFECTS = {
    pop: (t) => voice(520, t, 0.12, { type: 'sine', bus: sfxBus, slideTo: 880 }),
    tap: (t) => voice(660, t, 0.07, { type: 'triangle', bus: sfxBus }),
    flip: (t) => voice(420, t, 0.09, { type: 'triangle', bus: sfxBus, slideTo: 640 }),
    match: (t) => { voice(660, t, 0.14, { type: 'sine', bus: sfxBus }); voice(990, t + 0.1, 0.22, { type: 'sine', bus: sfxBus }); },
    wrong: (t) => voice(300, t, 0.22, { type: 'sine', bus: sfxBus, slideTo: 200 }),
    swap: (t) => { voice(500, t, 0.08, { type: 'triangle', bus: sfxBus, slideTo: 700 }); },
    place: (t) => { voice(784, t, 0.12, { type: 'sine', bus: sfxBus }); voice(1175, t + 0.08, 0.2, { type: 'sine', bus: sfxBus }); },
    tick: (t) => voice(800, t, 0.09, { type: 'square', volume: 0.25, bus: sfxBus }),
    go: (t) => voice(1200, t, 0.35, { type: 'square', volume: 0.3, bus: sfxBus }),
    heart: (t) => { voice(988, t, 0.1, { type: 'sine', bus: sfxBus }); voice(1319, t + 0.06, 0.16, { type: 'sine', bus: sfxBus }); },
    star: (t) => [1047, 1319, 1568, 2093].forEach((f, i) => voice(f, t + i * 0.06, 0.2, { type: 'sine', bus: sfxBus })),
    bad: (t) => voice(180, t, 0.3, { type: 'sawtooth', volume: 0.3, bus: sfxBus, slideTo: 90 }),
    kick: (t) => { voice(140, t, 0.16, { type: 'sine', volume: 0.9, bus: sfxBus, slideTo: 50 }); },
    goal: (t) => [523, 659, 784, 1047, 1319].forEach((f, i) => voice(f, t + i * 0.07, 0.25, { type: 'triangle', bus: sfxBus })),
    save: (t) => { voice(220, t, 0.18, { type: 'square', volume: 0.3, bus: sfxBus }); voice(165, t + 0.12, 0.3, { type: 'square', volume: 0.3, bus: sfxBus }); },
    whistle: (t) => voice(2200, t, 0.35, { type: 'sine', volume: 0.35, bus: sfxBus, slideTo: 2600 }),
    win: (t) => [523, 659, 784, 1047, 784, 1047, 1319].forEach((f, i) => voice(f, t + i * 0.11, 0.3, { type: 'triangle', bus: sfxBus })),
    lose: (t) => [392, 349, 330, 262].forEach((f, i) => voice(f, t + i * 0.16, 0.3, { type: 'triangle', bus: sfxBus })),
    draw: (t) => [523, 523, 659].forEach((f, i) => voice(f, t + i * 0.12, 0.22, { type: 'triangle', bus: sfxBus })),
    invite: (t) => [784, 988, 1175, 988, 1175, 1568].forEach((f, i) => voice(f, t + i * 0.1, 0.22, { type: 'sine', bus: sfxBus })),
};

export function sfx(name) {
    if (!sfxOn() || !EFFECTS[name]) return;
    const c = context();
    if (!c || c.state !== 'running') return;
    try {
        EFFECTS[name](c.currentTime + 0.01);
    } catch {
        /* a sound that cannot play is not worth an error */
    }
}

// The music rests while the tab is hidden.
if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        if (!ctx) return;
        if (document.visibilityState === 'hidden') ctx.suspend().catch(() => {});
        else if (playing || sfxOn()) ctx.resume().catch(() => {});
    });
}
