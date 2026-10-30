<script setup>
// A burst of paper pieces falling over the screen, for a win.
const pieces = Array.from({ length: 28 }, (_, i) => ({
    id: i,
    left: `${(i * 37 + 5) % 100}%`,
    delay: `${(i % 9) * 0.12}s`,
    time: `${2.2 + (i % 5) * 0.35}s`,
    color: ['#ff5c8a', '#ffc83d', '#5c9dff', '#3fcf8e', '#a98bff'][i % 5],
    size: 8 + (i % 4) * 3,
    spin: `${360 + (i % 6) * 120}deg`,
    round: i % 3 === 0,
}));
</script>

<template>
    <div class="pointer-events-none fixed inset-0 z-[60] overflow-hidden" aria-hidden="true">
        <span
            v-for="p in pieces"
            :key="p.id"
            class="absolute top-0"
            :style="{ left: p.left, width: `${p.size}px`, height: `${p.size * 1.4}px`, background: p.color, borderRadius: p.round ? '999px' : '3px', border: '2px solid #3b1d33', animation: `ar-fall ${p.time} ${p.delay} ease-in forwards`, '--spin': p.spin }"
        />
    </div>
</template>
