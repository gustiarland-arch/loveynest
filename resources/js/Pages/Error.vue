<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Emblem from '@/Components/brand/Emblem.vue';
import { t } from '@/i18n';

const props = defineProps({
    status: { type: Number, required: true },
});

const known = [403, 404, 419, 429, 500, 503];
const code = computed(() => (known.includes(props.status) ? props.status : 500));
</script>

<template>
    <Head :title="t(`errors.title.${code}`)" />
    <section class="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <Emblem :size="120" />
        <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-rose-ink">{{ status }}</p>
        <h1 class="mt-2 text-3xl font-semibold text-plum">{{ t(`errors.title.${code}`) }}</h1>
        <p class="mt-3 max-w-xs text-sm text-ink-muted">{{ t(`errors.body.${code}`) }}</p>
        <Button href="/" class="mt-8">{{ $t('errors.home') }}</Button>
    </section>
</template>
