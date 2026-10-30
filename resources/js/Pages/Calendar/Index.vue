<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronLeft, ChevronRight, Plus, CalendarHeart } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Button from '@/Components/ui/Button.vue';
import DateField from '@/Components/ui/DateField.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MonthGrid from '@/Components/calendar/MonthGrid.vue';
import EventItem from '@/Components/calendar/EventItem.vue';
import EventFormDialog from '@/Components/calendar/EventFormDialog.vue';
import { dayLabel, eventDayKeys, todayKey } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    month: { type: String, required: true },
    space: { type: String, required: true },
    hasCouple: { type: Boolean, required: true },
    events: { type: Array, required: true },
    upcoming: { type: Array, required: true },
});

const today = todayKey();
const selected = ref(today.startsWith(props.month) ? today : `${props.month}-01`);
watch(
    () => props.month,
    (month) => {
        if (!selected.value.startsWith(month)) selected.value = today.startsWith(month) ? today : `${month}-01`;
    },
);

const eventsByDay = computed(() => {
    const map = {};
    for (const event of props.events) {
        for (const key of eventDayKeys(event)) (map[key] ??= []).push(event);
    }
    return map;
});
const dayEvents = computed(() => eventsByDay.value[selected.value] ?? []);

const monthTitle = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, 1)));
});

// Tapping the month title opens the date sheet, which has a year and month list for far jumps.
const jumper = ref(null);
const jumpTo = ref('');
function jump(date) {
    if (!date) return;
    selected.value = date;
    if (!date.startsWith(props.month)) visit({ month: date.slice(0, 7) });
    jumpTo.value = '';
}

function shift(delta) {
    const [y, m] = props.month.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1 + delta, 1));
    visit({ month: date.toISOString().slice(0, 7) });
}

function visit(params) {
    router.get('/calendar', { month: props.month, space: props.space, ...params }, { preserveState: true, preserveScroll: true, replace: true });
}

// Month grid, a single week, or a plain agenda list.
const view = ref('month');
const viewOptions = ['month', 'week', 'agenda'].map((value) => ({ value, label: t(`calendar.view.${value}`) }));
const addDays = (key, n) => new Date(Date.parse(`${key}T00:00:00Z`) + n * 86400000).toISOString().slice(0, 10);
const weekStart = computed(() => {
    const dow = (new Date(`${selected.value}T00:00:00Z`).getUTCDay() + 6) % 7; // Monday first
    return addDays(selected.value, -dow);
});
const weekDays = computed(() => Array.from({ length: 7 }, (_, i) => addDays(weekStart.value, i)));
function shiftWeek(delta) {
    selected.value = addDays(selected.value, delta * 7);
    if (!selected.value.startsWith(props.month)) visit({ month: selected.value.slice(0, 7) });
}
const agenda = computed(() => Object.keys(eventsByDay.value).filter((k) => k.startsWith(props.month) && k >= (view.value === 'agenda' ? today : '')).sort());
const space = computed({ get: () => props.space, set: (value) => visit({ space: value }) });

const formOpen = ref(false);
const editing = ref(null);
function create() {
    editing.value = null;
    formOpen.value = true;
}
function edit(event) {
    editing.value = event;
    formOpen.value = true;
}
</script>

