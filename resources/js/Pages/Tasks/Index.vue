<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Plus, User, Users, Sparkles, HeartHandshake, CircleCheckBig, Search, X } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import TaskItem from '@/Components/tasks/TaskItem.vue';
import TaskFormDialog from '@/Components/tasks/TaskFormDialog.vue';
import { t } from '@/i18n';
import { e2e } from '@/lib/e2e';

const props = defineProps({
    space: { type: String, required: true },
    filter: { type: String, required: true },
    hasCouple: { type: Boolean, required: true },
    tasks: { type: Array, required: true },
    counts: { type: Object, required: true },
    query: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
});

const q = ref(props.query.q ?? '');
let searchTimer = null;
function onSearch() {
    // Locked titles cannot be searched on the server: the list on screen is searched here instead.
    if (e2e.enabled) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit({ q: q.value.trim() || undefined }), 350);
}
const shown = computed(() => {
    const needle = e2e.enabled ? q.value.trim().toLocaleLowerCase('id') : '';
    if (!needle) return props.tasks;
    return props.tasks.filter((task) => `${task.title ?? ''} ${task.description ?? ''}`.toLocaleLowerCase('id').includes(needle));
});
const active = computed(() => ['q', 'priority', 'category', 'due', 'who'].filter((k) => props.query[k]).length);
function setFilter(key, value) {
    visit({ [key]: value || undefined });
}
function clearFilters() {
    q.value = '';
    visit({ q: undefined, priority: undefined, category: undefined, due: undefined, who: undefined });
}
const selects = computed(() => [
    { key: 'priority', label: t('tasks.fieldPriority'), options: ['low', 'normal', 'high'].map((v) => ({ value: v, label: t(`tasks.priority.${v}`) })) },
    { key: 'due', label: t('tasks.fieldDueDate'), options: ['today', 'week', 'overdue', 'none'].map((v) => ({ value: v, label: t(`tasks.dueFilter.${v}`) })) },
    ...(props.categories.length ? [{ key: 'category', label: t('tasks.fieldCategory'), options: props.categories.map((v) => ({ value: v, label: v })) }] : []),
    ...(props.space === 'ours' ? [{ key: 'who', label: t('tasks.fieldAssignee'), options: [{ value: 'me', label: t('tasks.assignMe') }, { value: 'partner', label: t('tasks.assignPartner') }, { value: 'both', label: t('tasks.assignBoth') }] }] : []),
]);

const loading = ref(false);
const formOpen = ref(false);
const editing = ref(null);

