<script setup>
// One sticker picture from the Arena set (a bear, a cupcake, a rocket…).
import { computed } from 'vue';
import { sticker } from '@/lib/arenaArt';

const props = defineProps({ name: { type: String, required: true }, size: { type: [Number, String], default: 48 } });
const markup = computed(() => sticker(props.name));
</script>

<template>
    <svg viewBox="0 0 64 64" :width="size" :height="size" class="block overflow-visible" aria-hidden="true" v-html="markup" />
</template>
