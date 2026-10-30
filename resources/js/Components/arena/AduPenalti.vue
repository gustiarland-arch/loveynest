<script setup>
// Adu Penalti: five kicks each. On every kick one of you shoots (left, middle or right) while the other picks a side
// to dive to, both in secret. Different sides is a goal; the same side is a save. Alone, the other side is the computer.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Buddy from '@/Components/arena/Buddy.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import Face from '@/Components/arena/Face.vue';
import ResultPanel from '@/Components/arena/ResultPanel.vue';
import { http } from '@/lib/http';
import { sfx, startMusic, unlock } from '@/lib/arenaAudio';

const props = defineProps({
    game: { type: Object, required: true },
    mode: { type: String, required: true },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
    duo: { type: Object, default: null },
});
const emit = defineEmits(['leave']);

const isDuo = computed(() => props.mode === 'duo');
const EACH = props.game.kicks;
const TOTAL = EACH * 2;
const SIDES = ['Kiri', 'Tengah', 'Kanan'];
const other = computed(() => (isDuo.value ? props.partner : { id: 0, name: 'Bot Mochi', avatarUrl: null }));

const started = ref(false);

// ----- Alone: the same shape as the server's state, kept here -----
const soloState = ref({ kick: 1, picks: {}, log: [], round: 1 });
function soloPick(choice) {
    const s = soloState.value;
    if (s.kick > TOTAL) return;
    const shooter = s.kick % 2 === 1 ? 'me' : 'partner';
    const computer = Math.floor(Math.random() * 3);
    const mine = choice;
    const shot = shooter === 'me' ? mine : computer;
    const dive = shooter === 'me' ? computer : mine;
    soloState.value = {
        ...s,
        picks: {},
        kick: s.kick + 1,
        log: [...s.log, { kick: s.kick, shooter, shot, dive, goal: shot !== dive }],
    };
}

// ----- What both modes show -----
const view = computed(() => {
    if (!isDuo.value) {
        const s = soloState.value;
        return { kick: s.kick, over: s.kick > TOTAL, shooter: s.kick % 2 === 1 ? 'me' : 'partner', picked: { me: s.picks.me !== undefined, partner: false }, log: s.log, round: s.round };
    }
    const s = props.duo.state.value;
    if (!s) return null;
    return { kick: s.kick, over: s.over, shooter: s.shooter, picked: s.picked, log: s.log, round: s.round };
});

const shown = ref(0); // how many log entries have been played out on screen
const anim = ref(null); // { entry, step } while a kick is being shown
const initialised = ref(false);
let timers = [];
let busy = false;

function later(ms, fn) {
    const id = setTimeout(fn, ms);
    timers.push(id);
}

function playNext() {
    const v = view.value;
    if (!v || anim.value || shown.value >= v.log.length) return;
    const entry = v.log[shown.value];
    anim.value = { entry, step: 0 };
    sfx('whistle');
    later(450, () => {
        if (!anim.value) return;
        anim.value = { entry, step: 1 };
        sfx('kick');
    });
    later(1150, () => {
        if (!anim.value) return;
        anim.value = { entry, step: 2 };
        sfx(entry.goal ? 'goal' : 'save');
        if (entry.goal) navigator.vibrate?.(60);
    });
    later(2900, () => {
        shown.value++;
        anim.value = null;
        playNext();
    });
}

watch(
    () => view.value?.log.length,
    (length) => {
        if (length === undefined) return;
        if (!initialised.value) {
            // Opening the page in the middle of a match: do not replay what already happened.
            shown.value = length;
            initialised.value = true;
            return;
        }
        playNext();
    },
    { immediate: true },
);

// A rematch starts from kick one again.
watch(
    () => view.value?.round,
    (round, old) => {
        if (old === undefined || round === old) return;
        timers.forEach(clearTimeout);
        timers = [];
        anim.value = null;
        shown.value = 0;
        result.value = null;
    },
);

const revealed = computed(() => shown.value + (anim.value && anim.value.step >= 2 ? 1 : 0));
const shownLog = computed(() => (view.value ? view.value.log.slice(0, revealed.value) : []));
const goals = computed(() => ({
    me: shownLog.value.filter((l) => l.shooter === 'me' && l.goal).length,
    partner: shownLog.value.filter((l) => l.shooter === 'partner' && l.goal).length,
}));
const dots = (side) => {
    const entries = (view.value?.log ?? []).slice(0, revealed.value).filter((l) => l.shooter === side);
    return Array.from({ length: EACH }, (_, i) => (entries[i] ? (entries[i].goal ? 'goal' : 'miss') : 'todo'));
};

