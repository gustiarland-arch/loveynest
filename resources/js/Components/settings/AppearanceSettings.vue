<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Smartphone, Sun, Moon, ImagePlus, Check, Trash2 } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import Button from '@/Components/ui/Button.vue';
import { BACKGROUND_PRESETS, applyBackground, currentGlass, currentTheme, setGlass, setTheme } from '@/lib/theme';
import { t } from '@/i18n';
import { fitImage } from '@/lib/image';

const page = usePage();
const saved = computed(() => page.props.auth.user.background);

const theme = ref(currentTheme());
function chooseTheme(mode) {
    theme.value = mode;
    setTheme(mode);
}

const liquid = ref(currentGlass() === 'liquid');
watch(liquid, (on) => setGlass(on ? 'liquid' : 'soft'));

// Background: picked locally and previewed live; saved with the button.
const preset = ref(saved.value.preset);
const veil = ref(saved.value.veil);
const saving = ref(false);
const uploading = ref(false);
const error = ref('');
const fileInput = ref(null);
const dirty = computed(() => preset.value !== saved.value.preset || veil.value !== saved.value.veil);

const swatches = computed(() => [
    { key: 'photo', label: t('settings.bg.photo'), style: { backgroundImage: "url('/img/background.webp')" } },
    ...Object.entries(BACKGROUND_PRESETS).map(([key, image]) => ({ key, label: t(`settings.bg.${key}`), style: { backgroundImage: image } })),
    ...(saved.value.url ? [{ key: 'custom', label: t('settings.bg.custom'), style: { backgroundImage: `url("${saved.value.thumb}")` } }] : []),
]);

watch([preset, veil], () => applyBackground({ ...saved.value, preset: preset.value, veil: veil.value }));
// Leaving without saving puts the saved backdrop back.
onBeforeUnmount(() => applyBackground(page.props.auth.user?.background ?? null));
watch(saved, (bg) => {
    preset.value = bg.preset;
    veil.value = bg.veil;
});

function save() {
    saving.value = true;
    router.patch('/settings/background', { preset: preset.value, veil: veil.value }, { preserveScroll: true, onFinish: () => (saving.value = false) });
}

async function upload(event) {
    const picked = event.target.files?.[0];
    event.target.value = '';
    error.value = '';
    if (!picked) return;
    const file = await fitImage(picked);
    if (file.size > 25 * 1024 * 1024) {
        error.value = t('settings.bg.tooBig');
        return;
    }
    uploading.value = true;
    router.post('/settings/background/photo', { background: file }, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => (error.value = errors.background ?? t('settings.photoFailed')),
        onFinish: () => (uploading.value = false),
    });
}

function removePhoto() {
    router.delete('/settings/background/photo', { preserveScroll: true });
}
</script>

<template>
    <Card class="space-y-5">
        <SegmentedControl
            :model-value="theme"
            :label="t('settings.appearance')"
            :options="[
                { value: 'auto', label: t('settings.themeAuto'), icon: Smartphone },
                { value: 'light', label: t('settings.themeLight'), icon: Sun },
                { value: 'dark', label: t('settings.themeDark'), icon: Moon },
            ]"
            @update:model-value="chooseTheme"
        />

        <div>
            <Toggle v-model="liquid" :label="t('settings.liquidGlass')" />
            <p class="text-xs text-ink-muted">{{ t('settings.liquidGlassHint') }}</p>
        </div>

        <fieldset>
            <legend class="text-sm font-semibold text-plum">{{ t('settings.bg.title') }}</legend>
            <div class="mt-2 grid grid-cols-4 gap-2">
                <button
                    v-for="s in swatches"
                    :key="s.key"
                    type="button"
                    class="group flex flex-col items-center gap-1 text-[0.6875rem] font-semibold"
                    :class="preset === s.key ? 'text-plum' : 'text-ink-muted'"
                    :aria-pressed="preset === s.key"
                    @click="preset = s.key"
                >
                    <span class="relative grid aspect-[3/4] w-full place-items-center overflow-hidden rounded-[0.875rem] bg-cover bg-center ring-2 transition" :class="preset === s.key ? 'ring-berry' : 'ring-transparent group-hover:ring-line'" :style="s.style">
                        <span v-if="preset === s.key" class="grid size-6 place-items-center rounded-full bg-berry text-white shadow"><Check class="size-3.5" stroke-width="3" aria-hidden="true" /></span>
                    </span>
                    {{ s.label }}
                </button>
                <button type="button" class="flex flex-col items-center gap-1 text-[0.6875rem] font-semibold text-ink-muted" :disabled="uploading" @click="fileInput?.click()">
                    <span class="grid aspect-[3/4] w-full place-items-center rounded-[0.875rem] border-2 border-dashed border-line-strong bg-surface/50 text-rose-ink">
                        <ImagePlus class="size-5" :class="uploading && 'animate-pulse'" aria-hidden="true" />
                    </span>
                    {{ saved.url ? t('settings.bg.replace') : t('settings.bg.upload') }}
                </button>
            </div>
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="upload" />
            <p v-if="error" class="mt-2 text-xs font-medium text-danger" role="alert">{{ error }}</p>
        </fieldset>

        <div>
            <label for="veil" class="flex items-center justify-between text-sm font-semibold text-plum">
                {{ t('settings.bg.veil') }}
                <span class="text-xs font-normal text-ink-muted">{{ veil < 0 ? t('settings.bg.clearer') : veil > 0 ? t('settings.bg.softer') : t('settings.bg.normal') }}</span>
            </label>
            <input id="veil" v-model.number="veil" type="range" min="-30" max="40" step="5" class="mt-2 w-full accent-[var(--color-berry)]" />
            <p class="text-xs text-ink-muted">{{ t('settings.bg.veilHint') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button :disabled="!dirty" :loading="saving" @click="save">{{ t('common.save') }}</Button>
            <Button v-if="saved.url" variant="danger-ghost" size="sm" @click="removePhoto"><Trash2 class="size-4" aria-hidden="true" />{{ t('settings.bg.remove') }}</Button>
        </div>
    </Card>
</template>
