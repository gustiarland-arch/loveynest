<script setup>
// Slow floating hearts, stars and bubbles behind a game. Only decoration: nothing here is clickable.
import { computed } from 'vue';

const props = defineProps({ count: { type: Number, default: 12 }, tone: { type: String, default: 'pink' } });

const colors = {
    pink: ['#ff9ec0', '#ffc2d8', '#ffffff', '#ffb3ff'],
    blue: ['#8ec5ff', '#c1e0ff', '#ffffff', '#b8f0ff'],
    green: ['#8de3b6', '#c4f5d9', '#ffffff', '#fff3a3'],
    lilac: ['#c3a8ff', '#e0d3ff', '#ffffff', '#ffc2f0'],
    sun: ['#ffd36b', '#ffe9a8', '#ffffff', '#ffb18a'],
};

// Fixed positions (a small deterministic table), so the decoration looks the same on every phone and render.
const items = computed(() =>
    Array.from({ length: props.count }, (_, i) => {
        const k = (i * 37 + 11) % 100;
        return {
            id: i,
            left: `${(i * 53 + 7) % 96}%`,
            top: `${(i * 29 + 5) % 92}%`,
            size: 14 + (k % 5) * 6,
            delay: `${-(i % 7) * 0.9}s`,
            time: `${5 + (k % 4)}s`,
            rot: `${(i % 5) * 12 - 24}deg`,
            color: colors[props.tone][i % 4],
            shape: i % 3,
        };
    }),
);
</script>

<template>
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <span
            v-for="s in items"
            :key="s.id"
            class="ar-float absolute opacity-70"
            :style="{ left: s.left, top: s.top, width: `${s.size}px`, height: `${s.size}px`, '--d': s.delay, '--t': s.time, '--r': s.rot }"
        >
            <svg v-if="s.shape === 0" viewBox="0 0 24 24" class="size-full"><path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.8-8 11-8 11Z" :fill="s.color" /></svg>
            <svg v-else-if="s.shape === 1" viewBox="0 0 24 24" class="size-full"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2Z" :fill="s.color" /></svg>
            <svg v-else viewBox="0 0 24 24" class="size-full"><circle cx="12" cy="12" r="9" :fill="s.color" /></svg>
        </span>
    </div>
</template>