const watching = computed(() => !!anim.value || (view.value && shown.value < view.value.log.length));
const phase = computed(() => {
    const v = view.value;
    if (!v) return 'load';
    if (watching.value) return 'anim';
    if (v.over) return 'over';
    return 'choose';
});
const role = computed(() => (view.value?.shooter === 'me' ? 'shoot' : 'keep'));
const waiting = computed(() => view.value?.picked.me && !watching.value);

async function choose(side) {
    if (busy || phase.value !== 'choose' || view.value.picked.me) return;
    sfx('tap');
    if (!isDuo.value) {
        soloState.value = { ...soloState.value, picks: { me: side } };
        // A short pause, as if the computer were thinking.
        busy = true;
        later(350, () => {
            busy = false;
            soloPick(side);
        });
        return;
    }
    busy = true;
    try {
        await props.duo.act({ type: 'pick', round: view.value.kick, choice: side });
    } catch {
        /* the next poll shows what the server has */
    } finally {
        busy = false;
    }
}

// ----- The end -----
const result = ref(null);
const finalGoals = computed(() => ({
    me: (view.value?.log ?? []).filter((l) => l.shooter === 'me' && l.goal).length,
    partner: (view.value?.log ?? []).filter((l) => l.shooter === 'partner' && l.goal).length,
}));
const starsFor = (n) => (n >= 4 ? 3 : n >= 2 ? 2 : 1);
const winner = computed(() => (finalGoals.value.me === finalGoals.value.partner ? 'draw' : finalGoals.value.me > finalGoals.value.partner ? 'me' : 'partner'));

watch(phase, async (value) => {
    if (value !== 'over' || isDuo.value || result.value) return;
    const mine = finalGoals.value.me;
    result.value = { stars: starsFor(mine), best: false, saved: false };
    try {
        const res = await http.post('/arena/skor', { game: 'penalti', score: mine, won: winner.value === 'draw' ? null : winner.value === 'me' });
        result.value = { stars: res.stars, best: res.isBest, saved: true };
    } catch {
        result.value = { stars: starsFor(mine), best: false, saved: false };
    }
});

function begin() {
    unlock();
    startMusic('penalti');
    sfx('pop');
    started.value = true;
}

async function again() {
    sfx('pop');
    if (!isDuo.value) {
        timers.forEach(clearTimeout);
        timers = [];
        anim.value = null;
        shown.value = 0;
        result.value = null;
        soloState.value = { kick: 1, picks: {}, log: [], round: soloState.value.round + 1 };
        return;
    }
    try {
        await props.duo.act({ type: 'again', round: view.value.round });
    } catch {
        /* the next poll shows the real state */
    }
}

// ----- Positions on screen -----
const zoneX = [17, 50, 83]; // percent of the goal's width
const keeperZone = computed(() => (anim.value && anim.value.step >= 1 ? anim.value.entry.dive : 1));
const ballZone = computed(() => (anim.value && anim.value.step >= 1 ? anim.value.entry.shot : null));
const keeperTone = computed(() => {
    const defender = anim.value ? (anim.value.entry.shooter === 'me' ? 'partner' : 'me') : view.value?.shooter === 'me' ? 'partner' : 'me';
    return defender === 'me' ? 'pink' : 'blue';
});
const shooterPerson = computed(() => (view.value?.shooter === 'me' ? props.me : other.value));

