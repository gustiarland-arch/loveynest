<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, Vote, Check, Lock, LockOpen, X } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

defineProps({ polls: { type: Array, required: true } });

const open = ref(false);
const removing = ref(null);
const form = useForm({ question: '', options: ['', ''] });

function openForm() {
    form.clearErrors();
    form.defaults({ question: '', options: ['', ''] });
    form.reset();
    open.value = true;
}
const addOption = () => form.options.length < 6 && form.options.push('');
const dropOption = (i) => form.options.length > 2 && form.options.splice(i, 1);
function submit() {
    // Locked options all look different to the server, so equal ones are caught here.
    const filled = form.options.map((o) => o.trim().toLocaleLowerCase('id')).filter(Boolean);
    if (new Set(filled).size !== filled.length) return form.setError('options', 'Pilihan tidak boleh sama.');
    submitSealed(form, ['question', 'options.*'], (f) => f.post('/voting', { preserveScroll: true, onSuccess: () => (open.value = false) }));
}
const vote = (poll, option) => !poll.closed && router.post(`/voting/${poll.id}/vote`, { option: option.id }, { preserveScroll: true });
const close = (poll) => router.post(`/voting/${poll.id}/close`, {}, { preserveScroll: true });
const destroy = () => router.delete(`/voting/${removing.value.id}`, { preserveScroll: true, onSuccess: () => (removing.value = null) });
const pct = (poll, option) => (poll.total ? Math.round((option.votes / poll.total) * 100) : 0);
</script>

<template>
    <Head :title="t('polls.title')" />
    <PageHeader :title="t('polls.title')" :lead="t('polls.lead')" back="/berdua">
        <template #action><Button size="sm" @click="openForm"><Plus class="size-4" aria-hidden="true" />{{ t('polls.add') }}</Button></template>
    </PageHeader>

    <ul v-if="polls.length" class="space-y-3">
        <li v-for="poll in polls" :key="poll.id" class="glass rounded-[1.25rem] p-4 " :class="poll.closed && 'opacity-80'">
            <div class="flex items-start gap-2">
                <p class="min-w-0 flex-1 break-words font-semibold text-plum">{{ poll.question }}</p>
                <span v-if="poll.closed" class="shrink-0 rounded-full bg-lavender px-2 py-0.5 text-[0.6875rem] font-semibold text-lavender-ink">{{ t('polls.closed') }}</span>
            </div>
            <ul class="mt-3 space-y-2">
                <li v-for="o in poll.options" :key="o.id">
                    <button type="button" class="relative flex min-h-12 w-full items-center gap-2 overflow-hidden rounded-md px-3 text-left text-sm ring-1 transition"
                        :class="poll.voted === o.id ? 'ring-berry' : 'ring-line'" :disabled="poll.closed" :aria-pressed="poll.voted === o.id" @click="vote(poll, o)">
                        <span class="absolute inset-y-0 left-0 bg-blush/70 transition-[width] duration-500" :style="{ width: pct(poll, o) + '%' }" aria-hidden="true" />
                        <span class="relative flex min-w-0 flex-1 items-center gap-1.5 font-semibold text-plum"><Check v-if="poll.voted === o.id" class="size-4 shrink-0 text-berry" aria-hidden="true" /><span class="truncate">{{ o.label }}</span></span>
                        <span class="relative shrink-0 text-xs font-semibold text-ink-muted">{{ o.votes }} · {{ pct(poll, o) }}%</span>
                    </button>
                </li>
            </ul>
            <div class="mt-3 flex items-center gap-2 text-xs text-ink-muted">
                <span class="flex-1">{{ t('polls.total', { count: poll.total }) }}</span>
                <template v-if="poll.mine">
                    <button type="button" class="inline-flex min-h-9 items-center gap-1 rounded-full px-3 font-semibold text-plum hover:bg-blush-soft" @click="close(poll)"><component :is="poll.closed ? LockOpen : Lock" class="size-3.5" aria-hidden="true" />{{ poll.closed ? t('polls.reopen') : t('polls.close') }}</button>
                    <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-danger-soft hover:text-danger" :aria-label="t('common.delete')" @click="removing = poll"><Trash2 class="size-4" aria-hidden="true" /></button>
                </template>
            </div>
        </li>
    </ul>
    <Card v-else :padded="false">
        <EmptyState :title="t('polls.emptyTitle')" :body="t('polls.emptyBody')" :icon="Vote"><Button variant="soft" @click="openForm">{{ t('polls.add') }}</Button></EmptyState>
    </Card>

    <Dialog v-model:open="open" :title="t('polls.add')">
        <form id="poll-form" class="space-y-3" novalidate @submit.prevent="submit">
            <TextField v-model="form.question" :label="t('polls.question')" :placeholder="t('polls.questionPlaceholder')" :maxlength="120" :error="form.errors.question" required />
            <div v-for="(_, i) in form.options" :key="i" class="flex items-end gap-2">
                <div class="min-w-0 flex-1"><TextField v-model="form.options[i]" :label="t('polls.option', { n: i + 1 })" :maxlength="80" :error="form.errors[`options.${i}`]" /></div>
                <button v-if="form.options.length > 2" type="button" class="mb-1 grid size-10 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('common.delete')" @click="dropOption(i)"><X class="size-4" aria-hidden="true" /></button>
            </div>
            <p v-if="form.errors.options" class="text-xs font-medium text-danger">{{ form.errors.options }}</p>
            <Button v-if="form.options.length < 6" type="button" variant="ghost" size="sm" @click="addOption"><Plus class="size-4" aria-hidden="true" />{{ t('polls.addOption') }}</Button>
        </form>
        <template #footer>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="poll-form" :loading="form.processing">{{ t('polls.create') }}</Button>
        </template>
    </Dialog>
    <ConfirmDialog :open="removing !== null" :title="t('polls.deleteTitle')" :description="removing ? t('polls.deleteBody', { title: removing.question }) : ''" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="destroy" />
</template>
