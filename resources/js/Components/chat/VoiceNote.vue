<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Pause, Play } from '@lucide/vue';
import { t } from '@/i18n';

const props = defineProps({
    src: { type: String, required: true },
    seconds: { type: Number, default: null },
    mine: { type: Boolean, default: false },
});

// Only one voice note plays at a time.
let current = null;

const audio = ref(null);
const playing = ref(false);
const position = ref(0);
const length = ref(props.seconds ?? 0);

const progress = computed(() => (length.value > 0 ? Math.min(100, (position.value / length.value) * 100) : 0));
const label = computed(() => format(playing.value || position.value ? position.value : length.value));

function format(total) {
    const s = Math.max(0, Math.round(total || 0));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function toggle() {
    const el = audio.value;
    if (!el) return;
    if (playing.value) {
        el.pause();
        return;
    }
    if (current && current !== el) current.pause();
    current = el;
    el.play().catch(() => (playing.value = false));
}

function onLoaded() {
    // WebM from a phone often reports Infinity until it has been played once.
    if (Number.isFinite(audio.value.duration) && audio.value.duration > 0) length.value = audio.value.duration;
}

function onTime() {
    position.value = audio.value.currentTime;
    if (Number.isFinite(audio.value.duration) && audio.value.duration > 0) length.value = audio.value.duration;
}

function onEnded() {
    playing.value = false;
    position.value = 0;
}

onBeforeUnmount(() => {
    if (current === audio.value) current = null;
    audio.value?.pause();
});
</script>

<template>
    <div class="flex w-56 max-w-full items-center gap-3 py-0.5" @click.stop>
        <button
            type="button"
            class="grid size-10 shrink-0 place-items-center rounded-full transition active:scale-95"
            :class="mine ? 'bg-white/25 text-white' : 'bg-blush text-berry-ink'"
            :aria-label="playing ? t('chat.voicePause') : t('chat.voicePlay')"
            @click="toggle"
        >
            <Pause v-if="playing" class="size-5" aria-hidden="true" />
            <Play v-else class="size-5" aria-hidden="true" />
        </button>
        <div class="min-w-0 flex-1">
            <div class="h-1.5 overflow-hidden rounded-full" :class="mine ? 'bg-white/30' : 'bg-plum/15'" role="progressbar" :aria-valuenow="Math.round(progress)" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full transition-[width] duration-200" :class="mine ? 'bg-white' : 'bg-berry'" :style="{ width: progress + '%' }" />
            </div>
            <p class="mt-1 text-xs tabular-nums" :class="mine ? 'text-white/85' : 'text-ink-muted'">{{ label }}</p>
        </div>
        <audio ref="audio" :src="src" preload="metadata" class="hidden" @loadedmetadata="onLoaded" @timeupdate="onTime" @play="playing = true" @pause="playing = false" @ended="onEnded" />
    </div>
</template>
