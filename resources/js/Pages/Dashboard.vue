<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LockKeyhole, Users, ArrowRight, HeartHandshake, Bell, MessageCircleHeart, UserPlus, Images, BookHeart, Sun, Flame, Sparkles, Gift, CircleHelp, SlidersHorizontal, ChevronUp, ChevronDown, Eye, EyeOff, Quote, Camera, Gamepad2 } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import HeartMark from '@/Components/brand/HeartMark.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import TaskItem from '@/Components/tasks/TaskItem.vue';
import TaskFormDialog from '@/Components/tasks/TaskFormDialog.vue';
import EventItem from '@/Components/calendar/EventItem.vue';
import EventFormDialog from '@/Components/calendar/EventFormDialog.vue';
import NudgeBar from '@/Components/memories/NudgeBar.vue';
import CoupleHero from '@/Components/memories/CoupleHero.vue';
import WidgetGrid from '@/Components/memories/WidgetGrid.vue';
import NotificationItem from '@/Components/notifications/NotificationItem.vue';
import { currentHour, formatDate, todayKey } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    couple: { type: Object, default: null },
    today: { type: Array, required: true },
    events: { type: Array, required: true },
    counts: { type: Object, required: true },
    notifications: { type: Array, default: () => [] },
    facts: { type: Object, required: true },
    nudges: { type: Array, default: () => [] },
    moods: { type: Object, required: true },
    photo: { type: Object, default: null },
    letter: { type: Object, default: null },
    quote: { type: String, default: '' },
    prefs: { type: Object, default: () => ({ order: [], hidden: [] }) },
    widgetKeys: { type: Array, default: () => [] },
});

const taskForm = ref(false);
const editingTask = ref(null);
const eventForm = ref(false);
const editingEvent = ref(null);
const openTask = (task) => ((editingTask.value = task), (taskForm.value = true));
const openEvent = (event) => ((editingEvent.value = event), (eventForm.value = true));

const page = usePage();
const me = computed(() => page.props.auth.user);
const firstName = computed(() => me.value.name.split(' ')[0]);

const greeting = computed(() => {
    const hour = currentHour();
    if (hour < 11) return t('dashboard.morning');
    if (hour < 15) return t('dashboard.afternoon');
    if (hour < 19) return t('dashboard.evening');
    return t('dashboard.night');
});

const partner = computed(() => props.couple?.partner ?? null);

// Home cards can be hidden or reordered; the choice is saved on the account.
const hiddenSet = computed(() => new Set(props.prefs.hidden));
const show = (key) => !hiddenSet.value.has(key);
const ord = (key) => props.prefs.order.indexOf(key);
const customizing = ref(false);
const draftOrder = ref([]);
const draftHidden = ref([]);
function openCustomize() {
    draftOrder.value = [...props.prefs.order];
    draftHidden.value = [...props.prefs.hidden];
    customizing.value = true;
}
function move(i, delta) {
    const list = draftOrder.value;
    const j = i + delta;
    if (j < 0 || j >= list.length) return;
    [list[i], list[j]] = [list[j], list[i]];
}
function toggleHidden(key) {
    draftHidden.value = draftHidden.value.includes(key) ? draftHidden.value.filter((k) => k !== key) : [...draftHidden.value, key];
}
function saveCustomize() {
    router.patch('/settings/dashboard', { order: draftOrder.value, hidden: draftHidden.value }, { preserveScroll: true, onSuccess: () => (customizing.value = false) });
}
const unreadNotifications = computed(() => page.props.badges?.notifications ?? 0);
const unreadChat = computed(() => page.props.badges?.chat ?? 0);
</script>

