<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Smartphone, Bell, Download, LogOut, Trash2, ShieldCheck } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { formatDateTime } from '@/lib/dates';
import { t } from '@/i18n';
import { e2e } from '@/lib/e2e';
import E2eSettings from '@/Components/settings/E2eSettings.vue';
import { downloadUnsealed } from '@/lib/e2e/download';
import { useToast } from '@/composables/useToast';

defineProps({ sessions: Array, pushDevices: Array, inCouple: Boolean, partner: String });

const revoking = ref(null);
function revoke() {
    router.delete(`/settings/privasi/sesi/${revoking.value.id}`, { preserveScroll: true, onSuccess: () => (revoking.value = null) });
}
const removingPush = ref(null);
function removePush() {
    router.delete(`/settings/privasi/push/${removingPush.value.id}`, { preserveScroll: true, onSuccess: () => (removingPush.value = null) });
}

const allOpen = ref(false);
const allForm = useForm({ password: '' });
function logoutAll() {
    allForm.post('/settings/privasi/keluar-semua');
}

const toast = useToast();
// Locked text in the export is opened on this device before the file is saved.
function exportData() {
    downloadUnsealed('/settings/privasi/ekspor', 'json').catch((e) => toast.error(e.message));
}

const delOpen = ref(false);
const delForm = useForm({ password: '', confirmation: '' });
function deleteAccount() {
    delForm.delete('/settings/privasi/akun');
}
</script>

<template>
    <Head :title="t('privacy.title')" />
    <PageHeader :title="t('privacy.title')" :lead="t('privacy.lead')" back="/pengaturan" />

    <div class="space-y-4">
        <E2eSettings />

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold text-plum"><Smartphone class="size-4 text-berry-ink" aria-hidden="true" />{{ t('privacy.devices') }}</h2>
            <ul class="mt-3 space-y-2">
                <li v-for="s in sessions" :key="s.id" class="flex items-center gap-3 rounded-[1rem] bg-surface px-3 py-2.5 ring-1 ring-line">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-plum">{{ s.label }}<span v-if="s.current" class="ml-2 rounded-full bg-sage px-2 py-0.5 text-[0.6875rem] text-sage-ink">{{ t('privacy.thisDevice') }}</span></p>
                        <p class="truncate text-xs text-ink-muted">{{ formatDateTime(s.last) }}<template v-if="s.ip"> · {{ s.ip }}</template></p>
                    </div>
                    <Button v-if="!s.current" size="sm" variant="secondary" @click="revoking = s">{{ t('privacy.signOut') }}</Button>
                </li>
            </ul>
            <Button class="mt-4" variant="secondary" @click="allOpen = true"><LogOut class="size-4" aria-hidden="true" />{{ t('privacy.signOutAll') }}</Button>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold text-plum"><Bell class="size-4 text-berry-ink" aria-hidden="true" />{{ t('privacy.pushDevices') }}</h2>
            <p v-if="!pushDevices.length" class="mt-2 text-sm text-ink-muted">{{ t('privacy.noPush') }}</p>
            <ul v-else class="mt-3 space-y-2">
                <li v-for="p in pushDevices" :key="p.id" class="flex items-center gap-3 rounded-[1rem] bg-surface px-3 py-2.5 ring-1 ring-line">
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-plum">{{ p.label }}</p><p v-if="p.last" class="truncate text-xs text-ink-muted">{{ t('privacy.lastPush', { date: formatDateTime(p.last) }) }}</p></div>
                    <Button size="sm" variant="secondary" @click="removingPush = p">{{ t('common.delete') }}</Button>
                </li>
            </ul>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold text-plum"><Download class="size-4 text-berry-ink" aria-hidden="true" />{{ t('privacy.export') }}</h2>
            <p class="mt-2 text-sm text-ink-muted">{{ t('privacy.exportBody') }}</p>
            <a href="/settings/privasi/ekspor" download @click.prevent="exportData" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-full bg-surface px-5 text-sm font-semibold text-plum ring-1 ring-line transition hover:bg-blush-soft">{{ t('privacy.exportButton') }}</a>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold text-plum"><ShieldCheck class="size-4 text-berry-ink" aria-hidden="true" />{{ t('privacy.promises') }}</h2>
            <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-ink-muted">
                <template v-if="e2e.enabled"><li v-for="key in ['e1', 'e2', 'e3', 'e4', 'e5', 'e6']" :key="key">{{ t(`privacy.${key}`) }}</li></template>
                <template v-else><li>{{ t('privacy.p1') }}</li><li>{{ t('privacy.p2') }}</li><li>{{ t('privacy.p4') }}</li></template>
                <li>{{ t('privacy.p3') }}</li>
            </ul>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold text-danger"><Trash2 class="size-4" aria-hidden="true" />{{ t('privacy.deleteTitle') }}</h2>
            <p class="mt-2 text-sm text-ink-muted">{{ t('privacy.deleteBody') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink-muted">
                <li>{{ t('privacy.d1') }}</li>
                <li v-if="inCouple">{{ t('privacy.d2', { name: partner ?? t('chat.partner') }) }}</li>
                <li>{{ t('privacy.d3') }}</li>
            </ul>
            <Button class="mt-4" variant="danger-ghost" @click="delOpen = true">{{ t('privacy.deleteButton') }}</Button>
        </Card>
    </div>

    <Dialog v-model:open="allOpen" :title="t('privacy.signOutAll')">
        <form id="all-form" class="space-y-3" novalidate @submit.prevent="logoutAll">
            <p class="text-sm text-ink-muted">{{ t('privacy.signOutAllBody') }}</p>
            <TextField v-model="allForm.password" type="password" autocomplete="current-password" :label="t('settings.currentPassword')" :error="allForm.errors.password" required />
        </form>
        <template #footer><Button variant="secondary" @click="allOpen = false">{{ t('common.cancel') }}</Button><Button type="submit" form="all-form" :loading="allForm.processing">{{ t('privacy.signOutAll') }}</Button></template>
    </Dialog>

    <Dialog v-model:open="delOpen" :title="t('privacy.deleteTitle')">
        <form id="del-form" class="space-y-3" novalidate @submit.prevent="deleteAccount">
            <p class="text-sm text-ink-muted">{{ t('privacy.deleteConfirmBody') }}</p>
            <TextField v-model="delForm.password" type="password" autocomplete="current-password" :label="t('settings.currentPassword')" :error="delForm.errors.password" required />
            <TextField v-model="delForm.confirmation" :label="t('privacy.typeDelete')" autocomplete="off" :error="delForm.errors.confirmation" required />
        </form>
        <template #footer><Button variant="secondary" @click="delOpen = false">{{ t('common.cancel') }}</Button><Button type="submit" form="del-form" variant="danger" :loading="delForm.processing" :disabled="delForm.confirmation !== 'HAPUS' || !delForm.password">{{ t('privacy.deleteButton') }}</Button></template>
    </Dialog>

    <ConfirmDialog :open="!!revoking" :title="t('privacy.signOut')" :description="revoking?.label" :confirm-label="t('privacy.signOut')" @update:open="(v) => !v && (revoking = null)" @confirm="revoke" />
    <ConfirmDialog :open="!!removingPush" :title="t('privacy.removePush')" :description="removingPush?.label" :confirm-label="t('common.delete')" danger @update:open="(v) => !v && (removingPush = null)" @confirm="removePush" />
</template>
