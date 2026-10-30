<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, X, ChevronLeft, ChevronRight, Images, Lock, Users, Heart, Search, MapPin, ImageUp, Camera, Download } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import DateField from '@/Components/ui/DateField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AlbumFormDialog from '@/Components/memories/AlbumFormDialog.vue';
import { useToast } from '@/composables/useToast';
import { t } from '@/i18n';
import { fitImage } from '@/lib/image';
import { e2e, submitSealed } from '@/lib/e2e';
import { sealPhoto } from '@/lib/e2e/media';
import { shared as sharedPhotos, takeFor } from '@/lib/shared';

const props = defineProps({
    album: { type: Object, required: true },
    photos: { type: Array, required: true },
    maxPerAlbum: { type: Number, default: 300 },
    uploadLimits: { type: Object, default: () => ({ file: 2 * 1024 * 1024, request: 8 * 1024 * 1024 }) },
    events: { type: Array, default: () => [] },
});

const toast = useToast();
const picker = ref(null);
const camera = ref(null);
const uploading = ref(false);
const editOpen = ref(false);
const confirmAlbum = ref(false);
const deleting = ref(false);
const current = ref(null); // index of the photo in the lightbox
const confirmPhoto = ref(false);
const lightbox = ref(null);
const shared = computed(() => props.album.space === 'ours');
// Search, favourites and year narrow the grid; the lightbox follows the same list.
const q = ref('');
const onlyFav = ref(false);
const year = ref('');
const yearOf = (p) => (p.taken_on ?? p.date).slice(0, 4);
const years = computed(() => [...new Set(props.photos.map(yearOf))].sort().reverse());
const list = computed(() => {
    const needle = q.value.trim().toLowerCase();
    return props.photos.filter((p) => (!onlyFav.value || p.favorite) && (!year.value || yearOf(p) === year.value) && (!needle || `${p.caption ?? ''} ${p.location ?? ''}`.toLowerCase().includes(needle)));
});
const photo = computed(() => (current.value === null ? null : list.value[current.value] ?? null));
const yearOptions = computed(() => [{ value: '', label: t('albums.allYears') }, ...years.value.map((y) => ({ value: y, label: y }))]);

const detailsOpen = ref(false);
const details = useForm({ caption: '', location: '', taken_on: '', event_id: '' });
const eventOptions = computed(() => [{ value: '', label: t('albums.noEvent') }, ...props.events.map((e) => ({ value: e.id, label: `${e.title} · ${e.date}` }))]);
function openDetails() {
    details.clearErrors();
    details.defaults({ caption: photo.value.caption ?? '', location: photo.value.location ?? '', taken_on: photo.value.taken_on ?? '', event_id: photo.value.event_id ?? '' });
    details.reset();
    detailsOpen.value = true;
}
function saveDetails() {
    submitSealed(details, ['caption', 'location'], (f) => f.patch(`/photos/${photo.value.id}`, { preserveScroll: true, onSuccess: () => (detailsOpen.value = false) }), (d) => ({ caption: d.caption || null, location: d.location || null, taken_on: d.taken_on || null, event_id: d.event_id || null }));
}
function toggleFavorite() {
    router.patch(`/photos/${photo.value.id}`, { is_favorite: !photo.value.favorite }, { preserveScroll: true });
}
function makeCover() {
    router.post(`/albums/${props.album.id}/cover`, { photo_id: photo.value.id }, { preserveScroll: true });
}

function pick() {
    picker.value?.click();
}

async function upload(event) {
    let files = [...(event.target.files ?? [])];
    event.target.value = '';
    if (!files.length) return;
    if (files.length > 10) return toast.error(t('albums.uploadLimit'));

    const limits = props.uploadLimits;
    uploading.value = true;
    files = await Promise.all(files.map((f) => fitImage(f, Math.min(limits.file * 0.9, 4_000_000))));
    uploading.value = false;
    const tooBig = files.find((f) => f.size > limits.file);
    if (tooBig) return toast.error(t('albums.serverTooSmall', { max: `${Math.floor(limits.file / 1048576)} MB` }));

    // End-to-end encryption: each photo is shrunk, given a thumbnail and locked here before it leaves the phone.
    let items = files.map((file) => ({ file }));
    if (e2e.enabled) {
        uploading.value = true;
        try {
            items = await Promise.all(files.map(async (file) => {
                const locked = await sealPhoto(file);
                return { file: locked.full, thumb: locked.thumb, width: locked.width, height: locked.height };
            }));
        } catch (error) {
            uploading.value = false;
            return toast.error(error.message);
        }
        uploading.value = false;
    }

    // Split into requests that each fit the server's whole-request limit, so a
    // handful of large phone photos still goes through.
    const room = Math.max(1, limits.request - 512 * 1024);
    const batches = [];
    let batch = [];
    let size = 0;
    for (const item of items) {
        const bytes = item.file.size + (item.thumb?.size ?? 0);
        if (batch.length && size + bytes > room) {
            batches.push(batch);
            batch = [];
            size = 0;
        }
        batch.push(item);
        size += bytes;
    }
    batches.push(batch);

    uploading.value = true;
    let done = 0;
    const next = () => {
        const group = batches.shift();
        if (!group) {
            uploading.value = false;
            return;
        }
        const data = e2e.enabled
            ? { photos: group.map((i) => i.file), photos_thumb: group.map((i) => i.thumb), photos_width: group.map((i) => i.width), photos_height: group.map((i) => i.height) }
            : { photos: group.map((i) => i.file) };
        router.post(`/albums/${props.album.id}/photos`, data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                done += group.length;
                next();
            },
            onError: (errors) => {
                batches.length = 0;
                uploading.value = false;
                toast.error(Object.values(errors)[0] ?? t('albums.uploadLimit'));
            },
        });
    };
    next();
}

