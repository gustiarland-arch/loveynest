<script setup>
defineProps({
    tone: { type: String, default: 'neutral' }, // neutral | rose | lavender | sage | danger | warning
});

const tones = {
    neutral: 'bg-fill text-ink-muted',
    rose: 'bg-blush text-rose-ink',
    lavender: 'bg-lavender text-lavender-ink',
    sage: 'bg-sage text-sage-ink',
    danger: 'bg-danger-soft text-danger',
    warning: 'bg-warning-soft text-warning',
};
</script>

<template>
    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[0.75rem] font-semibold leading-none" :class="tones[tone] ?? tones.neutral">
        <slot />
    </span>
</template>