onBeforeUnmount(() => timers.forEach(clearTimeout));
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col px-4 pb-8" data-testid="penalti">
        <!-- Intro -->
        <div v-if="!started" class="mx-auto flex w-full max-w-sm flex-1 flex-col items-center justify-center text-center">
            <div class="flex items-end gap-1"><Buddy tone="pink" mood="cheer" :size="112" class="ar-bounce" /><Sticker name="ball" :size="64" class="mb-2 rotate-12 drop-shadow-[0_4px_0_rgb(59_29_51/.3)]" /></div>
            <h2 class="ar-title ar-outline mt-3 text-[2.4rem] leading-none text-white">{{ game.title }}</h2>
            <p class="mt-3 ar-card ar-paper p-4 text-[0.95rem] font-medium leading-relaxed">Bergantian jadi penendang dan penjaga gawang. Pilih kiri, tengah, atau kanan. Gol kalau pilihan kalian berbeda. Lima tendangan untuk masing-masing{{ isDuo ? '' : ', lawanmu Bot Mochi' }}.</p>
            <div class="mt-4 flex items-center gap-3"><Face :person="me" :size="52" ring="#3b1d33" /><span class="ar-title ar-outline text-3xl text-[#ffd54a]">VS</span><Face :person="other" :size="52" ring="#3b1d33" /></div>
            <button type="button" class="ar-btn ar-btn--green ar-btn--lg mt-6" data-testid="begin" @click="begin">Ayo tendang!</button>
        </div>

        <div v-else-if="!view" class="grid flex-1 place-items-center"><div><Buddy tone="pink" mood="think" :size="96" class="ar-wiggle" /></div></div>

        <!-- The match -->
        <template v-else-if="view && (phase !== 'over' || (isDuo ? true : result))">
            <!-- Scoreboard -->
            <div class="mx-auto grid w-full max-w-md grid-cols-[1fr_auto_1fr] items-center gap-2 pt-1" data-testid="board">
                <div class="ar-chip justify-between !px-2.5"><Face :person="me" :size="28" ring="#3b1d33" /><span class="ar-title text-2xl tabular-nums" data-testid="goals-me">{{ goals.me }}</span></div>
                <span class="ar-chip text-xs tabular-nums">{{ Math.min(view.kick, TOTAL) }}/{{ TOTAL }}</span>
                <div class="ar-chip justify-between !px-2.5"><span class="ar-title text-2xl tabular-nums" data-testid="goals-partner">{{ goals.partner }}</span><Face :person="other" :size="28" ring="#3b1d33" /></div>
            </div>
            <div class="mx-auto mt-1 grid w-full max-w-md grid-cols-2 gap-2">
                <div class="flex gap-1"><span v-for="(d, i) in dots('me')" :key="i" class="size-3.5 rounded-full border-2 border-[var(--ar-line)]" :class="d === 'goal' ? 'bg-[#35c987]' : d === 'miss' ? 'bg-[#ff6b6b]' : 'bg-white/70'" /></div>
                <div class="flex justify-end gap-1"><span v-for="(d, i) in dots('partner')" :key="i" class="size-3.5 rounded-full border-2 border-[var(--ar-line)]" :class="d === 'goal' ? 'bg-[#35c987]' : d === 'miss' ? 'bg-[#ff6b6b]' : 'bg-white/70'" /></div>
            </div>

            <p class="ar-chip mx-auto mt-3 min-h-[2.5rem] text-center text-base" aria-live="polite" data-testid="prompt">
                <template v-if="phase === 'over'">Adu penalti selesai!</template>
                <template v-else-if="phase === 'anim'">{{ anim && anim.step >= 2 ? (anim.entry.goal ? 'GOL!' : 'DITANGKAP!') : 'Tendangan…' }}</template>
                <template v-else-if="waiting">{{ isDuo ? `Menunggu ${other.name}…` : 'Bot Mochi sedang berpikir…' }}</template>
                <template v-else-if="role === 'shoot'">Giliranmu menendang! Pilih arah.</template>
                <template v-else>Giliranmu menjaga gawang! Pilih arah lompatan.</template>
            </p>

            <!-- The pitch -->
            <div class="relative mx-auto mt-1 w-full max-w-md flex-1">
                <div class="relative mx-auto aspect-[6/5] w-full overflow-hidden rounded-[1.5rem] border-[3px] border-[var(--ar-line)] bg-[#58c76b] shadow-[0_6px_0_#3b1d33]" :style="{ maxHeight: 'calc(100dvh - 22rem)' }">
                    <div class="absolute inset-0" style="background: repeating-linear-gradient(0deg, #5fcf72 0 12%, #52c066 12% 24%)" />
                    <div class="absolute bottom-[-30%] left-1/2 aspect-square w-[70%] -translate-x-1/2 rounded-full border-[3px] border-white/60" /><span class="absolute left-1/2 top-[84%] size-2 -translate-x-1/2 rounded-full bg-white/80" />
                    <!-- goal -->
                    <div class="absolute left-[10%] top-[8%] h-[54%] w-[80%] rounded-t-xl border-[5px] border-b-0 border-[var(--ar-line)] bg-white/30 shadow-[inset_0_0_0_3px_#fff]" style="background-image: linear-gradient(rgb(59 29 51/.25) 1.5px, transparent 1.5px), linear-gradient(90deg, rgb(59 29 51/.25) 1.5px, transparent 1.5px); background-size: 16px 16px">
                        <!-- keeper -->
                        <div
                            class="pointer-events-none absolute bottom-0 w-[24%] transition-all duration-500 ease-out"
                            :style="{ left: `${zoneX[keeperZone]}%`, transform: `translateX(-50%) rotate(${anim && anim.step >= 1 ? (keeperZone - 1) * 38 : 0}deg)` }"
                            data-testid="keeper"
                        >
                            <Buddy :tone="keeperTone" :mood="anim && anim.step >= 2 ? (anim.entry.goal ? 'oops' : 'cheer') : anim && anim.step >= 1 ? 'cheer' : 'happy'" :size="100" />
                        </div>
                        <!-- zones to tap -->
                        <div class="absolute inset-0 grid grid-cols-3">
                            <button
                                v-for="(name, side) in SIDES"
                                :key="side"
                                type="button"
                                class="group relative grid place-items-end justify-items-center pb-2 outline-none transition"
                                :class="phase === 'choose' && !view.picked.me ? 'cursor-pointer hover:bg-white/30 focus-visible:bg-white/40 active:bg-white/50' : 'cursor-default'"
                                :disabled="phase !== 'choose' || view.picked.me"
                                :aria-label="`${role === 'shoot' ? 'Tendang ke' : 'Lompat ke'} ${name.toLowerCase()}`"
                                :data-testid="`zone-${side}`"
                                @click="choose(side)"
                            >
                                <span v-if="phase === 'choose' && !view.picked.me" class="ar-chip !px-2.5 !py-0 text-[0.75rem]">{{ name }}</span>
                            </button>
                        </div>
                    </div>
                    <!-- ball -->
                    <div
                        class="pointer-events-none absolute leading-none transition-all ease-out"
                        :class="anim && anim.step >= 1 ? 'duration-[600ms]' : 'duration-200'"
                        :style="ballZone === null ? { left: '50%', top: '82%', transform: 'translate(-50%, -50%)' } : { left: `${10 + (zoneX[ballZone] / 100) * 80}%`, top: '30%', transform: 'translate(-50%, -50%) scale(.7) rotate(540deg)' }"
                        data-testid="ball"
                    ><Sticker name="ball" :size="40" /></div>
                    <!-- who is shooting -->
                    <div class="pointer-events-none absolute bottom-3 left-3 flex items-center gap-2"><Face v-if="phase !== 'over'" :person="shooterPerson" :size="34" ring="#3b1d33" /><span v-if="phase !== 'over'" class="ar-chip !px-2 !py-0 text-[0.75rem]">{{ view.shooter === 'me' ? 'Penendang: kamu' : `Penendang: ${other.name}` }}</span></div>
                    <!-- result banner -->
                    <div v-if="anim && anim.step >= 2" class="pointer-events-none absolute inset-0 grid place-items-center">
                        <p class="ar-title ar-outline ar-pop text-6xl" :class="anim.entry.goal ? 'text-[#7dffb0]' : 'text-[#ff9aa8]'" style="transform: rotate(-4deg)">{{ anim.entry.goal ? 'GOL!' : 'Tangkap!' }}</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- The end -->
        <div v-if="started && view && phase === 'over'" class="absolute inset-x-0 bottom-0 top-14 z-20 flex flex-col justify-center px-5 pb-8 pt-16" :class="isDuo || result ? '' : 'hidden'">
            <ResultPanel
                :title="game.title" :me="me" :partner="other" :score="finalGoals.me" unit="gol" :stars="starsFor(finalGoals.me)"
                :best="!!result?.best" :winner="winner" :partner-score="finalGoals.partner" :partner-stars="starsFor(finalGoals.partner)"
                :note="isDuo ? '' : result && !result.saved ? 'Skor belum tersimpan karena koneksi terputus.' : ''"
            >
                <button type="button" class="ar-btn ar-btn--green" data-testid="again" @click="again">Main lagi</button>
                <button type="button" class="ar-btn ar-btn--white ar-btn--sm" @click="emit('leave')">{{ isDuo ? 'Selesai' : 'Kembali ke Arena' }}</button>
            </ResultPanel>
        </div>
    </div>
</template>
