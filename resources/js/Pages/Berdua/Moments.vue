<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Plus, Flame, Pencil, Trash2, CalendarHeart } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import TextField from '@/Components/ui/TextField.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { dayLabel } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const props = defineProps({
    facts: { type: Object, required: true },
    items: { type: Array, required: true },
    today: { type: String, required: true },
});

const annForm = useForm({ anniversary_date: props.facts.anniversary ?? '' });
const saveAnniversary = () => annForm.post('/momen/jadian', { preserveScroll: true });

const open = ref(false);
const editing = ref(null);
const confirm = ref(false);
const form = useForm({ title: '', event_date: '', repeat_yearly: true });

function openForm(item = null) {
    editing.value = item;
    form.clearErrors();
    form.defaults({ title: item?.title ?? '', event_date: item?.event_date ?? props.today, repeat_yearly: item?.repeat_yearly ?? true });
    form.reset();
    open.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) submitSealed(form, ['title'], (f) => f.patch(`/momen/${editing.value.id}`, options));
    else submitSealed(form, ['title'], (f) => f.post('/momen', options));
}

function destroy() {
    router.delete(`/momen/${editing.value.id}`, { preserveScroll: true, onSuccess: () => ((confirm.value = false), (open.value = false)) });
}
</script>

<template>
    <Head :title="t('moments.title')" />
    <PageHeader :title="t('moments.title')" :lead="t('moments.lead')" back="/berdua">
        <template #action><Button size="sm" @click="openForm()"><Plus class="size-4" aria-hidden="true" />{{ t('moments.add') }}</Button></template>
    </PageHeader>

    <section class="rounded-[1.5rem] bg-hero p-5 text-white shadow-soft">
        <p class="text-xs font-semibold uppercase tracking-[0.08em] opacity-90">{{ t('berdua.together') }}</p>
        <p v-if="facts.days" class="mt-1 font-display text-5xl font-semibold leading-none">{{ facts.days }}<span class="ml-2 text-lg font-normal opacity-90">{{ t('berdua.days') }}</span></p>
        <p v-else class="mt-1 text-sm opacity-90">{{ t('moments.noDate') }}</p>
        <p v-if="facts.streak" class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold"><Flame class="size-3.5" aria-hidden="true" />{{ t('moments.streak', { count: facts.streak }) }}</p>
    </section>
    <Card class="mt-3">
        <form class="flex items-end gap-2" novalidate @submit.prevent="saveAnniversary">
            <div class="min-w-0 flex-1"><DateField v-model="annForm.anniversary_date" :label="t('moments.anniversary')" clearable :error="annForm.errors.anniversary_date" /></div>
            <Button type="submit" :loading="annForm.processing">{{ t('common.save') }}</Button>
        </form>
    </Card>

    <SectionTitle :title="t('moments.countdown')" />
    <ul v-if="items.length || facts.next" class="space-y-2">
        <li v-for="item in items" :key="item.id">
            <button type="button" class="flex w-full items-center gap-3 glass rounded-[1.25rem] p-4 text-left  transition hover:shadow-lift" @click="openForm(item)">
                <span class="grid size-14 shrink-0 place-items-center rounded-md bg-lavender text-center leading-tight text-lavender-ink">
                    <span v-if="item.in !== null"><span class="block font-display text-xl font-semibold">{{ item.in }}</span><span class="block text-[0.625rem] font-semibold uppercase tracking-wide">{{ t('moments.daysLeft') }}</span></span>
                    <span v-else class="text-xs font-semibold">{{ t('moments.past') }}</span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-plum">{{ item.title }}</span>
                    <span class="block text-xs text-ink-muted">{{ item.next ? dayLabel(item.next, t) : dayLabel(item.event_date, t) }}<template v-if="item.repeat_yearly"> · {{ t('moments.yearly') }}</template></span>
                </span>
                <Pencil class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
            </button>
        </li>
    </ul>
    <Card v-if="!items.length" :padded="false" class="mt-2">
        <EmptyState :title="t('moments.emptyTitle')" :body="t('moments.emptyBody')" :icon="CalendarHeart"><Button variant="soft" @click="openForm()">{{ t('moments.add') }}</Button></EmptyState>
    </Card>

    <Dialog v-model:open="open" :title="editing ? t('moments.edit') : t('moments.add')">
        <form id="moment-form" class="space-y-4" novalidate @submit.prevent="submit">
            <TextField v-model="form.title" :label="t('moments.fieldTitle')" :placeholder="t('moments.fieldTitlePlaceholder')" :maxlength="80" :error="form.errors.title" required />
            <DateField v-model="form.event_date" :label="t('journal.fieldDate')" :error="form.errors.event_date" required />
            <Toggle v-model="form.repeat_yearly" :label="t('moments.repeat')" />
        </form>
        <template #footer>
            <Button v-if="editing" variant="danger-ghost" class="sm:mr-auto" @click="confirm = true"><Trash2 class="size-4" aria-hidden="true" />{{ t('common.delete') }}</Button>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="moment-form" :loading="form.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>
    <ConfirmDialog v-if="editing" v-model:open="confirm" :title="t('moments.deleteTitle')" :description="t('moments.deleteBody', { title: editing.title })" :confirm-label="t('common.delete')" danger @confirm="destroy" />
</template>
