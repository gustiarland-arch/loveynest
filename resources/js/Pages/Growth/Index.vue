<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, User, Users, Check, Flame, Target, Repeat, Trash2 } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import DateField from '@/Components/ui/DateField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Badge from '@/Components/ui/Badge.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const props = defineProps({
    space: { type: String, required: true },
    tab: { type: String, required: true },
    hasCouple: { type: Boolean, required: true },
    habits: { type: Array, required: true },
    goals: { type: Array, required: true },
    summary: { type: Object, required: true },
});

function visit(params) {
    router.get('/kebiasaan', { space: props.space, tab: props.tab, ...params }, { preserveScroll: true, preserveState: true, replace: true });
}
const space = computed({ get: () => props.space, set: (v) => visit({ space: v }) });
const tab = computed({ get: () => props.tab, set: (v) => visit({ tab: v }) });

// Habits
const habitOpen = ref(false);
const editingHabit = ref(null);
const habitForm = useForm({ space: 'mine', title: '', period: 'daily', target: 1 });
const periods = ['daily', 'weekly', 'monthly'].map((value) => ({ value, label: t(`growth.period.${value}`) }));
const archiveTarget = ref(null);
const archiveOpen = ref(false);

function openHabit(habit = null) {
    editingHabit.value = habit;
    habitForm.clearErrors();
    habitForm.defaults({ space: props.space, title: habit?.title ?? '', period: habit?.period ?? 'daily', target: habit?.target ?? 1 });
    habitForm.reset();
    habitOpen.value = true;
}
function saveHabit() {
    const options = { preserveScroll: true, onSuccess: () => (habitOpen.value = false) };
    if (editingHabit.value) submitSealed(habitForm, ['title'], (f) => f.patch(`/kebiasaan/${editingHabit.value.id}`, options), ({ space, ...d }) => d);
    else submitSealed(habitForm, ['title'], (f) => f.post('/kebiasaan', options));
}
function check(habit, date) {
    router.post(`/kebiasaan/${habit.id}/centang`, date ? { date } : {}, { preserveScroll: true, preserveState: true });
}
function askArchive(habit) {
    archiveTarget.value = habit;
    archiveOpen.value = true;
}
function archive() {
    router.delete(`/kebiasaan/${archiveTarget.value.id}`, { preserveScroll: true, onSuccess: () => { archiveOpen.value = false; habitOpen.value = false; } });
}
const pct = (h) => Math.min(100, Math.round((h.mine.count / h.target) * 100));
const unit = (h) => t(`growth.unit.${h.period}`);

// Goals
const goalOpen = ref(false);
const goalForm = useForm({ space: 'mine', title: '', note: '', category: '', target_date: '' });
const goalStatuses = ['planned', 'active', 'done', 'cancelled'].map((value) => ({ value, label: t(`growth.status.${value}`) }));
const deleteGoal = ref(null);
const deleteOpen = ref(false);
const historyOpen = ref(null);

function openGoal() {
    goalForm.clearErrors();
    goalForm.defaults({ space: props.space, title: '', note: '', category: '', target_date: '' });
    goalForm.reset();
    goalOpen.value = true;
}
function saveGoal() {
    submitSealed(goalForm, ['title', 'note'], (f) => f.post('/tujuan', { preserveScroll: true, onSuccess: () => (goalOpen.value = false) }), (d) => ({ ...d, target_date: d.target_date || null }));
}
function patchGoal(goal, data) {
    router.patch(`/tujuan/${goal.id}`, data, { preserveScroll: true, preserveState: true });
}
function removeGoal() {
    router.delete(`/tujuan/${deleteGoal.value.id}`, { preserveScroll: true, onSuccess: () => (deleteOpen.value = false) });
}
const statusTone = { planned: 'neutral', active: 'lavender', done: 'sage', cancelled: 'neutral' };
const dayLetters = computed(() => {
    const out = [];
    for (let i = 6; i >= 0; i--) {
        const d = new Date(Date.now() - i * 86400000);
        out.push(d.toLocaleDateString('id-ID', { weekday: 'narrow' }));
    }
    return out;
});
const dateOf = (i) => new Date(Date.now() - (6 - i) * 86400000).toLocaleDateString('en-CA');
</script>

