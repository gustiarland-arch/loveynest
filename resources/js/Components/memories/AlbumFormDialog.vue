<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { User, Users } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    album: { type: Object, default: null },
    defaultSpace: { type: String, default: 'mine' },
    hasCouple: { type: Boolean, default: false },
});

const form = useForm({ space: 'mine', title: '', description: '' });
const editing = computed(() => props.album !== null);

watch(open, (value) => {
    if (!value) return;
    form.clearErrors();
    form.defaults({ space: props.defaultSpace, title: props.album?.title ?? '', description: props.album?.description ?? '' });
    form.reset();
});

const spaces = [
    { value: 'mine', label: t('space.mine'), icon: User },
    { value: 'ours', label: t('space.ours'), icon: Users },
];

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) submitSealed(form, ['title', 'description'], (f) => f.patch(`/albums/${props.album.id}`, options), ({ space, ...data }) => data);
    else submitSealed(form, ['title', 'description'], (f) => f.post('/albums', options));
}
</script>

<template>
    <Dialog v-model:open="open" :title="editing ? t('albums.editAlbum') : t('albums.newAlbum')">
        <form id="album-form" class="space-y-4" novalidate @submit.prevent="submit">
            <SegmentedControl v-if="!editing && hasCouple" v-model="form.space" :label="t('space.label')" :options="spaces" />
            <p v-if="form.errors.space" class="text-xs font-medium text-danger">{{ form.errors.space }}</p>
            <TextField v-model="form.title" :label="t('albums.fieldTitle')" :placeholder="t('albums.fieldTitlePlaceholder')" :maxlength="80" :error="form.errors.title" required />
            <TextArea v-model="form.description" :label="t('albums.fieldDescription')" :maxlength="300" :rows="2" :error="form.errors.description" />
        </form>
        <template #footer>
            <Button variant="secondary" @click="open = false">{{ t('common.cancel') }}</Button>
            <Button type="submit" form="album-form" :loading="form.processing">{{ t('common.save') }}</Button>
        </template>
    </Dialog>
</template>
