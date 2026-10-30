<script setup>
defineProps({
    tone: { type: String, default: 'glass' }, // glass | ivory | blush | lavender | sage
    padded: { type: Boolean, default: true },
});

const tones = {
    glass: 'glass',
    ivory: 'bg-surface',
    blush: 'bg-blush-soft ring-1 ring-blush',
    lavender: 'bg-lavender ring-1 ring-lavender',
    sage: 'bg-sage ring-1 ring-sage',
};
</script>

<template>
    <section class="rounded-[1.375rem] shadow-soft" :class="[tones[tone] ?? tones.glass, padded && 'p-5']">
        <slot />
    </section>
</template>
