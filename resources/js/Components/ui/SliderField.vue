<script setup>
import { computed, useId } from 'vue';
import { RotateCcw } from '@lucide/vue';
import { t } from '@/i18n';

// Range slider with a visible value and a reset to its default. The touch row is at least 44 px tall.
const model = defineModel({ type: Number, required: true });

const props = defineProps({
    label: { type: String, required: true },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 100 },
    step: { type: Number, default: 1 },
    reset: { type: Number, default: 0 }, // value the reset button restores
    unit: { type: String, default: '' },
});

const id = useId();
const changed = computed(() => model.value !== props.reset);
// Fill of the track, so the position reads at a glance.
const fill = computed(() => ((model.value - props.min) / (props.max - props.min)) * 100);
const origin = computed(() => ((props.reset - props.min) / (props.max - props.min)) * 100);
const left = computed(() => Math.min(fill.value, origin.value));
const width = computed(() => Math.abs(fill.value - origin.value));
</script>

<template>
    <div class="min-h-11">
        <div class="flex items-center justify-between gap-2">
            <label :for="id" class="text-sm font-semibold text-plum">{{ label }}</label>
            <span class="flex items-center gap-1">
                <span class="min-w-[3ch] text-right text-sm tabular-nums text-ink-muted">{{ model > 0 && min < 0 ? '+' : '' }}{{ model }}{{ unit }}</span>
                <button type="button" class="grid size-7 place-items-center rounded-full text-ink-muted transition hover:bg-fill disabled:pointer-events-none disabled:opacity-30" :disabled="!changed" :aria-label="t('booth.resetField', { name: label })" @click="model = reset">
                    <RotateCcw class="size-3.5" aria-hidden="true" />
                </button>
            </span>
        </div>
        <div class="relative flex h-8 items-center">
            <span class="pointer-events-none absolute inset-x-0 h-1.5 rounded-full bg-fill" aria-hidden="true" />
            <span class="pointer-events-none absolute h-1.5 rounded-full bg-berry" :style="{ left: `${left}%`, width: `${width}%` }" aria-hidden="true" />
            <input :id="id" v-model.number="model" type="range" :min="min" :max="max" :step="step" class="booth-range relative w-full" />
        </div>
    </div>
</template>

<style scoped>
.booth-range {
    appearance: none;
    -webkit-appearance: none;
    background: transparent;
    height: 2rem;
    margin: 0;
    touch-action: pan-y;
}
.booth-range::-webkit-slider-runnable-track { background: transparent; height: 2rem; }
.booth-range::-moz-range-track { background: transparent; height: 2rem; }
.booth-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 1.625rem;
    height: 1.625rem;
    margin-top: 0.1875rem;
    border-radius: 9999px;
    background: #fff;
    border: 0;
    box-shadow: 0 3px 8px rgb(0 0 0 / 0.2), 0 0 0 1px rgb(0 0 0 / 0.06);
}
.booth-range::-moz-range-thumb {
    width: 1.625rem;
    height: 1.625rem;
    border-radius: 9999px;
    background: #fff;
    border: 0;
    box-shadow: 0 3px 8px rgb(0 0 0 / 0.2), 0 0 0 1px rgb(0 0 0 / 0.06);
}
.booth-range:focus-visible { outline: none; }
.booth-range:focus-visible::-webkit-slider-thumb { box-shadow: 0 0 0 4px var(--color-blush), 0 3px 8px rgb(0 0 0 / 0.2); }
.booth-range:focus-visible::-moz-range-thumb { box-shadow: 0 0 0 4px var(--color-blush), 0 3px 8px rgb(0 0 0 / 0.2); }
</style>
