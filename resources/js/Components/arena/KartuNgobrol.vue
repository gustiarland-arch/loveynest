<script setup>
// Kartu Ngobrol: a stack of questions, little dares and "pick one" cards. Alone it is one phone passed back and forth;
// as a pair both phones show the same card and the turn passes on with every card.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Buddy from '@/Components/arena/Buddy.vue';
import Sticker from '@/Components/arena/Sticker.vue';
import Face from '@/Components/arena/Face.vue';
import Stars from '@/Components/arena/Stars.vue';
import { rng, shuffled } from '@/lib/rng';
import { http } from '@/lib/http';
import { sfx, startMusic, unlock } from '@/lib/arenaAudio';

const props = defineProps({
    game: { type: Object, required: true },
    mode: { type: String, required: true },
    seed: { type: Number, required: true },
    me: { type: Object, required: true },
    partner: { type: Object, default: null },
    duo: { type: Object, default: null },
});
const emit = defineEmits(['leave']);

const isDuo = computed(() => props.mode === 'duo');
// The same order for both of you (round 1 seed never changes in this game).
const deck = shuffled(props.game.cards, rng(props.seed));

const started = ref(false);
const localIndex = ref(0);
const summary = ref(null);
const saving = ref(false);
const busy = ref(false);

const index = computed(() => (isDuo.value ? props.duo.state.value?.index ?? 0 : localIndex.value));
const turn = computed(() => {
    if (isDuo.value) return props.duo.state.value?.turn ?? 'me';
    // One phone: you and your partner take turns.
    return props.partner && localIndex.value % 2 === 1 ? 'partner' : 'me';
});
const card = computed(() => deck[index.value % deck.length]);
const lap = computed(() => Math.floor(index.value / deck.length) + 1);
const kind = computed(() => {
    const text = card.value;
    if (text.startsWith('Tantangan:')) return { label: 'Tantangan kecil', color: '#ff9a3d', sticker: 'rocket', body: text.replace(/^Tantangan:\s*/, '') };
    if (text.startsWith('Pilih satu:')) return { label: 'Pilih satu!', color: '#4f9dff', sticker: 'frog', body: text.replace(/^Pilih satu:\s*/, '') };
    return { label: 'Pertanyaan', color: '#ff7aa2', sticker: 'letter', body: text };
});
const who = computed(() => (turn.value === 'me' ? props.me : props.partner ?? props.me));
const whoName = computed(() => (turn.value === 'me' ? (isDuo.value ? 'Giliranmu' : `Giliran ${props.me.name}`) : `Giliran ${props.partner?.name ?? 'pasanganmu'}`));

function begin() {
    unlock();
    startMusic('ngobrol');
    sfx('pop');
    started.value = true;
}

async function next() {
    if (busy.value) return;
    sfx('flip');
    if (!isDuo.value) {
        localIndex.value++;
        return;
    }
    busy.value = true;
    try {
        await props.duo.act({ type: 'next', index: index.value });
    } catch {
        /* the next poll shows the real card */
    } finally {
        busy.value = false;
    }
}

async function finishSolo() {
    if (saving.value) return;
    const count = localIndex.value;
    if (count < 3) {
        emit('leave');
        return;
    }
    saving.value = true;
    try {
        const res = await http.post('/arena/skor', { game: 'ngobrol', score: count });
        summary.value = { count, stars: res.stars };
    } catch {
        summary.value = { count, stars: 1 };
    } finally {
        saving.value = false;
        sfx('win');
    }
}

// A card that has just changed gets a little flip.
const flipKey = ref(0);
watch(index, () => flipKey.value++);

