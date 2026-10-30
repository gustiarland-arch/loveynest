<script setup>
// Three stars; the earned ones pop in one after another.
defineProps({ count: { type: Number, default: 0 }, size: { type: Number, default: 40 } });
</script>

<template>
    <div class="flex items-center justify-center gap-1.5" role="img" :aria-label="`${count} dari 3 bintang`">
        <svg v-for="n in 3" :key="n" viewBox="0 0 24 24" :width="size" :height="size" :class="n <= count ? 'ar-pop' : ''" :style="n <= count ? { animationDelay: `${n * 0.18}s` } : undefined">
            <path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2Z" :fill="n <= count ? '#ffd54a' : '#e8dde4'" :stroke="n <= count ? '#3b1d33' : '#b9a5b3'" stroke-width="1.6" stroke-linejoin="round" />
        </svg>
    </div>
</template>
