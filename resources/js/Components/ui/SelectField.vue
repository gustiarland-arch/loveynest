<script setup>
import { useId } from 'vue';
import { ChevronDown } from '@lucide/vue';

const model = defineModel({ type: [String, Number], default: '' });

defineProps({
    label: { type: String, required: true },
    options: { type: Array, required: true }, // [{ value, label }]
    error: { type: String, default: null },
});

const id = useId();
</script>

<template>
    <div class="space-y-1.5">
        <label :for="id" class="block text-sm font-semibold text-plum">{{ label }}</label>
        <div class="relative">
            <select
                :id="id"
                v-model="model"
                :aria-invalid="error ? 'true' : undefined"
                class="block h-12 w-full appearance-none rounded-md border bg-ivory pl-4 pr-10 text-base text-plum focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush"
                :class="error ? 'border-danger' : 'border-line-strong'"
            >
                <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <ChevronDown class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
        </div>
        <p v-if="error" class="text-xs font-medium text-danger">{{ error }}</p>
    </div>
</template>
