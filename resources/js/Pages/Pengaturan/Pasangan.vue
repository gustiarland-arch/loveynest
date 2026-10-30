<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Heart, HeartCrack } from '@lucide/vue';
import DateField from '@/Components/ui/DateField.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import TextField from '@/Components/ui/TextField.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ couple: { type: Object, default: null } });

const anniversaryForm = useForm({ anniversary_date: props.couple?.anniversary ?? '', met_date: props.couple?.metDate ?? '' });

const leaveOpen = ref(false);
const leaveForm = useForm({ confirmation: '' });
function leave() {
    leaveForm.delete('/couple', { onSuccess: () => (leaveOpen.value = false) });
}
const spaceThemes = [{id: 'rose', color: '#e0245e'}, {id: 'lavender', color: '#5856d6'}, {id: 'sage', color: '#248a3d'}, {id: 'biru', color: '#0a6fd8'}, {id: 'tosca', color: '#0e8f8f'}, {id: 'jingga', color: '#d9620b'}, {id: 'emas', color: '#a67c00'}, {id: 'magenta', color: '#c2188f'}];
</script>

<template>
    <Head :title="$t('pengaturan.couple')" />
    <PageHeader :title="$t('pengaturan.couple')" back="/pengaturan" />

    <div class="space-y-4">
        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold"><Heart class="size-4 text-berry-ink" aria-hidden="true" />{{ $t('settings.couple') }}</h2>
            <template v-if="couple">
                <p v-if="couple.name" class="mt-2 font-semibold text-plum">{{ couple.name }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ couple.partner ? $t('settings.partner', { name: couple.partner }) : $t('settings.waiting') }}</p>
                <form class="mt-4 space-y-3" novalidate @submit.prevent="anniversaryForm.transform((d) => ({ anniversary_date: d.anniversary_date || null, met_date: d.met_date || null })).post('/momen/jadian', { preserveScroll: true })">
                    <DateField v-model="anniversaryForm.met_date" :label="$t('settings.metDate')" clearable :error="anniversaryForm.errors.met_date" />
                    <DateField v-model="anniversaryForm.anniversary_date" :label="$t('moments.anniversary')" clearable :error="anniversaryForm.errors.anniversary_date" />
                    <Button type="submit" size="md" variant="secondary" :loading="anniversaryForm.processing">{{ $t('common.save') }}</Button>
                </form>
                <fieldset class="mt-4"><legend class="text-sm font-semibold text-plum">{{ $t('settings.spaceTheme') }}</legend>
                    <div class="mt-1.5 grid grid-cols-2 gap-1.5 min-[400px]:grid-cols-3"><button v-for="theme in spaceThemes" :key="theme.id" type="button" class="flex min-h-11 items-center justify-center gap-2 rounded-[0.875rem] px-2 text-xs font-semibold ring-1 transition" :class="couple.theme === theme.id ? 'bg-blush text-berry-ink ring-berry' : 'bg-surface text-ink-muted ring-line'" :aria-pressed="couple.theme === theme.id" @click="router.patch('/settings/theme', { theme: theme.id }, { preserveScroll: true })"><span class="size-3.5 shrink-0 rounded-full" :style="{ background: theme.color }" aria-hidden="true" />{{ $t(`settings.themes.${theme.id}`) }}</button></div>
                </fieldset>
                <Button v-if="!couple.partner" href="/couple/invite" size="sm" variant="secondary" class="mt-4">{{ $t('dashboard.invite') }}</Button>
                <Button variant="danger-ghost" size="sm" class="mt-4" @click="leaveOpen = true"><HeartCrack class="size-4" aria-hidden="true" />{{ $t('settings.leave') }}</Button>
            </template>
            <template v-else>
                <p class="mt-2 text-sm text-ink-muted">{{ $t('settings.noCouple') }}</p>
                <Button href="/onboarding" size="sm" class="mt-4">{{ $t('dashboard.connect') }}</Button>
            </template>
        </Card>

        <Dialog v-model:open="leaveOpen" :title="$t('settings.leaveTitle')">
            <form class="space-y-4" @submit.prevent="leave">
                <p class="text-sm text-ink-muted">{{ $t('settings.leaveBody') }}</p>
                <ul class="list-disc space-y-1 pl-5 text-sm text-ink-muted">
                    <li>{{ $t('settings.leave1') }}</li>
                    <li>{{ $t('settings.leave2') }}</li>
                    <li>{{ $t('settings.leave3') }}</li>
                </ul>
                <TextField v-model="leaveForm.confirmation" :label="$t('settings.leaveConfirm')" autocomplete="off" :error="leaveForm.errors.confirmation" />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" @click="leaveOpen = false">{{ $t('common.cancel') }}</Button>
                    <Button type="submit" variant="danger" :loading="leaveForm.processing" :disabled="leaveForm.confirmation !== 'KELUAR'">{{ $t('settings.leave') }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
