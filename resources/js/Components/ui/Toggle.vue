<script setup>
import { useId } from 'vue';

const model = defineModel({ type: Boolean, default: false });

defineProps({
    label: { type: String, required: true },
    // For compact grids where a visible heading already names the switch.
    hideLabel: { type: Boolean, default: false },
});

const id = useId();
</script>

<template>
    <label :for="id" class="flex min-h-11 cursor-pointer items-center justify-between gap-4">
        <span class="text-sm font-semibold text-plum" :class="{ 'sr-only': hideLabel }">{{ label }}</span>
        <span class="relative inline-flex">
            <input :id="id" v-model="model" type="checkbox" role="switch" class="peer sr-only" />
            <span class="h-[1.9375rem] w-[3.1875rem] rounded-full bg-fill transition duration-300 peer-checked:bg-switch peer-focus-visible:ring-4 peer-focus-visible:ring-blush" />
            <span class="absolute left-[2px] top-[2px] size-[1.6875rem] rounded-full bg-white shadow-[0_3px_8px_rgb(0_0_0/0.15),0_3px_1px_rgb(0_0_0/0.06)] transition duration-300 ease-gentle peer-checked:translate-x-5" />
        </span>
    </label>
</template>
