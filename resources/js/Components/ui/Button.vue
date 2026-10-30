<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | soft | ghost | danger | danger-ghost (iOS: filled, glass, tinted, plain)
    size: { type: String, default: 'md' }, // sm | md | lg
    href: { type: String, default: null },
    method: { type: String, default: 'get' },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const variants = {
    primary: 'bg-berry text-white hover:bg-berry-deep active:opacity-85',
    secondary: 'glass text-berry-ink hover:brightness-[1.03]',
    soft: 'bg-berry/[0.13] text-berry-ink hover:bg-berry/[0.19]',
    ghost: 'text-berry-ink hover:bg-fill',
    danger: 'bg-danger text-white hover:bg-danger/90',
    'danger-ghost': 'text-danger hover:bg-danger-soft',
};

const sizes = {
    sm: 'h-9 px-4 text-sm gap-1.5 rounded-full',
    md: 'h-11 px-5 text-[0.9375rem] gap-2 rounded-full',
    lg: 'h-[3.25rem] px-6 text-base gap-2 rounded-full',
};

const classes = computed(() => [
    'inline-flex select-none items-center justify-center font-semibold transition duration-200 ease-gentle',
    'active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50',
    variants[props.variant] ?? variants.primary,
    sizes[props.size] ?? sizes.md,
    props.block && 'w-full',
]);
</script>

<template>
    <Link v-if="href" :href="href" :method="method" :as="method === 'get' ? 'a' : 'button'" :class="classes">
        <slot />
    </Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading || undefined">
        <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
        <slot />
    </button>
</template>
