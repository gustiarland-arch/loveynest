<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, User, Users, Circle, CircleCheckBig, ListChecks, Gift } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const props = defineProps({
    kind: { type: String, required: true },
    space: { type: String, required: true },
    hasCouple: { type: Boolean, required: true },
    items: { type: Array, required: true },
    max: { type: Number, default: null },
});

const go = (params) => router.get('/daftar', { kind: props.kind, space: props.space, ...params }, { preserveState: true, preserveScroll: true, replace: true });
const space = computed({ get: () => props.space, set: (v) => go({ space: v }) });
const kind = computed({ get: () => props.kind, set: (v) => go({ kind: v }) });

const open = ref(false);
const form = useForm({ kind: props.kind, space: props.space, title: '', note: '', price: '' });
const budget = ref(props.max ?? '');
const rp = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
const applyBudget = () => go({ max: budget.value === '' ? undefined : Number(budget.value) });
const removing = ref(null);

function openForm() {
    form.clearErrors();
    form.defaults({ kind: props.kind, space: props.space, title: '', note: '', price: '' });
    form.reset();
    open.value = true;
}
const submit = () => submitSealed(form, ['title', 'note'], (f) => f.post('/daftar', { preserveScroll: true, onSuccess: () => (open.value = false) }), (d) => ({ ...d, price: d.price === '' ? null : Number(d.price) }));
const toggle = (item) => router.patch(`/daftar/${item.id}`, { done: !item.done }, { preserveScroll: true });
const destroy = () => router.delete(`/daftar/${removing.value.id}`, { preserveScroll: true, onSuccess: () => (removing.value = null) });

const done = computed(() => props.items.filter((i) => i.done).length);
</script>

<template>
    <Head :title="t(`lists.${kind}`)" />
    <PageHeader :title="t(`lists.${kind}`)" :lead="t(`lists.lead.${kind}`)" back="/berdua">
        <template #action><Button size="sm" @click="openForm"><Plus class="size-4" aria-hidden="true" />{{ t('lists.add') }}</Button></template>
    </PageHeader>

    <SegmentedControl v-model="kind" :label="t('lists.kind')" :options="[{ value: 'bucket', label: t('lists.bucket'), icon: ListChecks }, { value: 'wishlist', label: t('lists.wishlist'), icon: Gift }]" />
    <SegmentedControl v-if="hasCouple" v-model="space" class="mt-3" :label="t('space.label')" :options="[{ value: 'mine', label: t('space.mine'), icon: User }, { value: 'ours', label: t('space.ours'), icon: Users }]" />
    <form v-if="kind === 'wishlist'" class="mt-3 flex items-end gap-2" novalidate @submit.prevent="applyBudget">
        <div class="min-w-0 flex-1"><TextField v-model="budget" type="number" min="0" inputmode="numeric" :label="t('lists.maxBudget')" /></div>
        <Button type="submit" variant="secondary">{{ t('lists.filter') }}</Button>
    </form>
    <p v-if="items.length" class="mt-4 text-xs font-semibold text-ink-muted">{{ t('lists.progress', { done, total: items.length }) }}</p>

    <ul v-if="items.length" class="mt-2 space-y-2">
        <li v-for="item in items" :key="item.id" class="flex items-start gap-3 glass rounded-[1.25rem] p-3 ">
            <button type="button" class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full text-rose-ink hover:bg-blush-soft" :aria-label="item.done ? t('lists.undo') : t('lists.markDone')" :aria-pressed="item.done" @click="toggle(item)">
                <CircleCheckBig v-if="item.done" class="size-6 text-sage-ink" aria-hidden="true" /><Circle v-else class="size-6" aria-hidden="true" />
            </button>
            <div class="min-w-0 flex-1 py-1.5">
                <p class="break-words font-semibold" :class="item.done ? 'text-ink-muted line-through' : 'text-plum'">{{ item.title }}</p>
                <p v-if="item.price !== null && item.price !== undefined" class="text-xs font-semibold text-berry-ink">{{ rp(item.price) }}</p>
                <p v-if="item.note" class="break-words text-xs text-ink-muted">{{ item.note }}</p>
                <p v-if="item.by" class="text-xs text-ink-muted">{{ t('lists.by', { name: item.by }) }}</p>
            </div>
            <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-danger-soft hover:text-danger" :aria-label="t('common.delete')" @click="removing = item"><Trash2 class="size-4" aria-hidden="true" /></button>
        </li>
    </ul>
    <Card v-else :padded="false" class="mt-4">
        <EmptyState :title="t(`lists.empty.${kind}`)" :body="t('lists.emptyBody')" :icon="kind === 'bucket' ? ListChecks : Gift"><Button variant="soft" @click="openForm">{{ t('lists.add') }}</Button></EmptyState>
    </Card>

    <Dialog v-model:open="open" :title="t(`lists.new.${kind}`)">
        <form id="list-form" class="space-y-4" novalidate @submit.prevent="submit">
            <TextField v-model="form.title" :label="t('journal.fieldTitle')" :placeholder="t(`lists.placeholder.${kind}`)" :maxlength="120" :error="form.errors.title" required />
            <TextField v-if="kind === 'wishlist'" v-model="form.price" type="number" min="0" inputmode="numeric" :label="t('lists.price')" :error="form.errors.price" />
            <TextField v-model="form.note" :label="t('lists.note')" :maxlength="300" :error="form.errors.note" />
            <p v-if="form.errors.space" class="text-xs font-medium text-danger">{{ form.errors.space }}</p>
        </form>
        <template #footer>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="list-form" :loading="form.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>
    <ConfirmDialog :open="removing !== null" :title="t('lists.deleteTitle')" :description="removing ? t('lists.deleteBody', { title: removing.title }) : ''" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="destroy" />
</template>
