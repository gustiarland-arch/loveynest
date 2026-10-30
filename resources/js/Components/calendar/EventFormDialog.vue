<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Trash2, User, Users } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import TimeField from '@/Components/ui/TimeField.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    event: { type: Object, default: null },
    defaultDate: { type: String, required: true },
    defaultSpace: { type: String, default: 'mine' },
    hasCouple: { type: Boolean, default: false },
});

const form = useForm({
    space: 'mine',
    title: '',
    description: '',
    all_day: false,
    start_date: '',
    start_time: '',
    end_date: '',
    end_time: '',
    reminder_minutes: '',
    repeat: 'none',
    repeat_until: '',
    category: '',
});
const confirmDelete = ref(false);
const deleting = ref(false);
const editing = computed(() => props.event !== null);

watch(open, (value) => {
    if (!value) return;
    form.clearErrors();
    const e = props.event;
    form.defaults({
        space: e?.space ?? (props.defaultSpace === 'all' ? 'mine' : props.defaultSpace),
        title: e?.title ?? '',
        description: e?.description ?? '',
        all_day: e?.all_day ?? false,
        // A repeating event is edited as a series, so use its first occurrence.
        start_date: e ? (e.series?.start_date ?? e.start_date) : props.defaultDate,
        start_time: e ? (e.series?.start_time ?? e.start_time) : '19:00',
        // A one-day all-day event stores its own end; don't echo it back.
        end_date: e && (e.series?.end_date ?? e.end_date) && (e.series?.end_date ?? e.end_date) !== (e.series?.start_date ?? e.start_date) ? (e.series?.end_date ?? e.end_date) : '',
        end_time: (e?.series ? e.series.end_time : e?.end_time) ?? '',
        reminder_minutes: e ? (e.reminder_minutes ?? '') : 60,
        repeat: e?.repeat ?? 'none',
        repeat_until: e?.repeat_until ?? '',
        category: e?.category ?? '',
    });
    form.reset();
});

const reminders = [
    { value: '', label: t('calendar.reminder.none') },
    ...[0, 10, 30, 60, 120, 1440].map((value) => ({ value, label: t(`calendar.reminder.${value}`) })),
];
const repeats = ['none', 'weekly', 'monthly', 'yearly'].map((value) => ({ value, label: t(`calendar.repeat.${value}`) }));
const categories = [{ value: '', label: t('calendar.cat.none') }, ...['kencan', 'keluarga', 'kerja', 'lainnya'].map((value) => ({ value, label: t(`calendar.cat.${value}`) }))];
const spaces = [
    { value: 'mine', label: t('space.mine'), icon: User },
    { value: 'ours', label: t('space.ours'), icon: Users },
];

function rsvp(status) {
    router.post(`/events/${props.event.id}/rsvp`, { status }, { preserveScroll: true, only: ['events', 'upcoming'] });
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) {
        submitSealed(form, ['title', 'description'], (f) => f.patch(`/events/${props.event.id}`, options), ({ space, ...data }) => data);
    } else {
        submitSealed(form, ['title', 'description'], (f) => f.post('/events', options));
    }
}

function destroy() {
    deleting.value = true;
    form.delete(`/events/${props.event.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = false),
        onSuccess: () => {
            confirmDelete.value = false;
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open" :title="editing ? t('calendar.edit') : t('calendar.new')">
        <form id="event-form" class="space-y-4" novalidate @submit.prevent="submit">
            <SegmentedControl v-if="!editing && hasCouple" v-model="form.space" :label="t('space.label')" :options="spaces" />
            <p v-if="form.errors.space" class="text-xs font-medium text-danger">{{ form.errors.space }}</p>

            <TextField
                v-model="form.title"
                :label="t('calendar.fieldTitle')"
                :placeholder="t('calendar.fieldTitlePlaceholder')"
                :maxlength="120"
                :error="form.errors.title"
                required
            />

            <Toggle v-model="form.all_day" :label="t('calendar.fieldAllDay')" />

            <div class="grid gap-3" :class="form.all_day ? 'grid-cols-1' : 'grid-cols-[1fr_8.5rem]'">
                <DateField v-model="form.start_date" :label="t('calendar.fieldStartDate')" :error="form.errors.start_date" required />
                <TimeField v-if="!form.all_day" v-model="form.start_time" :label="t('calendar.fieldStartTime')" :error="form.errors.start_time" />
            </div>
            <div class="grid gap-3" :class="form.all_day ? 'grid-cols-1' : 'grid-cols-[1fr_8.5rem]'">
                <DateField v-model="form.end_date" :label="t('calendar.fieldEndDate')" clearable :error="form.errors.end_date" />
                <TimeField v-if="!form.all_day" v-model="form.end_time" :label="t('calendar.fieldEndTime')" clearable :error="form.errors.end_time" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <SelectField v-model="form.repeat" :label="t('calendar.fieldRepeat')" :options="repeats" :error="form.errors.repeat" />
                <SelectField v-model="form.category" :label="t('calendar.fieldCategory')" :options="categories" :error="form.errors.category" />
            </div>
            <DateField v-if="form.repeat !== 'none'" v-model="form.repeat_until" :label="t('calendar.fieldRepeatUntil')" clearable :error="form.errors.repeat_until" />
            <p v-if="form.repeat !== 'none'" class="-mt-2 text-xs text-ink-muted">{{ t('calendar.repeatNote') }}</p>
            <div v-if="editing && event.rsvp" class="rounded-[1rem] bg-lavender/60 p-3">
                <p class="text-sm font-semibold text-plum">{{ t('calendar.rsvpTitle') }}</p>
                <div class="mt-2 grid grid-cols-3 gap-1.5"><button v-for="s in ['yes', 'maybe', 'no']" :key="s" type="button" class="min-h-10 rounded-[0.75rem] px-2 text-xs font-semibold ring-1 transition" :class="event.rsvp.mine === s ? 'bg-berry text-white ring-transparent' : 'bg-surface text-ink-muted ring-line'" :aria-pressed="event.rsvp.mine === s" @click="rsvp(s)">{{ t(`calendar.rsvp.${s}`) }}</button></div>
                <p v-if="event.rsvp.other" class="mt-2 text-xs text-ink-muted">{{ t('calendar.rsvpOther', { answer: t(`calendar.rsvp.${event.rsvp.other}`) }) }}</p>
            </div>
            <SelectField v-model="form.reminder_minutes" :label="t('calendar.fieldReminder')" :options="reminders" :error="form.errors.reminder_minutes" />
            <TextArea v-model="form.description" :label="t('calendar.fieldDescription')" :maxlength="2000" :rows="2" :error="form.errors.description" />
        </form>

        <template #footer>
            <Button v-if="editing" variant="danger-ghost" class="sm:mr-auto" @click="confirmDelete = true">
                <Trash2 class="size-4" aria-hidden="true" />{{ t('common.delete') }}
            </Button>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="event-form" :loading="form.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>

    <ConfirmDialog
        v-if="editing"
        v-model:open="confirmDelete"
        :title="t('calendar.deleteTitle')"
        :description="t('calendar.deleteBody', { title: event.title })"
        :confirm-label="t('common.delete')"
        :loading="deleting"
        danger
        @confirm="destroy"
    />
</template>
