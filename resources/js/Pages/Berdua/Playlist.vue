<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Music, ExternalLink, Heart, Trash2 } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

defineProps({ items: Array, me: Number });

const open = ref(false);
const form = useForm({ title: '', artist: '', url: '', note: '', is_ours: false });
function save() {
    const url = form.url.trim();
    // The server cannot check a locked link any more, so it is checked here.
    if (url && !safeUrl(url)) return form.setError('url', t('playlist.linkHint'));
    submitSealed(form, ['title', 'artist', 'url', 'note'], (f) => f.post('/playlist', { preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } }), (d) => ({ ...d, url: d.url.trim() || null, artist: d.artist || null, note: d.note || null }));
}
/** Only web links open; anything else in a (locked) link is ignored. */
function safeUrl(url) {
    try {
        return ['http:', 'https:'].includes(new URL(url).protocol) ? url : null;
    } catch {
        return null;
    }
}
function toggleOurs(item) {
    router.patch(`/playlist/${item.id}`, { is_ours: !item.is_ours }, { preserveScroll: true });
}
const removing = ref(null);
function remove() {
    router.delete(`/playlist/${removing.value.id}`, { preserveScroll: true, onSuccess: () => (removing.value = null) });
}
</script>

<template>
    <Head :title="t('playlist.title')" />
    <PageHeader :title="t('playlist.title')" :lead="t('playlist.lead')" back="/berdua">
        <template #action><Button size="sm" @click="open = true"><Plus class="size-4" aria-hidden="true" />{{ t('growth.add') }}</Button></template>
    </PageHeader>

    <ul v-if="items.length" class="space-y-2">
        <li v-for="i in items" :key="i.id" class="glass flex items-center gap-3 rounded-[1.25rem] p-3.5">
            <span class="grid size-11 shrink-0 place-items-center rounded-[0.875rem] bg-lavender text-lavender-ink"><Music class="size-5" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <p class="break-words font-semibold text-plum">{{ i.title }}</p>
                <p class="break-words text-xs text-ink-muted">{{ [i.artist, i.by && t('playlist.addedBy', { name: i.by })].filter(Boolean).join(' · ') }}</p>
                <p v-if="i.note" class="mt-0.5 break-words text-xs text-plum">{{ i.note }}</p>
            </div>
            <a v-if="safeUrl(i.url)" :href="safeUrl(i.url)" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full text-rose-ink hover:bg-blush-soft" :aria-label="t('playlist.open')"><ExternalLink class="size-4" aria-hidden="true" /></a>
            <button type="button" class="grid size-10 place-items-center rounded-full hover:bg-blush-soft" :class="i.is_ours ? 'text-berry' : 'text-ink-muted'" :aria-pressed="i.is_ours" :aria-label="t('playlist.ours')" @click="toggleOurs(i)"><Heart class="size-4" :fill="i.is_ours ? 'currentColor' : 'none'" aria-hidden="true" /></button>
            <button type="button" class="grid size-10 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('common.delete')" @click="removing = i"><Trash2 class="size-4" aria-hidden="true" /></button>
        </li>
    </ul>
    <Card v-else :padded="false"><EmptyState :title="t('playlist.empty')" :body="t('playlist.emptyBody')" :icon="Music"><Button variant="soft" @click="open = true">{{ t('growth.add') }}</Button></EmptyState></Card>
    <p class="mt-4 text-center text-xs text-ink-muted">{{ t('playlist.hint') }}</p>

    <Dialog v-model:open="open" :title="t('playlist.new')">
        <form id="pl-form" class="space-y-4" novalidate @submit.prevent="save">
            <TextField v-model="form.title" :label="t('playlist.song')" :maxlength="120" :error="form.errors.title" required />
            <TextField v-model="form.artist" :label="t('playlist.artist')" :maxlength="120" :error="form.errors.artist" />
            <TextField v-model="form.url" type="url" inputmode="url" :label="t('playlist.link')" :hint="t('playlist.linkHint')" :error="form.errors.url" />
            <TextField v-model="form.note" :label="t('playlist.note')" :maxlength="200" :error="form.errors.note" />
            <Toggle v-model="form.is_ours" :label="t('playlist.ours')" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button><Button type="submit" form="pl-form" :loading="form.processing">{{ t('common.save') }}</Button></template>
    </Dialog>
    <ConfirmDialog :open="!!removing" :title="t('playlist.deleteTitle')" :description="removing?.title" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removing = null)" @confirm="remove" />
</template>
