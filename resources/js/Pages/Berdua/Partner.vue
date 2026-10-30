<script setup>
import { Head } from '@inertiajs/vue3';
import { Cake, CalendarHeart, Heart } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';

defineProps({ partner: Object, me: Object, daysSinceMet: Number, daysTogether: Number, metDate: String, anniversary: String, dates: Array });
const day = (iso) => formatDate(`${iso}T12:00:00Z`, { dateStyle: 'long' }, 'UTC');
</script>

<template>
    <Head :title="t('partner.title')" />
    <PageHeader :title="t('partner.title')" back="/berdua" />

    <Card v-if="partner" class="text-center">
        <Avatar :name="partner.name" :src="partner.avatarUrl" :frame="partner.avatarFrame" size="lg" class="mx-auto" />
        <h2 class="mt-3 break-words font-display text-2xl font-semibold text-plum">{{ partner.nickname || partner.name }}</h2>
        <p v-if="partner.nickname" class="text-sm text-ink-muted">{{ partner.name }}</p>
        <p v-else class="text-xs text-ink-muted">{{ t('partner.noNickname') }}</p>
    </Card>

    <div class="mt-3 grid grid-cols-2 gap-3">
        <div class="rounded-[1.5rem] bg-hero p-4 text-white shadow-soft">
            <p class="text-xs font-semibold uppercase tracking-[0.08em] opacity-90">{{ t('partner.sinceMet') }}</p>
            <p class="mt-1 font-display text-[2rem] font-semibold leading-none">{{ daysSinceMet ?? '–' }}</p>
            <p class="mt-1 text-xs opacity-90">{{ metDate ? day(metDate) : t('partner.setInSettings') }}</p>
        </div>
        <div class="rounded-[1.5rem] bg-hero-lav p-4 text-white shadow-soft">
            <p class="text-xs font-semibold uppercase tracking-[0.08em] opacity-90">{{ t('partner.sinceDating') }}</p>
            <p class="mt-1 font-display text-[2rem] font-semibold leading-none">{{ daysTogether ?? '–' }}</p>
            <p class="mt-1 text-xs opacity-90">{{ anniversary ? day(anniversary) : t('partner.setInSettings') }}</p>
        </div>
    </div>

    <h2 class="mb-2 mt-6 text-lg font-semibold text-plum">{{ t('partner.birthdays') }}</h2>
    <ul class="space-y-2">
        <li v-for="(b, who) in { partner: partner?.birthday, me: me.birthday }" :key="who" class="glass flex items-center gap-3 rounded-[1.25rem] p-3.5">
            <span class="grid size-11 shrink-0 place-items-center rounded-[0.875rem] bg-peach text-peach-ink"><Cake class="size-5" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-plum">{{ who === 'partner' ? (partner?.nickname || partner?.name) : t('partner.you') }}</p>
                <p v-if="b" class="text-xs text-ink-muted">{{ day(b.date) }} · {{ b.in === 0 ? t('partner.today', { age: b.turns }) : t('partner.inDays', { count: b.in, age: b.turns }) }}</p>
                <p v-else class="text-xs text-ink-muted">{{ who === 'me' ? t('partner.setBirthday') : t('partner.noBirthday') }}</p>
            </div>
        </li>
    </ul>

    <h2 class="mb-2 mt-6 flex items-center justify-between text-lg font-semibold text-plum">{{ t('partner.keyDates') }}<a href="/momen" class="text-sm font-semibold text-rose-ink">{{ t('partner.manage') }}</a></h2>
    <ul v-if="dates.length" class="space-y-2">
        <li v-for="d in dates" :key="d.id" class="glass flex items-center gap-3 rounded-[1.25rem] p-3.5">
            <span class="grid size-11 shrink-0 place-items-center rounded-[0.875rem] bg-blush text-berry-ink"><CalendarHeart class="size-5" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1"><p class="break-words font-semibold text-plum">{{ d.title }}</p><p class="text-xs text-ink-muted">{{ day(d.date) }}<template v-if="d.in !== null"> · {{ d.in === 0 ? t('partner.todayShort') : t('partner.inDaysShort', { count: d.in }) }}</template></p></div>
        </li>
    </ul>
    <p v-else class="flex items-center gap-2 text-sm text-ink-muted"><Heart class="size-4" aria-hidden="true" />{{ t('partner.noDates') }}</p>
</template>
