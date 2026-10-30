<script setup>
defineProps({
    title: { type: String, required: true },
    body: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
});
</script>

<template>
    <div class="flex flex-col items-center px-6 py-10 text-center">
        <div class="relative mb-5 grid size-20 place-items-center">
            <div class="absolute inset-0 rotate-6 rounded-[1.75rem] bg-blush" aria-hidden="true" />
            <div class="absolute inset-0 -rotate-6 rounded-[1.75rem] bg-lavender/70" aria-hidden="true" />
            <component :is="icon" v-if="icon" class="relative size-8 text-rose-ink" aria-hidden="true" />
        </div>
        <h3 class="text-xl font-semibold text-plum">{{ title }}</h3>
        <p v-if="body" class="mt-2 max-w-xs text-sm text-ink-muted">{{ body }}</p>
        <div v-if="$slots.default" class="mt-6">
            <slot />
        </div>
    </div>
</template>