<template>
    <Head :title="t('nav.home')" />

    <header class="mb-5 flex items-center gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-sm text-ink-muted">{{ greeting }},</p>
            <h1 class="truncate text-[1.875rem] font-semibold leading-tight text-plum">{{ firstName }}</h1>
        </div>
        <button type="button" class="glass grid size-11 shrink-0 place-items-center rounded-full text-plum hover:bg-petal" :aria-label="t('dashboard.customize')" @click="openCustomize"><SlidersHorizontal class="size-5" aria-hidden="true" /></button>
        <Link
            href="/notifications"
            class="glass relative grid size-11 shrink-0 place-items-center rounded-full text-plum hover:bg-petal"
            :aria-label="unreadNotifications ? `${t('notifications.open')} (${unreadNotifications})` : t('notifications.open')"
        >
            <Bell class="size-5" aria-hidden="true" />
            <span v-if="unreadNotifications" class="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-berry px-1 text-center text-[0.6875rem] font-semibold leading-5 text-white" aria-hidden="true">
                {{ unreadNotifications > 9 ? '9+' : unreadNotifications }}
            </span>
        </Link>
    </header>

    <!-- The two of you: days together, streak and the next date to look forward to. -->
    <CoupleHero v-if="partner" :me="me" :partner="partner" :facts="facts" :online="!!page.props.partnerOnline" />

    <section v-else class="glass relative mb-5 overflow-hidden rounded-[1.75rem] px-5 py-6" :aria-label="t('dashboard.couple')">
        <div class="flex items-center justify-center gap-4">
            <div class="flex w-28 flex-col items-center gap-2 text-center">
                <Avatar :name="me.name" :src="me.avatarUrl" :frame="me.avatarFrame" size="xl" />
                <p class="max-w-full truncate text-sm font-semibold text-plum">{{ firstName }}</p>
            </div>
            <HeartMark :size="30" class="mb-6 shrink-0" />
            <div class="flex w-28 flex-col items-center gap-2 text-center">
                <span class="grid size-20 place-items-center rounded-full border-2 border-dashed border-berry/40 bg-surface/50 text-berry-ink" aria-hidden="true"><UserPlus class="size-7" /></span>
                <p class="max-w-full truncate text-sm font-semibold text-plum">{{ t('dashboard.waiting') }}</p>
            </div>
        </div>
    </section>

    <Link
        v-if="unreadChat && partner"
        href="/chat"
        class="mb-4 flex items-center gap-3 rounded-[1.25rem] bg-brand px-4 py-3 text-white shadow-glow transition hover:brightness-105"
    >
        <MessageCircleHeart class="size-5 shrink-0" aria-hidden="true" />
        <span class="flex-1 text-sm font-semibold">{{ t('dashboard.unreadChat', { count: unreadChat, name: partner.firstName }) }}</span>
        <ArrowRight class="size-4" aria-hidden="true" />
    </Link>

    <ul v-if="notifications.length" class="mb-5 space-y-2" :aria-label="t('notifications.title')">
        <li v-for="notification in notifications" :key="notification.id"><NotificationItem :notification="notification" /></li>
    </ul>

    <Card v-if="!couple" tone="blush" class="mt-6">
        <div class="flex gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-md bg-ivory"><HeartHandshake class="size-5 text-rose-ink" aria-hidden="true" /></span>
            <div>
                <h2 class="text-lg font-semibold">{{ $t('dashboard.noSpaceTitle') }}</h2>
                <p class="mt-1 text-sm text-ink-muted">{{ $t('dashboard.noSpaceBody') }}</p>
                <Button href="/onboarding" size="sm" class="mt-4">{{ $t('dashboard.connect') }}</Button>
            </div>
        </div>
    </Card>

    <Card v-else-if="!partner" tone="blush" class="mt-6">
        <div class="flex gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-md bg-ivory"><HeartHandshake class="size-5 text-rose-ink" aria-hidden="true" /></span>
            <div>
                <h2 class="text-lg font-semibold">{{ $t('dashboard.noPartnerTitle') }}</h2>
                <p class="mt-1 text-sm text-ink-muted">{{ $t('dashboard.noPartnerBody') }}</p>
                <Button href="/couple/invite" size="sm" class="mt-4">{{ $t('dashboard.invite') }}</Button>
            </div>
        </div>
    </Card>

    <div class="flex flex-col">
        <figure v-if="show('quote') && quote" :style="{ order: ord('quote') }" class="glass my-4 flex gap-3 rounded-[1.25rem] p-4">
            <Quote class="mt-0.5 size-5 shrink-0 text-rose-ink" aria-hidden="true" />
            <div class="min-w-0"><figcaption class="text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ t('dashboard.quoteTitle') }}</figcaption><blockquote class="mt-1 break-words font-display text-[1.0625rem] font-semibold leading-snug text-plum">{{ quote }}</blockquote></div>
        </figure>
        <div v-if="show('nudge')" :style="{ order: ord('nudge') }">
    <template v-if="partner">
        <h2 class="mb-2.5 mt-1 text-lg font-semibold text-plum">{{ t('dashboard.sendFeeling', { name: partner.firstName }) }}</h2>
        <NudgeBar :partner-name="partner.firstName" :received="nudges" />
    </template>
        </div>
        <div v-if="show('spaces')" :style="{ order: ord('spaces') }">
    <div class="mt-4 grid grid-cols-2 gap-3">
        <Link href="/my-space" class="glass group rounded-[1.5rem] p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
            <span class="grid size-[2.375rem] place-items-center rounded-[0.75rem] bg-lavender text-lavender-ink"><LockKeyhole class="size-[1.125rem]" aria-hidden="true" /></span>
            <p class="mt-3 font-display text-lg font-semibold text-plum">{{ $t('nav.mySpace') }}</p>
            <p class="mt-0.5 text-xs text-ink-muted">{{ counts.mine ? $t('dashboard.openTasks', { count: counts.mine }) : $t('dashboard.mySpaceBody') }}</p>
        </Link>
        <Link :href="couple ? '/our-space' : '/onboarding'" class="glass group rounded-[1.5rem] p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
            <span class="grid size-[2.375rem] place-items-center rounded-[0.75rem] bg-blush text-berry-ink"><Users class="size-[1.125rem]" aria-hidden="true" /></span>
            <p class="mt-3 font-display text-lg font-semibold text-plum">{{ $t('nav.ourSpace') }}</p>
            <p class="mt-0.5 text-xs text-ink-muted">
                {{
                    counts.ours
                        ? $t('dashboard.openTasks', { count: counts.ours })
                        : partner
                          ? $t('dashboard.withPartner', { name: partner.firstName })
                          : $t('dashboard.ourSpaceBody')
                }}
            </p>
        </Link>
    </div>
        </div>
        <div v-if="show('question')" :style="{ order: ord('question') }">
    <Link v-if="partner" href="/pertanyaan" class="glass mt-3 flex items-center gap-3 rounded-[1.25rem] p-3.5 text-sm font-semibold text-plum transition hover:-translate-y-0.5">
        <span class="grid size-[2.375rem] shrink-0 place-items-center rounded-[0.75rem] bg-lavender text-lavender-ink"><CircleHelp class="size-[1.125rem]" aria-hidden="true" /></span>
        {{ t('berdua.f.question') }}<ArrowRight class="ml-auto size-4 text-ink-muted" aria-hidden="true" />
    </Link>
        </div>
        <div v-if="show('memories')" :style="{ order: ord('memories') }">
    <SectionTitle :title="t('memories.title')" href="/widget" :link-label="t('memories.widget')" />
    <WidgetGrid :moods="moods" :partner="partner" :photo="photo" :letter="letter" />
    <ul class="mt-3 grid grid-cols-3 gap-2 min-[480px]:grid-cols-6">
        <li v-for="link in [
            { href: '/berdua', label: t('memories.more'), icon: Sparkles },
            { href: '/albums', label: t('memories.albums'), icon: Images },
            { href: '/photobooth', label: t('memories.photobooth'), icon: Camera },
            { href: '/journal', label: t('memories.journal'), icon: BookHeart },
            { href: '/mood', label: t('memories.mood'), icon: Sun },
            { href: '/arena', label: t('memories.arena'), icon: Gamepad2 },
        ]" :key="link.href">
            <Link :href="link.href" class="glass flex h-full flex-col items-center gap-1.5 rounded-[1.25rem] px-2 py-3.5 text-center text-xs font-semibold text-plum transition hover:-translate-y-0.5 hover:shadow-lift">
                <component :is="link.icon" class="size-5 text-rose-ink" aria-hidden="true" />{{ link.label }}
            </Link>
        </li>
    </ul>
        </div>
        <div v-if="show('today')" :style="{ order: ord('today') }">
    <SectionTitle :title="t('dashboard.todayTitle')" href="/tasks" :link-label="t('tasks.seeAll')" />
    <ul v-if="today.length" class="space-y-2">
        <TaskItem v-for="task in today" :key="task.id" :task="task" show-space @edit="openTask" />
    </ul>
    <p v-else class="rounded-[1.25rem] bg-sage px-4 py-4 text-sm text-sage-ink">{{ t('dashboard.todayEmpty') }}</p>
        </div>
        <div v-if="show('events')" :style="{ order: ord('events') }">
    <SectionTitle :title="t('dashboard.nextTitle')" href="/calendar" :link-label="t('calendar.title')" />
    <ul v-if="events.length" class="space-y-2">
        <EventItem v-for="event in events" :key="event.id" :event="event" show-date @edit="openEvent" />
    </ul>
    <p v-else class="glass rounded-[1.25rem] px-4 py-4 text-sm text-ink-muted">{{ t('dashboard.nextEmpty') }}</p>
        </div>
    </div>

    <Dialog v-model:open="customizing" :title="t('dashboard.customizeTitle')">
        <p class="mb-3 text-sm text-ink-muted">{{ t('dashboard.customizeBody') }}</p>
        <ul class="space-y-2">
            <li v-for="(key, i) in draftOrder" :key="key" class="flex items-center gap-2 rounded-[1rem] bg-surface px-3 py-2 ring-1 ring-line">
                <span class="min-w-0 flex-1 truncate text-sm font-semibold" :class="draftHidden.includes(key) ? 'text-ink-muted line-through' : 'text-plum'">{{ t(`dashboard.w.${key}`) }}</span>
                <button type="button" class="grid size-9 place-items-center rounded-full text-plum hover:bg-blush-soft disabled:opacity-30" :disabled="i === 0" :aria-label="t('dashboard.moveUp')" @click="move(i, -1)"><ChevronUp class="size-4" aria-hidden="true" /></button>
                <button type="button" class="grid size-9 place-items-center rounded-full text-plum hover:bg-blush-soft disabled:opacity-30" :disabled="i === draftOrder.length - 1" :aria-label="t('dashboard.moveDown')" @click="move(i, 1)"><ChevronDown class="size-4" aria-hidden="true" /></button>
                <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-blush-soft" :class="draftHidden.includes(key) ? 'text-ink-muted' : 'text-berry'" :aria-pressed="!draftHidden.includes(key)" :aria-label="draftHidden.includes(key) ? t('dashboard.showCard') : t('dashboard.hideCard')" @click="toggleHidden(key)"><component :is="draftHidden.includes(key) ? EyeOff : Eye" class="size-4" aria-hidden="true" /></button>
            </li>
        </ul>
        <template #footer><Button variant="secondary" @click="customizing = false">{{ t('common.cancel') }}</Button><Button @click="saveCustomize">{{ t('common.save') }}</Button></template>
    </Dialog>

    <TaskFormDialog v-model:open="taskForm" :task="editingTask" :has-couple="!!couple" />
    <EventFormDialog v-model:open="eventForm" :event="editingEvent" :default-date="todayKey()" :has-couple="!!couple" />
</template>
