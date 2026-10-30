<script setup>
import CloudBand from '@/Components/brand/CloudBand.vue';
import ToastRegion from '@/Components/ui/ToastRegion.vue';
import { applyBackground } from '@/lib/theme';

// Signed out: back to the default backdrop.
applyBackground(null);
</script>

<template>
    <div class="relative min-h-dvh overflow-hidden">
        <CloudBand class="pointer-events-none fixed inset-x-0 bottom-0 w-full opacity-50" />
        <div class="blob-drift pointer-events-none absolute -left-24 -top-24 size-72 rounded-full bg-blush/70 blur-3xl" aria-hidden="true" />
        <div class="blob-drift pointer-events-none absolute -right-20 top-1/3 size-64 rounded-full bg-lavender/70 blur-3xl" style="animation-delay: -6s" aria-hidden="true" />
        <main class="relative mx-auto flex min-h-dvh w-full max-w-md flex-col px-5 pb-safe pt-safe sm:max-w-lg">
            <slot />
        </main>
        <ToastRegion />
    </div>
</template>
