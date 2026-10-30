<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';
import { X, SwitchCamera, RotateCcw, Check, ImageUp, Camera, TriangleAlert } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import FilterStrip from '@/Components/photobooth/FilterStrip.vue';
import { PRESETS } from '@/lib/booth/engine';
import { rawFromSource, renderPhoto, toJpeg, DEFAULT_TRANSFORM } from '@/lib/booth/pipeline';
import { booth, lookOf } from '@/lib/booth/settings';
import { t } from '@/i18n';

// Full-screen camera: live square preview, 3-2-1 countdown, then a review with the chosen filter applied.
// The photo that is sent is the unfiltered original, so the filter can still be changed in the editor.
// Without live video (no HTTPS, permission refused, old iOS home-screen app) the phone's own camera picker takes over.
const open = defineModel('open', { type: Boolean, default: false });
defineProps({ title: { type: String, required: true }, busy: Boolean });
const emit = defineEmits(['capture']);

const video = ref(null);
const fileInput = ref(null);
const stage = ref('live'); // live | review | fallback
const facing = ref('user');
const count = ref(0);
const flash = ref(false);
const reviewUrl = ref(null);
const problem = ref(null);
let stream = null;
let raw = null;
let timer = null;
let fromFile = false;
let run = 0;

const reduceMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function explain(error) {
    if (!window.isSecureContext) return t('booth.errHttps');
    switch (error?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return t('booth.errDenied');
        case 'NotFoundError':
        case 'OverconstrainedError':
            return t('booth.errNone');
        case 'NotReadableError':
            return t('booth.errBusy');
        default:
            return t('booth.noCamera');
    }
}

async function start() {
    stop();
    stage.value = 'live';
    problem.value = null;
    if (!navigator.mediaDevices?.getUserMedia) {
        problem.value = window.isSecureContext ? t('booth.noCamera') : t('booth.errHttps');
        stage.value = 'fallback';
        return;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: facing.value, width: { ideal: 1920 }, height: { ideal: 1920 } } });
        await nextTick();
        if (!open.value) return stop();
        video.value.srcObject = stream;
        await video.value.play().catch(() => {});
    } catch (error) {
        problem.value = explain(error);
        stage.value = 'fallback';
    }
}

function stop() {
    clearInterval(timer);
    timer = null;
    count.value = 0;
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
}

function flip() {
    facing.value = facing.value === 'user' ? 'environment' : 'user';
    start();
}

function shoot() {
    if (count.value || !video.value?.videoWidth) return;
    count.value = 3;
    timer = setInterval(() => {
        count.value -= 1;
        if (count.value > 0) return;
        clearInterval(timer);
        timer = null;
        const v = video.value;
        fromFile = false;
        raw = rawFromSource(v, v.videoWidth, v.videoHeight, { mirror: facing.value === 'user' });
        if (!reduceMotion) {
            flash.value = true;
            setTimeout(() => (flash.value = false), 180);
        }
        stop();
        showReview();
    }, 1000);
}

async function pickFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        raw = rawFromSource(bitmap, bitmap.width, bitmap.height);
        bitmap.close?.();
        fromFile = true;
        showReview();
    } catch {
        problem.value = t('booth.badFile');
        stage.value = 'fallback';
    }
}

async function showReview() {
    const mine = ++run;
    stage.value = 'review';
    reviewUrl.value = null;
    try {
        const canvas = await renderPhoto(raw, 0, DEFAULT_TRANSFORM, lookOf(), 480, { useFaces: false });
        if (mine === run) reviewUrl.value = canvas.toDataURL('image/jpeg', 0.9);
    } catch {
        if (mine === run) reviewUrl.value = raw.toDataURL('image/jpeg', 0.9);
    }
}

function retake() {
    reviewUrl.value = null;
    raw = null;
    if (fromFile) stage.value = 'fallback';
    else start();
}

async function use() {
    if (!raw) return;
    try {
        emit('capture', await toJpeg(raw, 0.9));
    } catch {
        problem.value = t('booth.composeFailed');
    }
}

watch(() => [booth.preset, booth.intensity, booth.adjust], () => stage.value === 'review' && raw && showReview(), { deep: true });

watch(open, (value) => {
    if (value) {
        reviewUrl.value = null;
        raw = null;
        start();
    } else {
        stop();
    }
}, { immediate: true });

onBeforeUnmount(stop);

