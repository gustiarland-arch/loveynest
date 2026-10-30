<script setup>
// A round picture of someone, with a cute ring. Without a photo it shows the first letter on a candy colour.
// When they picked a profile frame, the frame hugs the picture instead of the ring.
import { computed, ref, watch } from 'vue';
import { frameFit, frameUrl } from '@/lib/frames';

const props = defineProps({
    person: { type: Object, default: null },
    size: { type: Number, default: 48 },
    ring: { type: String, default: '#ffffff' },
});

const broken = ref(false);
watch(() => props.person?.avatarUrl, () => (broken.value = false));

const initial = computed(() => (props.person?.name ?? '?').slice(0, 1).toUpperCase());
const tints = ['#ff8fab', '#a98bff', '#5cc8ff', '#ffb347', '#4fd6a0'];
const tint = computed(() => tints[(props.person?.id ?? 0) % tints.length]);
const frameSrc = computed(() => frameUrl(props.person?.avatarFrame));
const fit = computed(() => frameFit(props.person?.avatarFrame));
</script>

<template>
    <span class="relative inline-block shrink-0 rounded-full" :class="frameSrc ? 'z-[1]' : ''" :style="{ width: `${size}px`, height: `${size}px` }">
        <span
            class="grid place-items-center overflow-hidden rounded-full font-extrabold text-white"
            :class="frameSrc ? 'absolute' : 'size-full'"
            :style="{
                background: tint,
                fontSize: `${size * 0.42}px`,
                ...(frameSrc ? { inset: `${(100 - fit.photo) / 2}%` } : { border: `3px solid ${ring}`, boxShadow: '0 4px 0 rgb(0 0 0 / .1)' }),
            }"
        >
            <img v-if="person?.avatarUrl && !broken" :src="person.avatarUrl" alt="" class="size-full object-cover" draggable="false" @error="broken = true" />
            <template v-else>{{ initial }}</template>
        </span>
        <img v-if="frameSrc" :src="frameSrc" alt="" aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 z-[1] max-w-none -translate-x-1/2 -translate-y-1/2 select-none" :style="{ width: `${fit.width}%`, height: `${fit.width}%` }" draggable="false" />
    </span>
</template>
