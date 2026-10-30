<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { playSound } from '@/lib/sound';
import { Check, CalendarClock, Users, Repeat, Tag, ListChecks, ChevronDown } from '@lucide/vue';
import Badge from '@/Components/ui/Badge.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import { dueLabel } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    task: { type: Object, required: true },
    showSpace: { type: Boolean, default: false },
});

const emit = defineEmits(['edit']);

// Optimistic: the circle fills at once; the server response confirms it.
const pendingDone = ref(null);
const done = computed(() => pendingDone.value ?? props.task.status === 'done');
const subsOpen = ref(false);
const subsDone = computed(() => props.task.subtasks?.filter((s) => s.done).length ?? 0);
const subsTotal = computed(() => props.task.subtasks?.length ?? 0);

function toggleSub(sub) {
    router.patch(`/tasks/${props.task.id}/subtasks/${sub.id}`, { done: !sub.done }, { preserveScroll: true, preserveState: true });
}

const due = computed(() => dueLabel(props.task, t));

function toggle() {
    const next = !done.value;
    pendingDone.value = next;
    if (next) playSound('selesai');
    router.patch(
        `/tasks/${props.task.id}/status`,
        { status: next ? 'done' : 'todo' },
        { preserveScroll: true, onFinish: () => (pendingDone.value = null) },
    );
}
</script>

<template>
    <li class="glass group flex flex-wrap items-start gap-x-2 rounded-[1.25rem] px-2 py-2 transition" :class="done && 'opacity-80'">
        <button
            type="button"
            class="relative grid size-11 shrink-0 place-items-center rounded-full"
            :aria-label="done ? t('tasks.markOpen', { title: task.title }) : t('tasks.markDone', { title: task.title })"
            :aria-pressed="done"
            @click="toggle"
        >
            <span
                class="grid size-7 place-items-center rounded-full border-2 transition duration-300 ease-gentle"
                :class="done ? 'scale-100 border-sage-ink bg-sage-ink' : task.priority === 'high' || task.overdue ? 'border-berry' : 'border-rose group-hover:border-berry'"
            >
                <Check v-if="done" class="size-4 text-surface motion-safe:animate-[pop_300ms_var(--ease-gentle)]" stroke-width="3" aria-hidden="true" />
            </span>
            <span v-if="done" class="pointer-events-none absolute inset-1 rounded-full bg-rose/40 motion-safe:animate-[ping_600ms_ease-out_1]" aria-hidden="true" />
        </button>

        <button type="button" class="min-w-0 flex-1 basis-40 py-1 text-left" @click="emit('edit', task)">
            <p class="break-words font-semibold text-plum transition" :class="done && 'text-ink-muted line-through decoration-2'">
                {{ task.title }}
            </p>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <Badge v-if="task.priority === 'high' && !done" tone="rose">{{ t('tasks.priority.high') }}</Badge>
                <Badge v-if="task.status === 'in_progress' && !done" tone="lavender">{{ t('tasks.status.in_progress') }}</Badge>
                <Badge v-if="due && !done" :tone="task.overdue ? 'danger' : 'neutral'">
                    <CalendarClock class="size-3" aria-hidden="true" />
                    {{ task.overdue ? `${t('tasks.overdue')} · ` : '' }}{{ due }}
                </Badge>
                <Badge v-if="task.category" tone="neutral"><Tag class="size-3" aria-hidden="true" />{{ task.category }}</Badge>
                <Badge v-if="task.repeat && task.repeat !== 'none'" tone="neutral"><Repeat class="size-3" aria-hidden="true" />{{ t(`tasks.repeat.${task.repeat}`) }}</Badge>
                <Badge v-if="showSpace && task.space === 'ours'" tone="lavender"><Users class="size-3" aria-hidden="true" />{{ t('space.oursShort') }}</Badge>
                <span v-if="task.completed_by && done" class="text-xs text-ink-muted">{{ t('tasks.doneBy', { name: task.completed_by }) }}</span>
                <span v-else-if="task.created_by && !done" class="text-xs text-ink-muted">{{ t('tasks.by', { name: task.created_by }) }}</span>
            </div>
            <div v-if="subsTotal" class="mt-2 flex items-center gap-2" aria-hidden="true">
                <span class="h-1.5 w-24 overflow-hidden rounded-full bg-plum/[0.1]"><span class="block h-full rounded-full bg-brand transition-all" :style="{ width: `${(subsDone / subsTotal) * 100}%` }" /></span>
                <span class="text-xs text-ink-muted">{{ subsDone }}/{{ subsTotal }}</span>
            </div>
        </button>
        <button v-if="subsTotal" type="button" class="mt-1.5 grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-expanded="subsOpen" :aria-label="t('tasks.checklist')" @click="subsOpen = !subsOpen">
            <ChevronDown class="size-5 transition" :class="subsOpen && 'rotate-180'" aria-hidden="true" />
        </button>
        <Avatar
            v-if="task.assignee"
            :name="task.assignee.name"
            :src="task.assignee.avatarUrl"
            :tone="task.assignee.id === $page.props.auth.user.id ? 'blush' : 'lavender'"
            size="sm"
            class="mr-1 mt-2.5 !size-8 shrink-0"
            :title="t('tasks.assignedTo', { name: task.assignee.name })"
        />
        <ul v-if="subsOpen && subsTotal" class="basis-full space-y-1 pb-2 pl-14 pr-3">
            <li v-for="sub in task.subtasks" :key="sub.id">
                <label class="flex min-h-9 cursor-pointer items-center gap-2.5 text-sm" :class="sub.done ? 'text-ink-muted line-through' : 'text-plum'">
                    <input type="checkbox" :checked="sub.done" class="size-5 accent-[var(--color-berry)]" @change="toggleSub(sub)" />{{ sub.title }}
                </label>
            </li>
        </ul>
    </li>
</template>

<style>
@keyframes pop {
    0% {
        transform: scale(0.4);
    }
    70% {
        transform: scale(1.15);
    }
}
</style>
