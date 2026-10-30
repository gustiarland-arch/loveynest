<script setup>
import { computed, ref } from 'vue';
import { emojiGroups } from '@/lib/emoji';
import { t } from '@/i18n';

defineEmits(['pick']);

const KEY = 'loveynest-recent-emoji';
function loadRecent() {
    try {
        const list = JSON.parse(localStorage.getItem(KEY) ?? '[]');
        return Array.isArray(list) ? list.filter((e) => typeof e === 'string').slice(0, 24) : [];
    } catch {
        return [];
    }
}

const recent = ref(loadRecent());
const active = ref(recent.value.length ? 'recent' : emojiGroups[0].key);

const groups = computed(() => [
    ...(recent.value.length ? [{ key: 'recent', label: t('chat.emojiRecent'), icon: '🕘', items: recent.value }] : []),
    ...emojiGroups,
]);
const current = computed(() => groups.value.find((g) => g.key === active.value) ?? groups.value[0]);

/** Called by the parent after an emoji was chosen, so the list is remembered on this device. */
function remember(emoji) {
    const next = [emoji, ...recent.value.filter((e) => e !== emoji)].slice(0, 24);
    recent.value = next;
    try {
        localStorage.setItem(KEY, JSON.stringify(next));
    } catch {
        // Private mode: the picker still works, it just forgets.
    }
}
defineExpose({ remember });
</script>

<template>
    <div class="glass mx-auto max-w-2xl rounded-[1.25rem] p-2" role="group" :aria-label="t('chat.emoji')">
        <div class="scrollbar-none mb-1 flex gap-1 overflow-x-auto" role="tablist">
            <button
                v-for="g in groups"
                :key="g.key"
                type="button"
                role="tab"
                :aria-selected="g.key === current.key"
                :aria-label="g.label"
                class="grid size-9 shrink-0 place-items-center rounded-full text-lg transition"
                :class="g.key === current.key ? 'bg-blush' : 'hover:bg-blush-soft'"
                @click="active = g.key"
            >{{ g.icon }}</button>
        </div>
        <p class="px-1 pb-1 text-[0.6875rem] font-semibold uppercase tracking-wide text-ink-muted">{{ current.label }}</p>
        <div class="grid max-h-48 grid-cols-8 gap-0.5 overflow-y-auto overscroll-contain">
            <button
                v-for="emoji in current.items"
                :key="emoji"
                type="button"
                class="grid aspect-square place-items-center rounded-lg text-[1.5rem] leading-none transition hover:bg-blush-soft active:scale-90"
                @click="$emit('pick', emoji)"
            >{{ emoji }}</button>
        </div>
    </div>
</template>
