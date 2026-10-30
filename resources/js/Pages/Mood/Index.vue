<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Lock, HandHeart, MessageCircleHeart, Phone, Footprints } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import Card from '@/Components/ui/Card.vue';
import MoodBadge from '@/Components/mood/MoodBadge.vue';
import MoodPicker from '@/Components/mood/MoodPicker.vue';
import { addDaysToKey, dayLabel } from '@/lib/dates';
import { moodFor } from '@/lib/mood';
import { http, HttpError } from '@/lib/http';
import { useToast } from '@/composables/useToast';
import { t } from '@/i18n';

const props = defineProps({
    today: { type: String, required: true },
    partner: { type: Object, default: null },
    mine: { type: Array, required: true },
    theirs: { type: Array, required: true },
});

const mineToday = computed(() => props.mine.find((m) => m.date === props.today) ?? null);
const theirsToday = computed(() => props.theirs.find((m) => m.date === props.today) ?? null);

// One row per day with both people's mood, newest first.
const days = computed(() => {
    const map = new Map();
    for (const m of props.mine) map.set(m.date, { date: m.date, mine: m });
    for (const m of props.theirs) map.set(m.date, { ...(map.get(m.date) ?? { date: m.date }), theirs: m });
    return [...map.values()].sort((a, b) => b.date.localeCompare(a.date));
});

// Last 30 days of my own mood, oldest first, as a colour strip.
const heat = computed(() => {
    const byDate = new Map(props.mine.map((m) => [m.date, m]));
    return Array.from({ length: 30 }, (_, i) => {
        const date = addDaysToKey(props.today, i - 29);
        return { date, mood: moodFor(byDate.get(date)?.level ?? null) };
    });
});

const toast = useToast();
const hugSent = ref(false);
async function sendHug() {
    try {
        await http.post('/nudges', { kind: 'peluk' });
        hugSent.value = true;
        toast.success(t('nudge.sent', { name: props.partner.firstName }));
    } catch (error) {
        toast.error(error instanceof HttpError && error.status === 429 ? t('nudge.wait') : t('chat.offline'));
    }
}
</script>

<template>
    <Head :title="t('mood.title')" />
    <PageHeader :title="t('mood.title')" :lead="t('mood.lead')" back="/dashboard">
        <template #action>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-lavender px-3 py-1 text-xs font-semibold text-lavender-ink"><Lock class="size-3.5" aria-hidden="true" />{{ t('dashboard.privateNote') }}</span>
        </template>
    </PageHeader>

    <Card>
        <h2 class="mb-3 text-[1.1875rem] font-semibold text-plum">{{ t('mood.howToday') }}</h2>
        <MoodPicker :current="mineToday" />
    </Card>

    <Card v-if="partner" class="mt-3 !p-4">
        <div class="flex items-center gap-3">
            <MoodBadge :level="theirsToday?.level ?? null" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-plum">{{ t('mood.todayTheirs', { name: partner.firstName }) }}</p>
                <p class="truncate text-sm text-ink-muted">{{ theirsToday ? (theirsToday.note || t(`mood.levels.${theirsToday.level}`)) : t('mood.notYet') }}</p>
            </div>
            <button
                type="button"
                class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full bg-blush px-3 text-[0.78rem] font-semibold text-berry-ink transition active:scale-95"
                :class="hugSent && 'ring-2 ring-berry'"
                @click="sendHug"
            >
                <HandHeart class="size-3.5" aria-hidden="true" />{{ t('mood.sendHug') }}
            </button>
        </div>
    </Card>

    <Card v-if="partner && theirsToday?.company" tone="blush" class="mt-3 !p-4" role="status">
        <p class="font-semibold text-plum">{{ t('mood.companyTitle', { name: partner.firstName }) }}</p>
        <p class="mt-1 text-sm text-ink-muted">{{ t('mood.companyBody') }}</p>
        <ul class="mt-3 space-y-1.5 text-sm text-plum">
            <li class="flex items-center gap-2"><HandHeart class="size-4 text-berry-ink" aria-hidden="true" />{{ t('mood.support1') }}</li>
            <li class="flex items-center gap-2"><Phone class="size-4 text-berry-ink" aria-hidden="true" />{{ t('mood.support2') }}</li>
            <li class="flex items-center gap-2"><Footprints class="size-4 text-berry-ink" aria-hidden="true" />{{ t('mood.support3') }}</li>
        </ul>
        <a href="/chat" class="mt-3 inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-rose-ink underline-offset-4 hover:underline"><MessageCircleHeart class="size-4" aria-hidden="true" />{{ t('mood.openChat') }}</a>
    </Card>

    <SectionTitle :title="t('mood.heatTitle')" />
    <Card class="!p-4">
        <ol class="grid grid-cols-10 gap-1.5">
            <li
                v-for="cell in heat"
                :key="cell.date"
                class="aspect-square rounded-[0.4375rem]"
                :class="cell.mood ? cell.mood.heat : 'bg-plum/[0.07]'"
                :title="t('mood.heatLabel', { date: dayLabel(cell.date, t), mood: cell.mood ? t(`mood.levels.${cell.mood.level}`) : t('mood.notYet') })"
            >
                <span class="sr-only">{{ t('mood.heatLabel', { date: dayLabel(cell.date, t), mood: cell.mood ? t(`mood.levels.${cell.mood.level}`) : t('mood.notYet') }) }}</span>
            </li>
        </ol>
        <div class="mt-3 flex items-center gap-1 text-xs text-ink-muted" aria-hidden="true">
            <span class="mr-auto">{{ t('mood.levels.1') }}</span>
            <span v-for="level in [1, 2, 3, 4, 5]" :key="level" class="size-3.5 rounded" :class="moodFor(level).heat" />
            <span class="ml-auto">{{ t('mood.levels.5') }}</span>
        </div>
    </Card>

    <SectionTitle :title="t('mood.history')" />
    <p v-if="partner && days.length" class="-mt-1 mb-3 text-xs text-ink-muted">{{ t('mood.legend', { name: partner.firstName }) }}</p>
    <ul v-if="days.length" class="space-y-2">
        <li v-for="day in days" :key="day.date" class="glass flex items-center gap-3 rounded-[1.25rem] px-4 py-3">
            <p class="w-24 shrink-0 text-sm font-semibold text-plum">{{ dayLabel(day.date, t) }}</p>
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <MoodBadge v-if="day.mine" :level="day.mine.level" size="sm" />
                <MoodBadge v-if="partner && day.theirs" :level="day.theirs.level" size="sm" />
                <p class="min-w-0 flex-1 truncate text-xs text-ink-muted">{{ day.mine?.note || day.theirs?.note || '' }}</p>
            </div>
        </li>
    </ul>
    <p v-else class="text-sm text-ink-muted">{{ t('mood.historyEmpty') }}</p>

    <SectionTitle :title="t('mood.guideTitle')" />
    <Card class="!p-4">
        <ul class="list-disc space-y-1.5 pl-5 text-sm text-ink-muted"><li>{{ t('mood.guide1') }}</li><li>{{ t('mood.guide2') }}</li><li>{{ t('mood.guide3') }}</li><li>{{ t('mood.guide4') }}</li></ul>
    </Card>
</template>
