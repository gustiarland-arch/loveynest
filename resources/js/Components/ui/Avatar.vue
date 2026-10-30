<script setup>
import { computed } from 'vue';
import { frameFit, frameUrl } from '@/lib/frames';

const props = defineProps({
    name: { type: String, required: true },
    src: { type: String, default: null },
    size: { type: String, default: 'md' }, // sm | md | lg
    tone: { type: String, default: 'blush' }, // blush | lavender
    frame: { type: String, default: null }, // profile frame id, see lib/frames
});

const initials = computed(() =>
    props.name
        .trim()
        .split(/\s+/)
        .filter((part) => /^\p{L}/u.test(part))
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);

const frameSrc = computed(() => frameUrl(props.frame));
const fit = computed(() => frameFit(props.frame));

const sizes = { sm: 'size-8 text-xs', md: 'size-11 text-sm', lg: 'size-16 text-lg', xl: 'size-20 text-2xl', hero: 'size-24 text-3xl' };
</script>

<template>
    <span class="relative inline-block shrink-0 rounded-full" :class="[sizes[size] ?? sizes.md, frameSrc ? 'z-[1]' : 'ring-[2.5px] ring-surface shadow-soft']">
        <span
            class="grid place-items-center overflow-hidden rounded-full font-bold"
            :class="[frameSrc ? 'absolute shadow-soft' : 'size-full', tone === 'lavender' ? 'bg-gradient-to-br from-[#ddd3fb] to-[#b9aaf0] text-[#463a7c]' : 'bg-gradient-to-br from-[#f8c9da] to-[#eaa3bf] text-[#7a2f4f]']"
            :style="frameSrc ? { inset: `${(100 - fit.photo) / 2}%` } : null"
        >
            <img v-if="src" :src="src" :alt="name" class="size-full object-cover" />
            <span v-else aria-hidden="true">{{ initials }}</span>
            <span v-if="!src" class="sr-only">{{ name }}</span>
        </span>
        <img v-if="frameSrc" :src="frameSrc" alt="" aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 z-[1] max-w-none -translate-x-1/2 -translate-y-1/2 select-none" :style="{ width: `${fit.width}%`, height: `${fit.width}%` }" draggable="false" />
    </span>
</template>
