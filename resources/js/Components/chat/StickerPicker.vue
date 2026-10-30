<script setup>
import { t } from '@/i18n';

defineProps({ stickers: { type: Array, required: true } });
defineEmits(['pick']);
</script>

<template>
    <div class="glass mx-auto mb-2 grid max-w-2xl grid-cols-6 gap-1 rounded-[1.25rem] p-2" role="group" :aria-label="t('chat.stickers')">
        <button
            v-for="s in stickers"
            :key="s.key"
            type="button"
            class="flex min-h-14 flex-col items-center justify-center rounded-[0.875rem] transition hover:bg-blush-soft active:scale-90"
            :aria-label="s.label"
            @click="$emit('pick', s)"
        >
            <span class="text-2xl leading-none">{{ s.emoji }}</span>
            <span class="mt-0.5 text-[0.625rem] font-semibold text-ink-muted">{{ s.label }}</span>
        </button>
    </div>
</template>
