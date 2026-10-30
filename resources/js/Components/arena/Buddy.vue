<script setup>
// The two game characters (pink and blue blobs) in a mood: happy | cheer | sad | wink | think | oops.
import { computed } from 'vue';
import { character } from '@/lib/arenaArt';

const props = defineProps({
    tone: { type: String, default: 'pink' },
    mood: { type: String, default: 'happy' },
    size: { type: [Number, String], default: 96 },
    flip: { type: Boolean, default: false },
});
const markup = computed(() => character(props.tone, props.mood));
</script>

<template>
    <svg viewBox="-6 0 112 112" :width="size" :height="size" class="block overflow-visible" :style="flip ? { transform: 'scaleX(-1)' } : undefined" aria-hidden="true" v-html="markup" />
</template>
