<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, LockKeyhole, LockKeyholeOpen, Trash2 } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { dayLabel, addDaysToKey } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const props = defineProps({ items: { type: Array, required: true }, today: { type: String, required: true }, hasPartner: Boolean });

const open = ref(false);
const reading = ref(null);
const removing = ref(null);
const form = useForm({ title: '', body: '', open_on: '' });

function openForm() {
    form.clearErrors();
    form.defaults({ title: '', body: '', open_on: addDaysToKey(props.today, 30) });
    form.reset();
    open.value = true;
}
const submit = () => submitSealed(form, ['title', 'body'], (f) => f.post('/kapsul', { preserveScroll: true, onSuccess: () => (open.value = false) }));
const destroy = () => router.delete(`/kapsul/${removing.value.id}`, { preserveScroll: true, onSuccess: () => (removing.value = null) });
</script>

<template>
    <Head :title="t('capsule.title')" />
    <PageHeader :title="t('capsule.title')" :lead="t('capsule.lead')" back="/berdua">
        <template #action><Button size="sm" @click="openForm"><Plus class="size-4" aria-hidden="true" />{{ t('capsule.add') }}</Button></template>
    </PageHeader>

    <ul v-if="items.length" class="space-y-2">
        <li v-for="c in items" :key="c.id" class="flex items-center gap-3 glass rounded-[1.25rem] p-4 ">
            <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" :disabled="!c.open" @click="reading = c">
                <span class="grid size-12 shrink-0 place-items-center rounded-md" :class="c.open ? 'bg-sage text-sage-ink' : 'bg-lavender text-lavender-ink'">
                    <component :is="c.open ? LockKeyholeOpen : LockKeyhole" class="size-6" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-plum">{{ c.title }}</span>
                    <span class="block text-xs text-ink-muted">
                        {{ c.open ? t('capsule.opened', { date: dayLabel(c.open_on, t) }) : t('capsule.locked', { count: c.in, date: dayLabel(c.open_on, t) }) }} · {{ c.mine ? t('mood.you') : c.by }}
                    </span>
                </span>
            </button>
            <button v-if="c.mine" type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-danger-soft hover:text-danger" :aria-label="t('common.delete')" @click="removing = c"><Trash2 class="size-4" aria-hidden="true" /></button>
        </li>
    </ul>
    <Card v-else :padded="false">
        <EmptyState :title="t('capsule.emptyTitle')" :body="t('capsule.emptyBody')" :icon="LockKeyhole"><Button variant="soft" @click="openForm">{{ t('capsule.add') }}</Button></EmptyState>
    </Card>

    <Dialog v-model:open="open" :title="t('capsule.add')" :description="t('capsule.note')">
        <form id="capsule-form" class="space-y-4" novalidate @submit.prevent="submit">
            <TextField v-model="form.title" :label="t('journal.fieldTitle')" :maxlength="120" :error="form.errors.title" required />
            <TextArea v-model="form.body" :label="t('journal.fieldBody')" :rows="6" :maxlength="5000" :error="form.errors.body" />
            <DateField v-model="form.open_on" :label="t('capsule.openOn')" :error="form.errors.open_on" required />
        </form>
        <template #footer>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="capsule-form" :loading="form.processing">{{ t('capsule.seal') }}</Button>
        </template>
    </Dialog>

    <Dialog :open="reading !== null" :title="reading?.title ?? ''" @update:open="(v) => !v && (reading = null)">
        <template v-if="reading">
            <p class="mb-3 text-xs text-ink-muted">{{ reading.mine ? t('mood.you') : reading.by }} · {{ dayLabel(reading.open_on, t) }}</p>
            <p class="whitespace-pre-wrap break-words leading-relaxed text-plum">{{ reading.body }}</p>
        </template>
    </Dialog>
    <ConfirmDialog :open="removing !== null" :title="t('capsule.deleteTitle')" :description="t('capsule.deleteBody')" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="destroy" />
</template>
