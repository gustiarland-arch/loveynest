<script setup>
import { ref, watch } from 'vue';
import { Search, X } from '@lucide/vue';
import { http } from '@/lib/http';
import { e2e } from '@/lib/e2e';
import { dateKey, dayLabel, timeLabel } from '@/lib/dates';
import { t } from '@/i18n';

const open = defineModel('open', { type: Boolean, default: false });
const term = ref('');
const results = ref(null);
const busy = ref(false);
let timer = null;
// With end-to-end encryption the server cannot search locked text: recent messages are fetched once
// and searched here, after this device has opened them.
let local = null;

async function find(term) {
    if (!e2e.enabled) return (await http.get(`/chat/search?q=${encodeURIComponent(term)}`)).messages;
    local ??= (await http.get('/chat/search?all=1')).messages;
    const needle = term.toLocaleLowerCase('id');

    return local.filter((m) => m.body.toLocaleLowerCase('id').includes(needle)).slice(0, 30).map((m) => ({ ...m, body: m.body.length > 140 ? `${m.body.slice(0, 139)}…` : m.body }));
}

watch(term, (value) => {
    clearTimeout(timer);
    results.value = null;
    if (value.trim().length < 2) return;
    timer = setTimeout(async () => {
        busy.value = true;
        try {
            results.value = await find(value.trim());
        } catch {
            results.value = [];
        } finally {
            busy.value = false;
        }
    }, 300);
});

watch(open, (value) => {
    if (!value) {
        local = null;
        term.value = '';
        results.value = null;
    }
});
</script>

<template>
    <div v-if="open" class="absolute inset-x-0 top-full z-20 border-b border-[var(--glass-edge)] bg-[var(--glass-strong)] px-3 pb-3 pt-2 shadow-lift backdrop-blur-xl" role="search">
        <div class="mx-auto flex max-w-2xl items-center gap-2">
            <label for="chat-search" class="sr-only">{{ t('chat.search') }}</label>
            <span class="relative min-w-0 flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
                <input id="chat-search" v-model="term" type="search" autofocus maxlength="60" :placeholder="t('chat.searchPlaceholder')" class="h-11 w-full rounded-full border border-line bg-surface/80 pl-9 pr-4 text-base text-plum focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush" />
            </span>
            <button type="button" class="grid size-11 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('common.close')" @click="open = false"><X class="size-5" aria-hidden="true" /></button>
        </div>
        <ul v-if="results" class="mx-auto mt-2 max-h-72 max-w-2xl divide-y divide-line overflow-y-auto rounded-[1rem] bg-surface/70">
            <li v-if="!results.length" class="px-4 py-3 text-sm text-ink-muted">{{ t('chat.searchEmpty') }}</li>
            <li v-for="m in results" :key="m.id" class="px-4 py-2.5">
                <p class="text-[0.6875rem] font-semibold text-ink-muted">{{ m.mine ? t('mood.you') : t('chat.partner') }} · {{ dayLabel(dateKey(m.created_at), t) }} {{ timeLabel(m.created_at) }}</p>
                <p class="break-words text-sm text-plum">{{ m.body }}</p>
            </li>
        </ul>
        <p v-else-if="busy" class="mx-auto mt-2 max-w-2xl px-1 text-xs text-ink-muted" role="status">{{ t('common.loading') }}</p>
    </div>
</template>
