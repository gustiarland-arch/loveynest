<script setup>
defineProps({
    lines: { type: Number, default: 3 },
});
</script>

<template>
    <div class="space-y-3" role="status" :aria-label="$t('common.loading')">
        <div v-for="n in lines" :key="n" class="h-4 animate-pulse rounded-full bg-line" :style="{ width: `${100 - ((n * 17) % 45)}%` }" />
    </div>
</template>