function visit(params) {
    const keep = Object.fromEntries(Object.entries(props.query).filter(([, v]) => v));
    router.get('/tasks', { space: props.space, filter: props.filter, ...keep, ...params }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

const space = computed({ get: () => props.space, set: (value) => { q.value = ''; visit({ space: value, filter: 'active', q: undefined, priority: undefined, category: undefined, due: undefined, who: undefined }); } });
const filter = computed({ get: () => props.filter, set: (value) => visit({ filter: value }) });

function create() {
    editing.value = null;
    formOpen.value = true;
}

function edit(task) {
    editing.value = task;
    formOpen.value = true;
}

const empty = computed(() => {
    if (props.filter === 'done') return { title: t('tasks.emptyDoneTitle'), body: t('tasks.emptyDoneBody'), icon: CircleCheckBig };
    return props.space === 'ours'
        ? { title: t('tasks.emptyOursTitle'), body: t('tasks.emptyOursBody'), icon: HeartHandshake }
        : { title: t('tasks.emptyMineTitle'), body: t('tasks.emptyMineBody'), icon: Sparkles };
});
</script>

<template>
    <Head :title="t('tasks.title')" />
    <PageHeader :title="t('tasks.title')">
        <template #action>
            <span class="hidden sm:block"><Button size="sm" @click="create"><Plus class="size-4" aria-hidden="true" />{{ t('tasks.add') }}</Button></span>
        </template>
    </PageHeader>

    <SegmentedControl
        v-if="hasCouple"
        v-model="space"
        :label="t('space.label')"
        :options="[
            { value: 'mine', label: t('space.mine'), icon: User },
            { value: 'ours', label: t('space.ours'), icon: Users },
        ]"
    />
    <p v-else class="rounded-md bg-blush-soft px-4 py-3 text-sm text-ink-muted ring-1 ring-blush">{{ t('tasks.needCouple') }}</p>

    <div class="mt-5 flex items-center gap-2" role="tablist" :aria-label="t('tasks.title')">
        <button
            v-for="option in ['active', 'done']"
            :key="option"
            type="button"
            role="tab"
            :aria-selected="filter === option"
            class="h-9 rounded-full px-4 text-sm font-semibold transition"
            :class="filter === option ? 'bg-berry text-white shadow-soft' : 'bg-surface text-ink-muted ring-1 ring-blush/70 hover:text-plum'"
            @click="filter = option"
        >
            {{ t(`tasks.${option}`) }} · {{ counts[option] }}
        </button>
        <span v-if="counts.overdue && filter === 'active'" class="ml-auto text-xs font-semibold text-danger">{{ t('tasks.overdueCount', { count: counts.overdue }) }}</span>
    </div>

    <div class="mt-3" role="search">
        <div class="relative">
            <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
            <input v-model="q" type="search" maxlength="60" :aria-label="t('tasks.search')" :placeholder="t('tasks.search')" class="h-11 w-full rounded-full border border-line bg-surface/80 pl-10 pr-4 text-base text-plum focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush" @input="onSearch" />
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <label v-for="s in selects" :key="s.key" class="relative">
                <span class="sr-only">{{ s.label }}</span>
                <select :value="query[s.key] ?? ''" class="h-9 appearance-none rounded-full border bg-surface/80 pl-3.5 pr-8 text-xs font-semibold focus:outline-none focus:ring-4 focus:ring-blush" :class="query[s.key] ? 'border-berry text-berry-ink' : 'border-line text-ink-muted'" @change="setFilter(s.key, $event.target.value)">
                    <option value="">{{ s.label }}</option>
                    <option v-for="o in s.options" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </label>
            <button v-if="active" type="button" class="inline-flex h-9 items-center gap-1 rounded-full px-3 text-xs font-semibold text-rose-ink hover:bg-blush-soft" @click="clearFilters"><X class="size-3.5" aria-hidden="true" />{{ t('tasks.clearFilters') }}</button>
        </div>
    </div>

    <div class="mt-4">
        <Card v-if="loading"><Skeleton :lines="4" /></Card>
        <TransitionGroup
            v-else-if="shown.length"
            tag="ul"
            class="space-y-2"
            enter-from-class="opacity-0 translate-y-1"
            enter-active-class="transition duration-300 ease-gentle"
            leave-to-class="opacity-0"
            leave-active-class="transition duration-200"
        >
            <TaskItem v-for="task in shown" :key="task.id" :task="task" @edit="edit" />
        </TransitionGroup>
        <Card v-else :padded="false">
            <EmptyState :title="empty.title" :body="empty.body" :icon="empty.icon">
                <Button v-if="filter === 'active'" variant="soft" @click="create">{{ t('tasks.add') }}</Button>
            </EmptyState>
        </Card>
    </div>

    <!-- Thumb-reachable add button on phones -->
    <Teleport to="body">
        <button
            type="button"
            class="fixed bottom-28 right-5 z-30 grid size-14 place-items-center rounded-full bg-berry text-white shadow-[0_14px_28px_-10px_rgb(184_68_111/0.8)] transition active:scale-95 sm:hidden"
            :aria-label="t('tasks.add')"
            @click="create"
        >
            <Plus class="size-6" aria-hidden="true" />
        </button>
    </Teleport>

    <TaskFormDialog v-model:open="formOpen" :task="editing" :default-space="space" :has-couple="hasCouple" :categories="categories" />
</template>
