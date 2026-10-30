<script setup>
// Soft layered clouds with a few hearts, used as quiet decoration behind content.
defineProps({
    tone: { type: String, default: 'pink' }, // pink | lavender
    hearts: { type: Boolean, default: true },
});
</script>

<template>
    <svg class="block" viewBox="0 0 400 140" preserveAspectRatio="xMidYMax slice" fill="none" aria-hidden="true">
        <path d="M0 96c26-30 62-34 88-14 20-26 66-30 90-4 22-18 58-14 74 8 20-20 58-18 76 6 20-10 52-6 72 14V140H0Z" :fill="tone === 'lavender' ? 'var(--cloud-lav-1)' : 'var(--cloud-1)'" />
        <path d="M0 116c30-22 64-22 90-6 26-22 70-22 94 2 24-14 60-10 78 8 24-16 62-12 80 6 20-6 40-2 58 10V140H0Z" :fill="tone === 'lavender' ? 'var(--cloud-lav-2)' : 'var(--cloud-2)'" />
        <path v-if="hearts" d="M312 52c-9-6.5-17-13-17-21.5 0-5.4 4.1-9.5 9.2-9.5 3.4 0 6.3 1.8 7.8 4.6 1.5-2.8 4.4-4.6 7.8-4.6 5.1 0 9.2 4.1 9.2 9.5 0 8.5-8 15-17 21.5Z" fill="#f2a9c2" opacity="0.8" />
        <path v-if="hearts" d="M60 64c-6-4.3-11-8.7-11-14.3 0-3.600 2.700-6.300 6.100-6.300 2.300 0 4.200 1.200 5.200 3.100 1-1.900 2.900-3.100 5.200-3.100 3.400 0 6.100 2.700 6.100 6.300 0 5.600-5.300 10-11 14.300Z" fill="#e7a0bb" opacity="0.7" />
        <circle cx="200" cy="40" r="2.500" fill="#f2a9c2" opacity="0.7" />
        <circle cx="140" cy="22" r="1.800" fill="#c9b8ee" opacity="0.8" />
    </svg>
</template>
