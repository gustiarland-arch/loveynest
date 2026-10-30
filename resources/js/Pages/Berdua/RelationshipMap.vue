<script setup>
import { Head } from '@inertiajs/vue3';
import { Heart, Sparkles, CalendarHeart, Wine, MapPin, Flag, Star } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import { t } from '@/i18n';

defineProps({ stops: Array, places: Array, trimmed: Boolean, partner: String });

const icons = { met: Sparkles, together: Heart, milestone: CalendarHeart, date: Wine, place: MapPin };
const tones = {
    met: 'bg-lavender text-lavender-ink',
    together: 'bg-hero text-white',
    milestone: 'bg-peach text-peach-ink',
    date: 'bg-blush text-berry-ink',
    place: 'bg-sage text-sage-ink',
};
const fmt = (d) => new Date(`${d}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head :title="t('relmap.title')" />
    <PageHeader :title="t('relmap.title')" :lead="t('relmap.lead')" back="/berdua" />

    <p v-if="!stops.length" class="glass rounded-[1.25rem] p-4 text-sm text-ink-muted">{{ t('relmap.empty') }}</p>

    <template v-else>
        <h2 class="mb-3 font-display text-lg font-semibold text-plum">{{ t('relmap.journey') }}</h2>
        <p v-if="trimmed" class="mb-2 text-xs text-ink-muted">{{ t('relmap.trimmed') }}</p>
        <ol class="relative ml-5 border-l-2 border-dashed border-blush pb-2">
            <li class="mb-3 -ml-[0.8125rem] flex items-center gap-2">
                <span class="grid size-6 place-items-center rounded-full bg-plum text-white"><Flag class="size-3.5" aria-hidden="true" /></span>
                <span class="text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ t('relmap.start') }}</span>
            </li>
            <li v-for="(s, i) in stops" :key="`${s.kind}-${s.date}-${i}`" class="relative mb-3 pl-7" :class="i % 2 ? 'pr-0' : 'pr-4'">
                <span class="absolute -left-[0.8125rem] top-3 grid size-6 place-items-center rounded-full ring-4 ring-white/70" :class="tones[s.kind]">
                    <component :is="icons[s.kind]" class="size-3.5" aria-hidden="true" />
                </span>
                <div class="glass rounded-[1rem] px-3.5 py-2.5">
                    <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.06em] text-ink-muted">{{ t(`relmap.${s.kind}`) }}</p>
                    <p class="font-semibold text-plum">{{ fmt(s.date) }}</p>
                    <p v-if="s.title" class="break-words font-semibold text-plum">{{ s.title }}</p>
                    <p v-if="s.rating" class="mt-0.5 flex gap-0.5 text-berry" :aria-label="`${s.rating}/5`">
                        <Star v-for="n in s.rating" :key="n" class="size-3.5 fill-current" aria-hidden="true" />
                    </p>
                </div>
            </li>
            <li class="-ml-[0.8125rem] flex items-center gap-2">
                <span class="grid size-6 place-items-center rounded-full bg-hero text-white"><Heart class="size-3.5 fill-current" aria-hidden="true" /></span>
                <span class="text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ t('relmap.now') }}</span>
            </li>
        </ol>
    </template>

    <template v-if="places.length">
        <h2 class="mb-3 mt-6 font-display text-lg font-semibold text-plum">{{ t('relmap.places') }}</h2>
        <ul class="grid grid-cols-2 gap-3">
            <li v-for="p in places" :key="p.name" class="glass min-w-0 overflow-hidden rounded-[1.25rem]">
                <img :src="`/photos/${p.photoId}/thumb`" :alt="p.name" class="aspect-[4/3] w-full object-cover" loading="lazy" />
                <div class="p-3">
                    <p class="flex items-start gap-1 break-words font-semibold text-plum"><MapPin class="mt-0.5 size-4 shrink-0 text-berry" aria-hidden="true" />{{ p.name }}</p>
                    <p class="mt-0.5 text-xs text-ink-muted">{{ t('relmap.placePhotos', { count: p.count }) }}</p>
                    <p v-if="p.first !== p.last" class="text-[0.6875rem] text-ink-muted">{{ t('relmap.placeRange', { first: fmt(p.first), last: fmt(p.last) }) }}</p>
                    <p v-else class="text-[0.6875rem] text-ink-muted">{{ fmt(p.first) }}</p>
                </div>
            </li>
        </ul>
    </template>
    <p class="mt-6 text-center text-xs text-ink-muted">{{ t('relmap.footnote') }}</p>
</template>
