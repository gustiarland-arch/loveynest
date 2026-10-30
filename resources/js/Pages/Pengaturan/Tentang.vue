<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';

const page = usePage();
</script>

<template>
    <Head :title="$t('pengaturan.about')" />
    <PageHeader :title="$t('pengaturan.about')" back="/pengaturan" />
    <Card>
        <h2 class="flex items-center gap-2 text-lg font-semibold"><Info class="size-4 text-ink-muted" aria-hidden="true" />{{ $t('settings.about') }}</h2>
        <p class="mt-2 text-sm text-ink-muted">LoveyNest · {{ $t('settings.version', { version: page.props.app.version }) }}</p>
        <p class="text-sm text-ink-muted">{{ $t('app.tagline') }}</p>
    </Card>
</template>
