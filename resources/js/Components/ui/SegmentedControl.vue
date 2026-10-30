<script setup>
// Accessible tab-like switch, used for My Space / Our Space and filters.
const model = defineModel({ type: String, required: true });

function select(event, value) {
    model.value = value;
    event.currentTarget.scrollIntoView?.({ inline: 'center', block: 'nearest', behavior: 'smooth' });
}

defineProps({
    options: { type: Array, required: true }, // [{ value, label, icon? }]
    label: { type: String, required: true },
});
</script>

<template>
    <div class="scrollbar-none flex w-full max-w-full gap-0.5 overflow-x-auto min-[400px]:gap-1 rounded-full bg-fill p-[3px]" role="radiogroup" :aria-label="label">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            class="flex h-9 min-w-fit flex-1 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-full px-2 text-[0.8125rem] font-semibold min-[400px]:px-3 min-[400px]:text-sm transition duration-200 ease-gentle"
            :class="model === option.value ? 'bg-thumb text-plum shadow-[0_3px_8px_rgb(0_0_0/0.12),0_3px_1px_rgb(0_0_0/0.04)]' : 'text-plum/75 hover:text-plum'"
            @click="select($event, option.value)"
        >
            <!-- On narrow phones the icon gives way so every label fits without scrolling. -->
            <component :is="option.icon" v-if="option.icon" class="size-4 shrink-0" :class="option.label ? 'hidden min-[400px]:block' : ''" aria-hidden="true" />
            {{ option.label }}
        </button>
    </div>
</template>
