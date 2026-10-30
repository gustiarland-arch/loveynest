<script setup>
import { computed, ref, useId, watch } from 'vue';
import { Clock } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/i18n';

// Time picker as a bottom sheet: hour grid + minute grid. The value is 'HH:MM' (24 hour).
const model = defineModel({ type: String, default: '' });

defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
});

const id = useId();
const open = ref(false);
const hour = ref('09');
const minute = ref('00');

const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));
// Keep an odd minute that was already saved selectable.
const minuteChoices = computed(() => (minutes.includes(minute.value) ? minutes : [...minutes, minute.value].sort()));

watch(open, (isOpen) => {
    if (!isOpen) return;
    const [h, m] = (model.value || '09:00').split(':');
    hour.value = h;
    minute.value = m;
});

function save() {
    model.value = `${hour.value}:${minute.value}`;
    open.value = false;
}

function clear() {
    model.value = '';
    open.value = false;
}

const chip = (active) => (active ? 'bg-berry font-bold text-white' : 'bg-petal text-plum hover:bg-blush');
</script>

<template>
    <div class="space-y-1.5">
        <label :for="id" class="block text-sm font-semibold text-plum">{{ label }}</label>
        <button
            :id="id"
            type="button"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            class="flex h-12 w-full items-center justify-between gap-2 rounded-md border bg-ivory px-4 text-left text-base transition focus:border-rose-ink focus:outline-none focus:ring-4 focus:ring-blush disabled:opacity-50"
            :class="[error ? 'border-danger' : 'border-line-strong', model ? 'text-plum' : 'text-ink-muted/70']"
            @click="open = true"
        >
            <span>{{ model ? model.replace(':', '.') : t('picker.chooseTime') }}</span>
            <Clock class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
        </button>
        <p v-if="error" class="text-xs font-medium text-danger">{{ error }}</p>

        <Dialog v-model:open="open" :title="label">
            <p class="mb-2 text-sm font-semibold text-ink-muted">{{ t('picker.hour') }}</p>
            <div class="grid grid-cols-6 gap-2" role="group" :aria-label="t('picker.hour')">
                <button v-for="h in hours" :key="h" type="button" :aria-pressed="hour === h" class="h-11 rounded-xl text-base transition" :class="chip(hour === h)" @click="hour = h">{{ h }}</button>
            </div>
            <p class="mb-2 mt-4 text-sm font-semibold text-ink-muted">{{ t('picker.minute') }}</p>
            <div class="grid grid-cols-6 gap-2" role="group" :aria-label="t('picker.minute')">
                <button v-for="m in minuteChoices" :key="m" type="button" :aria-pressed="minute === m" class="h-11 rounded-xl text-base transition" :class="chip(minute === m)" @click="minute = m">{{ m }}</button>
            </div>
            <template #footer>
                <Button v-if="clearable && model" variant="ghost" @click="clear">{{ t('picker.clear') }}</Button>
                <Button @click="save">{{ t('picker.set', { time: `${hour}.${minute}` }) }}</Button>
            </template>
        </Dialog>
    </div>
</template>
