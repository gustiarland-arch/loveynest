<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/layout/PageHeader.vue';
import WidgetGrid from '@/Components/memories/WidgetGrid.vue';
import { t } from '@/i18n';

defineProps({
    partner: { type: Object, default: null },
    moods: { type: Object, required: true },
    event: { type: Object, default: null },
    unreadChat: { type: Number, default: 0 },
    photo: { type: Object, default: null },
    letter: { type: Object, default: null },
});
</script>

<template>
    <Head :title="t('widget.title')" />
    <PageHeader :title="t('widget.title')" :lead="t('widget.lead')" back="/dashboard" />
    <WidgetGrid :moods="moods" :partner="partner" :photo="photo" :letter="letter" :event="event" :unread-chat="unreadChat" show-event />
    <p class="mt-6 text-center text-xs text-ink-muted">{{ t('widget.note') }}</p>
</template>
