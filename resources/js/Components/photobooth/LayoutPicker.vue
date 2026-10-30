<script setup>
import { LAYOUTS } from '@/lib/booth/themes';
import { t } from '@/i18n';

// Five photo arrangements, each with a small diagram of where the photos go.
const model = defineModel({ type: String, required: true });

// rem size of one square in the diagram, so each diagram fills about the same box
const cell = { strip: 0.5, trio: 0.72, duo: 0.95, grid: 0.82, collage: 0.58 };
</script>

<template>
    <div class="grid grid-cols-5 gap-1.5 sm:gap-2" role="radiogroup" :aria-label="t('booth.layoutTitle')">
        <button v-for="(cfg, key) in LAYOUTS" :key="key" type="button" role="radio" :aria-checked="model === key"
            class="flex min-h-[5.5rem] flex-col items-center justify-between gap-1.5 rounded-[1rem] px-1 py-2 transition active:scale-[0.97]"
            :class="model === key ? 'bg-blush ring-2 ring-berry' : 'glass'" @click="model = key">
            <span class="grid size-11 place-items-center" aria-hidden="true">
                <span class="grid gap-[3px]" :style="{ gridTemplateColumns: `repeat(${cfg.cols}, ${cell[key]}rem)`, gridAutoRows: `${cell[key]}rem` }">
                    <span v-for="i in cfg.count" :key="i" class="rounded-[3px]" :class="model === key ? 'bg-berry' : 'bg-rose'" />
                </span>
            </span>
            <span class="text-center text-[0.6875rem] font-semibold leading-tight text-plum">{{ t(`booth.layouts.${key}`) }}<span class="block font-normal text-ink-muted">{{ t('booth.photoCount', { n: cfg.count }) }}</span></span>
        </button>
    </div>
</template>
