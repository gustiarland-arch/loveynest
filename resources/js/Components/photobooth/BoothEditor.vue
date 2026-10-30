<script setup>
import { ref, shallowRef, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Eye, RotateCcw, Undo2, Save, Shuffle, Maximize2, FlipHorizontal2, LoaderCircle, TriangleAlert, ScanFace } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import SliderField from '@/Components/ui/SliderField.vue';
import TextField from '@/Components/ui/TextField.vue';
import ThemeCard from '@/Components/photobooth/ThemeCard.vue';
import FilterStrip from '@/Components/photobooth/FilterStrip.vue';
import { THEMES, STICKER_SETS, themeByKey } from '@/lib/booth/themes';
import { ADJUST_FIELDS, EFFECT_FIELDS, SKIN_FIELDS, NEUTRAL } from '@/lib/booth/engine';
import { composeBooth, ensureFonts } from '@/lib/booth/draw';
import { loadShot, renderPhoto, toJpeg, DEFAULT_TRANSFORM } from '@/lib/booth/pipeline';
import { faceDetectionAvailable } from '@/lib/booth/faces';
import { booth, lookOf, chooseTheme, resetLook, resetPicture } from '@/lib/booth/settings';
import { t } from '@/i18n';

// Editor for a finished round: theme, filter, adjustments, skin retouch, stickers and per-photo crop.
// Photos stay as the camera took them; every change is drawn again from those originals.
const props = defineProps({
    session: { type: Object, required: true }, // { layout, count, slots[{url}] }
    names: { type: Array, required: true },
    date: { type: Date, required: true },
    saving: Boolean,
});
const emit = defineEmits(['save']);

const PREVIEW_SIZE = 360;
const EXPORT_SIZE = 640;

const raws = shallowRef([]);
const status = ref('loading'); // loading | ready | failed
const working = ref(false);
const preview = ref(null);
const tab = ref('theme');
const selected = ref(0);
const comparing = ref(false);
const full = ref(false);
const fullUrl = ref(null);
const exporting = ref(false);
const exportError = ref(null);
let token = 0;
let timer = null;
let initial = null;

const tabs = computed(() => ['theme', 'filter', 'adjust', 'skin', 'decor', 'photo'].map((value) => ({ value, label: t(`booth.tabs.${value}`) })));
const theme = computed(() => themeByKey(booth.theme));
const names = computed(() => props.names.filter(Boolean));
const faces = faceDetectionAvailable();

const NEUTRAL_LOOK = { preset: 'original', intensity: 100, adjust: { ...NEUTRAL } };
const tf = (i) => ({ ...DEFAULT_TRANSFORM, ...(booth.transforms[i] ?? {}) });

function setT(key, value) {
    booth.transforms = { ...booth.transforms, [selected.value]: { ...tf(selected.value), [key]: value } };
}
const zoom = computed({ get: () => Math.round(tf(selected.value).zoom * 100), set: (v) => setT('zoom', v / 100) });
const panX = computed({ get: () => Math.round(tf(selected.value).x * 100), set: (v) => setT('x', v / 100) });
const panY = computed({ get: () => Math.round(tf(selected.value).y * 100), set: (v) => setT('y', v / 100) });
const rot = computed({ get: () => tf(selected.value).rot, set: (v) => setT('rot', v) });
const mirror = computed(() => tf(selected.value).mirror);

const stickerSets = computed(() => booth.stickers.sets ?? theme.value.ornaments.sets);
const density = computed({ get: () => booth.stickers.density ?? theme.value.ornaments.density, set: (v) => (booth.stickers.density = v) });
function toggleSet(key) {
    const now = new Set(stickerSets.value);
    now.has(key) ? now.delete(key) : now.add(key);
    booth.stickers.sets = STICKER_SETS.filter((k) => now.has(k));
}

const hasWork = computed(() => JSON.stringify(snapshot()) !== initial);

function snapshot() {
    return { theme: booth.theme, preset: booth.preset, intensity: booth.intensity, adjust: booth.adjust, stickers: { sets: booth.stickers.sets, density: booth.stickers.density, seed: booth.stickers.seed }, title: booth.title, sub: booth.sub, transforms: booth.transforms };
}

