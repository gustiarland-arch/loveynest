<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CalendarHeart, MessageCircleHeart, ListChecks, Gift, ArrowRight, CheckCircle2, Repeat, LockKeyhole, Vote, Dices, Flame, Sun, BookHeart, Images, Lock, Wallet, ListMusic, Sparkles, ClipboardCheck, Gamepad2, UserRound, CalendarRange, NotebookPen, MapPinned, Navigation, Camera } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import NudgeBar from '@/Components/memories/NudgeBar.vue';
import { t } from '@/i18n';

const props = defineProps({
    hasCouple: { type: Boolean, required: true },
    hasPartner: { type: Boolean, required: true },
    partner: { type: Object, default: null },
    facts: { type: Object, required: true },
    received: { type: Array, default: () => [] },
    question: { type: Object, default: null },
});

// Some features need the pair; the rest also work alone.
const tiles = [
    { href: '/photobooth', key: 'photobooth', icon: Camera, pair: false, tone: 'bg-peach text-peach-ink' },
    { href: '/momen', key: 'moments', icon: CalendarHeart, pair: true, tone: 'bg-blush text-berry-ink' },
    { href: '/pertanyaan', key: 'question', icon: MessageCircleHeart, pair: true, tone: 'bg-lavender text-lavender-ink' },
    { href: '/daftar?kind=bucket&space=ours', key: 'bucket', icon: ListChecks, pair: false, tone: 'bg-sage text-sage-ink' },
    { href: '/daftar?kind=wishlist&space=ours', key: 'wishlist', icon: Gift, pair: false, tone: 'bg-peach text-peach-ink' },
    { href: '/kapsul', key: 'capsule', icon: LockKeyhole, pair: true, tone: 'bg-lavender text-lavender-ink' },
    { href: '/voting', key: 'poll', icon: Vote, pair: true, tone: 'bg-blush text-berry-ink' },
    { href: '/kebiasaan', key: 'growth', icon: Repeat, pair: false, tone: 'bg-sage text-sage-ink' },
    { href: '/anggaran', key: 'budget', icon: Wallet, pair: true, tone: 'bg-lavender text-lavender-ink' },
    { href: '/playlist', key: 'playlist', icon: ListMusic, pair: true, tone: 'bg-blush text-berry-ink' },
    { href: '/impian', key: 'dreams', icon: Sparkles, pair: true, tone: 'bg-peach text-peach-ink' },
    { href: '/checkin', key: 'checkin', icon: ClipboardCheck, pair: true, tone: 'bg-sage text-sage-ink' },
    { href: '/arena', key: 'arena', icon: Gamepad2, pair: false, tone: 'bg-peach text-peach-ink' },
    { href: '/pasangan', key: 'partner', icon: UserRound, pair: true, tone: 'bg-blush text-berry-ink' },
    { href: '/lokasi', key: 'location', icon: Navigation, pair: true, tone: 'bg-lavender text-lavender-ink' },
    { href: '/peta-hubungan', key: 'relmap', icon: MapPinned, pair: true, tone: 'bg-sage text-sage-ink' },
    { href: '/tahun-kita', key: 'year', icon: CalendarRange, pair: true, tone: 'bg-lavender text-lavender-ink' },
    { href: '/refleksi', key: 'reflect', icon: NotebookPen, pair: true, tone: 'bg-sage text-sage-ink' },
    { href: '/ide', key: 'ideas', icon: Dices, pair: false, tone: 'bg-peach text-peach-ink' },
    { href: '/mood', key: 'mood', icon: Sun, pair: false, tone: 'bg-peach text-peach-ink' },
    { href: '/journal', key: 'journal', icon: BookHeart, pair: false, tone: 'bg-sage text-sage-ink' },
    { href: '/albums', key: 'albums', icon: Images, pair: false, tone: 'bg-blush text-berry-ink' },
];
</script>

