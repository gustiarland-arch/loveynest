<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Trash2, User, Users, Plus, X } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import TimeField from '@/Components/ui/TimeField.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Button from '@/Components/ui/Button.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    task: { type: Object, default: null }, // null = create
    defaultSpace: { type: String, default: 'mine' },
    hasCouple: { type: Boolean, default: false },
    categories: { type: Array, default: () => [] },
});

const form = useForm({ space: 'mine', title: '', description: '', priority: 'normal', status: 'todo', due_date: '', due_time: '', assignee_id: null, category: '', repeat: 'none', subtasks: [] });
const confirmDelete = ref(false);
const deleting = ref(false);
const editing = computed(() => props.task !== null);

watch(open, (value) => {
    if (!value) return;
    form.clearErrors();
    const task = props.task;
    form.defaults({
        space: task?.space ?? props.defaultSpace,
        title: task?.title ?? '',
        description: task?.description ?? '',
        priority: task?.priority ?? 'normal',
        status: task?.status ?? 'todo',
        due_date: task?.due_date ?? '',
        due_time: task?.due_time ?? '',
        assignee_id: task?.assignee?.id ?? null,
        category: task?.category ?? '',
        repeat: task?.repeat ?? 'none',
        subtasks: (task?.subtasks ?? []).map((s) => ({ title: s.title, done: s.done })),
    });
    newSub.value = '';
    form.reset();
});

const priorities = ['low', 'normal', 'high'].map((value) => ({ value, label: t(`tasks.priority.${value}`) }));
const statuses = ['todo', 'in_progress', 'done'].map((value) => ({ value, label: t(`tasks.status.${value}`) }));
const spaces = [
    { value: 'mine', label: t('space.mine'), icon: User },
    { value: 'ours', label: t('space.ours'), icon: Users },
];

// Who takes a shared task: both of us (null), me, or my partner.
const page = usePage();
const assignees = computed(() => {
    const me = page.props.auth.user;
    const partner = page.props.partner;
    const list = [{ id: null, label: t('tasks.assignBoth') }, { id: me.id, label: t('tasks.assignMe'), name: me.name, avatarUrl: me.avatarUrl, tone: 'blush' }];
    if (partner) list.push({ id: partner.id, label: partner.firstName, name: partner.name, avatarUrl: partner.avatarUrl, tone: 'lavender' });
    return list;
});

const newSub = ref('');
const repeats = ['none', 'daily', 'weekly', 'monthly'].map((value) => ({ value, label: t(`tasks.repeat.${value}`) }));
const suggested = ['Rumah', 'Kerja', 'Belanja', 'Date', 'Keuangan', 'Kesehatan'];

function addSub() {
    const title = newSub.value.trim();
    if (!title || form.subtasks.length >= 20) return;
    form.subtasks.push({ title, done: false });
    newSub.value = '';
}

const SEALED = ['title', 'description', 'subtasks.*.title'];

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) {
        submitSealed(form, SEALED, (f) => f.patch(`/tasks/${props.task.id}`, options), ({ space, ...data }) => data);
    } else {
        submitSealed(form, SEALED, (f) => f.post('/tasks', options), ({ status, ...data }) => (data.space === 'ours' ? data : { ...data, assignee_id: null }));
    }
}

