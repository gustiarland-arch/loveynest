<script setup>
import { computed } from 'vue';
import { addDaysToKey, todayKey } from '@/lib/dates';
import { t } from '@/i18n';

const selected = defineModel('selected', { type: String, required: true });

const props = defineProps({
    month: { type: String, required: true }, // 'YYYY-MM'
    eventsByDay: { type: Object, required: true }, // { 'YYYY-MM-DD': [{ space }] }
});

const today = todayKey();

// 1 Jan 2024 was a Monday; let Intl name the weekdays in the UI language.
const weekdays = Array.from({ length: 7 }, (_, i) =>
    new Intl.DateTimeFormat('id-ID', { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2024, 0, 1 + i))),
);

// Monday-first weeks covering the whole month.
const cells = computed(() => {
    const first = `${props.month}-01`;
    const [y, m] = props.month.split('-').map(Number);
    const weekday = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() + 6) % 7;
    const daysInMonth = new Date(Date.UTC(y, m, 0)).getUTCDate();
    const total = Math.ceil((weekday + daysInMonth) / 7) * 7;
    const start = addDaysToKey(first, -weekday);

    return Array.from({ length: total }, (_, i) => {
        const key = addDaysToKey(start, i);
        const events = props.eventsByDay[key] ?? [];
        return {
            key,
            day: Number(key.slice(8)),
            inMonth: key.startsWith(props.month),
            mine: events.some((e) => e.space === 'mine'),
            ours: events.some((e) => e.space === 'ours'),
            count: events.length,
        };
    });
});
</script>

<template>
    <div>
        <div class="grid grid-cols-7 pb-2 text-center text-xs font-semibold text-ink-muted" aria-hidden="true">
            <span v-for="(name, i) in weekdays" :key="i">{{ name }}</span>
        </div>
        <div class="grid grid-cols-7 gap-y-1" role="grid">
            <button
                v-for="cell in cells"
                :key="cell.key"
                type="button"
                role="gridcell"
                :aria-selected="selected === cell.key"
                :aria-label="`${cell.key}${cell.count ? ', ' + t('calendar.eventsOnDay', { count: cell.count }) : ''}`"
                class="relative mx-auto flex size-11 flex-col items-center justify-center rounded-2xl text-base transition duration-200 ease-gentle"
                :class="[
                    selected === cell.key ? 'bg-blush font-bold text-berry-ink-ink shadow-soft' : 'hover:bg-petal',
                    cell.key === today && selected !== cell.key && 'font-bold text-berry-ink ring-2 ring-berry/40',
                    !cell.inMonth && selected !== cell.key && 'text-ink-muted/50',
                    cell.inMonth && selected !== cell.key && cell.key !== today && 'text-plum',
                ]"
                @click="selected = cell.key"
            >
                {{ cell.day }}
                <span class="absolute bottom-1 flex gap-0.5" aria-hidden="true">
                    <span v-if="cell.mine" class="size-1.5 rounded-full bg-berry" />
                    <span v-if="cell.ours" class="size-1.5 rounded-full bg-lavender-ink" />
                </span>
            </button>
        </div>
        <p class="mt-3 flex justify-center gap-4 text-xs text-ink-muted">
            <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-berry" aria-hidden="true" />{{ t('space.mineShort') }}</span>
            <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-lavender-ink" aria-hidden="true" />{{ t('space.oursShort') }}</span>
        </p>
    </div>
</template>
