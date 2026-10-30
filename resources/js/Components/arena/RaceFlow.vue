<script setup>
// The frame around the three "race" games (cards, puzzle, hearts): intro or lobby, a shared countdown, the game
// itself, then the result. Alone it all happens here; as a pair the server keeps both phones in step.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Buddy from '@/Components/arena/Buddy.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import Face from '@/Components/arena/Face.vue';
import ResultPanel from '@/Components/arena/ResultPanel.vue';
import { GAMES } from '@/lib/arenaGames';
import { http } from '@/lib/http';
import { sfx, startMusic, unlock } from '@/lib/arenaAudio';

const props = defineProps({
    game: { type: Object, required: true },
    mode: { type: String, required: true },
    seed: { type: Number, required: true },
    photos: { type: Array, default: () => [] },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
    duo: { type: Object, default: null },
});
const emit = defineEmits(['leave']);

const info = computed(() => GAMES[props.game.key]);
const isDuo = computed(() => props.mode === 'duo');

// ----- Alone -----
const solo = ref('intro'); // intro | count | play | saving | result
const soloResult = ref(null);
const soloRound = ref(0);
const soloSeed = ref(props.seed);
const soloCount = ref(3);
let countTimer = null;

function soloStart() {
    unlock();
    startMusic(info.value.track);
    solo.value = 'count';
    soloCount.value = 3;
    sfx('tick');
    clearInterval(countTimer);
    countTimer = setInterval(() => {
        soloCount.value--;
        if (soloCount.value > 0) sfx('tick');
        else if (soloCount.value === 0) sfx('go');
        else {
            clearInterval(countTimer);
            playLimit.value = props.game.duration;
            solo.value = 'play';
        }
    }, 800);
}

async function soloFinish({ score }) {
    if (solo.value !== 'play') return;
    solo.value = 'saving';
    try {
        const res = await http.post('/arena/skor', { game: props.game.key, score });
        soloResult.value = { score: Math.max(0, Math.round(score)), stars: res.stars, best: res.isBest, failed: false };
    } catch {
        soloResult.value = { score: Math.max(0, Math.round(score)), stars: 1, best: false, failed: true };
    }
    solo.value = 'result';
}

function soloAgain() {
    soloRound.value++;
    soloSeed.value = (soloSeed.value * 31 + 17) % 2000000000;
    soloResult.value = null;
    soloStart();
}

// ----- As a pair -----
const state = computed(() => props.duo?.state.value ?? null);
const now = ref(Date.now());
let clock = null;
const finished = ref(false);
const playLimit = ref(props.game.duration);
const stage = computed(() => {
    if (!isDuo.value) return solo.value;
    const s = state.value;
    if (!s) return 'load';
    if (s.phase === 'lobby') return 'lobby';
    if (s.phase === 'result') return 'result';
    if (serverNowTick.value < s.startAt) return 'count';
    return finished.value || s.mine ? 'wait' : 'play';
});
const countdown = computed(() => {
    const s = state.value;
    if (!s?.startAt) return 0;
    return Math.max(0, Math.ceil((s.startAt - serverNowTick.value) / 1000));
});
const serverNowTick = computed(() => (now.value, props.duo ? props.duo.serverNow() : Date.now()));
const goFlash = computed(() => {
    const s = state.value;
    return !!s?.startAt && serverNowTick.value >= s.startAt && serverNowTick.value < s.startAt + 700;
});

watch(
    () => [state.value?.phase, state.value?.startAt],
    ([phase, startAt]) => {
        clearInterval(clock);
        if (phase === 'play' && startAt) clock = setInterval(() => (now.value = Date.now()), 100);
    },
    { immediate: true },
);
// A new round (rematch) puts both of you back in the lobby.
watch(() => state.value?.round, () => (finished.value = false));
// The limit for this run: the full time, or what is left if the page was opened late.
watch(
    () => stage.value === 'play',
    (playing) => {
        if (!playing || !state.value) return;
        const elapsed = Math.max(0, props.duo.serverNow() - state.value.startAt);
        playLimit.value = Math.max(8000, state.value.duration - elapsed);
    },
);
let lastTick = -1;
watch(countdown, (n) => {
    if (!isDuo.value || stage.value !== 'count' || n === lastTick) return;
    lastTick = n;
    if (n > 0) sfx('tick');
});
watch(goFlash, (on) => on && sfx('go'));

