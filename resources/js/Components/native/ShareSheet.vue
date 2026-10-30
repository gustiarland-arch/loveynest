<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { MessageCircleHeart, Images } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Button from '@/Components/ui/Button.vue';
import { shared, choose, cancel } from '@/lib/shared';
import { t } from '@/i18n';

// Android app: photos shared from the gallery ask where they go (chat or an album).
const page = usePage();
const partner = computed(() => page.props.partner?.firstName ?? null);
const previews = computed(() => shared.files.slice(0, 4).map((file) => URL.createObjectURL(file)));
let shown = [];
watch(previews, (urls) => {
    shown.forEach((url) => URL.revokeObjectURL(url));
    shown = urls;
});
onBeforeUnmount(() => shown.forEach((url) => URL.revokeObjectURL(url)));

const open = computed({
    get: () => shared.files.length > 0 && !shared.target,
    set: (value) => {
        if (!value) cancel();
    },
});

function toChat() {
    choose('chat');
    if (!page.component.startsWith('Chat/')) router.visit('/chat');
}

function toAlbum() {
    choose('album');
    if (page.component === 'Albums/Show') router.reload();
    else router.visit('/albums?space=ours');
}
</script>

<template>
    <Dialog v-model:open="open" :title="t('share.title', { count: shared.files.length })" :description="t('share.lead')">
        <div class="flex gap-2 overflow-hidden">
            <img v-for="url in previews" :key="url" :src="url" alt="" class="size-16 shrink-0 rounded-[0.875rem] object-cover" />
            <span v-if="shared.files.length > 4" class="grid size-16 shrink-0 place-items-center rounded-[0.875rem] bg-fill text-sm font-semibold text-ink-muted">+{{ shared.files.length - 4 }}</span>
        </div>
        <div class="mt-4 grid gap-2">
            <Button block size="lg" @click="toChat"><MessageCircleHeart class="size-5" aria-hidden="true" />{{ partner ? t('share.chatWith', { name: partner }) : t('share.chat') }}</Button>
            <Button block size="lg" variant="secondary" @click="toAlbum"><Images class="size-5" aria-hidden="true" />{{ t('share.album') }}</Button>
            <Button block variant="ghost" @click="cancel">{{ t('common.cancel') }}</Button>
        </div>
    </Dialog>
</template>
