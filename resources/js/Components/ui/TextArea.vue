<script setup>
import { useId } from 'vue';

const model = defineModel({ type: String, default: '' });

defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    rows: { type: Number, default: 3 },
    maxlength: { type: Number, default: null },
});

const id = useId();
</script>

<template>
    <div class="space-y-1.5">
        <label :for="id" class="block text-sm font-semibold text-plum">{{ label }}</label>
        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :maxlength="maxlength"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-desc` : undefined"
            class="block w-full resize-y rounded-md border bg-ivory px-4 py-3 text-base text-plum placeholder:text-ink-muted/70 transition focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush"
            :class="error ? 'border-danger' : 'border-line-strong'"
        />
        <p v-if="error" :id="`${id}-desc`" class="text-xs font-medium text-danger">{{ error }}</p>
    </div>
</template>
