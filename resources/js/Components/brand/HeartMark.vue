<script setup>
defineProps({ size: { type: Number, default: 28 } });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <path d="M16 28C7 21.500 2 16.500 2 10.800 2 6.900 5 4 8.700 4c2.700 0 5.100 1.500 7.300 4.300C18.200 5.500 20.600 4 23.300 4 27 4 30 6.900 30 10.800 30 16.500 25 21.500 16 28Z" fill="var(--color-berry)" />
        <path d="M9 9.500c1.200-1 2.600-1.200 3.800-.6" stroke="#fff" stroke-opacity=".55" stroke-width="1.800" stroke-linecap="round" />
    </svg>
</template>
