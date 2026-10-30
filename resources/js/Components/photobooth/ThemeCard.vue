<script setup>
import { ref, watch, onMounted } from 'vue';
import { Check } from '@lucide/vue';
import { themeByKey, LAYOUTS } from '@/lib/booth/themes';
import { composeBooth, placeholderCell, ensureFonts } from '@/lib/booth/draw';
import { t } from '@/i18n';

// A theme in the gallery: a small drawing of the real template, made by the same code that makes the final picture.
const props = defineProps({
    themeKey: { type: String, required: true },
    selected: { type: Boolean, default: false },
    layout: { type: String, default: null }, // preview in this layout instead of the theme's own
    names: { type: Array, default: () => ['Alya', 'Raka'] },
});
defineEmits(['select']);

const url = ref(null);
async function draw() {
    const theme = themeByKey(props.themeKey);
    const layout = props.layout ?? theme.layout;
    const key = `${props.themeKey}|${layout}`;
    if (cache.has(key)) {
        url.value = cache.get(key);
        return;
    }
    await ensureFonts();
    const count = LAYOUTS[layout].count;
    const cells = Array.from({ length: count }, (_, i) => placeholderCell(i, 200));
    const canvas = composeBooth({ themeKey: props.themeKey, layout, cells, names: props.names, date: new Date(), maxEdge: 520, stickers: { seed: 3 } });
    const data = canvas.toDataURL('image/jpeg', 0.82);
    cache.set(key, data);
    url.value = data;
}

onMounted(draw);
watch(() => [props.themeKey, props.layout], draw);
</script>

<script>
// Shared by every card, so a theme is drawn once per page visit.
const cache = new Map();
</script>

<template>
    <button type="button" class="group relative flex w-full flex-col gap-2 rounded-[1.25rem] p-2 text-left transition active:scale-[0.98]" :class="selected ? 'bg-blush ring-2 ring-berry' : 'glass'" :aria-pressed="selected" @click="$emit('select', themeKey)">
        <span class="relative block aspect-[3/4] w-full overflow-hidden rounded-[0.9rem] bg-fill">
            <img v-if="url" :src="url" :alt="t('booth.themePreview', { name: t(`booth.themes.${themeKey}`) })" class="absolute inset-0 size-full object-contain" />
            <span v-else class="absolute inset-0 animate-pulse bg-fill" aria-hidden="true" />
            <span v-if="selected" class="absolute right-1.5 top-1.5 grid size-6 place-items-center rounded-full bg-berry text-white"><Check class="size-3.5" aria-hidden="true" /></span>
        </span>
        <span class="px-1 pb-0.5 text-[0.8125rem] font-semibold leading-tight text-plum">{{ t(`booth.themes.${themeKey}`) }}</span>
    </button>
</template>