<template>
    <Head :title="t('calendar.title')" />
    <DateField ref="jumper" v-model="jumpTo" hide-trigger :label="t('calendar.jumpTo')" @update:model-value="jump" />
    <PageHeader :title="t('calendar.title')">
        <template #action>
            <Link v-if="hasCouple" href="/riwayat-kencan" class="mr-2 text-sm font-semibold text-rose-ink underline-offset-4 hover:underline">{{ t('calendar.history') }}</Link>
            <span class="hidden sm:block"><Button size="sm" @click="create"><Plus class="size-4" aria-hidden="true" />{{ t('calendar.add') }}</Button></span>
        </template>
    </PageHeader>

    <SegmentedControl
        v-if="hasCouple"
        v-model="space"
        :label="t('space.label')"
        :options="[
            { value: 'all', label: t('space.all') },
            { value: 'mine', label: t('space.mineShort') },
            { value: 'ours', label: t('space.oursShort') },
        ]"
    />

    <SegmentedControl v-model="view" :label="t('calendar.viewLabel')" :options="viewOptions" class="mt-3" />

    <Card v-if="view === 'week'" class="mt-4 !rounded-[1.75rem]">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">{{ dayLabel(weekDays[0], t) }} – {{ dayLabel(weekDays[6], t) }}</h2>
            <div class="flex shrink-0 gap-1.5">
                <button type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink hover:bg-blush" :aria-label="t('calendar.prevWeek')" @click="shiftWeek(-1)"><ChevronLeft class="size-5" aria-hidden="true" /></button>
                <button type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink hover:bg-blush" :aria-label="t('calendar.nextWeek')" @click="shiftWeek(1)"><ChevronRight class="size-5" aria-hidden="true" /></button>
            </div>
        </div>
        <ul class="space-y-3">
            <li v-for="d in weekDays" :key="d">
                <p class="text-xs font-semibold uppercase tracking-[0.08em]" :class="d === today ? 'text-berry-ink' : 'text-ink-muted'">{{ dayLabel(d, t) }}</p>
                <ul v-if="(eventsByDay[d] ?? []).length" class="mt-1 space-y-2"><EventItem v-for="event in eventsByDay[d]" :key="event.id" :event="event" @edit="edit" /></ul>
                <p v-else class="mt-0.5 text-xs text-ink-muted">{{ t('calendar.nothingOnDay') }}</p>
            </li>
        </ul>
    </Card>

    <section v-else-if="view === 'agenda'" class="mt-4 space-y-4">
        <div class="flex items-center justify-between gap-2"><h2 class="text-lg font-semibold capitalize"><button type="button" class="inline-flex min-h-10 items-center gap-1 rounded-full px-2 hover:bg-petal" :aria-label="t('picker.chooseYear')" @click="jumper?.show()">{{ monthTitle }}<ChevronDown class="size-4" aria-hidden="true" /></button></h2>
            <div class="flex gap-1.5"><button type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink hover:bg-blush" :aria-label="t('calendar.prev')" @click="shift(-1)"><ChevronLeft class="size-5" aria-hidden="true" /></button><button type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink hover:bg-blush" :aria-label="t('calendar.next')" @click="shift(1)"><ChevronRight class="size-5" aria-hidden="true" /></button></div></div>
        <div v-for="d in agenda" :key="d"><p class="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ dayLabel(d, t) }}</p><ul class="space-y-2"><EventItem v-for="event in eventsByDay[d]" :key="event.id" :event="event" @edit="edit" /></ul></div>
        <Card v-if="!agenda.length" :padded="false"><EmptyState :title="t('calendar.emptyTitle')" :body="t('calendar.emptyBody')" :icon="CalendarHeart" /></Card>
    </section>

    <Card v-if="view === 'month'" class="mt-4 !rounded-[1.75rem]">
        <div class="mb-4 flex items-center justify-between gap-2">
            <h2 class="min-w-0 text-xl font-semibold capitalize leading-tight"><button type="button" class="inline-flex min-h-10 items-center gap-1 rounded-full px-2 hover:bg-petal" :aria-label="t('picker.chooseYear')" @click="jumper?.show()">{{ monthTitle }}<ChevronDown class="size-4 shrink-0" aria-hidden="true" /></button></h2>
            <div class="flex shrink-0 items-center gap-1.5">
                <button type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink hover:bg-blush" :aria-label="t('calendar.prev')" @click="shift(-1)">
                    <ChevronLeft class="size-5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="h-10 rounded-full bg-blush px-3.5 text-sm font-semibold text-berry-ink-ink ring-1 ring-berry/30 hover:bg-blush/70 disabled:opacity-50"
                    :disabled="today.startsWith(month)"
                    @click="visit({ month: today.slice(0, 7) }); selected = today"
                >
                    {{ t('calendar.today') }}
                </button>
                <button type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink hover:bg-blush" :aria-label="t('calendar.next')" @click="shift(1)">
                    <ChevronRight class="size-5" aria-hidden="true" />
                </button>
            </div>
        </div>
        <MonthGrid v-model:selected="selected" :month="month" :events-by-day="eventsByDay" />
    </Card>

    <section v-if="view === 'month'" class="mt-6" aria-live="polite">
        <div class="mb-3 flex items-baseline justify-between gap-3"><h2 class="text-lg font-semibold">{{ dayLabel(selected, t) }}</h2><span class="shrink-0 text-sm text-ink-muted">{{ t('calendar.eventsOnDay', { count: dayEvents.length }) }}</span></div>
        <ul v-if="dayEvents.length" class="space-y-2">
            <EventItem v-for="event in dayEvents" :key="event.id" :event="event" @edit="edit" />
        </ul>
        <div v-else class="flex items-center justify-between gap-3 rounded-[1.25rem] bg-surface/70 px-4 py-3 text-sm text-ink-muted">
            {{ t('calendar.nothingOnDay') }}
            <Button size="sm" variant="soft" class="shrink-0 whitespace-nowrap" @click="create">{{ t('calendar.add') }}</Button>
        </div>
    </section>

    <section v-if="view === 'month'" class="mt-8">
        <h2 class="mb-3 text-lg font-semibold">{{ t('calendar.upcoming') }}</h2>
        <ul v-if="upcoming.length" class="space-y-2">
            <EventItem v-for="event in upcoming" :key="event.id" :event="event" show-date @edit="edit" />
        </ul>
        <Card v-else :padded="false">
            <EmptyState :title="t('calendar.emptyTitle')" :body="t('calendar.emptyBody')" :icon="CalendarHeart" />
        </Card>
    </section>

    <Teleport to="body">
        <button
            type="button"
            class="fixed bottom-28 right-5 z-30 grid size-14 place-items-center rounded-full bg-berry text-white shadow-[0_14px_28px_-10px_rgb(184_68_111/0.8)] transition active:scale-95 sm:hidden"
            :aria-label="t('calendar.add')"
            @click="create"
        >
            <Plus class="size-6" aria-hidden="true" />
        </button>
    </Teleport>

    <EventFormDialog v-model:open="formOpen" :event="editing" :default-date="selected" :default-space="space" :has-couple="hasCouple" />
</template>
