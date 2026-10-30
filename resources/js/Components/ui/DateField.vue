<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue';
import { CalendarDays, ChevronDown, ChevronLeft, ChevronRight } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Button from '@/Components/ui/Button.vue';
import { addDaysToKey, todayKey } from '@/lib/dates';
import { t } from '@/i18n';

// Date picker as a bottom sheet. The value is always 'YYYY-MM-DD' (or '' when empty).
const model = defineModel({ type: String, default: '' });

const props = defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    // Only the sheet, opened from outside with show(): used where another control (a month title) is the trigger.
    hideTrigger: { type: Boolean, default: false },
});

const id = useId();
const open = ref(false);
const today = todayKey();
const viewMonth = ref((model.value || today).slice(0, 7));
// The sheet shows days; tapping the month title jumps to a year list and then a month list, so a far date takes three taps.
const mode = ref('days'); // days | months | years
const yearList = ref(null);

watch(open, (isOpen) => {
    if (isOpen) {
        viewMonth.value = (model.value || today).slice(0, 7);
        mode.value = 'days';
    }
});

const fmt = (key, options) => new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', ...options }).format(new Date(`${key}T00:00:00Z`));
const display = computed(() => (model.value ? fmt(model.value, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : ''));
const monthTitle = computed(() => fmt(`${viewMonth.value}-01`, { month: 'long', year: 'numeric' }));
const weekdays = Array.from({ length: 7 }, (_, i) => new Intl.DateTimeFormat('id-ID', { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2024, 0, 1 + i))));

const cells = computed(() => {
    const [y, m] = viewMonth.value.split('-').map(Number);
    const lead = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() + 6) % 7;
    const days = new Date(Date.UTC(y, m, 0)).getUTCDate();
    const start = addDaysToKey(`${viewMonth.value}-01`, -lead);
    return Array.from({ length: Math.ceil((lead + days) / 7) * 7 }, (_, i) => {
        const key = addDaysToKey(start, i);
        return { key, day: Number(key.slice(8)), inMonth: key.startsWith(viewMonth.value) };
    });
});

function shift(delta) {
    const [y, m] = viewMonth.value.split('-').map(Number);
    viewMonth.value = new Date(Date.UTC(y, m - 1 + delta, 1)).toISOString().slice(0, 7);
}

const viewYear = computed(() => Number(viewMonth.value.slice(0, 4)));
const viewMonthNumber = computed(() => Number(viewMonth.value.slice(5, 7)));
const years = computed(() => {
    const now = Number(today.slice(0, 4));
    const from = Math.min(1920, viewYear.value);
    const to = Math.max(now + 30, viewYear.value);
    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
});
const monthNames = Array.from({ length: 12 }, (_, i) => fmt(`2024-${String(i + 1).padStart(2, '0')}-01`, { month: 'short' }));
const pad = (n) => String(n).padStart(2, '0');

function toggleMode() {
    mode.value = mode.value === 'days' ? 'years' : 'days';
    if (mode.value === 'years') {
        nextTick(() => yearList.value?.querySelector('[aria-pressed="true"]')?.scrollIntoView({ block: 'center' }));
    }
}

function pickYear(year) {
    viewMonth.value = `${year}-${pad(viewMonthNumber.value)}`;
    mode.value = 'months';
}

function pickMonth(month) {
    viewMonth.value = `${viewYear.value}-${pad(month)}`;
    mode.value = 'days';
}

function pick(key) {
    model.value = key;
    open.value = false;
}

defineExpose({ show: () => (open.value = true) });

function clear() {
    model.value = '';
    open.value = false;
}
</script>

