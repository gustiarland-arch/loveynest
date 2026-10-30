<script setup>
// The painted backdrop of a game or the hub. Purely decoration, it never takes taps.
import { computed } from 'vue';
import { scene } from '@/lib/arenaArt';

const props = defineProps({ name: { type: String, default: 'hub' } });
const markup = computed(() => scene(props.name));
</script>

<template>
    <svg viewBox="0 0 400 800" preserveAspectRatio="xMidYMax slice" class="pointer-events-none absolute inset-0 size-full" aria-hidden="true" v-html="markup" />
</template>
