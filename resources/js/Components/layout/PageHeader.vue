<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';

defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: null },
    lead: { type: String, default: null },
    back: { type: String, default: null },
});
</script>

<template>
    <header class="mb-6">
        <Link
            v-if="back"
            :href="back"
            class="-ml-2 mb-1 inline-flex h-10 items-center gap-0.5 rounded-full pl-0.5 pr-3 text-base text-berry-ink transition hover:opacity-70"
        >
            <ChevronLeft class="size-6" :stroke-width="2.4" aria-hidden="true" />
            {{ $t('common.back') }}
        </Link>
        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
            <div class="min-w-0 flex-1 basis-48">
                <p v-if="eyebrow" class="text-[0.8125rem] font-semibold text-berry-ink">{{ eyebrow }}</p>
                <h1 class="mt-0.5 break-words text-[2.125rem] font-bold leading-[1.15] text-plum">{{ title }}</h1>
            </div>
            <div v-if="$slots.action" class="shrink-0 pt-1.5"><slot name="action" /></div>
        </div>
        <p v-if="lead" class="mt-2 text-sm text-ink-muted">{{ lead }}</p>
    </header>
</template>
