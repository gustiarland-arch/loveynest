<script setup>
// The Arena: the five games, your best scores, and the button to invite your partner.
import '@/lib/arenaFonts';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Star } from '@lucide/vue';
import Scene from '@/Components/arena/Scene.vue';
import Buddy from '@/Components/arena/Buddy.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import MusicToggle from '@/Components/arena/MusicToggle.vue';
import Face from '@/Components/arena/Face.vue';
import { arena, expectAnswer, refreshArena } from '@/composables/useArenaWatch';
import { GAMES, ORDER } from '@/lib/arenaGames';
import { http } from '@/lib/http';
import { musicOn, sfx, startMusic, stopMusic, unlock } from '@/lib/arenaAudio';
import { useArenaBackdrop } from '@/composables/useArenaBackdrop';

useArenaBackdrop('linear-gradient(#bfe8ff 55%, #5fc76e 55%)');

const props = defineProps({
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
    games: { type: Array, required: true },
    stars: { type: Object, required: true },
    wins: { type: Object, required: true },
});

const byKey = computed(() => Object.fromEntries(props.games.map((g) => [g.key, g])));
const sending = ref('');
const error = ref('');
const cancelling = ref(false);

async function invite(key) {
    if (sending.value || !props.partner) return;
    unlock();
    sfx('pop');
    sending.value = key;
    error.value = '';
    try {
        const { id } = await http.post('/arena/undang', { game: key });
        arena.outgoing = { id, game: key, title: byKey.value[key].title };
        expectAnswer(id);
    } catch (e) {
        if (e.status === 409 && e.data?.running) router.visit(`/arena/sesi/${e.data.running}`);
        else if (e.status === 409 && e.data?.incoming) refreshArena();
        else error.value = 'Ajakan belum terkirim. Coba lagi ya.';
    } finally {
        sending.value = '';
    }
}

async function cancel() {
    const out = arena.outgoing;
    if (!out || cancelling.value) return;
    cancelling.value = true;
    try {
        await http.post(`/arena/sesi/${out.id}/batal`);
    } catch {
        /* it expires by itself */
    } finally {
        arena.outgoing = null;
        arena.invited = null;
        cancelling.value = false;
    }
}

// Music starts at the first tap on the page (browsers do not allow it earlier).
function firstTap() {
    if (musicOn()) startMusic('hub');
}
onMounted(() => document.addEventListener('pointerdown', firstTap, { once: true }));
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', firstTap);
    stopMusic();
});
</script>

