<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Camera, Images, Download, Trash2, LoaderCircle, Users, Smartphone, Sparkles, Pencil, ArrowLeft } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import BoothCamera from '@/Components/photobooth/BoothCamera.vue';
import BoothEditor from '@/Components/photobooth/BoothEditor.vue';
import ThemeCard from '@/Components/photobooth/ThemeCard.vue';
import LayoutPicker from '@/Components/photobooth/LayoutPicker.vue';
import FilterStrip from '@/Components/photobooth/FilterStrip.vue';
import { usePolling } from '@/composables/usePolling';
import { useToast } from '@/composables/useToast';
import { THEMES, LAYOUTS, themeByKey } from '@/lib/booth/themes';
import { booth, chooseTheme, resetPicture } from '@/lib/booth/settings';
import { t } from '@/i18n';
import { sealFile } from '@/lib/e2e';
import { sealPhoto } from '@/lib/e2e/media';

const props = defineProps({
    current: { type: Object, default: null },
    history: { type: Array, default: () => [] },
    hasPartner: Boolean,
    names: { type: Object, required: true },
    albumId: { type: Number, default: null },
});

const toast = useToast();
const mode = ref(props.hasPartner ? 'turns' : 'solo');
const layout = ref(themeByKey(booth.theme).layout);
const starting = ref(false);
const cameraOpen = ref(false);
const position = ref(0);
const uploading = ref(false);
const saving = ref(false);
const cancelling = ref(false);
const viewing = ref(null);
const manualEdit = ref(false);

const modes = computed(() => [
    { value: 'turns', label: t('booth.modeTurns'), icon: Users },
    { value: 'solo', label: t('booth.modeSolo'), icon: Smartphone },
]);
const fresh = computed(() => (!props.current && props.history[0]?.fresh ? props.history[0] : null));
const theme = computed(() => themeByKey(props.current?.theme ?? booth.theme));
const palette = computed(() => theme.value.palette);
const pair = computed(() => [props.names.me, props.current?.mode === 'solo' && !props.hasPartner ? null : props.names.partner].filter(Boolean));
const cameraTitle = computed(() => t('booth.shotOf', { n: position.value + 1, total: props.current?.count ?? 4 }));
const cols = computed(() => LAYOUTS[props.current?.layout]?.cols ?? 1);

// Choosing a theme also suggests its own arrangement; the person can still pick another one.
function pickTheme(key) {
    chooseTheme(key);
    layout.value = themeByKey(key).layout;
}

function slotLabel(slot) {
    if (props.current.mode === 'solo') return t('booth.photoN', { n: slot.position + 1 });
    return slot.mine ? t('booth.you') : props.names.partner;
}

function start() {
    if (mode.value === 'turns' && !props.hasPartner) mode.value = 'solo';
    starting.value = true;
    resetPicture();
    router.post('/photobooth', { mode: mode.value, layout: layout.value, theme: booth.theme }, {
        preserveScroll: true,
        onError: (errors) => toast.error(Object.values(errors)[0]),
        onFinish: () => (starting.value = false),
    });
}

function openCamera(at) {
    position.value = at;
    cameraOpen.value = true;
}

async function upload(blob) {
    uploading.value = true;
    let photo;
    try {
        // Each shot is locked on this phone; the other phone opens it with the couple key.
        photo = await sealFile(new File([blob], 'booth.jpg', { type: 'image/jpeg' }), 'booth');
    } catch (error) {
        uploading.value = false;
        return toast.error(error.message);
    }
    router.post(`/photobooth/${props.current.id}/foto`, { position: position.value, photo }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (cameraOpen.value = false),
        onError: (errors) => toast.error(Object.values(errors)[0]),
        onFinish: () => (uploading.value = false),
    });
}

// The phone that took the last shot edits and saves by default. The other phone waits, or can take over.
const lastMine = computed(() => !!props.current?.done && !!props.current.slots.at(-1)?.mine);
const editing = computed(() => !!props.current?.done && (lastMine.value || manualEdit.value || props.current.editing || props.current.mode === 'solo'));
const editDate = computed(() => new Date(props.current?.date ?? Date.now()));

// A new round brings in its own theme, so both phones draw the same template.
watch(() => props.current?.id, (id) => {
    manualEdit.value = false;
    if (id && props.current.theme && props.current.theme !== booth.theme) chooseTheme(props.current.theme);
    if (id && !props.current.editing) resetPicture();
}, { immediate: true });

async function save(blob) {
    if (saving.value) return;
    saving.value = true;
    const session = props.current;
    const file = new File([blob], 'photobooth.jpg', { type: 'image/jpeg' });
    let data = { strip: file, replace: session.editing ? 1 : 0 };
    try {
        const locked = await sealPhoto(file);
        if (locked) data = { ...data, strip: locked.full, strip_thumb: locked.thumb, strip_width: locked.width, strip_height: locked.height };
    } catch (error) {
        saving.value = false;
        return toast.error(error.message);
    }
    router.post(`/photobooth/${session.id}/strip`, data, {
        forceFormData: true,
        preserveScroll: false,
        onSuccess: () => {
            if (session.editing) router.visit('/photobooth');
        },
        onError: (errors) => toast.error(Object.values(errors)[0] ?? t('booth.composeFailed')),
        onFinish: () => (saving.value = false),
    });
}