<template>
    <Head :title="t('berdua.title')" />
    <PageHeader :title="t('berdua.title')" :lead="t('berdua.lead')" />

    <div v-if="hasPartner" class="mb-4 grid grid-cols-2 gap-3">
        <Link href="/momen" class="rounded-[1.5rem] bg-hero p-4 text-white shadow-soft transition hover:-translate-y-0.5">
            <p class="text-xs font-semibold uppercase tracking-[0.08em] opacity-90">{{ t('berdua.together') }}</p>
            <p class="mt-1 font-display text-[2.5rem] font-semibold leading-none">{{ facts.days ?? '–' }}</p>
            <p class="mt-1 text-xs opacity-90">{{ facts.days ? t('berdua.days') : t('berdua.setDate') }}</p>
        </Link>
        <div class="rounded-[1.5rem] bg-hero-lav p-4 text-white shadow-soft">
            <p class="flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] opacity-90"><Flame class="size-3.5" aria-hidden="true" />{{ t('berdua.streak') }}</p>
            <p class="mt-1 font-display text-[2.5rem] font-semibold leading-none">{{ facts.streak }}</p>
            <p class="mt-1 text-xs opacity-90">{{ t('berdua.streakHint') }}</p>
        </div>
    </div>
    <p v-if="facts.next" class="glass mb-5 flex items-center gap-2 rounded-[1.25rem] px-4 py-3 text-sm font-semibold text-plum">
        <Gift class="size-4 shrink-0 text-berry-ink" aria-hidden="true" />
        {{ facts.next.in === 0 ? t('berdua.nextToday', { title: facts.next.title }) : t('berdua.next', { title: facts.next.title, count: facts.next.in }) }}
    </p>

    <Link v-if="question" href="/pertanyaan" class="mb-5 block rounded-[1.5rem] bg-lavender p-5 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lift">
        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-lavender-ink"><MessageCircleHeart class="size-4" aria-hidden="true" />{{ t('question.today') }}</p>
        <p class="mt-2 font-display text-[1.375rem] font-semibold leading-snug text-plum">{{ question.text }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
            <span class="inline-flex items-center gap-1 rounded-full bg-surface/80 px-2.5 py-1" :class="question.answered ? 'text-sage-ink' : 'text-ink-muted'">
                <CheckCircle2 v-if="question.answered" class="size-3.5" aria-hidden="true" />{{ question.answered ? t('berdua.qYouDone') : t('berdua.qYouNot') }}
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-surface/80 px-2.5 py-1" :class="question.partnerAnswered ? 'text-sage-ink' : 'text-ink-muted'">
                <CheckCircle2 v-if="question.partnerAnswered" class="size-3.5" aria-hidden="true" />{{ question.partnerAnswered ? t('berdua.qPartnerDone', { name: partner?.firstName ?? '' }) : t('berdua.qPartnerNot', { name: partner?.firstName ?? '' }) }}
            </span>
            <span class="ml-auto inline-flex items-center gap-1 text-lavender-ink">{{ question.answered ? t('berdua.qOpen') : t('berdua.qAnswer') }}<ArrowRight class="size-3.5" aria-hidden="true" /></span>
        </div>
    </Link>

    <template v-if="hasPartner">
        <SectionTitle :title="t('nudge.title')" />
        <NudgeBar :partner-name="partner?.firstName ?? ''" :received="received" />
    </template>

    <SectionTitle :title="t('berdua.features')" />
    <ul class="grid grid-cols-2 gap-3">
        <li v-for="tile in tiles" :key="tile.key">
            <component
                :is="tile.pair && !hasCouple ? 'div' : Link"
                :href="tile.pair && !hasCouple ? undefined : tile.href"
                class="glass relative flex h-full flex-col rounded-[1.25rem] p-3.5"
                :class="tile.pair && !hasCouple ? 'opacity-60' : 'transition hover:-translate-y-0.5 hover:shadow-lift'"
            >
                <span class="grid size-[2.375rem] place-items-center rounded-[0.75rem]" :class="tile.tone"><component :is="tile.icon" class="size-[1.125rem]" aria-hidden="true" /></span>
                <p class="mt-2.5 text-[0.875rem] font-semibold text-plum">{{ t(`berdua.f.${tile.key}`) }}</p>
                <p class="mt-0.5 text-xs text-ink-muted">{{ tile.pair && !hasCouple ? t('berdua.needCouple') : t(`berdua.d.${tile.key}`) }}</p>
                <Lock v-if="tile.pair && !hasCouple" class="absolute right-3 top-3 size-4 text-ink-muted" aria-hidden="true" />
            </component>
        </li>
    </ul>
</template>
