// A short two-note chime made with the Web Audio API, so there is no sound file to download or host.
// Browsers only allow audio after the person has touched the page once; unlockAudio() runs on the first touch.
import peluk from '../../sounds/peluk.mp3';
import cium from '../../sounds/cium.mp3';
import kangen from '../../sounds/kangen.mp3';
import semangat from '../../sounds/semangat.mp3';
import selesai from '../../sounds/selesai.mp3';
import kabar from '../../sounds/ln-kabar.mp3';
import pengingat from '../../sounds/ln-pengingat.mp3';

const KEY = 'notif.sound';
let ctx = null;

export function soundOn() {
    try {
        return localStorage.getItem(KEY) !== 'off';
    } catch {
        return true;
    }
}

export function setSound(on) {
    try {
        localStorage.setItem(KEY, on ? 'on' : 'off');
    } catch {
        /* private mode: the choice just is not remembered */
    }
}

function context() {
    if (!ctx) {
        const Ctor = window.AudioContext || window.webkitAudioContext;
        if (!Ctor) return null;
        ctx = new Ctor();
    }
    return ctx;
}

export function unlockAudio() {
    const c = context();
    if (c && c.state === 'suspended') c.resume().catch(() => {});
}

/** @returns {boolean} true when the chime could be started */
export function playChime({ force = false } = {}) {
    if (!force && !soundOn()) return false;
    const c = context();
    if (!c) return false;
    if (c.state === 'suspended') c.resume().catch(() => {});
    if (c.state !== 'running') return false;

    const start = c.currentTime + 0.02;
    [[880, 0], [1318.5, 0.16], [1760, 0.32]].forEach(([freq, delay]) => {
        const osc = c.createOscillator();
        const gain = c.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, start + delay);
        gain.gain.exponentialRampToValueAtTime(0.22, start + delay + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + delay + 0.5);
        osc.connect(gain).connect(c.destination);
        osc.start(start + delay);
        osc.stop(start + delay + 0.55);
    });
    try {
        navigator.vibrate?.([120, 60, 120]);
    } catch {
        /* not supported */
    }
    return true;
}

if (typeof window !== 'undefined') {
    const once = () => {
        unlockAudio();
        window.removeEventListener('pointerdown', once);
        window.removeEventListener('keydown', once);
    };
    window.addEventListener('pointerdown', once, { passive: true });
    window.addEventListener('keydown', once);
}

// The LoveyNest sound pack: short tones made for the app (about 70 KB together), bundled with the screens.
export const sounds = { peluk, cium, kangen, semangat, selesai, kabar, pengingat };
const players = new Map();

/** Plays one sound of the pack (when sounds are on, or `force` for a preview). @returns {boolean} */
export function playSound(name, { force = false } = {}) {
    if ((!force && !soundOn()) || !sounds[name]) return false;
    try {
        let audio = players.get(name);
        if (!audio) {
            audio = new Audio(sounds[name]);
            audio.volume = 0.7;
            players.set(name, audio);
        }
        audio.currentTime = 0;
        audio.play()?.catch?.(() => {});
        return true;
    } catch {
        return false;
    }
}
