<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, User, Users, Images } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AlbumFormDialog from '@/Components/memories/AlbumFormDialog.vue';
import { t } from '@/i18n';
import { shared as sharedPhotos } from '@/lib/shared';

const props = defineProps({
    space: { type: String, required: true },
    hasCouple: { type: Boolean, required: true },
    albums: { type: Array, required: true },
});

const formOpen = ref(false);
const featured = computed(() => props.albums[0] ?? null);
const rest = computed(() => props.albums.slice(1));
const space = computed({
    get: () => props.space,
    set: (value) => router.get('/albums', { space: value }, { preserveState: true, preserveScroll: true, replace: true }),
});
</script>

<template>
    <Head :title="t('albums.title')" />
    <PageHeader :title="t('albums.title')" :lead="t('albums.lead')" back="/dashboard">
        <template #action>
            <button type="button" class="glass grid size-11 place-items-center rounded-full text-plum transition hover:bg-petal active:scale-95" :aria-label="t('albums.newAlbum')" @click="formOpen = true">
                <Plus class="size-5" aria-hidden="true" />
            </button>
        </template>
    </PageHeader>

    <p v-if="sharedPhotos.target === 'album' && sharedPhotos.files.length" class="mb-3 rounded-[1rem] bg-blush px-4 py-3 text-sm font-semibold text-berry-ink" role="status">{{ t('share.pickAlbum', { count: sharedPhotos.files.length }) }}</p>
    <SegmentedControl
        v-if="hasCouple"
        v-model="space"
        :label="t('space.label')"
        :options="[
            { value: 'mine', label: t('space.mine'), icon: User },
            { value: 'ours', label: t('space.ours'), icon: Users },
        ]"
    />
    <p v-else class="rounded-[1rem] bg-blush px-4 py-3 text-sm text-ink-muted">{{ t('albums.needCouple') }}</p>

    <template v-if="albums.length">
        <Link :href="`/albums/${featured.id}`" class="group relative mt-4 block aspect-[350/190] overflow-hidden rounded-[1.625rem] bg-gradient-to-br from-blush to-lavender shadow-soft transition hover:-translate-y-0.5 hover:shadow-lift">
            <img v-if="featured.cover" :src="featured.cover" alt="" class="absolute inset-0 size-full object-cover" />
            <Images v-else class="absolute left-1/2 top-1/3 size-10 -translate-x-1/2 text-berry-ink/50" aria-hidden="true" />
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-b from-transparent to-[rgb(60_20_40/0.6)] px-4 pb-4 pt-10 text-white">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] opacity-90">{{ t('albums.latest') }}</p>
                <p class="truncate font-display text-[1.375rem] font-semibold leading-tight">{{ featured.title }}</p>
                <p class="text-xs opacity-90">{{ t('albums.photosCount', { count: featured.count }) }}</p>
            </div>
        </Link>
        <ul v-if="rest.length" class="mt-3 grid grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-3">
            <li v-for="album in rest" :key="album.id">
                <Link :href="`/albums/${album.id}`" class="group block">
                    <div class="aspect-[169/130] w-full overflow-hidden rounded-[1.25rem] bg-gradient-to-br from-blush to-lavender shadow-soft transition group-hover:-translate-y-0.5 group-hover:shadow-lift">
                        <img v-if="album.cover" :src="album.cover" alt="" loading="lazy" class="size-full object-cover" />
                        <div v-else class="grid size-full place-items-center text-berry-ink/50"><Images class="size-8" aria-hidden="true" /></div>
                    </div>
                    <p class="mt-2 truncate text-[0.875rem] font-semibold text-plum">{{ album.title }}</p>
                    <p class="text-xs text-ink-muted">{{ t('albums.photosCount', { count: album.count }) }}</p>
                </Link>
            </li>
        </ul>
    </template>
    <Card v-else :padded="false" class="mt-4">
        <EmptyState :title="space === 'ours' ? t('albums.emptyOursTitle') : t('albums.emptyMineTitle')" :body="space === 'ours' ? t('albums.emptyOursBody') : t('albums.emptyMineBody')" :icon="Images">
            <Button variant="soft" @click="formOpen = true">{{ t('albums.newAlbum') }}</Button>
        </EmptyState>
    </Card>

    <AlbumFormDialog v-model:open="formOpen" :default-space="space" :has-couple="hasCouple" />
</template>
