<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import { moods } from '@/lib/mood';
import { t } from '@/i18n';
import { sealedVisit } from '@/lib/e2e';

const props = defineProps({
    current: { type: Object, default: null }, // { level, note } saved for today
    compact: { type: Boolean, default: false },
});

const level = ref(props.current?.level ?? null);
const note = ref(props.current?.note ?? '');
const share = ref(props.current?.share ?? 'full');
const company = ref(props.current?.company ?? false);
const saving = ref(false);
const error = ref(null);

watch(() => props.current, (value) => {
    level.value = value?.level ?? null;
    note.value = value?.note ?? '';
    share.value = value?.share ?? 'full';
    company.value = value?.company ?? false;
});

function save() {
    if (!level.value) return;
    sealedVisit('post', '/mood', { level: level.value, note: note.value, share: share.value, wants_company: company.value && share.value !== 'private' }, ['note'], {
        preserveScroll: true,
        onStart: () => ((saving.value = true), (error.value = null)),
        onError: (errors) => (error.value = errors.level ?? errors.note ?? null),
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <div>
        <div class="flex justify-between gap-1.5" role="radiogroup" :aria-label="t('mood.pick')">
            <button
                v-for="mood in moods"
                :key="mood.level"
                type="button"
                role="radio"
                :aria-checked="level === mood.level"
                class="group flex min-w-0 flex-1 flex-col items-center gap-1.5 rounded-lg px-0.5 py-1.5 transition duration-200 ease-gentle"
                @click="level = mood.level"
            >
                <span
                    class="grid size-[3.25rem] place-items-center rounded-[1.125rem] transition"
                    :class="[mood.tone, level === mood.level ? 'scale-105 ring-[3px] ring-berry ring-offset-[3px] ring-offset-surface' : 'group-hover:scale-105']"
                >
                    <component :is="mood.icon" class="size-6" aria-hidden="true" />
                </span>
                <span class="text-[0.6875rem] font-semibold leading-tight" :class="level === mood.level ? 'text-berry-ink' : 'text-ink-muted'">{{ t(`mood.levels.${mood.level}`) }}</span>
            </button>
        </div>
        <template v-if="level">
            <label class="mt-3 block text-sm font-semibold text-plum" :for="compact ? 'mood-note-c' : 'mood-note'">{{ t('mood.note') }}</label>
            <input
                :id="compact ? 'mood-note-c' : 'mood-note'"
                v-model="note"
                type="text"
                maxlength="140"
                :placeholder="t('mood.notePlaceholder')"
                class="mt-1.5 block h-12 w-full rounded-[0.875rem] border border-line bg-surface/90 px-4 text-base text-plum placeholder:text-ink-muted/70 focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush"
                @keydown.enter.prevent="save"
            />
            <fieldset v-if="!compact" class="mt-3">
                <legend class="text-sm font-semibold text-plum">{{ t('mood.shareTitle') }}</legend>
                <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                    <button v-for="option in ['full', 'level', 'private']" :key="option" type="button" class="min-h-11 rounded-[0.875rem] px-2 text-xs font-semibold ring-1 transition" :class="share === option ? 'bg-blush text-berry-ink ring-berry' : 'bg-surface text-ink-muted ring-line'" :aria-pressed="share === option" @click="share = option">{{ t(`mood.share.${option}`) }}</button>
                </div>
                <label v-if="share !== 'private'" class="mt-3 flex min-h-11 items-center gap-2 text-sm text-plum"><input v-model="company" type="checkbox" class="size-5 accent-[#b0446c]" />{{ t('mood.wantCompany') }}</label>
            </fieldset>
            <p v-if="error" class="mt-1 text-xs font-medium text-danger">{{ error }}</p>
            <Button class="mt-3" block :loading="saving" @click="save">{{ current ? t('mood.update') : t('mood.save') }}</Button>
        </template>
    </div>
</template>
