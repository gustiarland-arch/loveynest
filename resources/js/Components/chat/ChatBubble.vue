<script setup>
import { CornerUpLeft, Pencil, Pin, RotateCw, Trash2, Ban, SmilePlus } from '@lucide/vue';
import VoiceNote from '@/Components/chat/VoiceNote.vue';
import { timeLabel } from '@/lib/dates';
import { t } from '@/i18n';

const emojis = ['❤️', '😂', '😮', '😢', '👍', '🔥'];

defineProps({
    message: { type: Object, required: true },
    active: { type: Boolean, default: false }, // actions bar open
    status: { type: String, default: null }, // sending | queued (waiting for signal, app) | sent | read | failed
    firstInGroup: { type: Boolean, default: true },
    showMeta: { type: Boolean, default: true },
    canEdit: { type: Boolean, default: false }, // own text message inside the edit window
});

defineEmits(['retry', 'discard', 'toggle', 'react', 'react-more', 'reply', 'open-photo', 'edit', 'remove', 'pin']);
</script>

<template>
    <div class="flex" :class="[message.mine ? 'justify-end' : 'justify-start', firstInGroup ? 'mt-3' : 'mt-1']">
        <div class="max-w-[82%]">
            <div v-if="active && typeof message.id === 'number'" class="glass mb-1.5 flex items-center gap-0.5 rounded-full px-1.5 py-1 shadow-lift" :class="message.mine ? 'ml-auto w-fit' : 'w-fit'" role="toolbar" :aria-label="t('chat.actions')">
                <button v-for="e in emojis" :key="e" type="button" class="grid size-9 place-items-center rounded-full text-lg transition hover:bg-blush-soft active:scale-90" :aria-label="e" @click="$emit('react', e)">{{ e }}</button>
                <button type="button" class="grid size-9 place-items-center rounded-full text-rose-ink transition hover:bg-blush-soft" :aria-label="t('chat.moreEmoji')" @click="$emit('react-more')"><SmilePlus class="size-5" aria-hidden="true" /></button>
                <template v-if="!message.deleted">
                    <button type="button" class="ml-0.5 grid size-9 place-items-center rounded-full text-rose-ink hover:bg-blush-soft" :aria-label="t('chat.reply')" @click="$emit('reply')"><CornerUpLeft class="size-4" aria-hidden="true" /></button>
                    <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-blush-soft" :class="message.pinned ? 'text-berry' : 'text-rose-ink'" :aria-label="message.pinned ? t('chat.unpin') : t('chat.pin')" @click="$emit('pin')"><Pin class="size-4" aria-hidden="true" /></button>
                    <button v-if="canEdit" type="button" class="grid size-9 place-items-center rounded-full text-rose-ink hover:bg-blush-soft" :aria-label="t('chat.edit')" @click="$emit('edit')"><Pencil class="size-4" aria-hidden="true" /></button>
                </template>
                <button v-if="message.mine && !message.deleted" type="button" class="grid size-9 place-items-center rounded-full text-danger hover:bg-danger-soft" :aria-label="t('chat.delete')" @click="$emit('remove')"><Trash2 class="size-4" aria-hidden="true" /></button>
            </div>
            <div v-if="message.sticker && !message.deleted" role="button" tabindex="0" class="cursor-pointer select-none px-2 text-[4rem] leading-none" :class="message.mine ? 'text-right' : 'text-left'" :aria-label="t('chat.sticker')" @click="$emit('toggle')" @keydown.enter.prevent="$emit('toggle')">{{ message.sticker.emoji }}</div>
            <div
                v-else
                role="button"
                tabindex="0"
                class="cursor-pointer whitespace-pre-wrap break-words text-[0.9375rem] leading-relaxed shadow-soft"
                :class="[
                    message.photo && !message.body ? 'p-1' : 'px-4 py-2.5',
                    message.deleted ? 'rounded-[1.375rem] bg-plum/[0.06] italic text-ink-muted !shadow-none' : message.mine ? 'rounded-[1.375rem] rounded-br-md bg-brand text-white' : 'rounded-[1.375rem] rounded-bl-md bg-surface text-plum',
                    (status === 'sending' || status === 'queued') && 'opacity-70',
                    status === 'failed' && 'bg-danger-soft text-danger ring-1 ring-danger/30',
                ]"
                @click="$emit('toggle')"
                @keydown.enter.prevent="$emit('toggle')"
            >
                <p v-if="message.reply" class="mb-1.5 line-clamp-2 border-l-2 pl-2 text-xs" :class="message.mine ? 'border-white/60 text-white/85' : 'border-rose text-ink-muted'">
                    <span class="font-semibold">{{ message.reply.mine ? t('mood.you') : t('chat.partner') }}</span> {{ message.reply.body }}
                </p>
                <span v-if="message.deleted" class="inline-flex items-center gap-1.5"><Ban class="size-3.5" aria-hidden="true" />{{ t('chat.deleted') }}</span>
                <VoiceNote v-else-if="message.audio" :src="message.audio.url" :seconds="message.audio.seconds" :mine="message.mine" />
                <button
                    v-else-if="message.photo"
                    type="button"
                    class="block overflow-hidden rounded-[1.125rem] bg-blush"
                    :class="message.body ? '-mx-2.5 mb-2' : ''"
                    :aria-label="t('chat.openPhoto')"
                    @click.stop="$emit('open-photo')"
                >
                    <img :src="message.photo.full ?? message.photo.thumb" alt="" loading="lazy" class="max-h-72 w-60 max-w-full object-cover" :width="message.photo.width" :height="message.photo.height" />
                </button>
                <template v-if="message.body && !message.deleted">{{ message.body }}</template>
            </div>
            <div v-if="message.reactions?.length" class="-mt-2 flex flex-wrap gap-1 px-2" :class="message.mine ? 'justify-end' : 'justify-start'">
                <button
                    v-for="r in message.reactions"
                    :key="r.emoji"
                    type="button"
                    class="inline-flex h-6 items-center gap-1 rounded-full bg-surface px-1.5 text-xs shadow-soft ring-1"
                    :class="r.mine ? 'ring-berry' : 'ring-line'"
                    :aria-label="`${r.emoji} ${r.count}`"
                    @click="$emit('react', r.emoji)"
                >{{ r.emoji }}<span v-if="r.count > 1" class="font-semibold text-plum">{{ r.count }}</span></button>
            </div>
            <div v-if="showMeta || status === 'failed' || status === 'queued'" class="mt-1 flex items-center gap-2 px-1 text-[0.6875rem] text-ink-muted" :class="message.mine ? 'justify-end' : 'justify-start'">
                <template v-if="status === 'failed'">
                    <span class="font-semibold text-danger">{{ t('chat.failed') }}</span>
                    <button type="button" class="inline-flex min-h-8 items-center gap-1 font-semibold text-plum" @click="$emit('retry')">
                        <RotateCw class="size-3" aria-hidden="true" />{{ t('chat.retry') }}
                    </button>
                    <button type="button" class="inline-flex min-h-8 items-center gap-1" @click="$emit('discard')">
                        <Trash2 class="size-3" aria-hidden="true" />{{ t('chat.discard') }}
                    </button>
                </template>
                <template v-else>
                    <Pin v-if="message.pinned" class="size-3 text-berry" aria-hidden="true" />
                    <time :datetime="message.created_at">{{ timeLabel(message.created_at) }}</time>
                    <span v-if="message.edited && !message.deleted">· {{ t('chat.edited') }}</span>
                    <span v-if="status" :class="status === 'read' && 'font-semibold text-berry-ink'">· {{ t(`chat.${status}`) }}</span>
                    <button v-if="status === 'queued'" type="button" class="inline-flex min-h-8 items-center gap-1" @click="$emit('discard')">
                        <Trash2 class="size-3" aria-hidden="true" />{{ t('chat.discard') }}
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
