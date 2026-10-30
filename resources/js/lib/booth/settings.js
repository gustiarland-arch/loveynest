// The editor's settings, shared by the gallery, the camera and the editor so a choice made in one place
// is still there in the others. Look settings are remembered on this device; texts and photo crops are per picture.

import { reactive, watch } from 'vue';
import { DEFAULT_SETTINGS, NEUTRAL } from './engine';
import { themeByKey } from './themes';

const KEY = 'booth.settings.v1';

function load() {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) ?? 'null');
        if (saved && typeof saved === 'object') return saved;
    } catch {
        /* private mode or corrupt value: start fresh */
    }
    return {};
}

const saved = load();

export const booth = reactive({
    theme: saved.theme ?? 'pink-romance',
    preset: saved.preset ?? DEFAULT_SETTINGS.preset,
    intensity: saved.intensity ?? DEFAULT_SETTINGS.intensity,
    adjust: { ...NEUTRAL, ...(saved.adjust ?? {}) },
    stickers: { sets: saved.stickers?.sets ?? null, density: saved.stickers?.density ?? null, seed: 11 },
    title: '',
    sub: '',
    transforms: {},
});

watch(
    () => [booth.theme, booth.preset, booth.intensity, { ...booth.adjust }, { sets: booth.stickers.sets, density: booth.stickers.density }],
    () => {
        try {
            localStorage.setItem(KEY, JSON.stringify({ theme: booth.theme, preset: booth.preset, intensity: booth.intensity, adjust: booth.adjust, stickers: { sets: booth.stickers.sets, density: booth.stickers.density } }));
        } catch {
            /* ignore */
        }
    },
    { deep: true },
);

/** Look settings in the shape the engine expects. */
export const lookOf = (s = booth) => ({ preset: s.preset, intensity: s.intensity, adjust: { ...s.adjust } });

/** Picking a theme brings its own filter unless the person already chose a different one. */
export function chooseTheme(key) {
    const before = themeByKey(booth.theme);
    const after = themeByKey(key);
    if (booth.preset === before.preset.key && booth.intensity === before.preset.intensity) {
        booth.preset = after.preset.key;
        booth.intensity = after.preset.intensity;
    }
    booth.theme = after.key;
}

export function resetLook() {
    const t = themeByKey(booth.theme);
    booth.preset = t.preset.key;
    booth.intensity = t.preset.intensity;
    booth.adjust = { ...NEUTRAL };
}

export function resetPicture() {
    booth.title = '';
    booth.sub = '';
    booth.transforms = {};
    booth.stickers.seed = 11;
}
