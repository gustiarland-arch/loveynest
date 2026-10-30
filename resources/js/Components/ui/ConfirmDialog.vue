<script setup>
import Dialog from './Dialog.vue';
import Button from './Button.vue';

const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    confirmLabel: { type: String, default: null },
    danger: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm']);
</script>

<template>
    <Dialog v-model:open="open" :title="title" :description="description">
        <slot />
        <template #footer>
            <Button variant="secondary" @click="open = false">{{ $t('common.cancel') }}</Button>
            <Button :variant="danger ? 'danger' : 'primary'" :loading="loading" @click="emit('confirm')">
                {{ confirmLabel ?? $t('common.confirm') }}
            </Button>
        </template>
    </Dialog>
</template>
