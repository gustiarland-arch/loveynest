<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Users, Sprout, MessageCircleHeart, ArrowRight, UserPlus, Plus } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import TaskItem from '@/Components/tasks/TaskItem.vue';
import TaskFormDialog from '@/Components/tasks/TaskFormDialog.vue';
import EventItem from '@/Components/calendar/EventItem.vue';
import EventFormDialog from '@/Components/calendar/EventFormDialog.vue';
import { formatDate, formatDateTime, todayKey } from '@/lib/dates';
import { t } from '@/i18n';

const page = usePage();

const props = defineProps({
    space: { type: Object, required: true },
    tasks: { type: Array, required: true },
    openCount: { type: Number, required: true },
    events: { type: Array, required: true },
    activity: { type: Array, required: true },
    chat: { type: Object, default: null },
});

const taskForm = ref(false);
const editingTask = ref(null);
const eventForm = ref(false);
const editingEvent = ref(null);
const openTask = (task) => ((editingTask.value = task), (taskForm.value = true));
const openEvent = (event) => ((editingEvent.value = event), (eventForm.value = true));
const names = computed(() => props.space.members.map((m) => m.name.split(' ')[0]).join(' & '));
const since = computed(() => props.space.members.map((m) => m.since).sort()[props.space.members.length - 1] ?? null);
const isEmpty = computed(() => !props.tasks.length && !props.events.length && props.activity.length <= 1);
</script>

<template>
    <Head :title="t('ourSpace.title')" />
    <PageHeader :title="space.name || t('ourSpace.title')" :eyebrow="space.name ? t('ourSpace.title') : null" back="/dashboard">
        <template #action>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-lavender px-3 py-1 text-xs font-semibold text-lavender-ink">
                <Users class="size-3.5" aria-hidden="true" />{{ $t('dashboard.sharedNote') }}
            </span>
        </template>
    </PageHeader>

    <Card class="!p-4">
        <div class="flex items-center gap-3">
            <div class="flex shrink-0">
                <span v-for="(member, i) in space.members" :key="member.id" class="relative" :class="i && '-ml-3.5'">
                    <Avatar :name="member.name" :src="member.avatarUrl" :frame="member.avatarFrame" :tone="i ? 'lavender' : 'blush'" />
                    <span v-if="member.id !== page.props.auth.user.id && page.props.partnerOnline" class="absolute bottom-0 right-0 size-3 rounded-full bg-online ring-2 ring-surface" :title="$t('chat.online')" />
                </span>
                <span v-if="space.members.length < 2" class="-ml-3.5 grid size-11 place-items-center rounded-full border-2 border-dashed border-line-strong bg-surface/60 text-ink-muted" aria-hidden="true">
                    <UserPlus class="size-4" />
                </span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-plum">{{ names }}</p>
                <p v-if="space.members.length < 2" class="text-xs text-ink-muted">{{ $t('ourSpace.waiting') }}</p>
                <p v-else-if="since" class="text-xs text-ink-muted">{{ $t('ourSpace.since', { date: formatDate(since) }) }}</p>
            </div>
            <Button v-if="space.members.length < 2" href="/couple/invite" size="sm" variant="secondary">{{ $t('dashboard.invite') }}</Button>
        </div>
        <Link v-if="chat" href="/chat" class="mt-3 flex items-center gap-2 rounded-[0.875rem] bg-blush px-3 py-2.5 text-sm text-plum transition hover:brightness-[0.98]">
            <MessageCircleHeart class="size-4 shrink-0 text-berry-ink" aria-hidden="true" />
            <span v-if="chat.last" class="min-w-0 flex-1 truncate">{{ chat.last.mine ? $t('ourSpace.you') : '' }}{{ chat.last.body }}</span>
            <span v-else class="min-w-0 flex-1 truncate">{{ $t('ourSpace.chatEmpty') }}</span>
            <span v-if="chat.unread" class="min-w-6 rounded-full bg-berry px-2 py-0.5 text-center text-xs font-semibold text-white">{{ chat.unread }}</span>
            <ArrowRight v-else class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
        </Link>
    </Card>

    <template v-if="!isEmpty">
        <SectionTitle :title="`${t('ourSpace.tasksTitle')} · ${openCount}`" href="/tasks?space=ours" :link-label="t('tasks.seeAll')" />
        <ul v-if="tasks.length" class="space-y-2">
            <TaskItem v-for="task in tasks" :key="task.id" :task="task" @edit="openTask" />
        </ul>
        <p v-else class="text-sm text-ink-muted">{{ t('tasks.emptyOursBody') }}</p>

        <SectionTitle :title="t('ourSpace.eventsTitle')" href="/calendar?space=ours" :link-label="t('calendar.title')" />
        <ul v-if="events.length" class="space-y-2">
            <EventItem v-for="event in events" :key="event.id" :event="event" show-date @edit="openEvent" />
        </ul>
        <p v-else class="text-sm text-ink-muted">{{ t('calendar.emptyBody') }}</p>
    </template>
    <Card v-else :padded="false" class="mt-4">
        <EmptyState :title="t('ourSpace.emptyTitle')" :body="t('ourSpace.emptyBody')" :icon="Sprout" />
    </Card>

    <SectionTitle :title="t('activity.title')" />
    <ol v-if="activity.length" class="glass relative space-y-4 rounded-[1.5rem] border-l-0 py-4 pl-9 pr-4 before:absolute before:bottom-6 before:left-[1.05rem] before:top-6 before:w-0.5 before:bg-plum/10">
        <li v-for="item in activity" :key="item.id" class="relative">
            <span class="absolute -left-[1.6rem] top-1.5 size-2.5 rounded-full" :class="item.isMe ? 'bg-berry ring-4 ring-blush' : 'bg-lavender-ink ring-4 ring-lavender'" aria-hidden="true" />
            <p class="text-sm text-plum">
                <span class="font-semibold">{{ item.isMe ? t('activity.you') : item.actor }}</span>
                {{ t(`activity.${item.action.replace('.', '_')}`) }}
                <span v-if="item.title" class="font-semibold">“{{ item.title }}”</span>
            </p>
            <p class="text-xs text-ink-muted">{{ formatDateTime(item.at) }}</p>
        </li>
    </ol>
    <p v-else class="text-sm text-ink-muted">{{ t('activity.empty') }}</p>

    <Teleport to="body">
        <button
            type="button"
            class="fixed bottom-28 right-5 z-30 grid size-14 place-items-center rounded-[1.25rem] bg-plum text-surface shadow-lift transition active:scale-95 lg:bottom-10 lg:right-10"
            :aria-label="t('tasks.new')"
            @click="openTask(null)"
        >
            <Plus class="size-6" aria-hidden="true" />
        </button>
    </Teleport>

    <TaskFormDialog v-model:open="taskForm" :task="editingTask" default-space="ours" has-couple />
    <EventFormDialog v-model:open="eventForm" :event="editingEvent" :default-date="todayKey()" default-space="ours" has-couple />
</template>
