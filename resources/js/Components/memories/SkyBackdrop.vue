<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { userTimezone } from '@/lib/dates';

// Fractional hour (0-24) in the user's own time zone. Falls back to the device clock, then to noon.
const clock = () => {
    try {
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: userTimezone(), hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
        const get = (type) => Number(parts.find((p) => p.type === type)?.value);
        const value = (get('hour') % 24) + get('minute') / 60;
        if (Number.isFinite(value)) return value;
    } catch {
        // Fall through to the device clock.
    }
    const now = new Date();
    const value = now.getHours() + now.getMinutes() / 60;
    return Number.isFinite(value) ? value : 12;
};

const hour = ref(clock());
let timer = null;
onMounted(() => {
    timer = setInterval(() => {
        hour.value = clock();
    }, 30000);
});
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});

const phase = computed(() => {
    const h = hour.value;
    if (h >= 4.5 && h < 6.5) return 'dawn';
    if (h >= 6.5 && h < 10) return 'morning';
    if (h >= 10 && h < 15.5) return 'day';
    if (h >= 15.5 && h < 17.75) return 'golden';
    if (h >= 17.75 && h < 19.25) return 'dusk';
    return 'night';
});
const isDay = computed(() => phase.value !== 'night');
const showBirds = computed(() => phase.value === 'dawn' || phase.value === 'morning');

// The sun crosses the sky from 05:30 to 18:30, the moon from 18:30 to 05:30; both ride a low arc in the top-right corner, clear of the text on the left.
const arc = (from, to) => {
    let h = hour.value;
    if (h < from) h += 24;
    const p = Math.min(1, Math.max(0, (h - from) / (to - from)));
    return { left: `${86 + 7 * p}%`, top: `${36 - 24 * Math.sin(Math.PI * p)}%` };
};
const sunStyle = computed(() => ({ ...arc(5.5, 18.5), opacity: hour.value >= 5.25 && hour.value < 18.5 ? 1 : 0 }));
const moonStyle = computed(() => ({ ...arc(18.5, 29.5), opacity: hour.value >= 18.75 || hour.value <= 5.25 ? 1 : 0 }));

const stars = Array.from({ length: 22 }, (_, i) => ({
    x: (i * 37 + 11) % 100,
    y: (i * 53 + 7) % 62,
    d: ((i * 13) % 36) / 10,
}));
const clouds = [
    // Kept to the top and bottom edges so they never drift across the numbers and pills.
    { y: -4, d: 0, t: 46 },
    { y: -1, d: -22, t: 62 },
    { y: 97, d: -34, t: 54 },
];
const shoots = [
    { x: 12, y: 8, d: 1, t: 8 },
    { x: 46, y: 4, d: 5.5, t: 11 },
];
const flies = [
    { x: 14, y: 74, d: 0 },
    { x: 34, y: 82, d: 1.8 },
    { x: 70, y: 78, d: 3.1 },
    { x: 88, y: 70, d: 4.4 },
];
</script>

<template>
    <!-- Decoration only. The sky and the sun or moon sit behind the card content; stars and birds in front. -->
    <div class="sky-scene pointer-events-none absolute inset-0" :data-phase="phase" aria-hidden="true">
        <template v-if="isDay">
            <span v-for="(c, i) in clouds" :key="'c' + i" class="sky-cloud" :style="{ top: c.y + '%', animationDuration: c.t + 's', animationDelay: c.d + 's' }" />
        </template>
        <span class="sky-body sky-sun" :style="sunStyle" />
        <span class="sky-body sky-moon" :style="moonStyle" />
        <span class="sky-hill" />
        <span class="sky-hill sky-hill-b" />
        <span class="sky-gloss absolute inset-0" />
    </div>
    <div class="pointer-events-none absolute inset-0 z-10" aria-hidden="true">
        <template v-if="!isDay">
            <span v-for="(s, i) in stars" :key="'s' + i" class="sky-star" :style="{ left: s.x + '%', top: s.y + '%', animationDelay: s.d + 's' }" />
            <span v-for="(s, i) in shoots" :key="'m' + i" class="sky-shoot" :style="{ left: s.x + '%', top: s.y + '%', animationDelay: s.d + 's', animationDuration: s.t + 's' }" />
            <span v-for="(f, i) in flies" :key="'f' + i" class="sky-fly" :style="{ left: f.x + '%', top: f.y + '%', animationDelay: f.d + 's' }" />
        </template>
        <template v-if="showBirds">
            <span class="sky-bird" style="top: 6%; animation-duration: 18s; animation-delay: -4s" />
            <span class="sky-bird" style="top: 1%; animation-duration: 24s; animation-delay: -14s" />
        </template>
    </div>
</template>
