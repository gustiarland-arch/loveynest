<script setup>
import { computed } from 'vue';
import { Bell, ChevronRight, Repeat, Star, Users } from '@lucide/vue';
import Badge from '@/Components/ui/Badge.vue';
import { dayLabel, eventDayKey, eventTimeLabel } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    event: { type: Object, required: true },
    showDate: { type: Boolean, default: false },
});

defineEmits(['edit']);

const time = computed(() => eventTimeLabel(props.event, t));
const day = computed(() => dayLabel(eventDayKey(props.event), t));
// Date tile: day number over a short month, e.g. 27 / Okt.
const tile = computed(() => {
    const [y, m, d] = eventDayKey(props.event).split('-').map(Number);
    const month = new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', month: 'short' }).format(new Date(Date.UTC(y, m - 1, d)));
    return { day: d, month };
});
</script>

<template>
    <li>
        <button
            type="button"
            class="glass flex w-full items-center gap-3.5 rounded-[1.25rem] p-3.5 text-left transition active:scale-[0.99]"
            :class="event.past && 'opacity-70'"
            @click="$emit('edit', event)"
        >
            <span
                v-if="showDate"
                class="flex h-[3.25rem] w-12 shrink-0 flex-col items-center justify-center rounded-[0.875rem]"
                :class="event.space === 'ours' ? 'bg-blush text-berry-ink' : 'bg-lavender text-lavender-ink'"
                aria-hidden="true"
            >
                <span class="font-display text-xl font-semibold leading-none">{{ tile.day }}</span>
                <span class="text-[0.6875rem] font-semibold">{{ tile.month }}</span>
            </span>
            <span v-else class="w-14 shrink-0 text-sm font-semibold leading-snug text-plum">{{ time }}</span>
            <span class="min-w-0 flex-1">
                <span class="block break-words font-semibold text-plum">{{ event.title }}</span>
                <span v-if="showDate" class="block text-xs text-ink-muted">{{ day }} · {{ time }}</span>
                <span class="mt-1 flex flex-wrap items-center gap-1.5">
                    <Badge v-if="event.space === 'ours'" tone="lavender"><Users class="size-3" aria-hidden="true" />{{ t('space.oursShort') }}</Badge>
                    <Badge v-if="event.reminder_minutes !== null && !event.past" tone="neutral"><Bell class="size-3" aria-hidden="true" />{{ t(`calendar.reminder.${event.reminder_minutes}`) }}</Badge>
                    <Badge v-if="event.repeat && event.repeat !== 'none'" tone="neutral"><Repeat class="size-3" aria-hidden="true" />{{ t(`calendar.repeat.${event.repeat}`) }}</Badge>
                    <Badge v-if="event.category" tone="rose">{{ t(`calendar.cat.${event.category}`) }}</Badge>
                    <Badge v-if="event.rating" tone="sage"><Star class="size-3" aria-hidden="true" />{{ event.rating }}/5</Badge>
                    <Badge v-if="event.past" tone="neutral">{{ t('calendar.past') }}</Badge>
                    <span v-if="event.created_by" class="text-xs text-ink-muted">{{ t('calendar.by', { name: event.created_by }) }}</span>
                </span>
            </span>
            <ChevronRight class="size-5 shrink-0 text-ink-muted/60" aria-hidden="true" />
        </button>
    </li>
</template>
