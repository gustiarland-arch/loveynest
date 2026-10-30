<script setup>
import { ref, watch, onBeforeUnmount, useId } from 'vue';
import { X } from '@lucide/vue';

// Native <dialog> gives focus trapping, Esc handling and inert background.
// On phones it docks to the bottom like a sheet; on wider screens it centres.
const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});

const dialog = ref(null);
const titleId = useId();

watch(
    open,
    (value) => {
        const el = dialog.value;
        if (!el) return;
        if (value && !el.open) el.showModal();
        if (!value && el.open) el.close();
    },
    { flush: 'post' },
);

function onBackdrop(event) {
    if (event.target === dialog.value) open.value = false;
}

onBeforeUnmount(() => dialog.value?.open && dialog.value.close());
</script>

<template>
    <dialog
        ref="dialog"
        :aria-labelledby="titleId"
        class="ui-dialog m-0 mt-auto w-full max-w-none bg-transparent p-0 backdrop:bg-[rgb(0_0_0/0.32)] sm:m-auto sm:max-w-md"
        @close="open = false"
        @click="onBackdrop"
    >
        <div class="max-h-[88dvh] overflow-y-auto rounded-t-[1.75rem] bg-surface px-5 pb-safe pt-2.5 shadow-lift sm:rounded-[1.75rem] sm:p-6">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-line-strong sm:hidden" aria-hidden="true" />
            <header class="mb-4 flex items-start gap-3">
                <div class="flex-1">
                    <h2 :id="titleId" class="text-xl font-bold text-plum">{{ title }}</h2>
                    <p v-if="description" class="mt-1 text-sm text-ink-muted">{{ description }}</p>
                </div>
                <button
                    type="button"
                    class="-mr-2 grid size-11 place-items-center rounded-full bg-fill text-ink-muted hover:text-plum"
                    :aria-label="$t('common.close')"
                    @click="open = false"
                >
                    <X class="size-5" aria-hidden="true" />
                </button>
            </header>
            <slot />
            <footer v-if="$slots.footer" class="mt-6 flex flex-col-reverse gap-2 pb-2 sm:flex-row sm:justify-end sm:pb-0">
                <slot name="footer" />
            </footer>
        </div>
    </dialog>
</template>

<style scoped>
.ui-dialog[open] {
    animation: sheet-in 260ms var(--ease-gentle);
}

@media (min-width: 640px) {
    .ui-dialog[open] {
        animation-name: dialog-in;
    }
}

@keyframes sheet-in {
    from {
        transform: translateY(24px);
        opacity: 0;
    }
}

@keyframes dialog-in {
    from {
        transform: scale(0.97);
        opacity: 0;
    }
}
</style>
