<script setup>
// Puzzle Momen: a photo of yours (or a cute drawing) cut into 12 pieces and shuffled. Tap two pieces to swap them.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Face from '@/Components/arena/Face.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import { puzzlePicture } from '@/lib/arenaArt';
import { rng } from '@/lib/rng';
import { sfx } from '@/lib/arenaAudio';

const props = defineProps({
    seed: { type: Number, required: true },
    photos: { type: Array, default: () => [] },
    limit: { type: Number, default: 180000 },
    me: { type: Object, required: true },
});
const emit = defineEmits(['finish']);

const COLS = 3;
const ROWS = 4;
const COUNT = COLS * ROWS;
const IMG_W = 600;
const IMG_H = 800;

const image = ref('');
const preparing = ref(true);
const order = ref([]); // order[slot] = which piece sits there; piece p belongs in slot p
const selected = ref(null);
const moves = ref(0);
const peeks = ref(0);
const peeking = ref(false);
const secondsLeft = ref(Math.ceil(props.limit / 1000));
const over = ref(false);
const flash = ref(null);

let started = 0;
let elapsed = 0;
let tick = 0;
let endTimer = 0;
let flashTimer = 0;
let done = false;
let gone = false;

const placed = computed(() => order.value.filter((piece, slot) => piece === slot).length);
const isLocked = (slot) => order.value[slot] === slot;

function shuffleOrder() {
    const rand = rng(props.seed + 99);
    for (let attempt = 0; attempt < 80; attempt++) {
        const list = Array.from({ length: COUNT }, (_, i) => i);
        for (let i = list.length - 1; i > 0; i--) {
            const j = Math.floor(rand() * (i + 1));
            [list[i], list[j]] = [list[j], list[i]];
        }
        // Every piece starts in the wrong place.
        if (list.every((piece, slot) => piece !== slot)) return list;
    }
    return Array.from({ length: COUNT }, (_, i) => (i + 1) % COUNT);
}

function cover(img, ctx) {
    const scale = Math.max(IMG_W / img.naturalWidth, IMG_H / img.naturalHeight);
    const w = img.naturalWidth * scale;
    const h = img.naturalHeight * scale;
    ctx.drawImage(img, (IMG_W - w) / 2, (IMG_H - h) / 2, w, h);
}

function loadPhoto(id) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const timer = setTimeout(() => reject(new Error('slow')), 7000);
        img.onload = () => {
            clearTimeout(timer);
            img.naturalWidth > 0 ? resolve(img) : reject(new Error('empty'));
        };
        img.onerror = () => {
            clearTimeout(timer);
            reject(new Error('failed'));
        };
        img.src = `/photos/${id}/full`;
    });
}

