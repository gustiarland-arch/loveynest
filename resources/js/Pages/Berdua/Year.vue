<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Printer } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/i18n';

const print = () => window.print();

const props = defineProps({ year: Number, maxYear: Number, stats: Object, topMood: Number, daysTogether: Number, partner: String });

const rp = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
const nf = (n) => new Intl.NumberFormat('id-ID').format(n);
const items = computed(() => [
    ['messages', nf(props.stats.messages), t('year.messagesNote', { mine: nf(props.stats.messagesMine) })],
    ['photos', nf(props.stats.photos)],
    ['tasksDone', nf(props.stats.tasksDone)],
    ['events', nf(props.stats.events)],
    ['answers', nf(props.stats.answers)],
    ['moods', nf(props.stats.moods)],
    ['habitChecks', nf(props.stats.habitChecks)],
    ['dreamsDone', nf(props.stats.dreamsDone)],
    ['spent', rp(props.stats.spent)],
    ['saved', rp(props.stats.saved), t('year.savedNote')],
]);
const empty = computed(() => Object.values(props.stats).every((v) => !v));
const go = (year) => router.get('/tahun-kita', { year }, { preserveScroll: true, replace: true });
</script>

<template>
    <Head :title="t('year.title')" />
    <PageHeader :title="t('year.title')" :lead="t('year.lead')" back="/berdua">
        <template #action><Button size="sm" variant="secondary" class="no-print" @click="print"><Printer class="size-4" aria-hidden="true" />{{ t('common.print') }}</Button></template>
    </PageHeader>

    <div class="mb-3 flex items-center justify-between">
        <button type="button" class="grid size-10 place-items-center rounded-full text-plum hover:bg-blush-soft" :aria-label="t('year.prev')" @click="go(year - 1)"><ChevronLeft class="size-5" aria-hidden="true" /></button>
        <h2 class="font-display text-2xl font-semibold text-plum">{{ year }}</h2>
        <button type="button" class="grid size-10 place-items-center rounded-full text-plum hover:bg-blush-soft disabled:opacity-30" :disabled="year >= maxYear" :aria-label="t('year.next')" @click="go(year + 1)"><ChevronRight class="size-5" aria-hidden="true" /></button>
    </div>

    <section class="rounded-[1.5rem] bg-hero p-5 text-white shadow-soft">
        <p class="text-xs font-semibold uppercase tracking-[0.08em] opacity-90">{{ t('year.together', { name: partner ?? '' }) }}</p>
        <p class="mt-1 font-display text-4xl font-semibold leading-none">{{ daysTogether ?? '–' }}</p>
        <p class="mt-1 text-sm opacity-90">{{ daysTogether === null ? t('year.setDate') : t('year.daysSoFar') }}</p>
    </section>

    <p v-if="empty" class="glass mt-4 rounded-[1.25rem] p-4 text-sm text-ink-muted">{{ t('year.empty') }}</p>
    <ul class="mt-4 grid grid-cols-2 gap-3">
        <li v-for="[key, value, note] in items" :key="key" class="glass rounded-[1.25rem] p-4">
            <p class="font-display text-2xl font-semibold text-plum">{{ value }}</p>
            <p class="text-xs font-semibold text-ink-muted">{{ t(`year.stat.${key}`) }}</p>
            <p v-if="note" class="mt-0.5 text-[0.6875rem] text-ink-muted">{{ note }}</p>
        </li>
    </ul>
    <p v-if="topMood" class="glass mt-3 rounded-[1.25rem] px-4 py-3 text-sm text-plum">{{ t('year.topMood', { face: ['😞', '😕', '😐', '🙂', '🥰'][topMood - 1] }) }}</p>
    <p class="mt-6 text-center text-xs text-ink-muted">{{ t('year.footnote') }}</p>
</template>
