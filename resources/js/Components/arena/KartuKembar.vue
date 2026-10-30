<script setup>
// Kartu Kembar Kenangan: 16 cards, 8 pairs. Photos from your shared albums are used first; cute pictures fill the rest.
// Both phones get the same layout from the seed, so a pair game is a fair race.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Face from '@/Components/arena/Face.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import { STICKER_NAMES } from '@/lib/arenaArt';
import { rng, shuffled } from '@/lib/rng';
import { sfx } from '@/lib/arenaAudio';

const props = defineProps({
    seed: { type: Number, required: true },
    photos: { type: Array, default: () => [] },
    limit: { type: Number, default: 120000 },
    me: { type: Object, required: true },
});
const emit = defineEmits(['finish']);

const PAIRS = 8;

const rand = rng(props.seed);
const pictures = shuffled(STICKER_NAMES.filter((n) => n !== 'letter'), rand);
// Pairs 0..7: the first ones are photos (if the albums have any), the rest are pictures.
const faces = Array.from({ length: PAIRS }, (_, i) => (props.photos[i] ? { photo: props.photos[i], sticker: pictures[i] } : { sticker: pictures[i] }));
const brokenPhotos = ref(new Set());
const deck = shuffled(
    faces.flatMap((face, pair) => [{ id: pair * 2, pair, face }, { id: pair * 2 + 1, pair, face }]),
    rand,
);

const open = ref([]); // ids face up and not yet matched
const matched = ref(new Set());
const moves = ref(0);
const secondsLeft = ref(Math.ceil(props.limit / 1000));
const peek = ref(true);
const locked = ref(true);
const over = ref(false);
const shakeId = ref(null);
const sparkle = ref(null);

let elapsed = 0;
let started = 0;
let tick = 0;
let hideTimer = 0;
let backTimer = 0;
let endTimer = 0;
let done = false;

const isUp = (card) => peek.value || matched.value.has(card.pair) || open.value.includes(card.id);
const foundPairs = computed(() => matched.value.size);

function begin() {
    started = performance.now();
    tick = setInterval(() => {
        elapsed = (performance.now() - started) / 1000;
        secondsLeft.value = Math.max(0, Math.ceil(props.limit / 1000 - elapsed));
        if (elapsed >= props.limit / 1000 && !over.value) finish(false);
    }, 250);
}

function flip(card) {
    if (locked.value || over.value || isUp(card)) return;
    sfx('flip');
    open.value = [...open.value, card.id];
    if (open.value.length < 2) return;

    moves.value++;
    locked.value = true;
    const [a, b] = open.value.map((id) => deck.find((c) => c.id === id));
    if (a.pair === b.pair) {
        backTimer = setTimeout(() => {
            matched.value = new Set([...matched.value, a.pair]);
            open.value = [];
            sparkle.value = a.pair;
            sfx('match');
            locked.value = false;
            if (matched.value.size === PAIRS) finish(true);
        }, 350);
    } else {
        shakeId.value = `${a.id}-${b.id}`;
        backTimer = setTimeout(() => {
            sfx('wrong');
            shakeId.value = null;
            open.value = [];
            locked.value = false;
        }, 850);
    }
}

function score(complete) {
    if (!complete) return matched.value.size * 50;
    return Math.max(150, Math.round(1000 - Math.max(0, moves.value - PAIRS) * 25 - elapsed * 3));
}

function finish(complete) {
    if (over.value) return;
    over.value = true;
    locked.value = true;
    clearInterval(tick);
    sfx(complete ? 'win' : 'lose');
    endTimer = setTimeout(() => {
        if (done) return;
        done = true;
        emit('finish', { score: score(complete) });
    }, complete ? 1000 : 1200);
}

onMounted(() => {
    // A quick look at the cards first, then they flip back and the clock starts.
    hideTimer = setTimeout(() => {
        peek.value = false;
        locked.value = false;
        sfx('pop');
        begin();
    }, 2200);
});

