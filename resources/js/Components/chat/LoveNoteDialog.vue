<script setup>
import { ref, watch } from 'vue';
import { Trash2 } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import DateField from '@/Components/ui/DateField.vue';
import TimeField from '@/Components/ui/TimeField.vue';
import Button from '@/Components/ui/Button.vue';
import { http, HttpError } from '@/lib/http';
import { LockedError, seal } from '@/lib/e2e';
import { dayLabel, dateKey, timeLabel } from '@/lib/dates';
import { useToast } from '@/composables/useToast';
import { t } from '@/i18n';

const open = defineModel('open', { type: Boolean, default: false });
const toast = useToast();
const body = ref('');
const date = ref('');
const time = ref('');
const notes = ref([]);
const error = ref('');
const saving = ref(false);

async function load() {
    try {
        notes.value = (await http.get('/chat/love-notes')).notes;
    } catch {
        notes.value = [];
    }
}

watch(open, (value) => {
    if (value) {
        error.value = '';
        load();
    }
});

async function save() {
    error.value = '';
    saving.value = true;
    try {
        await http.post('/chat/love-notes', { body: await seal(body.value.trim()), date: date.value, time: time.value });
        body.value = '';
        toast.success(t('chat.noteScheduled'));
        await load();
    } catch (e) {
        error.value = (e instanceof LockedError && e.message) || (e instanceof HttpError && (e.data?.errors && Object.values(e.data.errors)[0]?.[0] || e.data?.message)) || t('chat.offline');
    } finally {
        saving.value = false;
    }
}

async function cancel(note) {
    try {
        await http.delete(`/chat/love-notes/${note.id}`);
        notes.value = notes.value.filter((n) => n.id !== note.id);
    } catch {
        toast.error(t('chat.offline'));
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="t('chat.loveNote')">
        <p class="mb-3 text-sm text-ink-muted">{{ t('chat.loveNoteHint') }}</p>
        <form id="note-form" class="space-y-4" novalidate @submit.prevent="save">
            <TextArea v-model="body" :label="t('chat.noteBody')" :rows="3" :maxlength="500" required />
            <div class="grid grid-cols-[1fr_8.5rem] gap-3">
                <DateField v-model="date" :label="t('chat.noteDate')" required />
                <TimeField v-model="time" :label="t('chat.noteTime')" required />
            </div>
            <p v-if="error" class="text-xs font-medium text-danger" role="alert">{{ error }}</p>
        </form>
        <ul v-if="notes.length" class="mt-4 space-y-2">
            <li class="text-xs font-semibold text-ink-muted">{{ t('chat.notesWaiting') }}</li>
            <li v-for="n in notes" :key="n.id" class="flex items-start gap-2 rounded-[0.875rem] bg-blush-soft px-3 py-2">
                <div class="min-w-0 flex-1">
                    <p class="break-words text-sm text-plum">{{ n.body }}</p>
                    <p class="text-xs text-ink-muted">{{ dayLabel(dateKey(n.deliver_at), t) }} · {{ timeLabel(n.deliver_at) }}</p>
                </div>
                <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-blush" :aria-label="t('common.delete')" @click="cancel(n)"><Trash2 class="size-4" aria-hidden="true" /></button>
            </li>
        </ul>
        <template #footer>
            <Button variant="secondary" @click="open = false">{{ t('common.close') }}</Button>
            <Button type="submit" form="note-form" :loading="saving" :disabled="!body.trim() || !date || !time">{{ t('chat.noteSchedule') }}</Button>
        </template>
    </Dialog>
</template>