// Wait for the partner's shot, or for their phone to save the picture.
const waiting = computed(() => !!props.current && !cameraOpen.value && !editing.value && (!props.current.myTurn || props.current.done));
const waitedLong = ref(false);
const { poke } = usePolling(async () => {
    if (!waiting.value) return false;
    const before = JSON.stringify(props.current);
    await new Promise((resolve) => router.reload({ only: ['current', 'history', 'albumId'], onFinish: resolve }));
    if (props.current?.done && Date.now() - new Date(props.current.date).getTime() > 60000) waitedLong.value = true;
    return before !== JSON.stringify(props.current);
}, { min: 4000, max: 12000 });
watch(waiting, (value) => value && poke());

function cancel() {
    router.delete(`/photobooth/${props.current.id}`, { preserveScroll: true, onSuccess: () => (cancelling.value = false) });
}
</script>

<template>
    <Head :title="t('booth.title')" />
    <PageHeader :title="t('booth.title')" :lead="current ? undefined : t('booth.lead')" back="/berdua" />

    <!-- Editor -->
    <section v-if="current && editing" class="mb-8">
        <BoothEditor :key="current.id" :session="current" :names="pair" :date="editDate" :saving="saving" @save="save" />
        <div class="mt-2 text-center">
            <Button v-if="current.editing" variant="ghost" size="sm" href="/photobooth"><ArrowLeft class="size-4" aria-hidden="true" />{{ t('common.back') }}</Button>
            <Button v-else variant="danger-ghost" size="sm" @click="cancelling = true"><Trash2 class="size-4" aria-hidden="true" />{{ t('booth.cancel') }}</Button>
        </div>
    </section>

    <!-- Taking photos -->
    <section v-else-if="current" class="mb-6">
        <div class="mx-auto w-full rounded-[1.5rem] p-3 shadow-soft transition-colors" :class="cols === 1 ? 'max-w-[15rem]' : 'max-w-sm'" :style="{ background: theme.bg.c1 }">
            <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }">
                <component :is="slot.url && slot.mine && !current.done ? 'button' : 'div'" v-for="slot in current.slots" :key="slot.position"
                    :type="slot.url && slot.mine && !current.done ? 'button' : undefined"
                    class="relative aspect-square overflow-hidden rounded-[0.875rem]"
                    :class="!slot.url && 'grid place-items-center border-2 border-dashed'"
                    :style="!slot.url ? { borderColor: palette.soft, color: palette.ink } : null"
                    :aria-label="slot.url && slot.mine && !current.done ? t('booth.retakeN', { n: slot.position + 1 }) : undefined"
                    @click="slot.url && slot.mine && !current.done && openCamera(slot.position)">
                    <img v-if="slot.url" :src="slot.url" :alt="t('booth.photoN', { n: slot.position + 1 })" class="size-full object-cover" />
                    <span v-else class="px-2 text-center text-xs font-semibold leading-tight">
                        <span class="block text-2xl font-bold">{{ slot.position + 1 }}</span>
                        {{ slotLabel(slot) }}
                    </span>
                </component>
            </div>
            <p class="mt-3 text-center text-sm font-bold" :style="{ color: palette.ink }">{{ pair.join(' & ') }}</p>
        </div>

        <div class="mt-4 space-y-3 text-center">
            <template v-if="!current.done">
                <template v-if="current.myTurn">
                    <p class="text-sm font-semibold text-plum">{{ current.mode === 'solo' ? t('booth.soloNext', { n: current.next + 1 }) : t('booth.yourTurn', { n: current.next + 1 }) }}</p>
                    <Button size="lg" @click="openCamera(current.next)"><Camera class="size-5" aria-hidden="true" />{{ t('booth.take', { n: current.next + 1 }) }}</Button>
                </template>
                <p v-else class="inline-flex items-center gap-2 text-sm font-semibold text-ink-muted">
                    <span class="size-2 animate-pulse rounded-full bg-berry" aria-hidden="true" />{{ t('booth.waiting', { name: names.partner, n: current.next + 1 }) }}
                </p>
                <p v-if="current.slots.some((s) => s.url && s.mine)" class="text-xs text-ink-muted">{{ t('booth.retakeHint') }}</p>
            </template>
            <template v-else>
                <p class="inline-flex items-center gap-2 text-sm font-semibold text-plum">
                    <LoaderCircle v-if="!waitedLong" class="size-4 animate-spin" aria-hidden="true" />{{ waitedLong ? t('booth.waitedLong', { name: names.partner }) : t('booth.waitingEdit', { name: names.partner }) }}
                </p>
                <div><Button @click="manualEdit = true"><Pencil class="size-4" aria-hidden="true" />{{ t('booth.openEditor') }}</Button></div>
            </template>
            <div>
                <Button variant="danger-ghost" size="sm" @click="cancelling = true"><Trash2 class="size-4" aria-hidden="true" />{{ t('booth.cancel') }}</Button>
            </div>
        </div>
    </section>

    <!-- Home: result, theme gallery and settings -->
    <template v-else>
        <Card v-if="fresh" class="mb-5 text-center">
            <p class="flex items-center justify-center gap-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-berry-ink"><Sparkles class="size-4" aria-hidden="true" />{{ t('booth.freshTitle') }}</p>
            <img :src="fresh.full" :alt="t('booth.stripAlt')" class="mx-auto mt-3 max-h-[60dvh] w-auto max-w-full rounded-[1rem] shadow-soft" />
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <Button variant="soft" @click="viewing = fresh"><Download class="size-4" aria-hidden="true" />{{ t('booth.save') }}</Button>
                <Button v-if="fresh.editable" variant="soft" :href="`/photobooth?edit=${fresh.id}`"><Pencil class="size-4" aria-hidden="true" />{{ t('booth.editAgain') }}</Button>
                <Button v-if="albumId" variant="secondary" :href="`/albums/${albumId}`"><Images class="size-4" aria-hidden="true" />{{ t('booth.openAlbum') }}</Button>
            </div>
            <p v-if="fresh.editable" class="mt-2 text-xs text-ink-muted">{{ t('booth.editAgainHint') }}</p>
        </Card>

        <div class="grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:items-start">
            <Card class="space-y-3">
                <SectionTitle :title="t('booth.themesTitle')" />
                <div class="grid grid-cols-2 gap-2.5 min-[520px]:grid-cols-3 md:grid-cols-2 lg:grid-cols-3">
                    <ThemeCard v-for="th in THEMES" :key="th.key" :theme-key="th.key" :layout="layout" :selected="booth.theme === th.key" :names="[names.me, names.partner].filter(Boolean)" @select="pickTheme" />
                </div>
            </Card>

            <Card class="space-y-4 md:sticky md:top-4">
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-plum">{{ t('booth.howTitle') }}</p>
                    <SegmentedControl v-model="mode" :options="hasPartner ? modes : modes.slice(1)" :label="t('booth.howTitle')" />
                    <p class="text-xs text-ink-muted">{{ mode === 'turns' ? t('booth.modeTurnsHint', { name: names.partner }) : t('booth.modeSoloHint') }}</p>
                </div>
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-plum">{{ t('booth.layoutTitle') }}</p>
                    <LayoutPicker v-model="layout" />
                </div>
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-plum">{{ t('booth.filterTitle') }}</p>
                    <FilterStrip v-model="booth.preset" />
                </div>
                <Button block size="lg" :loading="starting" @click="start"><Camera class="size-5" aria-hidden="true" />{{ t('booth.start') }}</Button>
                <p class="text-xs text-ink-muted">{{ t('booth.privacy') }}</p>
            </Card>
        </div>

        <template v-if="history.length">
            <SectionTitle :title="t('booth.history')" class="mt-6" />
            <div class="grid grid-cols-3 gap-2 min-[520px]:grid-cols-4 md:grid-cols-6">
                <button v-for="h in history" :key="h.id" type="button" class="overflow-hidden rounded-[1rem] bg-fill" :aria-label="t('booth.openStrip', { date: h.date })" @click="viewing = h">
                    <img :src="h.thumb" alt="" class="aspect-square w-full object-cover" loading="lazy" />
                </button>
            </div>
        </template>
    </template>

    <Dialog :open="!!viewing" :title="t('booth.stripTitle')" :description="t('booth.saveHint')" @update:open="(v) => !v && (viewing = null)">
        <img v-if="viewing" :src="viewing.full" :alt="t('booth.stripAlt')" class="mx-auto max-h-[62dvh] w-auto max-w-full rounded-[1rem]" />
        <template #footer>
            <div v-if="viewing" class="flex flex-wrap justify-end gap-2">
                <Button v-if="viewing.editable" variant="soft" :href="`/photobooth?edit=${viewing.id}`"><Pencil class="size-4" aria-hidden="true" />{{ t('booth.editAgain') }}</Button>
                <a :href="viewing.full" :download="`photobooth-${viewing.date}.jpg`" class="inline-flex h-11 items-center gap-2 rounded-full bg-berry px-5 text-[0.9375rem] font-semibold text-white"><Download class="size-4" aria-hidden="true" />{{ t('booth.download') }}</a>
            </div>
        </template>
    </Dialog>

    <ConfirmDialog v-model:open="cancelling" :title="t('booth.cancelTitle')" :description="t('booth.cancelBody')" :confirm-label="t('booth.cancel')" danger @confirm="cancel" />

    <BoothCamera v-if="current && !current.done" v-model:open="cameraOpen" :title="cameraTitle" :busy="uploading" @capture="upload" />
</template>