async function ready() {
    unlock();
    startMusic(info.value.track);
    sfx('pop');
    try {
        await props.duo.act({ type: 'ready' });
    } catch {
        /* the next poll shows the real state */
    }
}

async function duoFinish({ score }) {
    if (finished.value) return;
    finished.value = true;
    try {
        await props.duo.act({ type: 'finish', score: Math.max(0, Math.round(score)) });
    } catch {
        finished.value = false;
    }
}

async function duoAgain() {
    sfx('pop');
    try {
        await props.duo.act({ type: 'again', round: state.value.round });
    } catch {
        /* ignored: the next poll shows the real state */
    }
}

function onFinish(payload) {
    if (isDuo.value) duoFinish(payload);
    else soloFinish(payload);
}

const gameSeed = computed(() => (isDuo.value ? state.value?.seed ?? props.seed : soloSeed.value));
const gamePhotos = computed(() => (isDuo.value ? state.value?.photos ?? props.photos : props.photos));
const playKey = computed(() => (isDuo.value ? `d${state.value?.round}` : `s${soloRound.value}`));

onBeforeUnmount(() => {
    clearInterval(countTimer);
    clearInterval(clock);
});
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col">
        <!-- Intro (alone) -->
        <div v-if="stage === 'intro'" class="mx-auto flex w-full max-w-sm flex-1 flex-col items-center justify-center px-6 pb-10 text-center">
            <div class="flex items-end gap-1"><Buddy tone="pink" mood="cheer" :size="112" class="ar-bounce" /><Sticker :name="info.stickers[0]" :size="84" class="mb-2 rotate-6 drop-shadow-[0_4px_0_rgb(59_29_51/.25)]" /></div>
            <h2 class="ar-title ar-outline mt-3 text-[2.4rem] leading-none text-white">{{ game.title }}</h2>
            <p class="mt-3 ar-card ar-paper p-4 text-[0.95rem] font-medium leading-relaxed">{{ info.how }}</p>
            <button type="button" class="ar-btn ar-btn--lg mt-6" @click="soloStart">Mulai main!</button>
        </div>

        <!-- Lobby (pair) -->
        <div v-else-if="stage === 'lobby' || stage === 'load'" class="mx-auto flex w-full max-w-sm flex-1 flex-col items-center justify-center px-6 pb-10 text-center">
            <h2 class="ar-title ar-outline text-[2.4rem] leading-none text-white">{{ game.title }}</h2>
            <p class="mt-3 ar-card ar-paper p-4 text-[0.95rem] font-medium leading-relaxed">{{ info.how }}</p>
            <div v-if="state" class="mt-5 flex items-end justify-center gap-6">
                <div class="text-center">
                    <Face :person="me" :size="64" :ring="state.ready.me ? '#35c987' : '#3b1d33'" />
                    <p class="ar-chip mt-1 text-xs">{{ me.name }}</p>
                    <p class="text-xs font-bold" :class="state.ready.me ? 'text-[#0b5e3a]' : 'text-[#3b1d33]/70'">{{ state.ready.me ? 'Siap!' : 'Belum siap' }}</p>
                </div>
                <span class="ar-title ar-outline pb-8 text-3xl text-[#ffd54a]">VS</span>
                <div class="text-center">
                    <Face :person="partner" :size="64" :ring="state.ready.partner ? '#35c987' : '#3b1d33'" />
                    <p class="ar-chip mt-1 text-xs">{{ partner.name }}</p>
                    <p class="text-xs font-bold" :class="state.ready.partner ? 'text-[#0b5e3a]' : 'text-[#3b1d33]/70'">{{ state.ready.partner ? 'Siap!' : 'Belum siap' }}</p>
                </div>
            </div>
            <button type="button" class="ar-btn ar-btn--green ar-btn--lg mt-6" :disabled="!state || state.ready.me" @click="ready">{{ state?.ready.me ? `Menunggu ${partner.name}…` : 'Aku siap!' }}</button>
        </div>

        <!-- Countdown -->
        <div v-else-if="stage === 'count'" class="grid flex-1 place-items-center">
            <p :key="isDuo ? countdown : soloCount" class="ar-title ar-outline text-[9rem] leading-none text-white" style="animation: ar-count 0.5s ease-out both" aria-live="assertive">{{ (isDuo ? countdown : soloCount) || 'Ayo!' }}</p>
        </div>

        <!-- The game -->
        <div v-else-if="stage === 'play' || stage === 'saving'" class="relative flex min-h-0 flex-1 flex-col">
            <slot :key="playKey" :seed="gameSeed" :photos="gamePhotos" :limit="playLimit" :finish="onFinish" />
            <div v-if="goFlash" class="pointer-events-none absolute inset-0 grid place-items-center"><p class="ar-title ar-outline ar-pop text-8xl text-white">Ayo!</p></div>
        </div>

        <!-- Waiting for the partner to finish -->
        <div v-else-if="stage === 'wait'" class="mx-auto flex w-full max-w-sm flex-1 flex-col items-center justify-center px-6 pb-10 text-center">
            <div><Buddy tone="blue" mood="think" :size="112" class="ar-wiggle" /></div>
            <p class="ar-title ar-outline mt-3 text-3xl text-white">Kamu sudah selesai!</p>
            <p class="ar-card ar-paper mt-3 px-4 py-2 text-sm font-medium">Menunggu {{ partner.name }} menyelesaikan permainannya…</p>
        </div>

        <!-- Result -->
        <div v-else-if="stage === 'result'" class="flex flex-1 flex-col justify-center px-5 pb-10 pt-16">
            <template v-if="isDuo">
                <ResultPanel
                    v-if="state.mine"
                    :title="game.title" :me="me" :partner="partner" :score="state.mine.score" :stars="state.mine.stars"
                    :winner="state.partnerScore ? state.winner : null" :partner-score="state.partnerScore?.score ?? null" :partner-stars="state.partnerScore?.stars ?? 0"
                    :note="state.partnerScore ? '' : `${partner.name} belum menyelesaikan permainannya.`"
                >
                    <button type="button" class="ar-btn ar-btn--green" @click="duoAgain">Main lagi</button>
                    <button type="button" class="ar-btn ar-btn--white ar-btn--sm" @click="emit('leave')">Selesai</button>
                </ResultPanel>
                <ResultPanel v-else :title="game.title" :score="0" :stars="1" note="Waktu habis sebelum kamu sempat menyelesaikannya." :me="me" :partner="partner">
                    <button type="button" class="ar-btn ar-btn--green" @click="duoAgain">Main lagi</button>
                    <button type="button" class="ar-btn ar-btn--white ar-btn--sm" @click="emit('leave')">Selesai</button>
                </ResultPanel>
            </template>
            <ResultPanel v-else :title="game.title" :score="soloResult.score" :unit="info.unit" :stars="soloResult.stars" :best="soloResult.best" :me="me" :note="soloResult.failed ? 'Skor belum tersimpan karena koneksi terputus.' : ''">
                <button type="button" class="ar-btn ar-btn--green" @click="soloAgain">Main lagi</button>
                <button type="button" class="ar-btn ar-btn--white ar-btn--sm" @click="emit('leave')">Kembali ke Arena</button>
            </ResultPanel>
        </div>
    </div>
</template>