function openPhoto(index) {
    current.value = index;
    nextTick(() => lightbox.value?.showModal());
}

function closePhoto() {
    if (lightbox.value?.open) lightbox.value.close();
}

const step = (delta) => (current.value = (current.value + delta + list.value.length) % list.value.length);

function onKey(event) {
    if (event.key === 'ArrowRight') step(1);
    if (event.key === 'ArrowLeft') step(-1);
}

function removePhoto() {
    const id = photo.value.id;
    deleting.value = true;
    router.delete(`/photos/${id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = false),
        onSuccess: () => {
            confirmPhoto.value = false;
            if (list.value.length === 0) closePhoto();
            else current.value = Math.min(current.value ?? 0, list.value.length - 1);
        },
    });
}

function removeAlbum() {
    deleting.value = true;
    router.delete(`/albums/${props.album.id}`, { onFinish: () => (deleting.value = false) });
}

watch(() => props.photos.length, (length) => {
    if (!length) closePhoto();
});

// Photos shared from the gallery into the Android app, after "Simpan ke album" and opening this album.
function uploadShared() {
    const files = takeFor('album');
    if (files.length) upload({ target: { files, value: '' } });
}
watch(() => sharedPhotos.target, (target) => target === 'album' && uploadShared());
onMounted(uploadShared);
</script>

<template>
    <Head :title="album.title" />
    <PageHeader :title="album.title" :lead="album.description" :back="`/albums?space=${album.space}`">
        <template #action>
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" :class="shared ? 'bg-lavender text-lavender-ink' : 'bg-blush text-rose-ink'">
                <component :is="shared ? Users : Lock" class="size-3.5" aria-hidden="true" />{{ shared ? t('dashboard.sharedNote') : t('dashboard.privateNote') }}
            </span>
        </template>
    </PageHeader>

    <div class="mb-4 flex flex-wrap gap-2">
        <Button variant="secondary" :disabled="uploading || photos.length >= maxPerAlbum" :aria-label="t('albums.takePhoto')" @click="camera?.click()"><Camera class="size-4" aria-hidden="true" />{{ t('albums.camera') }}</Button>
        <Button :loading="uploading" :disabled="photos.length >= maxPerAlbum" @click="pick"><Plus class="size-4" aria-hidden="true" />{{ uploading ? t('albums.uploading') : t('albums.addPhotos') }}</Button>
        <Button variant="secondary" @click="editOpen = true"><Pencil class="size-4" aria-hidden="true" />{{ t('common.edit') }}</Button>
        <Button variant="danger-ghost" @click="confirmAlbum = true"><Trash2 class="size-4" aria-hidden="true" />{{ t('common.delete') }}</Button>
        <input ref="camera" type="file" class="sr-only" accept="image/*" capture="environment" tabindex="-1" aria-hidden="true" @change="upload" />
        <input ref="picker" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" multiple tabindex="-1" aria-hidden="true" @change="upload" />
    </div>

    <div v-if="photos.length > 1" class="mb-3 grid grid-cols-[1fr_auto] items-end gap-2">
        <TextField v-model="q" :label="t('albums.search')" :icon="Search" :maxlength="60" />
        <button type="button" class="grid size-12 place-items-center rounded-full ring-1" :class="onlyFav ? 'bg-blush text-berry ring-berry' : 'bg-surface text-ink-muted ring-line'" :aria-pressed="onlyFav" :aria-label="t('albums.onlyFavorites')" @click="onlyFav = !onlyFav"><Heart class="size-5" :fill="onlyFav ? 'currentColor' : 'none'" aria-hidden="true" /></button>
        <SelectField v-if="years.length > 1" v-model="year" class="col-span-2" :label="t('albums.year')" :options="yearOptions" />
    </div>
    <p v-if="photos.length && !list.length" class="text-sm text-ink-muted">{{ t('albums.noMatch') }}</p>
    <ul v-else-if="photos.length" class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
        <li v-for="(item, index) in list" :key="item.id">
            <button type="button" class="relative block aspect-square w-full overflow-hidden rounded-md bg-blush-soft" :aria-label="t('albums.photoOf', { n: index + 1, total: list.length })" @click="openPhoto(index)">
                <img :src="item.thumb" alt="" loading="lazy" class="size-full object-cover transition duration-300 hover:scale-105" />
                <Heart v-if="item.favorite" class="absolute right-1.5 top-1.5 size-4 text-white drop-shadow" fill="currentColor" aria-hidden="true" />
            </button>
        </li>
    </ul>
    <Card v-else :padded="false">
        <EmptyState :title="t('albums.photosEmptyTitle')" :body="t('albums.photosEmptyBody')" :icon="Images">
            <Button variant="soft" @click="pick">{{ t('albums.addPhotos') }}</Button>
        </EmptyState>
    </Card>

    <dialog
        ref="lightbox"
        class="m-0 size-full max-h-none max-w-none bg-black p-0 text-white backdrop:bg-black"
        aria-label="Foto"
        @close="current = null"
        @keydown="onKey"
    >
        <div v-if="photo" class="flex size-full flex-col">
            <div class="flex items-center gap-2 px-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                <button type="button" class="grid size-11 place-items-center rounded-full hover:bg-white/10" :aria-label="t('common.close')" @click="closePhoto"><X class="size-6" aria-hidden="true" /></button>
                <p class="flex-1 text-center text-sm">{{ t('albums.photoOf', { n: current + 1, total: list.length }) }}<template v-if="photo.by"> · {{ t('albums.by', { name: photo.by }) }}</template></p>
                <button type="button" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-red-500/25 px-3.5 text-sm font-semibold text-red-100 hover:bg-red-500/40" :aria-label="t('albums.deletePhoto')" @click="confirmPhoto = true"><Trash2 class="size-4" aria-hidden="true" />{{ t('albums.deletePhotoShort') }}</button>
            </div>
            <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 py-3">
                <img :key="photo.id" :src="photo.full" alt="" class="max-h-full max-w-full rounded-md object-contain" />
                <template v-if="list.length > 1">
                    <button type="button" class="absolute left-1 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-black/40 hover:bg-black/60" :aria-label="t('albums.prev')" @click="step(-1)"><ChevronLeft class="size-6" aria-hidden="true" /></button>
                    <button type="button" class="absolute right-1 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-black/40 hover:bg-black/60" :aria-label="t('albums.next')" @click="step(1)"><ChevronRight class="size-6" aria-hidden="true" /></button>
                </template>
            </div>
            <div class="px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <p v-if="photo.caption" class="break-words text-sm">{{ photo.caption }}</p>
                <p v-if="photo.location" class="mt-0.5 flex items-center gap-1 text-xs text-white/80"><MapPin class="size-3.5" aria-hidden="true" />{{ photo.location }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-white/15 px-3.5 text-sm font-semibold hover:bg-white/25" :aria-pressed="photo.favorite" @click="toggleFavorite"><Heart class="size-4" :fill="photo.favorite ? 'currentColor' : 'none'" aria-hidden="true" />{{ t('albums.favorite') }}</button>
                    <button type="button" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-white/15 px-3.5 text-sm font-semibold hover:bg-white/25" @click="openDetails"><Pencil class="size-4" aria-hidden="true" />{{ t('albums.details') }}</button>
                    <a :href="photo.full" :download="`loveynest-${photo.id}.jpg`" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-white/15 px-3.5 text-sm font-semibold text-white hover:bg-white/25"><Download class="size-4" aria-hidden="true" />{{ t('albums.save') }}</a>
                    <button type="button" class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold hover:bg-white/25" :class="album.cover === photo.id ? 'bg-white !text-black' : 'bg-white/15'" @click="makeCover"><ImageUp class="size-4" aria-hidden="true" />{{ album.cover === photo.id ? t('albums.isCover') : t('albums.setCover') }}</button>
                </div>
            </div>
        </div>
    </dialog>

    <Dialog v-model:open="detailsOpen" :title="t('albums.details')">
        <form id="pd-form" class="space-y-4" novalidate @submit.prevent="saveDetails">
            <TextField v-model="details.caption" :label="t('albums.caption')" :maxlength="200" :error="details.errors.caption" />
            <TextField v-model="details.location" :label="t('albums.location')" :maxlength="120" :error="details.errors.location" />
            <DateField v-model="details.taken_on" :label="t('albums.takenOn')" clearable :error="details.errors.taken_on" />
            <SelectField v-model="details.event_id" :label="t('albums.linkEvent')" :options="eventOptions" :error="details.errors.event_id" />
        </form>
        <template #footer><Button variant="secondary" @click="detailsOpen = false">{{ t('common.cancel') }}</Button><Button type="submit" form="pd-form" :loading="details.processing">{{ t('common.save') }}</Button></template>
    </Dialog>

    <ConfirmDialog v-model:open="confirmPhoto" :title="t('albums.deletePhotoTitle')" :description="t('albums.deletePhotoBody')" :confirm-label="t('common.delete')" :loading="deleting" danger @confirm="removePhoto" />
    <ConfirmDialog v-model:open="confirmAlbum" :title="t('albums.deleteAlbumTitle')" :description="t('albums.deleteAlbumBody', { title: album.title })" :confirm-label="t('common.delete')" :loading="deleting" danger @confirm="removeAlbum" />
    <AlbumFormDialog v-model:open="editOpen" :album="album" />
</template>