<template>
    <Head title="Arena" />
    <div class="ar-stage -mx-5 -mb-32 -mt-5 min-h-[calc(100dvh)] px-5 pb-32 pt-[max(1rem,env(safe-area-inset-top))] sm:-mx-6 sm:px-6 lg:-mb-12 lg:-mt-10 lg:pt-10" style="--ar-bg: #bfe8ff">
        <Scene name="hub" />
        <div class="relative z-10 mx-auto max-w-2xl">
            <div class="flex items-center justify-between pt-5">
                <Link href="/berdua" class="ar-chip !size-11 !justify-center !rounded-xl !p-0 text-[#b0306a] transition active:translate-y-0.5" aria-label="Kembali"><ChevronLeft class="size-6" :stroke-width="2.8" aria-hidden="true" /></Link>
                <MusicToggle track="hub" />
            </div>

            <div class="mt-1 flex items-end justify-between gap-2">
                <div class="min-w-0 pb-2">
                    <h1 class="ar-title ar-outline text-[3.4rem] leading-none text-[#ff7aa2]">ARENA</h1>
                    <p class="ar-chip mt-3 !bg-[#fff2a0] text-sm font-semibold">Main bareng, ketawa bareng!</p>
                </div>
                <div class="flex shrink-0 items-end -space-x-3">
                    <Buddy tone="pink" mood="cheer" :size="92" class="ar-bounce" />
                    <Buddy tone="blue" mood="wink" :size="92" flip class="ar-bounce" style="animation-delay: -0.5s" />
                </div>
            </div>

            <!-- A game is already running -->
            <Link v-if="arena.active" :href="`/arena/sesi/${arena.active.id}`" class="ar-card ar-pop mt-4 flex items-center gap-3 px-4 py-3" data-testid="resume">
                <Sticker name="rocket" :size="40" />
                <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-[#1f9a64]">Permainan sedang berjalan</span><span class="block truncate text-base font-semibold">{{ arena.active.title }}</span></span>
                <span class="ar-btn ar-btn--green ar-btn--sm">Masuk</span>
            </Link>

            <!-- Scoreboard of the two of you -->
            <div class="ar-card ar-paper mt-4 grid grid-cols-[1fr_auto_1fr] items-center gap-2 px-4 py-3" data-testid="scoreboard">
                <div class="flex items-center gap-2"><Face :person="me" :size="44" ring="#3b1d33" /><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ me.name }}</p><p class="flex items-center gap-1 text-base font-semibold text-[#c98900]"><Star class="size-4 fill-[#ffc83d] text-[#3b1d33]" aria-hidden="true" />{{ stars.mine }}</p><p class="text-xs text-[var(--ar-soft)]">{{ wins.mine }} menang</p></div></div>
                <span class="ar-title grid size-11 place-items-center rounded-full border-[3px] border-[var(--ar-line)] bg-[#ff7aa2] text-lg text-white">VS</span>
                <div v-if="partner" class="flex items-center justify-end gap-2 text-right"><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ partner.name }}</p><p class="flex items-center justify-end gap-1 text-base font-semibold text-[#c98900]"><Star class="size-4 fill-[#ffc83d] text-[#3b1d33]" aria-hidden="true" />{{ stars.partner }}</p><p class="text-xs text-[var(--ar-soft)]">{{ wins.partner }} menang</p></div><Face :person="partner" :size="44" ring="#3b1d33" /></div>
                <p v-else class="text-right text-xs font-medium text-[var(--ar-soft)]">Ajak pasanganmu bergabung dulu untuk main berdua.</p>
            </div>

            <p v-if="error" class="ar-card mt-3 px-4 py-2 text-sm font-semibold text-[#d70015]" role="alert">{{ error }}</p>

            <ul class="mt-6 grid gap-6">
                <li v-for="(key, i) in ORDER" :key="key" class="ar-card ar-rise overflow-hidden" :style="{ animationDelay: `${i * 70}ms` }" :data-testid="`game-${key}`">
                    <div class="relative flex min-h-[7.5rem] items-center overflow-hidden px-4 py-3 text-white" :style="{ backgroundColor: GAMES[key].flat, backgroundImage: 'radial-gradient(rgb(255 255 255 / .3) 2.2px, transparent 2.8px)', backgroundSize: '18px 18px', borderBottom: '3px solid #3b1d33' }">
                        <div class="relative z-10 min-w-0 max-w-[58%]">
                            <h2 class="ar-title ar-outline text-[1.6rem] leading-[1.05]" style="--ar-line: #3b1d33">{{ byKey[key].title }}</h2>
                            <p class="mt-1 text-[0.82rem] font-semibold leading-tight" style="text-shadow: 0 1px 0 rgb(59 29 51 / .5)">{{ GAMES[key].tagline }}</p>
                        </div>
                        <div class="absolute -right-1 top-1/2 flex -translate-y-1/2 items-center" aria-hidden="true">
                            <Sticker :name="GAMES[key].stickers[0]" :size="82" class="-rotate-6 drop-shadow-[0_4px_0_rgb(59_29_51/.25)]" />
                            <Sticker :name="GAMES[key].stickers[1]" :size="58" class="-ml-5 mt-8 rotate-12 drop-shadow-[0_4px_0_rgb(59_29_51/.25)]" />
                        </div>
                    </div>
                    <div class="ar-paper px-4 pb-4 pt-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm font-semibold">
                            <span class="flex items-center gap-1"><Star class="size-4 fill-[#ffc83d] text-[#3b1d33]" aria-hidden="true" />Terbaikmu: <span class="tabular-nums">{{ byKey[key].mine ? `${byKey[key].mine.score} ${GAMES[key].unit}` : '–' }}</span></span>
                            <span v-if="partner" class="text-[var(--ar-soft)]">{{ partner.name }}: <span class="tabular-nums">{{ byKey[key].partner ? `${byKey[key].partner.score} ${GAMES[key].unit}` : '–' }}</span></span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <Link :href="`/arena/${key}`" class="ar-btn ar-btn--blue" data-testid="solo">{{ key === 'ngobrol' ? 'Satu HP' : 'Main sendiri' }}</Link>
                            <button type="button" class="ar-btn" :disabled="!partner || !!sending || !!arena.outgoing" data-testid="invite" @click="invite(key)">{{ sending === key ? 'Mengirim…' : partner ? `Ajak ${partner.name}` : 'Ajak pasangan' }}</button>
                        </div>
                    </div>
                </li>
                <li class="ar-card ar-rise overflow-hidden" :style="{ animationDelay: `${ORDER.length * 70}ms` }" data-testid="game-main">
                    <div class="relative flex min-h-[7.5rem] items-center overflow-hidden px-4 py-3 text-white" style="background-color: #9f80ff; background-image: radial-gradient(rgb(255 255 255 / .3) 2.2px, transparent 2.8px); background-size: 18px 18px; border-bottom: 3px solid #3b1d33">
                        <div class="relative z-10 min-w-0 max-w-[58%]">
                            <h2 class="ar-title ar-outline text-[1.6rem] leading-[1.05]" style="--ar-line: #3b1d33">Main Berdua</h2>
                            <p class="mt-1 text-[0.82rem] font-semibold leading-tight" style="text-shadow: 0 1px 0 rgb(59 29 51 / .5)">Kuis, tantangan, kartu, dan hadiah</p>
                        </div>
                        <div class="absolute -right-1 top-1/2 flex -translate-y-1/2 items-center" aria-hidden="true">
                            <Sticker name="heart" :size="82" class="-rotate-6 drop-shadow-[0_4px_0_rgb(59_29_51/.25)]" />
                            <Sticker name="star" :size="58" class="-ml-5 mt-8 rotate-12 drop-shadow-[0_4px_0_rgb(59_29_51/.25)]" />
                        </div>
                    </div>
                    <div class="ar-paper px-4 pb-4 pt-3">
                        <p class="text-sm font-semibold text-[var(--ar-soft)]">Santai saja, boleh dilewati kapan pun. Jawab kuis tentang satu sama lain dan kumpulkan lencana.</p>
                        <div class="mt-3">
                            <Link v-if="partner" href="/main" class="ar-btn ar-btn--blue w-full" data-testid="main-berdua">Buka Main Berdua</Link>
                            <span v-else class="ar-btn opacity-60" aria-disabled="true">Ajak pasangan dulu</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Waiting for the answer -->
        <Teleport to="body">
            <div v-if="arena.outgoing" class="fixed inset-0 z-[80] grid place-items-center bg-[#3a1030]/55 p-6" role="dialog" aria-modal="true" aria-labelledby="arena-wait" data-testid="waiting">
                <div class="ar-stage ar-card ar-pop !min-h-0 !overflow-visible w-full max-w-sm px-6 pb-6 pt-4 text-center" style="--ar-bg: #fffaf3">
                    <div class="mx-auto -mt-16 w-24"><Buddy tone="lilac" mood="wink" :size="96" class="ar-wiggle" /></div>
                    <p id="arena-wait" class="ar-title mt-2 text-[1.7rem] text-[#e0245e]">Ajakan terkirim!</p>
                    <p class="mt-1 text-base font-semibold">{{ arena.outgoing.title }}</p>
                    <p class="mt-1 text-sm font-medium text-[var(--ar-soft)]">Menunggu {{ partner?.name }} menerima. Begitu diterima, kalian langsung masuk ke permainan.</p>
                    <button type="button" class="ar-btn ar-btn--white ar-btn--sm mt-5" :disabled="cancelling" data-testid="cancel-invite" @click="cancel">Batalkan ajakan</button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