function destroy() {
    deleting.value = true;
    form.delete(`/tasks/${props.task.id}`, {
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
    <Dialog v-model:open="open" :title="editing ? t('tasks.edit') : t('tasks.new')">
        <form id="task-form" class="space-y-4" novalidate @submit.prevent="submit">
            <SegmentedControl v-if="!editing && hasCouple" v-model="form.space" :label="t('space.label')" :options="spaces" />
            <p v-if="form.errors.space" class="text-xs font-medium text-danger">{{ form.errors.space }}</p>

            <TextField
                v-model="form.title"
                :label="t('tasks.fieldTitle')"
                :placeholder="t('tasks.fieldTitlePlaceholder')"
                :maxlength="120"
                :error="form.errors.title"
                required
            />
            <fieldset v-if="hasCouple && form.space === 'ours'">
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('tasks.fieldAssignee') }}</legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in assignees"
                        :key="option.id ?? 'both'"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-full py-1 pl-1 pr-3.5 text-sm font-semibold ring-1 transition"
                        :class="form.assignee_id === option.id ? 'bg-blush text-plum ring-berry' : 'bg-surface/70 text-ink-muted ring-line hover:text-plum'"
                        :aria-pressed="form.assignee_id === option.id"
                        @click="form.assignee_id = option.id"
                    >
                        <Avatar v-if="option.name" aria-hidden="true" :name="option.name" :src="option.avatarUrl" :tone="option.tone" size="sm" class="!size-8 !ring-0" />
                        <span v-else class="grid size-8 place-items-center rounded-full bg-lavender text-lavender-ink"><Users class="size-4" aria-hidden="true" /></span>
                        {{ option.label }}
                    </button>
                </div>
                <p v-if="form.errors.assignee_id" class="mt-1 text-xs font-medium text-danger">{{ form.errors.assignee_id }}</p>
            </fieldset>

            <TextArea v-model="form.description" :label="t('tasks.fieldDescription')" :maxlength="2000" :rows="2" :error="form.errors.description" />

            <div class="grid grid-cols-2 gap-3">
                <SelectField v-model="form.priority" :label="t('tasks.fieldPriority')" :options="priorities" :error="form.errors.priority" />
                <SelectField v-if="editing" v-model="form.status" :label="t('tasks.fieldStatus')" :options="statuses" :error="form.errors.status" />
            </div>

            <div class="grid grid-cols-[1fr_8.5rem] gap-3">
                <DateField v-model="form.due_date" :label="t('tasks.fieldDueDate')" clearable :error="form.errors.due_date" />
                <TimeField v-model="form.due_time" :label="t('tasks.fieldDueTime')" clearable :disabled="!form.due_date" :error="form.errors.due_time" />
            </div>
            <p v-if="form.priority === 'high'" class="text-xs text-ink-muted">{{ t('tasks.reminderNote') }}</p>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="task-category" class="block text-sm font-semibold text-plum">{{ t('tasks.fieldCategory') }}</label>
                    <input id="task-category" v-model="form.category" list="task-categories" maxlength="30" class="block h-12 w-full rounded-md border border-line-strong bg-ivory px-4 text-base text-plum focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush" />
                    <datalist id="task-categories"><option v-for="c in [...new Set([...categories, ...suggested])]" :key="c" :value="c" /></datalist>
                </div>
                <SelectField v-model="form.repeat" :label="t('tasks.fieldRepeat')" :options="repeats" :error="form.errors.repeat" />
            </div>
            <p v-if="form.repeat !== 'none' && !form.due_date" class="-mt-2 text-xs text-ink-muted">{{ t('tasks.repeatNeedsDate') }}</p>

            <fieldset>
                <legend class="mb-1.5 text-sm font-semibold text-plum">{{ t('tasks.checklist') }}</legend>
                <ul v-if="form.subtasks.length" class="mb-2 space-y-1.5">
                    <li v-for="(sub, i) in form.subtasks" :key="i" class="flex items-center gap-2">
                        <input v-model="sub.done" type="checkbox" class="size-5 shrink-0 accent-[var(--color-berry)]" :aria-label="sub.title" />
                        <input v-model="sub.title" maxlength="120" class="h-10 min-w-0 flex-1 rounded-md border border-line bg-ivory px-3 text-sm text-plum focus:border-rose-ink focus:outline-none" :aria-label="t('tasks.checklist')" />
                        <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('common.delete')" @click="form.subtasks.splice(i, 1)"><X class="size-4" aria-hidden="true" /></button>
                    </li>
                </ul>
                <div class="flex items-center gap-2">
                    <input v-model="newSub" maxlength="120" :placeholder="t('tasks.checklistAdd')" class="h-10 min-w-0 flex-1 rounded-md border border-line bg-ivory px-3 text-sm text-plum focus:border-rose-ink focus:outline-none" @keydown.enter.prevent="addSub" />
                    <Button type="button" variant="secondary" size="sm" :disabled="!newSub.trim()" @click="addSub"><Plus class="size-4" aria-hidden="true" />{{ t('tasks.add') }}</Button>
                </div>
            </fieldset>
        </form>

        <template #footer>
            <Button v-if="editing" variant="danger-ghost" class="sm:mr-auto" @click="confirmDelete = true">
                <Trash2 class="size-4" aria-hidden="true" />{{ t('common.delete') }}
            </Button>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="task-form" :loading="form.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>

    <ConfirmDialog
        v-if="editing"
        v-model:open="confirmDelete"
        :title="t('tasks.deleteTitle')"
        :description="t('tasks.deleteBody', { title: task.title })"
        :confirm-label="t('common.delete')"
        :loading="deleting"
        danger
        @confirm="destroy"
    />
</template>
