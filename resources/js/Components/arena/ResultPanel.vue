<script setup>
// The end-of-game screen: stars, the score, and (when two played) who won.
import { computed, onMounted } from 'vue';
import Buddy from '@/Components/arena/Buddy.vue';
import Stars from '@/Components/arena/Stars.vue';
import Face from '@/Components/arena/Face.vue';
import Confetti from '@/Components/arena/Confetti.vue';
import { sfx } from '@/lib/arenaAudio';

const props = defineProps({
    title: { type: String, default: '' },
    score: { type: Number, default: 0 },
    unit: { type: String, default: 'poin' },
    stars: { type: Number, default: 1 },
    best: { type: Boolean, default: false },
    me: { type: Object, default: null },
    partner: { type: Object, default: null },
    // Duo: 'me' | 'partner' | 'draw' | null
    winner: { type: String, default: null },
    partnerScore: { type: Number, default: null },
    partnerStars: { type: Number, default: 0 },
    note: { type: String, default: '' },
});

const duo = computed(() => props.winner !== null && props.partnerScore !== null);
const headline = computed(() => {
    if (!duo.value) return props.best ? 'Rekor baru!' : 'Selesai!';
    if (props.winner === 'me') return 'Kamu menang!';
    if (props.winner === 'draw') return 'Seri, sama kuat!';
    return `${props.partner?.name ?? 'Pasanganmu'} menang!`;
});
const face = computed(() => (props.winner === 'partner' ? 'wink' : 'cheer'));
const celebrate = computed(() => (duo.value ? props.winner === 'me' : props.stars >= 2 || props.best));

onMounted(() => sfx(!duo.value ? 'win' : props.winner === 'me' ? 'win' : props.winner === 'draw' ? 'draw' : 'lose'));
</script>

<template>
    <div class="ar-card ar-pop mx-auto w-full max-w-sm px-6 pb-6 pt-5 text-center">
        <Confetti v-if="celebrate" />
        <div class="mx-auto -mt-16 w-24">
            <Buddy :tone="winner === 'partner' ? 'blue' : 'pink'" :mood="face" :size="96" class="ar-bounce" />
        </div>
        <p class="ar-title ar-outline mt-1 text-4xl text-[#ffd54a]">{{ headline }}</p>
        <p v-if="title" class="mt-0.5 text-sm font-bold text-[var(--ar-soft)]">{{ title }}</p>

        <template v-if="duo">
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-2xl border-[2.5px] border-[var(--ar-line)] p-3" :class="winner === 'me' ? 'bg-[#ffe27a]' : 'bg-[#f3e6ee]'">
                    <Face :person="me" :size="44" />
                    <p class="mt-1 text-xs font-bold">{{ me?.name }}</p>
                    <p class="text-3xl font-black leading-none text-[#e0245e]">{{ score }}</p>
                    <Stars :count="stars" :size="18" class="mt-1" />
                </div>
                <div class="rounded-2xl border-[2.5px] border-[var(--ar-line)] p-3" :class="winner === 'partner' ? 'bg-[#ffe27a]' : 'bg-[#f3e6ee]'">
                    <Face :person="partner" :size="44" />
                    <p class="mt-1 text-xs font-bold">{{ partner?.name }}</p>
                    <p class="text-3xl font-black leading-none text-[#7a5ad6]">{{ partnerScore }}</p>
                    <Stars :count="partnerStars" :size="18" class="mt-1" />
                </div>
            </div>
        </template>
        <template v-else>
            <Stars :count="stars" :size="52" class="mt-3" />
            <p class="mt-2 text-5xl font-black leading-none text-[#e0245e]">{{ score }}</p>
            <p class="mt-1 text-sm font-bold text-[var(--ar-soft)]">{{ unit }}</p>
        </template>

        <p v-if="note" class="mt-3 text-sm font-semibold text-[var(--ar-soft)]">{{ note }}</p>
        <div class="mt-5 flex flex-col gap-3">
            <slot />
        </div>
    </div>
</template>
