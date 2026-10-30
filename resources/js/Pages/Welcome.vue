<script setup>
import { Head } from '@inertiajs/vue3';
import { HeartHandshake, CalendarHeart, MessageCircleHeart, Wifi } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Emblem from '@/Components/brand/Emblem.vue';
import { t } from '@/i18n';

const features = [
    { icon: HeartHandshake, title: t('welcome.featureSpacesTitle'), body: t('welcome.featureSpacesBody'), tone: 'bg-blush' },
    { icon: CalendarHeart, title: t('welcome.featurePlanTitle'), body: t('welcome.featurePlanBody'), tone: 'bg-lavender' },
    { icon: MessageCircleHeart, title: t('welcome.featureChatTitle'), body: t('welcome.featureChatBody'), tone: 'bg-sage' },
];
</script>

<template>
    <Head :title="null" />

    <header class="flex items-center justify-between py-5">
        <span class="font-display text-xl font-semibold text-plum">LoveyNest</span>
        <Button href="/login" variant="ghost" size="sm">{{ $t('nav.login') }}</Button>
    </header>

    <section class="flex flex-1 flex-col justify-center pb-6 pt-4 text-center">
        <div class="mx-auto mb-6 motion-safe:animate-[float_6s_ease-in-out_infinite]">
            <Emblem :size="168" />
        </div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-ink">{{ $t('app.tagline') }}</p>
        <h1 class="mt-3 text-[2.5rem] font-semibold leading-[1.1] text-plum sm:text-5xl">{{ $t('welcome.headline') }}</h1>
        <p class="mx-auto mt-4 max-w-sm text-base text-ink-muted">{{ $t('welcome.lead') }}</p>

        <div class="mt-8 flex flex-col gap-3">
            <Button href="/register" size="lg" block>{{ $t('welcome.start') }}</Button>
            <Button href="/login" variant="secondary" size="lg" block>{{ $t('welcome.login') }}</Button>
        </div>
    </section>

    <section class="space-y-3 pb-8">
        <article v-for="feature in features" :key="feature.title" class="glass flex gap-4 rounded-[1.25rem] p-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-md" :class="feature.tone">
                <component :is="feature.icon" class="size-5 text-plum" aria-hidden="true" />
            </span>
            <div>
                <h2 class="font-sans text-sm font-bold tracking-normal text-plum">{{ feature.title }}</h2>
                <p class="mt-0.5 text-sm text-ink-muted">{{ feature.body }}</p>
            </div>
        </article>
        <p class="flex items-center justify-center gap-2 pt-3 text-xs text-ink-muted">
            <Wifi class="size-4" aria-hidden="true" />
            {{ $t('welcome.localNote') }}
        </p>
    </section>
</template>

<style>
@keyframes float {
    50% {
        transform: translateY(-6px);
    }
}
</style>
