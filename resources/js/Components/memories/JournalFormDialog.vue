<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Trash2, User, Users } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { todayKey } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    entry: { type: Object, default: null }, // null = create
    kind: { type: String, default: 'journal' }, // journal | letter (create only)
    defaultSpace: { type: String, default: 'mine' },
    hasCouple: { type: Boolean, default: false },
});

const form = useForm({ kind: 'journal', space: 'mine', title: '', body: '', entry_date: '' });
const confirmDelete = ref(false);
const deleting = ref(false);
const editing = computed(() => props.entry !== null);
const isLetter = computed(() => (props.entry ? props.entry.kind === 'letter' : props.kind === 'letter'));

watch(open, (value) => {
    if (!value) return;
    form.clearErrors();
    form.defaults({
        kind: props.entry?.kind ?? props.kind,
        space: props.entry?.space ?? (props.kind === 'letter' ? 'ours' : props.defaultSpace),
        title: props.entry?.title ?? '',
        body: props.entry?.body ?? '',
        entry_date: props.entry?.entry_date ?? todayKey(),
    });
    form.reset();
});

const spaces = [
    { value: 'mine', label: t('space.mine'), icon: User },
    { value: 'ours', label: t('space.ours'), icon: Users },
];

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) submitSealed(form, ['title', 'body'], (f) => f.patch(`/journal/${props.entry.id}`, options), ({ kind, space, ...data }) => data);
    else submitSealed(form, ['title', 'body'], (f) => f.post('/journal', options));
}

function destroy() {
    deleting.value = true;
    form.delete(`/journal/${props.entry.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = false),
        onSuccess: () => ((confirmDelete.value = false), (open.value = false)),
    });
}
</script>

<template>
    <Dialog v-model:open="open" :title="editing ? t('journal.editEntry') : isLetter ? t('journal.newLetter') : t('journal.newEntry')">
        <form id="journal-form" class="space-y-4" novalidate @submit.prevent="submit">
            <SegmentedControl v-if="!editing && !isLetter && hasCouple" v-model="form.space" :label="t('space.label')" :options="spaces" />
            <p v-if="form.errors.space || form.errors.kind" class="text-xs font-medium text-danger">{{ form.errors.kind || form.errors.space }}</p>
            <p v-if="isLetter && !editing" class="rounded-md bg-lavender/60 px-3 py-2 text-xs text-lavender-ink">{{ t('journal.letterNote') }}</p>

            <TextField v-model="form.title" :label="t('journal.fieldTitle')" :placeholder="t('journal.fieldTitlePlaceholder')" :maxlength="120" :error="form.errors.title" required />
            <TextArea v-model="form.body" :label="t('journal.fieldBody')" :maxlength="10000" :rows="isLetter ? 9 : 7" :error="form.errors.body" />
            <DateField v-model="form.entry_date" :label="t('journal.fieldDate')" :error="form.errors.entry_date" required />
        </form>

        <template #footer>
            <Button v-if="editing" variant="danger-ghost" class="sm:mr-auto" @click="confirmDelete = true">
                <Trash2 class="size-4" aria-hidden="true" />{{ t('common.delete') }}
            </Button>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="journal-form" :loading="form.processing">{{ isLetter && !editing ? t('journal.send') : t('common.save') }}</Button>
        </template>
    </Dialog>

    <ConfirmDialog
        v-if="editing"
        v-model:open="confirmDelete"
        :title="t('journal.deleteTitle')"
        :description="t('journal.deleteBody', { title: entry.title })"
        :confirm-label="t('common.delete')"
        :loading="deleting"
        danger
        @confirm="destroy"
    />
</template>
