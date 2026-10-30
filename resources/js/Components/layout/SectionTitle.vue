<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    href: { type: String, default: null },
    linkLabel: { type: String, default: null },
});
</script>

<template>
    <div class="mb-3 mt-8 flex items-baseline justify-between gap-3">
        <h2 class="text-[1.25rem] font-bold text-plum">{{ title }}</h2>
        <Link v-if="href" :href="href" class="text-[0.9375rem] text-berry-ink transition hover:opacity-70">{{ linkLabel }}</Link>
    </div>
</template>
