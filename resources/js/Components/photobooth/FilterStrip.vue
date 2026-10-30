<script setup>
import { PRESETS } from '@/lib/booth/engine';
import { t } from '@/i18n';

// Row of filter presets. The dot is a colour hint, the camera and editor show the real result.
const model = defineModel({ type: String, required: true });

const dots = {
    original: 'linear-gradient(135deg,#f5f5f7,#d9d9de)',
    korean: 'linear-gradient(135deg,#fff0f4,#ffd1df)',
    warm: 'linear-gradient(135deg,#ffe2b8,#ff9d6b)',
    dreamy: 'linear-gradient(135deg,#f0e6ff,#ffd6ec)',
    pastel: 'linear-gradient(135deg,#d9f0ff,#ffe0f0)',
    y2k: 'linear-gradient(135deg,#ff5fb0,#52e0ff)',
    vintage: 'linear-gradient(135deg,#d9b678,#4d6f6a)',
    bw: 'linear-gradient(135deg,#ededed,#3a3a3a)',
};
</script>

<template>
    <div class="scrollbar-none -mx-1 flex gap-2 overflow-x-auto px-1 py-1" role="radiogroup" :aria-label="t('booth.filter')">
        <button v-for="p in PRESETS" :key="p.key" type="button" role="radio" :aria-checked="model === p.key"
            class="flex w-[4.5rem] shrink-0 flex-col items-center gap-1.5 rounded-[1rem] px-1 py-2 transition active:scale-[0.97]"
            :class="model === p.key ? 'bg-blush ring-2 ring-berry' : 'glass'" @click="model = p.key">
            <span class="size-10 rounded-full ring-1 ring-black/10" :style="{ background: dots[p.key] }" aria-hidden="true" />
            <span class="text-center text-[0.6875rem] font-semibold leading-tight text-plum">{{ t(`booth.filters.${p.key}`) }}</span>
        </button>
    </div>
</template>