<template>
    <div :class="hideTrigger ? undefined : 'space-y-1.5'">
        <label v-if="!hideTrigger" :for="id" class="block text-sm font-semibold text-plum">{{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span></label>
        <button
            v-if="!hideTrigger"
            :id="id"
            type="button"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            class="flex h-12 w-full items-center justify-between gap-2 rounded-md border bg-ivory px-3 text-left text-[0.9375rem] transition min-[400px]:px-4 min-[400px]:text-base focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush disabled:opacity-50"
            :class="[error ? 'border-danger' : 'border-line-strong', model ? 'text-plum' : 'text-ink-muted/70']"
            @click="open = true"
        >
            <span class="truncate">{{ display || t('picker.chooseDate') }}</span>
            <CalendarDays class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
        </button>
        <p v-if="error && !hideTrigger" class="text-xs font-medium text-danger">{{ error }}</p>

        <Dialog v-model:open="open" :title="label">
            <div class="mb-3 flex items-center justify-between">
                <button v-if="mode !== 'years'" type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink" :aria-label="t('calendar.prev')" @click="mode === 'months' ? (viewMonth = `${viewYear - 1}-${pad(viewMonthNumber)}`) : shift(-1)"><ChevronLeft class="size-5" aria-hidden="true" /></button>
                <span v-else class="size-10" aria-hidden="true" />
                <button type="button" class="flex min-h-10 items-center gap-1.5 rounded-full px-3 font-display text-lg font-semibold capitalize text-plum hover:bg-petal" :aria-label="mode === 'years' ? t('picker.backToDays') : t('picker.chooseYear')" :aria-expanded="mode !== 'days'" aria-live="polite" @click="toggleMode">
                    {{ mode === 'days' ? monthTitle : mode === 'months' ? viewYear : t('picker.chooseYear') }}
                    <ChevronDown class="size-4 transition" :class="mode !== 'days' && 'rotate-180'" aria-hidden="true" />
                </button>
                <button v-if="mode !== 'years'" type="button" class="grid size-10 place-items-center rounded-full bg-petal text-berry-ink" :aria-label="t('calendar.next')" @click="mode === 'months' ? (viewMonth = `${viewYear + 1}-${pad(viewMonthNumber)}`) : shift(1)"><ChevronRight class="size-5" aria-hidden="true" /></button>
                <span v-else class="size-10" aria-hidden="true" />
            </div>
            <div v-if="mode === 'years'" ref="yearList" class="grid max-h-72 grid-cols-4 gap-1.5 overflow-y-auto overscroll-contain pb-1" role="listbox" :aria-label="t('picker.chooseYear')">
                <button
                    v-for="year in years"
                    :key="year"
                    type="button"
                    role="option"
                    :aria-pressed="year === viewYear"
                    :aria-selected="year === viewYear"
                    class="grid h-11 place-items-center rounded-2xl text-base transition"
                    :class="year === viewYear ? 'bg-berry font-bold text-white' : ['hover:bg-petal', year === Number(today.slice(0, 4)) ? 'font-bold text-berry-ink ring-2 ring-berry/40' : 'text-plum']"
                    @click="pickYear(year)"
                >
                    {{ year }}
                </button>
            </div>
            <div v-else-if="mode === 'months'" class="grid grid-cols-3 gap-2 pb-1">
                <button
                    v-for="(name, i) in monthNames"
                    :key="name"
                    type="button"
                    :aria-pressed="i + 1 === viewMonthNumber"
                    class="grid h-12 place-items-center rounded-2xl text-base capitalize transition"
                    :class="i + 1 === viewMonthNumber ? 'bg-berry font-bold text-white' : 'text-plum hover:bg-petal'"
                    @click="pickMonth(i + 1)"
                >
                    {{ name }}
                </button>
            </div>
            <div v-if="mode === 'days'" class="grid grid-cols-7 pb-1 text-center text-xs font-semibold text-ink-muted" aria-hidden="true">
                <span v-for="name in weekdays" :key="name">{{ name }}</span>
            </div>
            <div v-if="mode === 'days'" class="grid grid-cols-7 gap-y-1">
                <button
                    v-for="cell in cells"
                    :key="cell.key"
                    type="button"
                    :aria-pressed="model === cell.key"
                    :aria-label="fmt(cell.key, { dateStyle: 'full' })"
                    class="mx-auto grid size-11 place-items-center rounded-2xl text-base transition"
                    :class="[
                        model === cell.key ? 'bg-berry font-bold text-white' : 'hover:bg-petal',
                        cell.key === today && model !== cell.key && 'font-bold text-berry-ink ring-2 ring-berry/40',
                        model !== cell.key && (cell.inMonth ? 'text-plum' : 'text-ink-muted/50'),
                    ]"
                    @click="pick(cell.key)"
                >
                    {{ cell.day }}
                </button>
            </div>
            <template #footer>
                <Button v-if="clearable && model" variant="ghost" @click="clear">{{ t('picker.clear') }}</Button>
                <Button variant="soft" @click="pick(today)">{{ t('calendar.today') }}</Button>
            </template>
        </Dialog>
    </div>
</template>
