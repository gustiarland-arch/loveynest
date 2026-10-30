<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, ChevronLeft, ChevronRight, Download, PiggyBank, Trash2, Undo2, HandCoins, Printer } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import DateField from '@/Components/ui/DateField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { dayLabel, todayKey } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';
import { downloadUnsealed } from '@/lib/e2e/download';
import { useToast } from '@/composables/useToast';

const print = () => window.print();

const props = defineProps({
    month: String, monthLabel: String, category: { type: String, default: null }, budget: { type: Number, default: null },
    total: Number, byCategory: Object, balance: Number, unsettled: Number, partner: Object, me: Object,
    expenses: Array, voided: Array, goals: Array,
});

const rp = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
const categories = ['makan', 'jalan', 'hadiah', 'nonton', 'lainnya'];
const nameOf = (id) => (id === props.me.id ? props.me.name : props.partner?.name ?? '');

function go(params) {
    router.get('/anggaran', { month: props.month, category: props.category || undefined, ...params }, { preserveScroll: true, preserveState: true, replace: true });
}
function shift(delta) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(Date.UTC(y, m - 1 + delta, 1));
    go({ month: `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}` });
}

const pct = computed(() => (props.budget ? Math.min(100, Math.round((props.total / props.budget) * 100)) : 0));
const over = computed(() => props.budget && props.total > props.budget);
const near = computed(() => props.budget && !over.value && pct.value >= 80);

// Expense dialog
const open = ref(false);
const editing = ref(null);
const form = useForm({ title: '', note: '', amount: '', category: 'makan', spent_on: '', paid_by: props.me.id, payer_share: 50 });
const splits = computed(() => [
    { value: 50, label: t('budget.split50') },
    { value: 100, label: t('budget.splitSelf') },
    { value: 0, label: t('budget.splitOther') },
    ...(![50, 100, 0].includes(Number(form.payer_share)) ? [{ value: Number(form.payer_share), label: `${form.payer_share}%` }] : []),
]);
const payers = computed(() => [{ value: props.me.id, label: props.me.name }, ...(props.partner ? [{ value: props.partner.id, label: props.partner.name }] : [])]);
const catOptions = categories.map((value) => ({ value, label: t(`budget.cat.${value}`) }));

function openForm(e = null) {
    editing.value = e;
    form.clearErrors();
    form.defaults({ title: e?.title ?? '', note: e?.note ?? '', amount: e?.amount ?? '', category: e?.category ?? 'makan', spent_on: e?.spent_on ?? todayKey(), paid_by: e?.paid_by ?? props.me.id, payer_share: e?.payer_share ?? 50 });
    form.reset();
    open.value = true;
}
function save() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    const t2 = (d) => ({ ...d, amount: Number(d.amount), payer_share: Number(d.payer_share), paid_by: Number(d.paid_by) });
    if (editing.value) submitSealed(form, ['title', 'note'], (f) => f.patch(`/anggaran/${editing.value.id}`, options), t2);
    else submitSealed(form, ['title', 'note'], (f) => f.post('/anggaran', options), t2);
}
const toast = useToast();
// The file is made by the server with locked text; it is opened here before it is saved.
function exportCsv(event) {
    downloadUnsealed(event.currentTarget.getAttribute('href'), 'csv').catch((e) => toast.error(e.message));
}
function voidExpense(e) {
    router.post(`/anggaran/${e.id}/batal`, {}, { preserveScroll: true, onSuccess: () => (open.value = false) });
}

// Monthly budget
const budgetOpen = ref(false);
const budgetForm = useForm({ budget: '' });
function openBudget() {
    budgetForm.defaults({ budget: props.budget ?? '' });
    budgetForm.reset();
    budgetOpen.value = true;
}
function saveBudget() {
    budgetForm.transform((d) => ({ budget: d.budget === '' ? 0 : Number(d.budget) })).put('/anggaran/bulanan', { preserveScroll: true, onSuccess: () => (budgetOpen.value = false) });
}

// Settle
const settleOpen = ref(false);
function settle() {
    router.post('/anggaran/lunas', {}, { preserveScroll: true, onSuccess: () => (settleOpen.value = false) });
}

