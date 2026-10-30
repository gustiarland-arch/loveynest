<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BellRing, CheckCheck, Settings2 } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import NotificationItem from '@/Components/notifications/NotificationItem.vue';
import { t } from '@/i18n';

const props = defineProps({
    notifications: { type: Array, required: true },
    nextPage: { type: Number, default: null },
    page: { type: Number, default: 1 },
});

const unread = computed(() => usePage().props.badges?.notifications ?? 0);

function markAll() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('notifications.title')" />
    <PageHeader :title="t('notifications.title')" back="/dashboard">
        <template #action>
            <Link href="/pengaturan/notifikasi" class="grid size-11 place-items-center rounded-full text-ink-muted hover:bg-blush-soft hover:text-plum" :aria-label="t('notifications.settings')">
                <Settings2 class="size-5" aria-hidden="true" />
            </Link>
        </template>
    </PageHeader>

    <div v-if="unread" class="mb-3 flex justify-end">
        <Button variant="ghost" size="sm" @click="markAll"><CheckCheck class="size-4" aria-hidden="true" />{{ $t('notifications.markAll') }}</Button>
    </div>

    <EmptyState v-if="!notifications.length" :title="t('notifications.empty')" :body="t('notifications.emptyBody')" :icon="BellRing" class="mt-8" />

    <ul v-else class="space-y-2">
        <li v-for="notification in notifications" :key="notification.id">
            <NotificationItem :notification="notification" />
        </li>
    </ul>

    <div v-if="nextPage || page > 1" class="mt-6 flex justify-center gap-2">
        <Button v-if="page > 1" :href="`/notifications?page=${page - 1}`" variant="ghost" size="sm">{{ $t('common.back') }}</Button>
        <Button v-if="nextPage" :href="`/notifications?page=${nextPage}`" variant="secondary" size="sm">{{ $t('notifications.more') }}</Button>
    </div>
</template>
