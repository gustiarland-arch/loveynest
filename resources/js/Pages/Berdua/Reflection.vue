<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Lock, Users, Plus, Trash2 } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const props = defineProps({ month: String, current: Object, history: Array, partner: Object, partnerRows: Array, agreements: Array });

const tab = ref('reflection');
const tabs = [{ value: 'reflection', label: t('reflect.tabReflection') }, { value: 'agreements', label: t('reflect.tabAgreements') }];
const monthLabel = (iso) => formatDate(`${iso}T12:00:00Z`, { month: 'long', year: 'numeric' }, 'UTC');

const form = useForm({ went_well: props.current?.went_well ?? '', to_improve: props.current?.to_improve ?? '', next_month: props.current?.next_month ?? '', shared: props.current?.shared ?? false });
function save() {
    submitSealed(form, ['went_well', 'to_improve', 'next_month'], (f) => f.put('/refleksi', { preserveScroll: true }), (d) => ({ ...d, went_well: d.went_well || null, to_improve: d.to_improve || null, next_month: d.next_month || null }));
}

const open = ref(false);
const agForm = useForm({ title: '', note: '' });
function addAgreement() {
    submitSealed(agForm, ['title', 'note'], (f) => f.post('/kesepakatan', { preserveScroll: true, onSuccess: () => { open.value = false; agForm.reset(); } }), (d) => ({ ...d, note: d.note || null }));
}
const statuses = ['active', 'paused', 'done'];
function setStatus(a, status) {
    router.patch(`/kesepakatan/${a.id}`, { status }, { preserveScroll: true });
}
const removing = ref(null);
function remove() {
    router.delete(`/kesepakatan/${removing.value.id}`, { preserveScroll: true, onSuccess: () => (removing.value = null) });
}
</script>

<template>
    <Head :title="t('reflect.title')" />
    <PageHeader :title="t('reflect.title')" :lead="t('reflect.lead')" back="/berdua" />
    <SegmentedControl v-model="tab" :options="tabs" :label="t('reflect.title')" class="mb-4" />

    <template v-if="tab === 'reflection'">
        <Card>
            <p class="mb-3 text-sm font-semibold text-plum">{{ monthLabel(month) }}</p>
            <form class="space-y-4" novalidate @submit.prevent="save">
                <TextArea v-model="form.went_well" :label="t('reflect.wentWell')" :maxlength="500" :error="form.errors.went_well" />
                <TextArea v-model="form.to_improve" :label="t('reflect.toImprove')" :maxlength="500" :error="form.errors.to_improve" />
                <TextArea v-model="form.next_month" :label="t('reflect.nextMonth')" :maxlength="500" :error="form.errors.next_month" />
                <Toggle v-model="form.shared" :label="t('checkin.share')" />
                <p class="flex items-start gap-1.5 text-xs text-ink-muted"><component :is="form.shared ? Users : Lock" class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />{{ form.shared ? t('checkin.sharedNote', { name: partner?.name ?? '' }) : t('checkin.privateNote') }}</p>
                <Button type="submit" block :loading="form.processing">{{ t('common.save') }}</Button>
            </form>
        </Card>
        <section v-if="partnerRows.length" class="mt-6">
            <h2 class="mb-2 text-lg font-semibold text-plum">{{ t('checkin.fromPartner', { name: partner.name }) }}</h2>
            <ul class="space-y-2"><li v-for="r in partnerRows" :key="r.month" class="glass rounded-[1.25rem] p-4">
                <p class="text-xs font-semibold text-ink-muted">{{ monthLabel(r.month) }}</p>
                <p v-if="r.went_well" class="mt-1 break-words text-sm text-plum">💗 {{ r.went_well }}</p>
                <p v-if="r.to_improve" class="mt-1 break-words text-sm text-plum">🌱 {{ r.to_improve }}</p>
                <p v-if="r.next_month" class="mt-1 break-words text-sm text-plum">🎯 {{ r.next_month }}</p>
            </li></ul>
        </section>
        <section v-if="history.length" class="mt-6">
            <h2 class="mb-2 text-lg font-semibold text-plum">{{ t('checkin.history') }}</h2>
            <ul class="space-y-2"><li v-for="r in history" :key="r.month" class="glass rounded-[1.25rem] p-4">
                <p class="text-xs font-semibold text-ink-muted">{{ monthLabel(r.month) }}<template v-if="!r.shared"> · {{ t('checkin.onlyMe') }}</template></p>
                <p v-if="r.went_well" class="mt-1 break-words text-sm text-plum">💗 {{ r.went_well }}</p>
                <p v-if="r.to_improve" class="mt-1 break-words text-sm text-plum">🌱 {{ r.to_improve }}</p>
                <p v-if="r.next_month" class="mt-1 break-words text-sm text-plum">🎯 {{ r.next_month }}</p>
            </li></ul>
        </section>
    </template>

    <template v-else>
        <div class="mb-3 flex justify-end"><Button size="sm" @click="open = true"><Plus class="size-4" aria-hidden="true" />{{ t('growth.add') }}</Button></div>
        <ul v-if="agreements.length" class="space-y-2">
            <li v-for="a in agreements" :key="a.id" class="glass rounded-[1.25rem] p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0"><p class="break-words font-semibold text-plum" :class="a.status === 'done' && 'line-through opacity-70'">{{ a.title }}</p><p v-if="a.note" class="mt-0.5 break-words text-sm text-ink-muted">{{ a.note }}</p></div>
                    <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('common.delete')" @click="removing = a"><Trash2 class="size-4" aria-hidden="true" /></button>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5" role="group" :aria-label="a.title"><button v-for="s in statuses" :key="s" type="button" class="h-8 rounded-full px-3 text-xs font-semibold ring-1 transition" :class="a.status === s ? 'bg-berry text-white ring-transparent' : 'bg-surface text-ink-muted ring-line'" :aria-pressed="a.status === s" @click="setStatus(a, s)">{{ t(`reflect.status.${s}`) }}</button></div>
            </li>
        </ul>
        <Card v-else :padded="false"><EmptyState :title="t('reflect.emptyAgreements')" :body="t('reflect.emptyAgreementsBody')"><Button variant="soft" @click="open = true">{{ t('growth.add') }}</Button></EmptyState></Card>
    </template>

    <Dialog v-model:open="open" :title="t('reflect.newAgreement')">
        <form id="ag-form" class="space-y-4" novalidate @submit.prevent="addAgreement">
            <TextField v-model="agForm.title" :label="t('reflect.agreementTitle')" :maxlength="120" :error="agForm.errors.title" required />
            <TextField v-model="agForm.note" :label="t('reflect.agreementNote')" :maxlength="300" :error="agForm.errors.note" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button><Button type="submit" form="ag-form" :loading="agForm.processing">{{ t('common.save') }}</Button></template>
    </Dialog>
    <ConfirmDialog :open="!!removing" :title="t('reflect.deleteTitle')" :description="removing?.title" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="remove" />
</template>