onBeforeUnmount(() => {});
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col px-5 pb-8" data-testid="ngobrol">
        <!-- Intro -->
        <div v-if="!started" class="mx-auto flex w-full max-w-sm flex-1 flex-col items-center justify-center text-center">
            <div class="flex items-end gap-1"><Buddy tone="lilac" mood="happy" :size="112" class="ar-bounce" /><Sticker name="letter" :size="84" class="mb-2 rotate-6 drop-shadow-[0_4px_0_rgb(59_29_51/.35)]" /></div>
            <h2 class="ar-title ar-outline mt-3 text-[2.4rem] leading-none text-white">{{ game.title }}</h2>
            <p class="ar-card ar-paper mt-4 p-4 text-[0.95rem] font-medium leading-relaxed">Buka kartu, lalu jawab bergantian. Ada pertanyaan, pilihan seru, dan tantangan kecil. Tidak ada jawaban yang salah, yang penting jujur dan seru.</p>
            <div v-if="isDuo && partner" class="mt-4 flex items-center gap-3"><Face :person="me" :size="52" ring="#3b1d33" /><span class="ar-title ar-outline text-3xl text-[#ffd54a]">&amp;</span><Face :person="partner" :size="52" ring="#3b1d33" /></div>
            <button type="button" class="ar-btn ar-btn--lilac ar-btn--lg mt-6" data-testid="begin" @click="begin">Buka kartu pertama</button>
        </div>

        <!-- Summary (alone) -->
        <div v-else-if="summary" class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center">
            <div class="ar-card ar-paper ar-pop px-6 pb-6 pt-5 text-center">
                <div class="mx-auto -mt-16 w-24"><Buddy tone="lilac" mood="cheer" :size="96" class="ar-bounce" /></div>
                <p class="ar-title ar-outline mt-1 text-4xl text-[#ffd54a]">Seru banget!</p>
                <Stars :count="summary.stars" :size="48" class="mt-3" />
                <p class="ar-title mt-2 text-6xl leading-none text-[#7a5ad6]">{{ summary.count }}</p>
                <p class="mt-1 text-sm font-medium text-[var(--ar-soft)]">kartu terbuka</p>
                <div class="mt-5 flex flex-col gap-3">
                    <button type="button" class="ar-btn ar-btn--lilac" @click="emit('leave')">Kembali ke Arena</button>
                </div>
            </div>
        </div>

        <!-- Cards -->
        <template v-else>
            <div class="mx-auto flex w-full max-w-sm items-center justify-between pt-1">
                <div class="ar-chip" data-testid="turn"><Face :person="who" :size="28" ring="#3b1d33" /> {{ whoName }}</div>
                <div class="ar-chip tabular-nums" data-testid="count">Kartu {{ index + 1 }}<span v-if="lap > 1"> · putaran {{ lap }}</span></div>
            </div>

            <div class="mx-auto flex w-full max-w-sm flex-1 items-center">
                <div class="relative w-full">
                    <!-- the rest of the deck, peeking out below -->
                    <div class="absolute inset-0 translate-y-2 rotate-[3deg] rounded-[1.5rem] border-[3px] border-[var(--ar-line)] bg-[#e9dcc8]" aria-hidden="true"></div>
                    <div class="absolute inset-0 translate-y-1 -rotate-[2.5deg] rounded-[1.5rem] border-[3px] border-[var(--ar-line)] bg-[#f3e8d6]" aria-hidden="true"></div>
                    <div :key="flipKey" class="ar-card ar-paper relative w-full overflow-hidden" style="animation: ar-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) both">
                        <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-white" :style="{ background: kind.color, borderBottom: '3px solid #3b1d33' }">
                            <span class="ar-title text-lg uppercase tracking-wider" style="text-shadow: 0 2px 0 rgb(59 29 51 / .45)">{{ kind.label }}</span>
                            <Sticker :name="kind.sticker" :size="38" class="-my-2 rotate-6" />
                        </div>
                        <div class="grid min-h-[16rem] place-items-center px-6 py-8 text-center">
                            <p class="text-[1.45rem] font-semibold leading-snug" data-testid="card-text">{{ kind.body }}</p>
                        </div>
                        <p class="px-6 pb-5 text-center text-sm font-medium text-[var(--ar-soft)]">{{ turn === 'me' && isDuo ? 'Kamu yang menjawab dulu, lalu ' + (partner?.name ?? 'pasanganmu') + ' boleh menambahkan.' : isDuo ? `${partner?.name ?? 'Pasanganmu'} menjawab dulu, lalu kamu boleh menambahkan.` : 'Jawab keras-keras, lalu ganti giliran.' }}</p>
                    </div>
                </div>
            </div>

            <div class="mx-auto mt-5 flex w-full max-w-sm flex-col gap-3">
                <button type="button" class="ar-btn ar-btn--lilac ar-btn--lg" :disabled="busy" data-testid="next" @click="next">Sudah dijawab, kartu berikutnya</button>
                <button v-if="!isDuo" type="button" class="ar-btn ar-btn--white ar-btn--sm self-center" :disabled="saving" data-testid="done" @click="finishSolo">Cukup, selesai</button>
            </div>
        </template>
    </div>
</template>
