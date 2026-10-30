<script setup>
import { Link } from '@inertiajs/vue3';
import { CalendarHeart, Images, Mail, MailOpen, MessageCircleHeart } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import MoodBadge from '@/Components/mood/MoodBadge.vue';
import MoodPicker from '@/Components/mood/MoodPicker.vue';
import { eventTimeLabel, dayLabel, dateKey } from '@/lib/dates';
import { t } from '@/i18n';

// The small "at a glance" cards shared by the dashboard and the /widget page.
defineProps({
    moods: { type: Object, required: true },
    partner: { type: Object, default: null },
    photo: { type: Object, default: null },
    letter: { type: Object, default: null },
    event: { type: Object, default: null },
    unreadChat: { type: Number, default: null }, // null = not shown here
    showEvent: { type: Boolean, default: false },
});
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <Card class="col-span-2">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ t('widget.mood') }}</h2>
                <Link v-if="moods.mine" href="/mood" class="text-sm font-semibold text-rose-ink underline-offset-4 hover:underline">{{ t('memories.mood') }}</Link>
            </div>
            <MoodPicker v-if="!moods.mine" :current="null" compact />
            <div v-else class="flex items-center gap-4">
                <div class="flex items-center gap-2.5">
                    <MoodBadge :level="moods.mine.level" />
                    <div><p class="text-xs text-ink-muted">{{ t('mood.you') }}</p><p class="text-sm font-semibold text-plum">{{ t(`mood.levels.${moods.mine.level}`) }}</p></div>
                </div>
                <div v-if="partner" class="flex items-center gap-2.5">
                    <MoodBadge :level="moods.partner?.level ?? null" />
                    <div><p class="text-xs text-ink-muted">{{ partner.firstName }}</p><p class="text-sm font-semibold text-plum">{{ moods.partner ? t(`mood.levels.${moods.partner.level}`) : t('mood.notYet') }}</p></div>
                </div>
            </div>
            <p v-if="moods.mine?.note" class="mt-3 text-sm text-ink-muted">“{{ moods.mine.note }}”</p>
        </Card>

        <Link :href="photo ? `/albums/${photo.album_id}` : '/albums?space=ours'" class="group overflow-hidden glass rounded-[1.5rem] transition hover:-translate-y-0.5 hover:shadow-lift">
            <div class="aspect-[4/3] w-full bg-gradient-to-br from-blush to-lavender/70">
                <img v-if="photo" :src="`/photos/${photo.id}/thumb`" alt="" loading="lazy" class="size-full object-cover" />
                <div v-else class="grid size-full place-items-center text-rose-ink/60"><Images class="size-8" aria-hidden="true" /></div>
            </div>
            <p class="px-3 py-2.5 text-sm font-semibold text-plum">{{ photo ? t('widget.photo') : t('widget.noPhoto') }}</p>
        </Link>

        <Link :href="letter ? '/journal?tab=letters' : '/journal'" class="flex flex-col rounded-[1.5rem] bg-lavender/70 p-4 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lift">
            <span class="grid size-10 place-items-center rounded-md bg-ivory text-lavender-ink"><component :is="letter?.unread ? Mail : MailOpen" class="size-5" aria-hidden="true" /></span>
            <p class="mt-3 text-xs font-semibold text-lavender-ink">{{ t('widget.letter') }}</p>
            <p class="mt-0.5 line-clamp-2 break-words text-sm font-semibold text-plum">{{ letter ? letter.title : t('widget.noLetter') }}</p>
            <p v-if="letter" class="mt-auto pt-2 text-xs text-ink-muted">{{ letter.unread ? t('journal.unread') : letter.from ? t('journal.from', { name: letter.from }) : t('journal.sent') }}</p>
        </Link>

        <Link v-if="showEvent" href="/calendar" class="col-span-2 flex items-center gap-3 glass rounded-[1.25rem] p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
            <span class="grid size-11 shrink-0 place-items-center rounded-md bg-blush text-rose-ink"><CalendarHeart class="size-5" aria-hidden="true" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold text-rose-ink">{{ t('widget.nextEvent') }}</span>
                <span class="block truncate font-semibold text-plum">{{ event ? event.title : t('widget.noEvent') }}</span>
                <span v-if="event" class="block text-xs text-ink-muted">{{ dayLabel(dateKey(event.starts_at), t) }} · {{ eventTimeLabel(event, t) }}</span>
            </span>
        </Link>

        <Link v-if="unreadChat !== null" href="/chat" class="col-span-2 flex items-center gap-3 glass rounded-[1.25rem] p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
            <span class="grid size-11 shrink-0 place-items-center rounded-md bg-lavender text-lavender-ink"><MessageCircleHeart class="size-5" aria-hidden="true" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold text-lavender-ink">{{ t('widget.chat') }}</span>
                <span class="block font-semibold text-plum">{{ unreadChat ? t('widget.unread', { count: unreadChat }) : t('widget.noUnread') }}</span>
            </span>
        </Link>
    </div>
</template>
