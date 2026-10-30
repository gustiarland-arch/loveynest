<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { CloudOff, RotateCw } from '@lucide/vue';
import { dayLabel, dateKey, timeLabel } from '@/lib/dates';
import { t } from '@/i18n';

// Android app without signal: the page is the copy saved on the phone (props.offline = when it was saved).
const page = usePage();
const savedAt = computed(() => (typeof page.props.offline === 'number' ? new Date(page.props.offline) : null));
const when = computed(() => {
    if (!savedAt.value) return '';
    const iso = savedAt.value.toISOString();
    return `${dayLabel(dateKey(iso), t)} ${timeLabel(iso)}`;
});
</script>

<template>
    <div v-if="savedAt" class="flex items-center justify-center gap-2 bg-warning-soft px-4 py-2 text-xs font-semibold text-warning" role="status">
        <CloudOff class="size-4 shrink-0" aria-hidden="true" />
        <span class="min-w-0">{{ t('offline.banner', { when }) }}</span>
        <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full hover:bg-warning/10" :aria-label="t('offline.retry')" @click="router.reload()">
            <RotateCw class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