const liveFilter = () => PRESETS.find((p) => p.key === booth.preset)?.css ?? 'none';
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[60] flex flex-col bg-[#120d11] text-white" role="dialog" aria-modal="true" :aria-label="title" @keydown.esc="open = false">
            <header class="flex items-center gap-3 px-4 pt-safe">
                <button type="button" class="grid size-11 place-items-center rounded-full bg-white/10 hover:bg-white/20" :aria-label="t('common.close')" @click="open = false"><X class="size-5" aria-hidden="true" /></button>
                <p class="flex-1 text-center text-[0.9375rem] font-semibold">{{ title }}</p>
                <button v-if="stage === 'live'" type="button" class="grid size-11 place-items-center rounded-full bg-white/10 hover:bg-white/20" :aria-label="t('booth.flip')" @click="flip"><SwitchCamera class="size-5" aria-hidden="true" /></button>
                <span v-else class="size-11" aria-hidden="true" />
            </header>

            <div class="flex min-h-0 flex-1 items-center justify-center px-4 py-3">
                <div class="relative aspect-square w-full max-w-[min(100%,calc(100dvh-19rem))] overflow-hidden rounded-[1.75rem] bg-black">
                    <video v-show="stage === 'live'" ref="video" class="size-full object-cover" :class="facing === 'user' && '-scale-x-100'" :style="{ filter: liveFilter() }" playsinline muted autoplay />
                    <img v-if="stage === 'review' && reviewUrl" :src="reviewUrl" :alt="t('booth.preview')" class="size-full object-cover" />
                    <div v-else-if="stage === 'review'" class="absolute inset-0 grid place-items-center text-sm text-white/70">{{ t('booth.processing') }}</div>
                    <div v-if="stage === 'fallback'" class="absolute inset-0 grid place-items-center p-6 text-center">
                        <div class="space-y-3">
                            <component :is="problem ? TriangleAlert : Camera" class="mx-auto size-10 opacity-70" aria-hidden="true" />
                            <p class="text-sm text-white/85" role="alert">{{ problem ?? t('booth.noCamera') }}</p>
                            <Button variant="soft" class="!bg-white/15 !text-white" @click="fileInput.click()"><ImageUp class="size-4" aria-hidden="true" />{{ t('booth.pick') }}</Button>
                            <button v-if="!fromFile" type="button" class="block w-full text-sm text-white/70 underline underline-offset-4" @click="start">{{ t('booth.tryAgain') }}</button>
                        </div>
                    </div>
                    <span v-if="count" :key="count" class="booth-count absolute inset-0 grid place-items-center text-[7rem] font-bold drop-shadow-[0_4px_18px_rgb(0_0_0/0.45)]" aria-live="assertive">{{ count }}</span>
                    <span v-if="flash" class="absolute inset-0 bg-white" aria-hidden="true" />
                </div>
            </div>

            <div class="px-3 pb-2">
                <FilterStrip v-model="booth.preset" class="[&_button]:!bg-white/10 [&_button]:!ring-0 [&_button[aria-checked=true]]:!bg-white/25 [&_span]:!text-white" />
            </div>

            <footer class="flex items-center justify-center gap-4 px-4 pb-safe pt-1">
                <template v-if="stage === 'review'">
                    <Button variant="soft" size="lg" class="!bg-white/15 !text-white" :disabled="busy" @click="retake"><RotateCcw class="size-5" aria-hidden="true" />{{ t('booth.retake') }}</Button>
                    <Button size="lg" :loading="busy" :disabled="!reviewUrl" @click="use"><Check class="size-5" aria-hidden="true" />{{ t('booth.use') }}</Button>
                </template>
                <button v-else-if="stage === 'live'" type="button" class="grid size-[4.75rem] place-items-center rounded-full border-4 border-white/80 transition active:scale-95 disabled:opacity-50" :disabled="!!count" :aria-label="t('booth.shutter')" @click="shoot">
                    <span class="size-[3.6rem] rounded-full bg-white" />
                </button>
                <Button v-else variant="soft" size="lg" class="!bg-white/15 !text-white" @click="fileInput.click()"><ImageUp class="size-5" aria-hidden="true" />{{ t('booth.pick') }}</Button>
            </footer>
            <input ref="fileInput" type="file" accept="image/*" capture="user" class="hidden" @change="pickFile" />
        </div>
    </Teleport>
</template>

<style scoped>
.booth-count {
    animation: booth-pop 1s ease-out both;
}
@keyframes booth-pop {
    from { transform: scale(1.35); opacity: 0; }
    25% { transform: scale(1); opacity: 1; }
    to { transform: scale(0.92); opacity: 0.85; }
}
@media (prefers-reduced-motion: reduce) {
    .booth-count { animation: none; }
}
</style>