// A drawing of the two of you, for when there is no photo to use. It is an SVG turned into a picture first.
function drawPicture(ctx, rand) {
    return new Promise((resolve) => {
        const img = new Image();
        const timer = setTimeout(resolve, 4000);
        img.onload = () => {
            clearTimeout(timer);
            ctx.drawImage(img, 0, 0, IMG_W, IMG_H);
            resolve();
        };
        img.onerror = () => {
            clearTimeout(timer);
            ctx.fillStyle = '#ffc2d6';
            ctx.fillRect(0, 0, IMG_W, IMG_H);
            resolve();
        };
        img.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(puzzlePicture(Math.floor(rand() * 3)))}`;
    });
}

async function prepare() {
    const canvas = document.createElement('canvas');
    canvas.width = IMG_W;
    canvas.height = IMG_H;
    const ctx = canvas.getContext('2d');
    let used = false;
    if (props.photos.length) {
        try {
            const img = await loadPhoto(props.photos[0]);
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, IMG_W, IMG_H);
            cover(img, ctx);
            used = true;
        } catch {
            /* falls back to the drawing */
        }
    }
    if (!used) await drawPicture(ctx, rng(props.seed));
    if (gone) return;
    image.value = canvas.toDataURL('image/jpeg', 0.85);
    order.value = shuffleOrder();
    preparing.value = false;
    started = performance.now();
    tick = setInterval(() => {
        elapsed = (performance.now() - started) / 1000;
        secondsLeft.value = Math.max(0, Math.ceil(props.limit / 1000 - elapsed));
        if (elapsed >= props.limit / 1000 && !over.value) finish(false);
    }, 250);
    sfx('pop');
}

function choose(slot) {
    if (preparing.value || over.value || isLocked(slot)) return;
    if (selected.value === null) {
        selected.value = slot;
        sfx('tap');
        return;
    }
    if (selected.value === slot) {
        selected.value = null;
        return;
    }
    const a = selected.value;
    const next = order.value.slice();
    [next[a], next[slot]] = [next[slot], next[a]];
    order.value = next;
    selected.value = null;
    moves.value++;
    const good = [a, slot].filter((s) => next[s] === s);
    if (good.length) {
        sfx('place');
        flash.value = good;
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => (flash.value = null), 700);
    } else {
        sfx('swap');
    }
    if (placed.value === COUNT) finish(true);
}

function finishScore(complete) {
    if (!complete) return Math.max(0, placed.value * 60 - peeks.value * 25);
    return Math.max(150, Math.round(1000 - Math.max(0, moves.value - 10) * 14 - elapsed * 3 - peeks.value * 25));
}

function finish(complete) {
    if (over.value) return;
    over.value = true;
    clearInterval(tick);
    selected.value = null;
    sfx(complete ? 'win' : 'lose');
    endTimer = setTimeout(() => {
        if (done) return;
        done = true;
        emit('finish', { score: finishScore(complete) });
    }, complete ? 1100 : 1300);
}

function peek(on) {
    if (preparing.value || over.value) return;
    if (on && !peeking.value) {
        peeks.value++;
        sfx('tap');
    }
    peeking.value = on;
}

// Where the piece sits on the board, and which part of the picture it shows.
const placeStyle = (slot) => ({
    left: `${(slot % COLS) * (100 / COLS)}%`,
    top: `${Math.floor(slot / COLS) * (100 / ROWS)}%`,
    width: `${100 / COLS}%`,
    height: `${100 / ROWS}%`,
});
const pictureStyle = (slot) => {
    const piece = order.value[slot];
    return {
        backgroundImage: `url(${image.value})`,
        backgroundSize: `${COLS * 100}% ${ROWS * 100}%`,
        backgroundPosition: `${((piece % COLS) / (COLS - 1)) * 100}% ${(Math.floor(piece / COLS) / (ROWS - 1)) * 100}%`,
    };
};

onMounted(prepare);
onBeforeUnmount(() => {
    gone = true;
    done = true;
    clearInterval(tick);
    clearTimeout(endTimer);
    clearTimeout(flashTimer);
});
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col px-4 pb-6" data-testid="puzzle">
        <div class="mx-auto flex w-full max-w-md items-center justify-between gap-2 pt-1">
            <div class="ar-chip"><Face :person="me" :size="26" ring="#3b1d33" /> <span data-testid="placed">{{ placed }}/{{ COUNT }}</span></div>
            <div class="ar-chip tabular-nums">👣 {{ moves }}</div>
            <div class="ar-chip tabular-nums" :class="secondsLeft <= 20 && !preparing ? '!bg-[#ff9aa8]' : ''">⏱ {{ secondsLeft }} dtk</div>
        </div>
        <p class="ar-chip mx-auto mt-3 text-center text-sm" aria-live="polite">{{ preparing ? 'Menyiapkan foto…' : over ? (placed === COUNT ? 'Fotonya sudah utuh!' : 'Waktu habis!') : selected !== null ? 'Sekarang ketuk kepingan untuk ditukar' : 'Ketuk dua kepingan untuk menukarnya' }}</p>

        <div class="relative mx-auto mt-4 w-full flex-1">
            <!-- a photo in a white frame, held down with tape -->
            <div class="ar-card relative mx-auto aspect-[3/4] !rounded-xl !bg-white p-2" :style="{ width: 'min(100%, calc((100dvh - 15rem) * 0.75))', transform: 'rotate(-0.8deg)' }">
                <span class="absolute -top-3 left-1/2 z-20 h-6 w-20 -translate-x-1/2 rotate-2 border-2 border-[var(--ar-line)] bg-[#ffe27a]/90" aria-hidden="true"></span>
                <div class="relative size-full overflow-hidden rounded-md bg-[#fff4f8]">
                    <div v-if="preparing" class="absolute inset-0 grid place-items-center"><Sticker name="berry" :size="72" class="ar-bounce" /></div>
                    <template v-else>
                        <button
                            v-for="(piece, slot) in order"
                            :key="piece"
                            type="button"
                            class="absolute p-[1.5px] transition-[left,top] duration-300 ease-out"
                            :aria-label="`Kepingan ${slot + 1}${isLocked(slot) ? ', sudah pas' : ''}`"
                            data-testid="piece"
                            :data-slot="slot"
                            :data-piece="piece"
                            :data-locked="isLocked(slot)"
                            :style="placeStyle(slot)"
                            @click="choose(slot)"
                        >
                            <span
                                class="block size-full rounded-md border-[2.5px] bg-no-repeat transition"
                                :class="[
                                    isLocked(slot) ? 'border-[#35c987]' : selected === slot ? 'z-10 scale-[1.06] border-[#ff5c8a] shadow-[0_5px_0_#3b1d33]' : 'border-[#3b1d33]/80',
                                    flash?.includes(slot) ? 'ar-pop' : '',
                                ]"
                                :style="pictureStyle(slot)"
                            />
                        </button>
                        <img v-show="peeking" :src="image" alt="Foto yang harus disusun" class="pointer-events-none absolute inset-0 size-full object-cover" />
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            <button
                type="button"
                class="ar-btn ar-btn--yellow ar-btn--sm"
                :disabled="preparing || over"
                @pointerdown.prevent="peek(true)"
                @pointerup="peek(false)"
                @pointerleave="peek(false)"
                @pointercancel="peek(false)"
                @keydown.space.prevent="peek(true)"
                @keyup.space="peek(false)"
            >👀 Tahan untuk mengintip (−25)</button>
        </div>
    </div>
</template>