function undoAll() {
    const s = JSON.parse(initial);
    booth.theme = s.theme;
    booth.preset = s.preset;
    booth.intensity = s.intensity;
    booth.adjust = { ...NEUTRAL, ...s.adjust };
    booth.stickers.sets = s.stickers.sets;
    booth.stickers.density = s.stickers.density;
    booth.stickers.seed = s.stickers.seed;
    booth.title = s.title;
    booth.sub = s.sub;
    booth.transforms = s.transforms;
}

function resetAll() {
    resetLook();
    resetPicture();
    booth.stickers.sets = null;
    booth.stickers.density = null;
}

async function cellsFor(look, size) {
    return Promise.all(raws.value.map((raw, i) => renderPhoto(raw, i, tf(i), look, size)));
}

function compose(cells, maxEdge) {
    return composeBooth({
        themeKey: booth.theme,
        layout: props.session.layout,
        cells,
        names: names.value,
        date: props.date,
        text: { title: booth.title.trim(), sub: booth.sub.trim() },
        stickers: { sets: booth.stickers.sets, density: booth.stickers.density, seed: booth.stickers.seed },
        maxEdge,
    });
}

async function refresh() {
    if (status.value !== 'ready') return;
    const mine = ++token;
    working.value = true;
    try {
        const [cells] = await Promise.all([cellsFor(comparing.value ? NEUTRAL_LOOK : lookOf(), PREVIEW_SIZE), ensureFonts()]);
        if (mine !== token) return;
        const canvas = compose(cells, 1100);
        const target = preview.value;
        if (target) {
            target.width = canvas.width;
            target.height = canvas.height;
            target.getContext('2d').drawImage(canvas, 0, 0);
        }
        if (full.value) fullUrl.value = canvas.toDataURL('image/jpeg', 0.9);
    } finally {
        if (mine === token) working.value = false;
    }
}

function schedule() {
    clearTimeout(timer);
    timer = setTimeout(refresh, 80);
}

watch(() => [booth.theme, booth.preset, booth.intensity, { ...booth.adjust }, booth.title, booth.sub, booth.stickers.sets, booth.stickers.density, booth.stickers.seed, booth.transforms, comparing.value], schedule, { deep: true });
watch(full, (open) => {
    if (open) fullUrl.value = preview.value?.toDataURL('image/jpeg', 0.9) ?? null;
});

async function load() {
    status.value = 'loading';
    try {
        raws.value = await Promise.all(props.session.slots.map((slot) => loadShot(slot.url)));
        status.value = 'ready';
        initial ??= JSON.stringify(snapshot());
        await nextTick();
        refresh();
    } catch {
        status.value = 'failed';
    }
}

async function save() {
    if (exporting.value || props.saving) return;
    exporting.value = true;
    exportError.value = null;
    try {
        await ensureFonts();
        const cells = await cellsFor(lookOf(), EXPORT_SIZE);
        const blob = await toJpeg(compose(cells, 2560), 0.9);
        emit('save', blob);
    } catch {
        exportError.value = t('booth.composeFailed');
    } finally {
        exporting.value = false;
    }
}

// Hold to see the originals in the same template; a key press toggles for people who cannot hold.
function holdStart() {
    comparing.value = true;
}
function holdEnd() {
    comparing.value = false;
}
function toggleCompare(event) {
    if (event.detail === 0) comparing.value = !comparing.value;
}