<template>
    <Head :title="t('growth.title')" />
    <PageHeader :title="t('growth.title')" :lead="t('growth.lead')" back="/berdua">
        <template #action><Button size="sm" @click="tab === 'habits' ? openHabit() : openGoal()"><Plus class="size-4" aria-hidden="true" />{{ t('growth.add') }}</Button></template>
    </PageHeader>

    <SegmentedControl v-if="hasCouple" v-model="space" :label="t('space.label')" :options="[{ value: 'mine', label: t('space.mine'), icon: User }, { value: 'ours', label: t('space.ours'), icon: Users }]" />
    <SegmentedControl v-model="tab" class="mt-3" :label="t('growth.title')" :options="[{ value: 'habits', label: t('growth.habits'), icon: Repeat }, { value: 'goals', label: t('growth.goals'), icon: Target }]" />

    <section v-if="tab === 'habits'" class="mt-4 space-y-3" :aria-label="t('growth.habits')">
        <p class="text-xs text-ink-muted">{{ t('growth.gentle') }}</p>
        <ul v-if="habits.length" class="space-y-3">
            <li v-for="h in habits" :key="h.id" class="glass rounded-[1.25rem] p-4">
                <div class="flex items-start gap-3">
                    <button type="button" class="grid size-12 shrink-0 place-items-center rounded-full border-2 transition active:scale-95" :class="h.mine.today ? 'border-sage-ink bg-sage-ink text-white' : 'border-rose text-rose-ink hover:border-berry'" :aria-pressed="h.mine.today" :aria-label="t('growth.checkToday', { title: h.title })" @click="check(h)">
                        <Check class="size-5" stroke-width="3" aria-hidden="true" />
                    </button>
                    <button type="button" class="min-w-0 flex-1 text-left" @click="openHabit(h)">
                        <p class="break-words font-semibold text-plum">{{ h.title }}</p>
                        <p class="text-xs text-ink-muted">{{ h.mine.count }}/{{ h.target }} {{ unit(h) }}<template v-if="h.mine.streak > 1"> · <Flame class="inline size-3 text-berry-ink" aria-hidden="true" /> {{ t('growth.rhythm', { count: h.mine.streak }) }}</template></p>
                    </button>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-plum/[0.1]" role="progressbar" :aria-valuenow="pct(h)" aria-valuemin="0" aria-valuemax="100" :aria-label="h.title"><div class="h-full rounded-full bg-brand transition-all duration-500" :style="{ width: `${pct(h)}%` }" /></div>
                <div class="mt-3 flex items-center justify-between gap-2">
                    <div class="flex gap-1.5" role="group" :aria-label="t('growth.last7')">
                        <button v-for="(on, i) in h.mine.week" :key="i" type="button" class="grid size-8 place-items-center rounded-full text-[0.625rem] font-bold transition" :class="on ? 'bg-sage text-sage-ink' : 'bg-plum/[0.07] text-ink-muted'" :disabled="i < 4" :aria-pressed="on" :aria-label="dateOf(i)" @click="check(h, dateOf(i))">{{ dayLetters[i] }}</button>
                    </div>
                    <p v-if="h.partner" class="flex items-center gap-1.5 text-xs text-ink-muted"><span class="size-2 rounded-full" :class="h.partner.today ? 'bg-online' : 'bg-plum/20'" aria-hidden="true" />{{ h.partner.name }} {{ h.partner.count }}/{{ h.target }}</p>
                </div>
            </li>
        </ul>
        <Card v-else :padded="false"><EmptyState :title="t('growth.emptyHabits')" :body="t('growth.emptyHabitsBody')" :icon="Repeat"><Button variant="soft" @click="openHabit()">{{ t('growth.add') }}</Button></EmptyState></Card>
        <p v-if="habits.length" class="text-center text-xs text-ink-muted">{{ t('growth.summary30', { count: summary.checkins30 }) }}</p>
    </section>

    <section v-else class="mt-4 space-y-3" :aria-label="t('growth.goals')">
        <p v-if="summary.goalsDone || summary.goalsActive" class="text-xs text-ink-muted">{{ t('growth.goalSummary', { active: summary.goalsActive, done: summary.goalsDone }) }}</p>
        <ul v-if="goals.length" class="space-y-3">
            <li v-for="g in goals" :key="g.id" class="glass rounded-[1.25rem] p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="break-words font-semibold text-plum" :class="g.status === 'cancelled' && 'line-through opacity-70'">{{ g.title }}</p>
                        <p v-if="g.note" class="mt-0.5 whitespace-pre-wrap break-words text-sm text-ink-muted">{{ g.note }}</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5"><Badge :tone="statusTone[g.status]">{{ t(`growth.status.${g.status}`) }}</Badge><Badge v-if="g.category" tone="neutral">{{ g.category }}</Badge><span v-if="g.target_date" class="text-xs text-ink-muted">{{ t('growth.until', { date: formatDate(g.target_date + 'T12:00:00Z', { dateStyle: 'medium' }, 'UTC') }) }}</span></div>
                    </div>
                    <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('common.delete')" @click="deleteGoal = g; deleteOpen = true"><Trash2 class="size-4" aria-hidden="true" /></button>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-plum/[0.1]" role="progressbar" :aria-valuenow="g.progress" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-brand transition-all duration-500" :style="{ width: `${g.progress}%` }" /></div>
                    <span class="w-10 text-right text-xs font-semibold text-plum">{{ g.progress }}%</span>
                </div>
                <input type="range" min="0" max="100" step="5" :value="g.progress" class="mt-2 w-full accent-[var(--color-berry)]" :aria-label="t('growth.progress')" :disabled="g.status === 'cancelled'" @change="patchGoal(g, { progress: Number($event.target.value) })" />
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <label class="relative"><span class="sr-only">{{ t('growth.statusLabel') }}</span>
                        <select :value="g.status" class="h-9 rounded-full border border-line bg-surface/80 px-3 text-xs font-semibold text-plum focus:outline-none focus:ring-4 focus:ring-blush" @change="patchGoal(g, { status: $event.target.value })"><option v-for="o in goalStatuses" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                    </label>
                    <button v-if="g.history.length" type="button" class="text-xs font-semibold text-rose-ink underline-offset-4 hover:underline" @click="historyOpen = historyOpen === g.id ? null : g.id">{{ t('growth.history') }}</button>
                </div>
                <ol v-if="historyOpen === g.id" class="mt-2 space-y-1 border-l-2 border-blush pl-3 text-xs text-ink-muted">
                    <li v-for="(e, i) in g.history" :key="i">{{ e.at ? formatDate(e.at, { dateStyle: 'medium' }) : '' }} · {{ e.from ? `${t(`growth.status.${e.from}`)} → ` : '' }}{{ t(`growth.status.${e.to}`) }} · {{ e.progress }}%</li>
                </ol>
            </li>
        </ul>
        <Card v-else :padded="false"><EmptyState :title="t('growth.emptyGoals')" :body="t('growth.emptyGoalsBody')" :icon="Target"><Button variant="soft" @click="openGoal()">{{ t('growth.add') }}</Button></EmptyState></Card>
    </section>

    <Dialog v-model:open="habitOpen" :title="editingHabit ? t('growth.editHabit') : t('growth.newHabit')">
        <form id="habit-form" class="space-y-4" novalidate @submit.prevent="saveHabit">
            <SegmentedControl v-if="!editingHabit && hasCouple" v-model="habitForm.space" :label="t('space.label')" :options="[{ value: 'mine', label: t('space.mine'), icon: User }, { value: 'ours', label: t('space.ours'), icon: Users }]" />
            <TextField v-model="habitForm.title" :label="t('growth.habitName')" :placeholder="t('growth.habitPlaceholder')" :maxlength="80" :error="habitForm.errors.title" required />
            <div class="grid grid-cols-2 gap-3">
                <SelectField v-model="habitForm.period" :label="t('growth.periodLabel')" :options="periods" />
                <TextField v-if="habitForm.period !== 'daily'" v-model="habitForm.target" type="number" min="1" :max="habitForm.period === 'weekly' ? 7 : 31" :label="t('growth.targetLabel')" :error="habitForm.errors.target" />
            </div>
        </form>
        <template #footer>
            <Button v-if="editingHabit" variant="danger-ghost" class="sm:mr-auto" @click="askArchive(editingHabit)"><Trash2 class="size-4" aria-hidden="true" />{{ t('growth.archive') }}</Button>
            <Button variant="secondary" @click="habitOpen = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="habit-form" :loading="habitForm.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>

    <Dialog v-model:open="goalOpen" :title="t('growth.newGoal')">
        <form id="goal-form" class="space-y-4" novalidate @submit.prevent="saveGoal">
            <SegmentedControl v-if="hasCouple" v-model="goalForm.space" :label="t('space.label')" :options="[{ value: 'mine', label: t('space.mine'), icon: User }, { value: 'ours', label: t('space.ours'), icon: Users }]" />
            <TextField v-model="goalForm.title" :label="t('growth.goalTitle')" :maxlength="120" :error="goalForm.errors.title" required />
            <TextField v-model="goalForm.note" :label="t('growth.goalNote')" :maxlength="1000" />
            <div class="grid grid-cols-2 gap-3">
                <TextField v-model="goalForm.category" :label="t('tasks.fieldCategory')" :maxlength="30" />
                <DateField v-model="goalForm.target_date" :label="t('growth.targetDate')" clearable :error="goalForm.errors.target_date" />
            </div>
        </form>
        <template #footer>
            <Button variant="secondary" @click="goalOpen = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="goal-form" :loading="goalForm.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>

    <ConfirmDialog v-model:open="archiveOpen" :title="t('growth.archive')" :description="t('growth.archiveBody')" :confirm-label="t('growth.archive')" danger @confirm="archive" />
    <ConfirmDialog v-model:open="deleteOpen" :title="t('growth.deleteGoal')" :description="t('growth.deleteGoalBody')" :confirm-label="t('common.delete')" danger @confirm="removeGoal" />
</template>
