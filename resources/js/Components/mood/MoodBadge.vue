<script setup>
import { computed } from 'vue';
import { moodFor } from '@/lib/mood';
import { t } from '@/i18n';

const props = defineProps({
    level: { type: Number, default: null },
    size: { type: String, default: 'md' }, // sm | md
});

const mood = computed(() => moodFor(props.level));
</script>

<template>
    <span
        class="grid shrink-0 place-items-center rounded-[0.75rem]"
        :class="[size === 'sm' ? 'size-9' : 'size-12', mood ? mood.tone : 'border-2 border-dashed border-line-strong text-ink-muted']"
        :title="mood ? t(`mood.levels.${mood.level}`) : t('mood.notYet')"
    >
        <component :is="mood.icon" v-if="mood" :class="size === 'sm' ? 'size-5' : 'size-6'" aria-hidden="true" />
        <span v-else class="text-sm" aria-hidden="true">?</span>
        <span class="sr-only">{{ mood ? t(`mood.levels.${mood.level}`) : t('mood.notYet') }}</span>
    </span>
</template>