onMounted(load);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] md:items-start lg:grid-cols-[minmax(0,1fr)_26rem]">
        <!-- Preview -->
        <div class="sticky top-2 z-10 md:top-4">
            <div class="glass relative mx-auto flex w-full items-center justify-center rounded-[1.5rem] p-2 md:p-3" :class="session.layout === 'strip' || session.layout === 'trio' ? 'h-[38dvh] md:h-[78dvh]' : 'h-[38dvh] md:h-auto md:aspect-[4/5]'">
                <canvas ref="preview" class="max-h-full max-w-full rounded-[0.75rem] object-contain" role="img" :aria-label="t('booth.previewLabel')" />
                <div v-if="status === 'loading'" class="absolute inset-0 grid place-items-center text-sm font-semibold text-ink-muted"><span class="inline-flex items-center gap-2"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" />{{ t('booth.loadingPhotos') }}</span></div>
                <div v-else-if="status === 'failed'" class="absolute inset-0 grid place-items-center p-4 text-center">
                    <div class="space-y-2"><TriangleAlert class="mx-auto size-6 text-danger" aria-hidden="true" /><p class="text-sm text-plum">{{ t('booth.loadFailed') }}</p><Button size="sm" variant="soft" @click="load">{{ t('booth.retry') }}</Button></div>
                </div>
                <span v-if="comparing" class="absolute left-3 top-3 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">{{ t('booth.before') }}</span>
                <span v-else-if="working" class="absolute right-3 top-3 grid size-7 place-items-center rounded-full bg-black/45 text-white" :aria-label="t('booth.processing')"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" /></span>
                <button type="button" class="absolute bottom-3 right-3 grid size-10 place-items-center rounded-full bg-black/45 text-white" :aria-label="t('booth.viewFull')" @click="full = true"><Maximize2 class="size-4" aria-hidden="true" /></button>
            </div>
        </div>

        <!-- Controls -->
        <div class="min-w-0 space-y-4">
            <SegmentedControl v-model="tab" :options="tabs" :label="t('booth.editTitle')" />

            <Card v-show="tab === 'theme'" class="space-y-3">
                <p class="text-sm text-ink-muted">{{ t('booth.themeHint') }}</p>
                <div class="grid grid-cols-2 gap-2.5 min-[520px]:grid-cols-3 md:grid-cols-2 lg:grid-cols-3">
                    <ThemeCard v-for="th in THEMES" :key="th.key" :theme-key="th.key" :layout="session.layout" :selected="booth.theme === th.key" :names="names" @select="chooseTheme" />
                </div>
            </Card>

            <Card v-show="tab === 'filter'" class="space-y-3">
                <FilterStrip v-model="booth.preset" />
                <SliderField v-model="booth.intensity" :label="t('booth.intensity')" :reset="80" />
                <p class="pt-1 text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ t('booth.effects') }}</p>
                <SliderField v-for="f in EFFECT_FIELDS" :key="f.key" v-model="booth.adjust[f.key]" :label="t(`booth.effect.${f.key}`)" :min="f.min" :max="f.max" />
            </Card>

            <Card v-show="tab === 'adjust'" class="space-y-1.5">
                <SliderField v-for="f in ADJUST_FIELDS" :key="f.key" v-model="booth.adjust[f.key]" :label="t(`booth.adjust.${f.key}`)" :min="f.min" :max="f.max" />
            </Card>

            <Card v-show="tab === 'skin'" class="space-y-1.5">
                <p class="text-sm text-ink-muted">{{ t('booth.skinHint') }}</p>
                <SliderField v-for="f in SKIN_FIELDS" :key="f.key" v-model="booth.adjust[f.key]" :label="t(`booth.skin.${f.key}`)" :min="f.min" :max="f.max" />
                <p class="flex items-start gap-2 pt-1 text-xs text-ink-muted"><ScanFace class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ faces ? t('booth.facesOn') : t('booth.facesOff') }}</p>
            </Card>

            <Card v-show="tab === 'decor'" class="space-y-4">
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-plum">{{ t('booth.stickers') }}</p>
                    <div class="flex flex-wrap gap-2" role="group" :aria-label="t('booth.stickers')">
                        <button v-for="key in STICKER_SETS" :key="key" type="button" class="h-10 rounded-full px-4 text-sm font-semibold ring-1 transition active:scale-[0.97]" :class="stickerSets.includes(key) ? 'bg-berry text-white ring-transparent' : 'bg-surface text-ink-muted ring-line'" :aria-pressed="stickerSets.includes(key)" @click="toggleSet(key)">{{ t(`booth.sticker.${key}`) }}</button>
                    </div>
                    <SliderField v-model="density" :label="t('booth.density')" :reset="theme.ornaments.density" />
                    <Button variant="soft" size="sm" @click="booth.stickers.seed += 1"><Shuffle class="size-4" aria-hidden="true" />{{ t('booth.shuffle') }}</Button>
                </div>
                <TextField v-model="booth.title" :label="t('booth.titleText')" :maxlength="40" :hint="t('booth.titleHint', { names: names.join(' & ') })" />
                <TextField v-model="booth.sub" :label="t('booth.subText')" :maxlength="50" :hint="t('booth.subHint')" />
            </Card>

            <Card v-show="tab === 'photo'" class="space-y-1.5">
                <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('booth.pickPhoto')">
                    <button v-for="(slot, i) in session.slots" :key="i" type="button" role="radio" :aria-checked="selected === i" class="h-10 min-w-10 rounded-full px-4 text-sm font-semibold ring-1 transition" :class="selected === i ? 'bg-berry text-white ring-transparent' : 'bg-surface text-ink-muted ring-line'" @click="selected = i">{{ i + 1 }}</button>
                </div>
                <SliderField v-model="zoom" :label="t('booth.zoom')" :min="100" :max="300" :step="5" :reset="100" unit="%" />
                <SliderField v-model="panX" :label="t('booth.panX')" :min="-100" :max="100" />
                <SliderField v-model="panY" :label="t('booth.panY')" :min="-100" :max="100" />
                <SliderField v-model="rot" :label="t('booth.rotate')" :min="-30" :max="30" unit="°" />
                <button type="button" class="flex min-h-11 w-full items-center justify-between rounded-[0.875rem] text-sm font-semibold text-plum" :aria-pressed="mirror" @click="setT('mirror', !mirror)">
                    <span class="inline-flex items-center gap-2"><FlipHorizontal2 class="size-4" aria-hidden="true" />{{ t('booth.mirror') }}</span>
                    <span class="rounded-full px-3 py-1 text-xs" :class="mirror ? 'bg-berry text-white' : 'bg-fill text-ink-muted'">{{ mirror ? t('booth.on') : t('booth.off') }}</span>
                </button>
            </Card>

            <!-- Actions -->
            <div class="space-y-2.5 pb-24 md:pb-2">
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" class="glass flex min-h-12 select-none flex-col items-center justify-center gap-0.5 rounded-[1rem] px-1 text-xs font-semibold text-berry-ink active:scale-[0.97]" :aria-pressed="comparing" style="touch-action: none" @pointerdown.prevent="holdStart" @pointerup="holdEnd" @pointerleave="holdEnd" @pointercancel="holdEnd" @click="toggleCompare">
                        <Eye class="size-[1.125rem]" aria-hidden="true" />{{ t('booth.compare') }}
                    </button>
                    <button type="button" class="glass flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-[1rem] px-1 text-xs font-semibold text-berry-ink active:scale-[0.97]" @click="resetAll"><RotateCcw class="size-[1.125rem]" aria-hidden="true" />{{ t('booth.resetAll') }}</button>
                    <button type="button" class="glass flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-[1rem] px-1 text-xs font-semibold text-berry-ink active:scale-[0.97] disabled:opacity-40" :disabled="!hasWork" @click="undoAll"><Undo2 class="size-[1.125rem]" aria-hidden="true" />{{ t('booth.undo') }}</button>
                </div>
                <p v-if="exportError" class="text-center text-sm text-danger" role="alert">{{ exportError }}</p>
                <Button block size="lg" :loading="exporting || saving" :disabled="status !== 'ready'" @click="save"><Save class="size-5" aria-hidden="true" />{{ t('booth.saveToMemories') }}</Button>
                <p class="text-center text-xs text-ink-muted">{{ t('booth.saveNote') }}</p>
            </div>
        </div>

        <Dialog v-model:open="full" :title="t('booth.previewLabel')" :description="t('booth.fullHint')">
            <img v-if="fullUrl" :src="fullUrl" :alt="t('booth.previewLabel')" class="mx-auto max-h-[62dvh] w-auto rounded-[1rem]" />
        </Dialog>
    </div>
</template>
