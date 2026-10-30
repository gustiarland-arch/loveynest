<script setup>
// The LoveyNest mark: two birds in a nest under one roof.
defineProps({
    size: { type: Number, default: 160 },
});
</script>

<template>
    <img src="/img/loveynest-mark.png" alt="" :width="size" :height="Math.round(size * 0.94)" class="object-contain" draggable="false" />
</template>
