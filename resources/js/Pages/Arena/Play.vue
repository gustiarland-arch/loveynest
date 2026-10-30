<script setup>
// One game on a full screen of its own. The same page serves playing alone and playing as a pair.
import '@/lib/arenaFonts';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import Scene from '@/Components/arena/Scene.vue';
import Buddy from '@/Components/arena/Buddy.vue';
import MusicToggle from '@/Components/arena/MusicToggle.vue';
import VoiceToggle from '@/Components/arena/VoiceToggle.vue';
import RaceFlow from '@/Components/arena/RaceFlow.vue';
import TangkapHati from '@/Components/arena/TangkapHati.vue';
import KartuKembar from '@/Components/arena/KartuKembar.vue';
import PuzzleMomen from '@/Components/arena/PuzzleMomen.vue';
import KartuNgobrol from '@/Components/arena/KartuNgobrol.vue';
import AduPenalti from '@/Components/arena/AduPenalti.vue';
import { useArenaSession } from '@/composables/useArenaSession';
import { GAMES } from '@/lib/arenaGames';
import { stopMusic } from '@/lib/arenaAudio';
import { useArenaBackdrop } from '@/composables/useArenaBackdrop';

const props = defineProps({
    game: { type: Object, required: true },
    mode: { type: String, required: true },
    seed: { type: Number, required: true },
    photos: { type: Array, default: () => [] },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
    session: { type: Object, default: null },
});

const info = GAMES[props.game.key];
useArenaBackdrop(info.flat);
const duo = props.mode === 'duo' ? useArenaSession(props.session.id) : null;
const races = { kartu: KartuKembar, puzzle: PuzzleMomen, tangkap: TangkapHati };
const confirming = ref(false);
let leaving = false;

async function leave() {
    if (leaving) return;
    leaving = true;
    stopMusic();
    if (duo) {
        duo.stop();
        try {
            await duo.act({ type: 'leave' });
        } catch {
            /* the other side sees the session end by itself after a while */
        }
    }
    router.visit('/arena');
}

function back() {
    if (duo && !duo.ended.value) confirming.value = !confirming.value;
    else leave();
}

const partnerLeft = computed(() => duo?.ended.value && !leaving);

onBeforeUnmount(() => {
    stopMusic();
    duo?.stop();
});

// The browser's back button leaves the game too.
const onPop = () => stopMusic();
onMounted(() => window.addEventListener('popstate', onPop));
onBeforeUnmount(() => window.removeEventListener('popstate', onPop));
</script>

<template>
    <Head :title="game.title" />
    <div class="ar-stage flex flex-col" :style="{ '--ar-bg': info.flat }">
        <Scene :name="info.scene" />

        <header class="relative z-10 flex items-center justify-between gap-3 px-4 pb-1 pt-[max(0.75rem,env(safe-area-inset-top))]">
            <button type="button" class="ar-chip !size-11 !justify-center !rounded-xl !p-0 text-[#b0306a] transition active:translate-y-0.5" aria-label="Keluar dari permainan" data-testid="back" @click="back">
                <ChevronLeft class="size-6" :stroke-width="2.6" aria-hidden="true" />
            </button>
            <h1 class="ar-title ar-chip min-w-0 !rounded-2xl !px-4 !py-1.5 text-center text-[1.1rem] leading-tight" :style="{ background: info.flat, color: '#fff', textShadow: '0 2px 0 rgb(59 29 51 / .45)' }">{{ game.title }}</h1>
            <div class="flex items-center gap-2">
                <VoiceToggle v-if="duo && partner" :session="session.id" :me="me.id" :partner="partner.id" :name="partner.name" />
                <MusicToggle :track="info.track" />
            </div>
        </header>

        <div v-if="confirming" class="relative z-20 mx-4 mt-1 ar-card p-4 text-center" role="alertdialog" aria-label="Keluar dari permainan?">
            <p class="font-extrabold">Keluar dari permainan?</p>
            <p class="mt-0.5 text-sm font-semibold text-[var(--ar-soft)]">{{ partner?.name }} juga akan keluar dari permainan ini.</p>
            <div class="mt-3 flex justify-center gap-3">
                <button type="button" class="ar-btn ar-btn--sm" data-testid="leave-yes" @click="leave">Ya, keluar</button>
                <button type="button" class="ar-btn ar-btn--white ar-btn--sm" @click="confirming = false">Lanjut main</button>
            </div>
        </div>
        <p v-if="duo && duo.failing.value && !partnerLeft" class="relative z-10 mx-auto mt-1 rounded-full bg-white/90 px-4 py-1 text-xs font-bold text-[#d70015]" role="status">Koneksi terputus, mencoba lagi…</p>

        <main class="relative z-10 flex min-h-0 flex-1 flex-col pb-[env(safe-area-inset-bottom)]">
            <RaceFlow v-if="game.kind === 'race'" :game="game" :mode="mode" :seed="seed" :photos="photos" :me="me" :partner="partner" :duo="duo" @leave="leave">
                <template #default="{ seed: s, photos: p, limit, finish }">
                    <component :is="races[game.key]" :seed="s" :photos="p" :limit="limit" :me="me" :partner="partner" @finish="finish" />
                </template>
            </RaceFlow>
            <KartuNgobrol v-else-if="game.key === 'ngobrol'" :game="game" :mode="mode" :seed="seed" :me="me" :partner="partner" :duo="duo" @leave="leave" />
            <AduPenalti v-else :game="game" :mode="mode" :me="me" :partner="partner" :duo="duo" @leave="leave" />
        </main>

        <!-- The other one left, or the session ended -->
        <div v-if="partnerLeft" class="fixed inset-0 z-[70] grid place-items-center bg-[#3a1030]/45 p-6">
            <div class="ar-card ar-pop w-full max-w-sm px-6 pb-6 pt-4 text-center">
                <div class="mx-auto -mt-16 w-24"><Buddy tone="lilac" mood="sad" :size="96" /></div>
                <p class="ar-title mt-2 text-2xl text-[#e0245e]">Permainan selesai</p>
                <p class="mt-1 text-sm font-semibold text-[var(--ar-soft)]">{{ partner?.name }} sudah keluar dari permainan.</p>
                <button type="button" class="ar-btn mt-5" data-testid="ended-ok" @click="leave">Kembali ke Arena</button>
            </div>
        </div>
    </div>
</template>