onBeforeUnmount(() => {
    clearInterval(tick);
    clearTimeout(hideTimer);
    clearTimeout(backTimer);
    clearTimeout(endTimer);
    done = true;
});
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col px-4 pb-6" data-testid="kartu">
        <div class="mx-auto flex w-full max-w-md items-center justify-between gap-2 pt-1">
            <div class="ar-chip"><Face :person="me" :size="26" ring="#3b1d33" /> <span data-testid="pairs">{{ foundPairs }}/{{ PAIRS }}</span> pasang</div>
            <div class="ar-chip tabular-nums">👣 {{ moves }}</div>
            <div class="ar-chip tabular-nums" :class="secondsLeft <= 15 && !peek ? '!bg-[#ff9aa8]' : ''">⏱ {{ secondsLeft }} dtk</div>
        </div>
        <p class="ar-chip mx-auto mt-3 text-center text-sm" aria-live="polite">{{ peek ? 'Intip dulu, ingat posisinya!' : over ? (foundPairs === PAIRS ? 'Semua pasang ketemu!' : 'Waktu habis!') : 'Ketuk dua kartu yang sama' }}</p>

        <div class="mx-auto mt-4 grid w-full max-w-md flex-1 grid-cols-4 content-center gap-2.5">
            <button
                v-for="card in deck"
                :key="card.id"
                type="button"
                class="group relative aspect-[3/4] [perspective:700px]"
                :class="shakeId && shakeId.split('-').includes(String(card.id)) ? 'ar-shake' : ''"
                :aria-label="isUp(card) ? 'Kartu terbuka' : 'Kartu tertutup'"
                :aria-pressed="isUp(card)"
                data-testid="card"
                :data-pair="card.pair"
                :data-up="isUp(card)"
                @click="flip(card)"
            >
                <span class="absolute inset-0 transition-transform duration-300 [transform-style:preserve-3d]" :style="{ transform: isUp(card) ? 'rotateY(180deg)' : 'rotateY(0deg)' }">
                    <!-- back: a stitched pink card with a heart stamp -->
                    <span class="absolute inset-0 grid place-items-center rounded-xl border-[3px] border-[var(--ar-line)] bg-[#ff7aa2] shadow-[0_4px_0_#3b1d33] [backface-visibility:hidden]" style="background-image: radial-gradient(rgb(255 255 255 / .35) 2px, transparent 2.6px); background-size: 12px 12px">
                        <span class="absolute inset-1 rounded-lg border-2 border-dashed border-white/80"></span>
                        <Sticker name="heart" :size="34" class="relative -rotate-6" />
                    </span>
                    <!-- front -->
                    <span
                        class="ar-paper absolute inset-0 grid place-items-center overflow-hidden rounded-xl border-[3px] [backface-visibility:hidden] [transform:rotateY(180deg)]"
                        :class="matched.has(card.pair) ? 'border-[#1f9a64] shadow-[0_4px_0_#1f9a64] !bg-[#e6ffef]' : 'border-[var(--ar-line)] shadow-[0_4px_0_#3b1d33]'"
                    >
                        <span v-if="card.face.photo && !brokenPhotos.has(card.face.photo)" class="absolute inset-1 overflow-hidden rounded-md border-2 border-[var(--ar-line)]">
                            <img :src="`/photos/${card.face.photo}/thumb`" alt="" class="size-full object-cover" draggable="false" @error="brokenPhotos = new Set([...brokenPhotos, card.face.photo])" />
                        </span>
                        <Sticker v-else :name="card.face.sticker" :size="52" :class="matched.has(card.pair) ? 'ar-bounce' : ''" />
                        <span v-if="matched.has(card.pair)" class="ar-pop absolute right-0.5 top-0.5 grid size-5 place-items-center rounded-full border-2 border-[var(--ar-line)] bg-[#35c987] text-[0.65rem] font-black text-white">✓</span>
                    </span>
                </span>
            </button>
        </div>
    </div>
</template>
