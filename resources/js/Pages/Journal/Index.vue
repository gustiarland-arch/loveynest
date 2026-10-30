<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Mail, MailOpen, Pencil, BookHeart, Heart } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import JournalFormDialog from '@/Components/memories/JournalFormDialog.vue';
import { dayLabel } from '@/lib/dates';
import { http } from '@/lib/http';
import { t } from '@/i18n';

const props = defineProps({
    tab: { type: String, required: true },
    hasCouple: { type: Boolean, required: true },
    hasPartner: { type: Boolean, required: true },
    entries: { type: Array, required: true },
});

const formOpen = ref(false);
const editing = ref(null);
const reading = ref(null);
const readerOpen = ref(false);

const tab = computed({
    get: () => props.tab,
    set: (value) => router.get('/journal', { tab: value }, { preserveState: true, preserveScroll: true, replace: true }),
});

const tabs = computed(() => [
    { value: 'mine', label: t('journal.tabMine') },
    ...(props.hasCouple ? [{ value: 'ours', label: t('journal.tabOurs') }, { value: 'letters', label: t('journal.tabLetters') }] : []),
]);

const kind = computed(() => (props.tab === 'letters' ? 'letter' : 'journal'));
const canWrite = computed(() => (props.tab === 'letters' ? props.hasPartner : true));

const empty = computed(() => ({
    mine: { title: t('journal.emptyMineTitle'), body: t('journal.emptyMineBody'), icon: BookHeart },
    ours: { title: t('journal.emptyOursTitle'), body: t('journal.emptyOursBody'), icon: BookHeart },
    letters: { title: t('journal.emptyLettersTitle'), body: t('journal.emptyLettersBody'), icon: Mail },
}[props.tab]));

function create() {
    editing.value = null;
    formOpen.value = true;
}

function edit(entry) {
    editing.value = entry;
    reading.value = null;
    readerOpen.value = false;
    formOpen.value = true;
}

function open(entry) {
    reading.value = entry;
    readerOpen.value = true;
    if (entry.unread) {
        // Opening a letter tells the writer it was read.
        http.post(`/journal/${entry.id}/read`, {}).then(() => router.reload({ only: ['entries'], preserveScroll: true })).catch(() => {});
    }
}
</script>

<template>
    <Head :title="t('journal.title')" />
    <PageHeader :title="t('journal.title')" :lead="t('journal.lead')" back="/dashboard">
        <template #action>
            <span v-if="canWrite" class="hidden sm:block"><Button size="sm" @click="create"><Plus class="size-4" aria-hidden="true" />{{ tab === 'letters' ? t('journal.newLetter') : t('journal.newEntry') }}</Button></span>
        </template>
    </PageHeader>

    <SegmentedControl v-if="hasCouple" v-model="tab" :label="t('journal.tabsLabel')" :options="tabs" />
    <p v-else class="rounded-md bg-blush-soft px-4 py-3 text-sm text-ink-muted ring-1 ring-blush">{{ t('journal.needCouple') }}</p>

    <p v-if="tab === 'letters' && !hasPartner" class="mt-4 rounded-md bg-lavender/50 px-4 py-3 text-sm text-lavender-ink">{{ t('journal.needPartner') }}</p>

    <ul v-if="entries.length" class="mt-4 space-y-2">
        <li v-for="entry in entries" :key="entry.id">
            <button type="button" class="block w-full glass rounded-[1.25rem] p-4 text-left  transition hover:-translate-y-0.5 hover:shadow-lift" @click="open(entry)">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-md" :class="entry.kind === 'letter' ? 'bg-lavender text-lavender-ink' : 'bg-blush text-rose-ink'">
                        <component :is="entry.kind === 'letter' ? (entry.unread ? Mail : MailOpen) : BookHeart" class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-semibold text-plum">
                            <span class="min-w-0 truncate">{{ entry.title }}</span>
                            <span v-if="entry.unread" class="shrink-0 rounded-full bg-berry px-2 py-0.5 text-[0.6875rem] font-semibold text-white">{{ t('journal.unread') }}</span>
                        </p>
                        <p class="mt-0.5 line-clamp-2 break-words text-sm text-ink-muted">{{ entry.body }}</p>
                        <p class="mt-1.5 text-xs text-ink-muted">
                            {{ dayLabel(entry.entry_date, t) }}<template v-if="entry.author"> · {{ entry.mine ? t('mood.you') : entry.author }}</template>
                            <template v-if="entry.seen"> · {{ t('journal.seen') }}</template>
                        </p>
                    </div>
                </div>
            </button>
        </li>
    </ul>
    <Card v-else-if="canWrite || tab !== 'letters'" :padded="false" class="mt-4">
        <EmptyState :title="empty.title" :body="empty.body" :icon="empty.icon">
            <Button variant="soft" @click="create">{{ tab === 'letters' ? t('journal.newLetter') : t('journal.newEntry') }}</Button>
        </EmptyState>
    </Card>

    <Teleport to="body">
        <button
            v-if="canWrite"
            type="button"
            class="fixed bottom-28 right-5 z-30 grid size-14 place-items-center rounded-full bg-berry text-white shadow-[0_14px_28px_-10px_rgb(184_68_111/0.8)] transition active:scale-95 sm:hidden"
            :aria-label="tab === 'letters' ? t('journal.newLetter') : t('journal.newEntry')"
            @click="create"
        >
            <Plus class="size-6" aria-hidden="true" />
        </button>
    </Teleport>

    <Dialog v-model:open="readerOpen" :title="reading?.title ?? ''">
        <template v-if="reading">
            <p class="mb-3 text-xs text-ink-muted">
                {{ dayLabel(reading.entry_date, t) }}<template v-if="reading.author"> · {{ reading.kind === 'letter' && !reading.mine ? t('journal.from', { name: reading.author }) : reading.author }}</template>
            </p>
            <p class="whitespace-pre-wrap break-words text-base leading-relaxed text-plum">{{ reading.body }}</p>
            <p v-if="reading.kind === 'letter' && !reading.mine" class="mt-4 flex items-center gap-1.5 text-xs text-rose-ink"><Heart class="size-3.5" aria-hidden="true" />{{ reading.author }}</p>
        </template>
        <template v-if="reading && (reading.kind !== 'letter' || reading.mine)" #footer>
            <Button variant="secondary" @click="edit(reading)"><Pencil class="size-4" aria-hidden="true" />{{ t('common.edit') }}</Button>
        </template>
    </Dialog>

    <JournalFormDialog v-model:open="formOpen" :entry="editing" :kind="kind" :default-space="tab === 'ours' ? 'ours' : 'mine'" :has-couple="hasCouple" />
</template>