// Savings
const goalOpen = ref(false);
const goalForm = useForm({ title: '', kind: 'tabungan', target_amount: '', deadline: '' });
const kinds = ['tabungan', 'liburan', 'lainnya'].map((value) => ({ value, label: t(`budget.kind.${value}`) }));
function saveGoal() {
    submitSealed(goalForm, ['title'], (f) => f.post('/tabungan', { preserveScroll: true, onSuccess: () => { goalOpen.value = false; goalForm.reset(); } }), (d) => ({ ...d, target_amount: Number(d.target_amount), deadline: d.deadline || null }));
}
const depositFor = ref(null);
const deposit = useForm({ amount: '' });
function addSaving(sign) {
    deposit.transform((d) => ({ amount: sign * Number(d.amount) })).post(`/tabungan/${depositFor.value.id}/setor`, { preserveScroll: true, onSuccess: () => { depositFor.value = null; deposit.reset(); } });
}
const delGoal = ref(null);
function removeGoal() {
    router.delete(`/tabungan/${delGoal.value.id}`, { preserveScroll: true, onSuccess: () => (delGoal.value = null) });
}
const gp = (g) => Math.min(100, Math.round((g.saved / g.target) * 100));
</script>

<template>
    <Head :title="t('budget.title')" />
    <PageHeader :title="t('budget.title')" :lead="t('budget.lead')" back="/berdua">
        <template #action><Button size="sm" variant="secondary" class="no-print mr-2" @click="print"><Printer class="size-4" aria-hidden="true" />{{ t('common.print') }}</Button><Button size="sm" class="no-print" @click="openForm()"><Plus class="size-4" aria-hidden="true" />{{ t('growth.add') }}</Button></template>
    </PageHeader>

    <div class="mb-3 flex items-center justify-between">
        <button type="button" class="grid size-10 place-items-center rounded-full text-plum hover:bg-blush-soft" :aria-label="t('budget.prev')" @click="shift(-1)"><ChevronLeft class="size-5" aria-hidden="true" /></button>
        <h2 class="font-display text-lg font-semibold text-plum">{{ monthLabel }}</h2>
        <button type="button" class="grid size-10 place-items-center rounded-full text-plum hover:bg-blush-soft" :aria-label="t('budget.next')" @click="shift(1)"><ChevronRight class="size-5" aria-hidden="true" /></button>
    </div>

    <section class="rounded-[1.5rem] bg-hero p-5 text-white shadow-soft">
        <p class="text-xs font-semibold uppercase tracking-[0.08em] opacity-90">{{ t('budget.spent') }}</p>
        <p class="mt-1 font-display text-4xl font-semibold leading-none">{{ rp(total) }}</p>
        <template v-if="budget">
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/25" role="progressbar" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100" :aria-label="t('budget.progress')"><div class="h-full rounded-full bg-white transition-all duration-500" :style="{ width: `${pct}%` }" /></div>
            <p class="mt-2 text-sm opacity-95">{{ over ? t('budget.over', { amount: rp(total - budget) }) : t('budget.left', { amount: rp(budget - total), budget: rp(budget) }) }}</p>
            <p v-if="near" class="mt-1 text-xs font-semibold opacity-95" role="status">{{ t('budget.near') }}</p>
        </template>
        <button type="button" class="mt-3 text-sm font-semibold underline underline-offset-4" @click="openBudget">{{ budget ? t('budget.changeBudget') : t('budget.setBudget') }}</button>
    </section>

    <Card class="mt-3 !p-4">
        <div class="flex items-center gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-[0.875rem] bg-lavender text-lavender-ink"><HandCoins class="size-5" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-ink-muted">{{ t('budget.balance') }}</p>
                <p class="font-semibold text-plum">
                    <template v-if="!partner || balance === 0">{{ unsettled ? t('budget.even') : t('budget.allSettled') }}</template>
                    <template v-else-if="balance > 0">{{ t('budget.owesMe', { name: partner.name, amount: rp(balance) }) }}</template>
                    <template v-else>{{ t('budget.iOwe', { name: partner.name, amount: rp(-balance) }) }}</template>
                </p>
            </div>
            <Button v-if="unsettled" size="sm" variant="secondary" @click="settleOpen = true">{{ t('budget.markSettled') }}</Button>
        </div>
    </Card>

    <div class="mt-4 flex flex-wrap gap-2" role="group" :aria-label="t('budget.filterCategory')">
        <button type="button" class="h-9 rounded-full px-3.5 text-xs font-semibold transition" :class="!category ? 'bg-berry text-white' : 'bg-surface text-ink-muted ring-1 ring-line'" @click="go({ category: undefined })">{{ t('budget.all') }}</button>
        <button v-for="c in categories" :key="c" type="button" class="h-9 rounded-full px-3.5 text-xs font-semibold transition" :class="category === c ? 'bg-berry text-white' : 'bg-surface text-ink-muted ring-1 ring-line'" @click="go({ category: c })">{{ t(`budget.cat.${c}`) }}<template v-if="byCategory[c]"> · {{ rp(byCategory[c]) }}</template></button>
    </div>

    <ul v-if="expenses.length" class="mt-3 space-y-2">
        <li v-for="e in expenses" :key="e.id">
            <button type="button" class="glass flex w-full items-center gap-3 rounded-[1.25rem] p-3.5 text-left" @click="openForm(e)">
                <span class="grid size-11 shrink-0 place-items-center rounded-[0.875rem] bg-peach text-peach-ink text-xs font-bold">{{ t(`budget.cat.${e.category}`).slice(0, 3) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-plum">{{ e.title }}</span>
                    <span class="block text-xs text-ink-muted">{{ dayLabel(e.spent_on, t) }} · {{ t('budget.paidBy', { name: nameOf(e.paid_by) }) }}<template v-if="e.owed && !e.settled"> · {{ t('budget.unpaid') }}</template></span>
                </span>
                <span class="shrink-0 font-semibold text-plum">{{ rp(e.amount) }}</span>
            </button>
        </li>
    </ul>
    <Card v-else class="mt-3" :padded="false"><EmptyState :title="t('budget.empty')" :body="t('budget.emptyBody')" :icon="HandCoins"><Button variant="soft" @click="openForm()">{{ t('growth.add') }}</Button></EmptyState></Card>

    <div v-if="voided.length" class="mt-3">
        <p class="text-xs font-semibold text-ink-muted">{{ t('budget.cancelled') }}</p>
        <ul class="mt-1 space-y-1">
            <li v-for="v in voided" :key="v.id" class="flex items-center gap-2 rounded-[0.875rem] bg-plum/[0.05] px-3 py-1.5 text-sm text-ink-muted">
                <span class="min-w-0 flex-1 truncate line-through">{{ v.title }} · {{ rp(v.amount) }}</span>
                <button type="button" class="inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-rose-ink" @click="voidExpense(v)"><Undo2 class="size-3.5" aria-hidden="true" />{{ t('budget.restore') }}</button>
            </li>
        </ul>
    </div>

    <a :href="`/anggaran/ekspor?month=${month}`" @click.prevent="exportCsv" class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-rose-ink underline-offset-4 hover:underline" download><Download class="size-4" aria-hidden="true" />{{ t('budget.export') }}</a>

    <div class="mb-3 mt-8 flex items-baseline justify-between gap-3">
        <h2 class="text-lg font-semibold text-plum">{{ t('budget.savings') }}</h2>
        <button type="button" class="min-h-9 text-sm font-semibold text-rose-ink" @click="goalOpen = true">{{ t('budget.newGoal') }}</button>
    </div>
    <ul v-if="goals.length" class="space-y-3">
        <li v-for="g in goals" :key="g.id" class="glass rounded-[1.25rem] p-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0"><p class="break-words font-semibold text-plum">{{ g.title }}</p><p class="text-xs text-ink-muted">{{ t(`budget.kind.${g.kind}`) }}<template v-if="g.deadline"> · {{ dayLabel(g.deadline, t) }}</template></p></div>
                <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('common.delete')" @click="delGoal = g"><Trash2 class="size-4" aria-hidden="true" /></button>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-plum/[0.1]" role="progressbar" :aria-valuenow="gp(g)" aria-valuemin="0" aria-valuemax="100" :aria-label="g.title"><div class="h-full rounded-full bg-brand transition-all duration-500" :style="{ width: `${gp(g)}%` }" /></div>
            <div class="mt-2 flex items-center justify-between gap-2"><p class="text-sm text-plum">{{ rp(g.saved) }} <span class="text-ink-muted">/ {{ rp(g.target) }} · {{ gp(g) }}%</span></p><Button size="sm" variant="secondary" @click="depositFor = g"><PiggyBank class="size-4" aria-hidden="true" />{{ t('budget.deposit') }}</Button></div>
        </li>
    </ul>
    <p v-else class="text-sm text-ink-muted">{{ t('budget.noGoals') }}</p>
    <p class="mt-6 text-center text-xs text-ink-muted">{{ t('budget.disclaimer') }}</p>

    <Dialog v-model:open="open" :title="editing ? t('budget.edit') : t('budget.new')">
        <form id="exp-form" class="space-y-4" novalidate @submit.prevent="save">
            <TextField v-model="form.title" :label="t('budget.fieldTitle')" :maxlength="80" :error="form.errors.title" required />
            <div class="grid grid-cols-2 gap-3">
                <TextField v-model="form.amount" type="number" min="1" inputmode="numeric" :label="t('budget.fieldAmount')" :error="form.errors.amount" required />
                <SelectField v-model="form.category" :label="t('budget.fieldCategory')" :options="catOptions" />
            </div>
            <DateField v-model="form.spent_on" :label="t('budget.fieldDate')" :error="form.errors.spent_on" required />
            <div v-if="partner" class="grid grid-cols-2 gap-3">
                <SelectField v-model="form.paid_by" :label="t('budget.fieldPayer')" :options="payers" />
                <SelectField v-model="form.payer_share" :label="t('budget.fieldSplit')" :options="splits" />
            </div>
            <TextField v-model="form.note" :label="t('budget.fieldNote')" :maxlength="200" />
        </form>
        <template #footer>
            <Button v-if="editing" variant="danger-ghost" class="sm:mr-auto" @click="voidExpense(editing)"><Trash2 class="size-4" aria-hidden="true" />{{ t('budget.cancelExpense') }}</Button>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="exp-form" :loading="form.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>

    <Dialog v-model:open="budgetOpen" :title="t('budget.setBudget')">
        <form id="bud-form" class="space-y-3" novalidate @submit.prevent="saveBudget">
            <TextField v-model="budgetForm.budget" type="number" min="0" inputmode="numeric" :label="t('budget.monthlyBudget')" :error="budgetForm.errors.budget" />
            <p class="text-xs text-ink-muted">{{ t('budget.budgetHint') }}</p>
        </form>
        <template #footer><Button variant="secondary" @click="budgetOpen = false">{{ t('common.cancel') }}</Button><Button type="submit" form="bud-form" :loading="budgetForm.processing">{{ t('common.save') }}</Button></template>
    </Dialog>

    <Dialog v-model:open="goalOpen" :title="t('budget.newGoal')">
        <form id="sg-form" class="space-y-4" novalidate @submit.prevent="saveGoal">
            <TextField v-model="goalForm.title" :label="t('budget.goalName')" :maxlength="80" :error="goalForm.errors.title" required />
            <div class="grid grid-cols-2 gap-3"><SelectField v-model="goalForm.kind" :label="t('budget.goalKind')" :options="kinds" /><TextField v-model="goalForm.target_amount" type="number" min="1" inputmode="numeric" :label="t('budget.goalTarget')" :error="goalForm.errors.target_amount" required /></div>
            <DateField v-model="goalForm.deadline" :label="t('budget.goalDeadline')" clearable />
        </form>
        <template #footer><Button variant="secondary" @click="goalOpen = false">{{ t('common.cancel') }}</Button><Button type="submit" form="sg-form" :loading="goalForm.processing">{{ t('common.save') }}</Button></template>
    </Dialog>

    <Dialog :open="!!depositFor" :title="t('budget.deposit')" @update:open="(v) => !v && (depositFor = null)">
        <form id="dep-form" class="space-y-3" novalidate @submit.prevent="addSaving(1)">
            <p v-if="depositFor" class="text-sm text-ink-muted">{{ depositFor.title }}</p>
            <TextField v-model="deposit.amount" type="number" min="1" inputmode="numeric" :label="t('budget.fieldAmount')" :error="deposit.errors.amount" required />
        </form>
        <template #footer>
            <Button variant="secondary" :disabled="!deposit.amount" @click="addSaving(-1)">{{ t('budget.withdraw') }}</Button>
            <Button type="submit" form="dep-form" :loading="deposit.processing" :disabled="!deposit.amount">{{ t('budget.deposit') }}</Button>
        </template>
    </Dialog>

    <ConfirmDialog v-model:open="settleOpen" :title="t('budget.markSettled')" :description="t('budget.settleBody')" :confirm-label="t('budget.markSettled')" @confirm="settle" />
    <ConfirmDialog :open="!!delGoal" :title="t('budget.deleteGoal')" :description="t('budget.deleteGoalBody')" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (delGoal = null)" @confirm="removeGoal" />
</template>
