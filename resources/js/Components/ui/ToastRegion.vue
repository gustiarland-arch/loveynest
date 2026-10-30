<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert, Info, X } from '@lucide/vue';
import { useToast } from '@/composables/useToast';

const toast = useToast();
const page = usePage();

// Server flash messages arrive as page props and are shown once per visit.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { immediate: true },
);

const icons = { success: CircleCheck, error: CircleAlert, info: Info };
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-0 z-50 flex flex-col items-center gap-2 px-4 pt-[max(1rem,env(safe-area-inset-top))]" aria-live="polite">
        <TransitionGroup
            enter-from-class="-translate-y-3 opacity-0"
            leave-to-class="opacity-0"
            enter-active-class="transition duration-300 ease-gentle"
            leave-active-class="transition duration-200"
        >
            <div
                v-for="item in toast.items"
                :key="item.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg bg-plum px-4 py-3 text-sm text-ivory shadow-lift"
                :role="item.tone === 'error' ? 'alert' : 'status'"
            >
                <component :is="icons[item.tone]" class="mt-0.5 size-5 shrink-0" :class="item.tone === 'error' ? 'text-blush' : 'text-sage'" aria-hidden="true" />
                <p class="flex-1">{{ item.message }}</p>
                <button type="button" class="-m-1 rounded-full p-1 text-ivory/70 hover:text-ivory" :aria-label="$t('common.close')" @click="toast.dismiss(item.id)">
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
