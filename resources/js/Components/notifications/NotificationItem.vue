<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { MessageCircleHeart, CalendarHeart, ListTodo, Sparkles, HeartHandshake } from '@lucide/vue';
import { dateKey, dayLabel, timeLabel } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    notification: { type: Object, required: true },
});

const icons = {
    chat_message: MessageCircleHeart,
    event_reminder: CalendarHeart,
    task_reminder: ListTodo,
    partner_activity: Sparkles,
    couple_update: HeartHandshake,
};
const icon = computed(() => icons[props.notification.type] ?? Sparkles);
const when = computed(() => `${dayLabel(dateKey(props.notification.created_at), t)} · ${timeLabel(props.notification.created_at)}`);
</script>

<template>
    <Link
        :href="`/notifications/${notification.id}/open`"
        class="flex items-start gap-3 rounded-lg p-3 transition hover:bg-blush-soft"
        :class="notification.read ? '' : 'glass'"
    >
        <span class="grid size-10 shrink-0 place-items-center rounded-md" :class="notification.read ? 'bg-cream text-ink-muted' : 'bg-blush text-rose-ink'">
            <component :is="icon" class="size-5" aria-hidden="true" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-plum" :class="notification.read ? 'font-medium' : ''">{{ notification.title }}</span>
            <span v-if="notification.body" class="mt-0.5 block truncate text-sm text-ink-muted">{{ notification.body }}</span>
            <span class="mt-1 block text-xs text-ink-muted">{{ when }}</span>
        </span>
        <span v-if="!notification.read" class="mt-2 size-2.5 shrink-0 rounded-full bg-rose-ink">
            <span class="sr-only">{{ $t('notifications.unread') }}</span>
        </span>
    </Link>
</template>
