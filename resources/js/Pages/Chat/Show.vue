<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, SendHorizontal, WifiOff, ArrowDown, MessageCircleHeart, Lock, X, CornerUpLeft, ImagePlus, Camera, Mic, Search, Smile, EllipsisVertical, BellOff, Bell, Pin, Mail, Check, Download } from '@lucide/vue';
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Button from '@/Components/ui/Button.vue';
import ToastRegion from '@/Components/ui/ToastRegion.vue';
import ChatBubble from '@/Components/chat/ChatBubble.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import StickerPicker from '@/Components/chat/StickerPicker.vue';
import EmojiPicker from '@/Components/chat/EmojiPicker.vue';
import { useVoiceRecorder } from '@/composables/useVoiceRecorder';
import ChatSearch from '@/Components/chat/ChatSearch.vue';
import LoveNoteDialog from '@/Components/chat/LoveNoteDialog.vue';
import { usePolling } from '@/composables/usePolling';
import { useToast } from '@/composables/useToast';
import { http, HttpError, uuid } from '@/lib/http';
import { dateKey, dayLabel, timeLabel } from '@/lib/dates';
import { connectRealtime } from '@/lib/realtime';
import { t } from '@/i18n';
import { fitImage } from '@/lib/image';
import { LockedError, e2e, seal, sealFile } from '@/lib/e2e';
import E2eBanner from '@/Components/settings/E2eBanner.vue';
import { sealPhoto } from '@/lib/e2e/media';
import ShareSheet from '@/Components/native/ShareSheet.vue';
import { NATIVE, outbox } from '@/lib/native';
import { flush as flushOutbox, isNetworkError, onOutbox, queue as queueOffline, waitingItems } from '@/lib/outbox';
import OfflineBanner from '@/Components/native/OfflineBanner.vue';
import { shared as sharedPhotos, takeFor } from '@/lib/shared';

const props = defineProps({
    partner: { type: Object, default: null },
    messages: { type: Array, required: true },
    hasMore: { type: Boolean, default: false },
    partnerLastRead: { type: Number, default: 0 },
    maxLength: { type: Number, default: 2000 },
    spaceId: { type: Number, default: null },
    presence: { type: Object, default: null },
    muted: { type: Boolean, default: false },
    pinned: { type: Array, default: () => [] },
    stickers: { type: Array, default: () => [] },
    editMinutes: { type: Number, default: 15 },
});

const toast = useToast();
const messages = ref([...props.messages]);
const pending = ref([]); // { client_uuid, body, created_at, status: 'sending' | 'queued' | 'failed', mine: true }
const hasMore = ref(props.hasMore);
const partnerLastRead = ref(props.partnerLastRead);
const draft = ref('');
const loadingOlder = ref(false);
const scroller = ref(null);
const composer = ref(null);
const showNewPill = ref(false);
const activeId = ref(null);
const replyTo = ref(null);
let lastReadSent = 0;
const presence = ref({ online: false, lastSeen: null, typing: false, ...(props.presence ?? {}) });
const photoInput = ref(null);
const cameraInput = ref(null);
const panelTab = ref('emoji');
const emojiPicker = ref(null);
const reactTarget = ref(null); // message getting a reaction from the full emoji list
const voice = useVoiceRecorder();
const voiceBusy = ref(false);
const muted = ref(props.muted);
const menuOpen = ref(false);
const searchOpen = ref(false);
const noteOpen = ref(false);
const stickersOpen = ref(false);
const editing = ref(null); // own message being edited
const pinnedList = ref([...props.pinned]);
const pinnedOpen = ref(false);
const deleteTarget = ref(null);
const deleteOpen = ref(false);
const viewing = ref(null); // photo opened full screen

// Bubbles that arrive while the chat is open ease in; the history that was there on load does not.
const arrived = ref(new Set());

const lastId = () => messages.value.at(-1)?.id ?? 0;

function nearBottom() {
    const el = scroller.value;
    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120;
}

function scrollToBottom(smooth = false) {
    nextTick(() => {
        const el = scroller.value;
        if (!el) return;
        // A long glide through hundreds of bubbles stutters; short hops glide, long ones jump.
        const far = el.scrollHeight - el.scrollTop - el.clientHeight > el.clientHeight * 2;
        el.scrollTo({ top: el.scrollHeight, behavior: smooth && !far ? 'smooth' : 'auto' });
        showNewPill.value = false;
    });
}

function merge(incoming) {
    const known = new Set(messages.value.map((m) => m.id));
    const fresh = incoming.filter((m) => !known.has(m.id));
    if (!fresh.length) return false;
    const uuids = new Set(fresh.map((m) => m.client_uuid).filter(Boolean));
    pending.value = pending.value.filter((p) => !uuids.has(p.client_uuid));
    for (const m of fresh) if (!m.mine) arrived.value.add(m.id);
    messages.value = [...messages.value, ...fresh].sort((a, b) => a.id - b.id);
    changed = true;
    if (NATIVE) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(refreshSaved, 4000);
    }
    return fresh.some((m) => !m.mine);
}

// Android app: the copy of this chat kept for use without signal is refreshed when leaving it (the app saves
// every page it fetches, see AppWebViewClient), so messages sent or received here are in it too.
let changed = false;
let saveTimer = null;
const inertiaPage = usePage();
function refreshSaved() {
    if (!NATIVE || !changed || !navigator.onLine) return;
    changed = false;
    fetch('/chat', {
        credentials: 'same-origin',
        headers: { 'X-Inertia': 'true', 'X-Inertia-Version': inertiaPage.version ?? '', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml' },
    }).catch(() => {
        changed = true;
    });
}
const onHidden = () => document.visibilityState === 'hidden' && refreshSaved();

// Read markers move only while the conversation is on screen and scrolled
// to the newest messages, i.e. when they have actually been seen.
async function markRead() {
    if (document.visibilityState !== 'visible' || !nearBottom()) return;
    const latestFromPartner = [...messages.value].reverse().find((m) => !m.mine)?.id ?? 0;
    if (latestFromPartner <= lastReadSent) return;
    lastReadSent = latestFromPartner;
    try {
        await http.post('/chat/read', { message_id: latestFromPartner });
    } catch {
        lastReadSent = 0;
    }
}

const { failing, poke, pollNow } = usePolling(async () => {
    const wasNearBottom = nearBottom();
    const data = await http.get(`/chat/messages?after=${lastId()}`);
    if (pending.value.some((p) => p.status === 'queued')) flushOutbox();
    partnerLastRead.value = Math.max(partnerLastRead.value, data.partnerLastRead ?? 0);
    applyReactions(data.reactions);
    if (data.presence) presence.value = data.presence;
    applyStates(data.states);
    const fromPartner = merge(data.messages);
    if (fromPartner) presence.value = { ...presence.value, typing: false };
    if (data.messages.length) {
        if (wasNearBottom) scrollToBottom(true);
        else if (fromPartner) showNewPill.value = true;
    }
    nextTick(markRead);
    // Keep polling fast while the partner is typing, so their message shows up promptly.
    return data.messages.length > 0 || Boolean(data.presence?.typing);
});

// Reactions of recent messages arrive with every poll, so they stay current.
// Edits, deletions and pins of older messages arrive with every poll too.
function applyStates(map) {
    if (!map) return;
    messages.value = messages.value.map((m) => {
        const st = map[m.id];
        if (!st) return m;
        if (m.body === st.body && m.edited === st.edited && m.deleted === st.deleted && m.pinned === st.pinned) return m;
        return { ...m, body: st.body, edited: st.edited, deleted: st.deleted, pinned: st.pinned, photo: st.photo ? m.photo : null, audio: st.audio ? m.audio : null, sticker: st.deleted ? null : m.sticker };
    });
    const pins = messages.value.filter((m) => m.pinned && !m.deleted);
    const known = new Set(pins.map((m) => m.id));
    pinnedList.value = [...pins, ...pinnedList.value.filter((m) => !known.has(m.id) && map[m.id]?.pinned && !map[m.id]?.deleted)].sort((a, b) => b.id - a.id);
}

function applyReactions(map) {
    if (!map) return;
    messages.value = messages.value.map((m) => (map[m.id] ? { ...m, reactions: map[m.id] } : m));
}

async function react(message, emoji) {
    activeId.value = null;
    try {
        const data = await http.post(`/chat/messages/${message.id}/reactions`, { emoji });
        applyReactions(data.reactions);
    } catch {
        toast.error(t('chat.offline'));
    }
}

function startReply(message) {
    replyTo.value = message;
    activeId.value = null;
    nextTick(() => composer.value?.focus());
}

async function deliver(item) {
    item.status = 'sending';
    let sealed = null;
    try {
        // Text, photos and voice notes are locked on this device; the server only stores the locked form.
        let payload = { body: await seal(item.body), client_uuid: item.client_uuid, reply_to: item.reply?.id ?? null, sticker: item.sticker?.key ?? null };
        if (item.file) {
            payload = new FormData();
            payload.append('body', '');
            payload.append('client_uuid', item.client_uuid);
            if (item.reply) payload.append('reply_to', item.reply.id);
            if (item.audio) {
                const locked = await sealFile(item.file, 'suara');
                payload.append('audio', locked, locked.name);
                payload.append('audio_seconds', String(item.audio.seconds ?? 1));
                payload.append('audio_type', item.file.type);
            } else {
                const locked = await sealPhoto(item.file);
                if (locked) {
                    payload.append('photo', locked.full);
                    payload.append('photo_thumb', locked.thumb);
                    payload.append('photo_width', String(locked.width));
                    payload.append('photo_height', String(locked.height));
                } else {
                    payload.append('photo', item.file);
                }
            }
        }
        sealed = payload;
        const { message } = await http.post('/chat/messages', payload, { timeout: item.file ? 90000 : 12000 });
        merge([message]);
        poke();
    } catch (error) {
        // No signal (Android app): text and stickers wait on the phone and go out when it is back.
        if (sealed && !item.file && isNetworkError(error) && (await keepForLater(item, sealed))) return;
        if (error instanceof LockedError) {
            item.status = 'failed';
            toast.error(error.message);
            return;
        }
        if (error instanceof HttpError && error.status === 422) {
            pending.value = pending.value.filter((p) => p !== item);
            toast.error(error.data?.errors?.photo?.[0] ?? error.data?.errors?.audio?.[0] ?? error.data?.errors?.body?.[0] ?? error.message);
            return;
        }
        item.status = 'failed';
    }
}

async function keepForLater(item, payload) {
    if (!NATIVE) return false;
    try {
        await queueOffline({
            client_uuid: item.client_uuid,
            payload,
            display: { body: item.body, sticker: item.sticker ?? null, reply: item.reply ?? null },
            created_at: item.created_at,
        });
        item.status = 'queued';
        return true;
    } catch {
        return false;
    }
}

function send(file = null, voiceNote = null) {
    if (editing.value) return saveEdit();
    const body = file ? '' : draft.value.trim();
    if (!file && (!body || body.length > props.maxLength)) return;
    const reply = replyTo.value ? { id: replyTo.value.id, body: previewOf(replyTo.value).slice(0, 90), mine: replyTo.value.mine } : null;
    const photo = file && !voiceNote ? { thumb: URL.createObjectURL(file), full: null, width: null, height: null } : null;
    const audio = voiceNote ? { url: URL.createObjectURL(file), seconds: voiceNote.seconds } : null;
    const item = { client_uuid: uuid(), body, file, photo, audio, created_at: new Date().toISOString(), status: 'sending', mine: true, reply, reactions: [] };
    replyTo.value = null;
    pending.value.push(item);
    if (!file) {
        draft.value = '';
        lastTypingSent = 0;
    }
    nextTick(resizeComposer);
    scrollToBottom(true);
    deliver(pending.value.at(-1));
}

function previewOf(message) {
    if (message.deleted) return t('chat.deleted');
    if (message.sticker) return `${message.sticker.emoji} ${t('chat.sticker')}`;
    return message.body || (message.photo ? t('chat.photo') : message.audio ? t('chat.voice') : '');
}

function sendSticker(sticker) {
    stickersOpen.value = false;
    const reply = replyTo.value ? { id: replyTo.value.id, body: previewOf(replyTo.value).slice(0, 90), mine: replyTo.value.mine } : null;
    const item = { client_uuid: uuid(), body: '', sticker: { key: sticker.key, emoji: sticker.emoji }, created_at: new Date().toISOString(), status: 'sending', mine: true, reply, reactions: [] };
    replyTo.value = null;
    pending.value.push(item);
    scrollToBottom(true);
    deliver(pending.value.at(-1));
}

const canEdit = (m) => m.mine && typeof m.id === 'number' && !m.sticker && !m.deleted && Date.now() - new Date(m.created_at).getTime() < props.editMinutes * 60000;

function startEdit(message) {
    activeId.value = null;
    editing.value = message;
    replyTo.value = null;
    draft.value = message.body;
    nextTick(() => {
        resizeComposer();
        const el = composer.value;
        el?.focus();
        if (el) {
            const sel = window.getSelection();
            sel?.selectAllChildren(el);
            sel?.collapseToEnd();
        }
    });
}

function cancelEdit() {
    editing.value = null;
    draft.value = '';
    nextTick(resizeComposer);
}

async function saveEdit() {
    const body = draft.value.trim();
    const target = editing.value;
    if (!target || !body || body.length > props.maxLength) return;
    try {
        const { message } = await http.patch(`/chat/messages/${target.id}`, { body: await seal(body) });
        messages.value = messages.value.map((m) => (m.id === message.id ? { ...m, body: message.body, edited: true } : m));
        cancelEdit();
    } catch (error) {
        toast.error(error instanceof LockedError ? error.message : error instanceof HttpError && error.data?.message ? error.data.message : t('chat.offline'));
    }
}

function removeMessage(message) {
    activeId.value = null;
    deleteTarget.value = message;
    deleteOpen.value = true;
}

async function confirmRemove() {
    const message = deleteTarget.value;
    deleteOpen.value = false;
    if (!message) return;
    try {
        await http.delete(`/chat/messages/${message.id}`);
        messages.value = messages.value.map((m) => (m.id === message.id ? { ...m, body: '', photo: null, sticker: null, deleted: true, pinned: false, reactions: [] } : m));
        pinnedList.value = pinnedList.value.filter((m) => m.id !== message.id);
    } catch {
        toast.error(t('chat.offline'));
    }
}

async function togglePin(message) {
    activeId.value = null;
    try {
        const { result } = await http.post(`/chat/messages/${message.id}/pin`, {});
        const pinned = result === 'pinned';
        messages.value = messages.value.map((m) => (m.id === message.id ? { ...m, pinned } : m));
        pinnedList.value = pinned ? [{ ...message, pinned: true }, ...pinnedList.value] : pinnedList.value.filter((m) => m.id !== message.id);
    } catch (error) {
        toast.error(error instanceof HttpError && error.data?.message ? error.data.message : t('chat.offline'));
    }
}

async function toggleMute() {
    menuOpen.value = false;
    const next = !muted.value;
    try {
        await http.post('/chat/mute', { muted: next });
        muted.value = next;
        toast.success(next ? t('chat.mutedOn') : t('chat.mutedOff'));
    } catch {
        toast.error(t('chat.offline'));
    }
}

async function pickPhoto(event) {
    const picked = event.target.files?.[0];
    event.target.value = '';
    if (!picked) return;
    const file = await fitImage(picked);
    if (file.size > 25 * 1024 * 1024) {
        toast.error(t('chat.photoTooBig'));
        return;
    }
    send(file);
}

function insertEmoji(emoji) {
    emojiPicker.value?.remember(emoji);
    const el = composer.value;
    if (!el) return;
    const sel = window.getSelection();
    const inside = sel?.rangeCount && el.contains(sel.anchorNode);
    el.focus();
    if (!inside) {
        // No caret yet: write at the end.
        sel?.selectAllChildren(el);
        sel?.collapseToEnd();
    }
    if (!document.execCommand('insertText', false, emoji)) el.textContent = draft.value + emoji;
    syncDraft();
    signalTyping();
}

function reactWithPicked(emoji) {
    const target = reactTarget.value;
    reactTarget.value = null;
    emojiPicker.value?.remember(emoji);
    if (target) react(target, emoji);
}

async function startVoice() {
    if (!voice.supported) return toast.error(t('chat.voiceUnsupported'));
    try {
        await voice.start();
    } catch (error) {
        toast.error(error?.name === 'NotAllowedError' ? t('chat.voiceDenied') : t('chat.voiceFailed'));
    }
}

async function sendVoice() {
    voiceBusy.value = true;
    const result = await voice.stop();
    voiceBusy.value = false;
    if (!result) return toast.error(t('chat.voiceFailed'));
    const ext = result.mime.includes('mp4') ? 'm4a' : result.mime.includes('ogg') ? 'ogg' : 'webm';
    const file = new File([result.blob], `suara.${ext}`, { type: result.mime });
    if (file.size > 6 * 1024 * 1024) return toast.error(t('chat.voiceTooBig'));
    send(file, { seconds: result.seconds });
}

// "Typing…": tell the server at most every few seconds while keys are pressed.
let lastTypingSent = 0;
function signalTyping() {
    const now = Date.now();
    if (!draft.value.trim() || now - lastTypingSent < 4000) return;
    lastTypingSent = now;
    http.post('/chat/typing', { typing: true }).catch(() => {});
}

const presenceLabel = computed(() => {
    if (presence.value.typing) return t('chat.typing');
    if (presence.value.online) return t('chat.online');
    if (presence.value.lastSeen) {
        const seen = presence.value.lastSeen;
        return t('chat.lastSeen', { when: dateKey(seen) === dateKey(new Date().toISOString()) ? timeLabel(seen) : `${dayLabel(dateKey(seen), t)} ${timeLabel(seen)}` });
    }
    return null;
});

function onKeydown(event) {
    // Enter sends on devices with a hardware keyboard; Shift+Enter adds a line.
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && window.matchMedia('(pointer: fine)').matches) {
        event.preventDefault();
        send();
    }
}

// iOS keeps a textarea "composing" while predictive text, dictation or Siri writing is active, and v-model ignores input until
// that ends, so the Send button never replaced the microphone. Read the field directly on every event instead.
function composerText() {
    const text = composer.value?.innerText ?? '';
    // An emptied contenteditable keeps a lone line break.
    return text === '\n' ? '' : text.replace(/\n$/, '');
}

function syncDraft() {
    const el = composer.value;
    if (!el) return;
    const text = composerText();
    if (text !== draft.value) draft.value = text;
    if (!text && el.firstChild) el.textContent = '';
}

// The field grows with its text (CSS); this only writes a draft changed elsewhere (send, edit, emoji) into it.
function resizeComposer() {
    const el = composer.value;
    if (el && composerText() !== draft.value) el.textContent = draft.value;
}

async function loadOlder() {
    if (loadingOlder.value || !hasMore.value) return;
    loadingOlder.value = true;
    const el = scroller.value;
    const before = el.scrollHeight - el.scrollTop;
    try {
        const data = await http.get(`/chat/messages?before=${messages.value[0].id}`);
        messages.value = [...data.messages, ...messages.value];
        hasMore.value = data.hasMore;
        // Keep the reader's place instead of jumping.
        await nextTick();
        el.scrollTop = el.scrollHeight - before;
    } catch {
        toast.error(t('chat.offline'));
    } finally {
        loadingOlder.value = false;
    }
}

function onScroll() {
    if (scroller.value.scrollTop < 80 && hasMore.value) loadOlder();
    if (nearBottom()) {
        showNewPill.value = false;
        markRead();
    }
}

// Flatten into day separators + bubbles with grouping hints.
const rows = computed(() => {
    const all = [...messages.value, ...pending.value.map((p) => ({ ...p, id: `p-${p.client_uuid}` }))];
    const out = [];
    let lastDay = null;
    all.forEach((message, i) => {
        const day = dateKey(message.created_at);
        if (day !== lastDay) {
            out.push({ type: 'day', key: `d-${day}`, label: dayLabel(day, t) });
            lastDay = day;
        }
        const prev = all[i - 1];
        const next = all[i + 1];
        const close = (a, b) => a && b && a.mine === b.mine && Math.abs(new Date(a.created_at) - new Date(b.created_at)) < 5 * 60000 && dateKey(a.created_at) === dateKey(b.created_at);
        out.push({ type: 'message', key: message.id, message, first: !close(prev, message), last: !close(message, next) });
    });
    return out;
});

const lastOwnId = computed(() => [...messages.value].reverse().find((m) => m.mine)?.id ?? null);

function statusFor(message) {
    if (typeof message.id === 'string') return message.status;
    if (message.id !== lastOwnId.value) return null;
    return message.id <= partnerLastRead.value ? 'read' : 'sent';
}

function retry(message) {
    const item = pending.value.find((p) => p.client_uuid === message.client_uuid);
    if (item?.status === 'queued') flushOutbox();
    else if (item) deliver(item);
}

// Messages still waiting from an earlier offline moment show at the end, marked "Menunggu sinyal".
async function showWaiting() {
    const known = new Set([...messages.value.map((m) => m.client_uuid), ...pending.value.map((p) => p.client_uuid)].filter(Boolean));
    for (const item of await waitingItems()) {
        if (known.has(item.client_uuid)) continue;
        pending.value.push({ client_uuid: item.client_uuid, body: item.display?.body ?? '', sticker: item.display?.sticker ?? null, reply: item.display?.reply ?? null, created_at: item.created_at, status: 'queued', mine: true, reactions: [] });
    }
    if (pending.value.some((p) => p.status === 'queued')) flushOutbox();
}

const stopOutbox = onOutbox((event) => {
    if (event.type === 'sent') {
        merge([event.message]);
        scrollToBottom(true);
        return;
    }
    const item = pending.value.find((p) => p.client_uuid === event.client_uuid);
    if (item) item.status = 'failed';
});

function discard(message) {
    pending.value = pending.value.filter((p) => p.client_uuid !== message.client_uuid);
    if (message.status === 'queued') outbox.remove(message.client_uuid).catch(() => {});
}

const remaining = computed(() => props.maxLength - draft.value.length);

// Live updates: the socket only says "something new"; the message itself is
// fetched through the same authorized endpoint as polling. If the socket is
// unavailable the page simply stays in polling mode.
const live = ref(false);
let channelName = null;
let echoInstance = null;
let typingTimer = null;

function startLive() {
    const page = usePage();
    const spaceId = props.spaceId;
    echoInstance = connectRealtime(page.props.app.realtime);
    if (!echoInstance || !spaceId) return;

    channelName = `couple.${spaceId}.chat`;
    echoInstance.private(channelName)
        .listen('.message.sent', () => pollNow())
        .listen('.typing', (e) => {
            if (e.user !== props.partner?.id) return;
            presence.value = { ...presence.value, typing: e.typing, online: true };
            if (e.typing) {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => (presence.value = { ...presence.value, typing: false }), 8000);
            }
        });

    const connection = echoInstance.connector?.pusher?.connection;
    const sync = () => {
        live.value = connection?.state === 'connected';
        // After a reconnect, catch up on anything missed while offline.
        if (live.value) pollNow();
    };
    connection?.bind('state_change', sync);
    sync();
}

// Photos shared from the gallery into the Android app: sent one by one, locked like any picked photo.
async function sendShared() {
    for (const picked of takeFor('chat')) {
        const file = await fitImage(picked);
        if (file.size > 25 * 1024 * 1024) {
            toast.error(t('chat.photoTooBig'));
            continue;
        }
        send(file);
    }
}
watch(() => sharedPhotos.target, (target) => target === 'chat' && sendShared());

onMounted(() => {
    sendShared();
    showWaiting();
    document.addEventListener('visibilitychange', onHidden);
    scrollToBottom();
    nextTick(markRead);
    startLive();
});

onBeforeUnmount(() => {
    stopOutbox();
    document.removeEventListener('visibilitychange', onHidden);
    clearTimeout(saveTimer);
    refreshSaved();
    clearTimeout(typingTimer);
    pending.value.forEach((p) => {
        if (p.photo?.thumb?.startsWith('blob:')) URL.revokeObjectURL(p.photo.thumb);
        if (p.audio?.url?.startsWith('blob:')) URL.revokeObjectURL(p.audio.url);
    });
    if (echoInstance && channelName) echoInstance.leave(channelName);
});
</script>

<template>
    <Head :title="partner ? partner.firstName : t('chat.title')" />
    <div class="chat-fade flex h-dvh flex-col">
        <header class="z-10 flex items-center gap-2 border-b border-[var(--glass-edge)] bg-[var(--glass-strong)] px-2 pb-2 pt-[max(0.5rem,env(safe-area-inset-top))] shadow-soft backdrop-blur-xl chat-bar">
            <Link href="/dashboard" class="grid size-11 place-items-center rounded-full text-plum hover:bg-blush-soft" :aria-label="t('common.back')">
                <ChevronLeft class="size-6" aria-hidden="true" />
            </Link>
            <template v-if="partner">
                <span class="relative shrink-0">
                    <Avatar :name="partner.name" :src="partner.avatarUrl" :frame="partner.avatarFrame" tone="lavender" class="!size-10" />
                    <span v-if="presence.online" class="absolute bottom-0 right-0 size-3 rounded-full bg-online ring-2 ring-surface" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-display text-lg font-semibold leading-tight text-plum">{{ partner.name }}</h1>
                    <p v-if="presenceLabel" class="truncate text-[0.6875rem] font-semibold" :class="presence.typing || presence.online ? 'text-sage-ink' : 'text-ink-muted'" role="status">{{ presenceLabel }}</p>
                    <p v-else class="flex items-center gap-1 text-[0.6875rem] text-ink-muted"><Lock class="size-3 shrink-0" aria-hidden="true" /><span class="truncate">{{ t('chat.privacy') }}</span></p>
                </div>
                <div class="relative flex shrink-0 items-center gap-0.5 pr-1">
                    <button type="button" class="grid size-10 place-items-center rounded-full text-plum hover:bg-blush-soft" :aria-label="t('chat.search')" @click="searchOpen = !searchOpen; menuOpen = false"><Search class="size-5" aria-hidden="true" /></button>
                    <button type="button" class="relative grid size-10 place-items-center rounded-full text-plum hover:bg-blush-soft" :aria-label="t('chat.menu')" :aria-expanded="menuOpen" aria-haspopup="menu" @click="menuOpen = !menuOpen">
                        <EllipsisVertical class="size-5" aria-hidden="true" />
                        <BellOff v-if="muted" class="absolute -right-0.5 -top-0.5 size-3.5 rounded-full bg-surface p-0.5 text-berry" aria-hidden="true" />
                    </button>
                    <div v-if="menuOpen" class="glass absolute right-0 top-full z-30 mt-1 w-60 overflow-hidden rounded-[1.125rem] py-1 shadow-lift" role="menu" @keydown.esc="menuOpen = false">
                        <button type="button" role="menuitem" class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm font-semibold text-plum hover:bg-blush-soft" @click="noteOpen = true; menuOpen = false"><Mail class="size-4 text-rose-ink" aria-hidden="true" />{{ t('chat.loveNote') }}</button>
                        <button type="button" role="menuitem" class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm font-semibold text-plum hover:bg-blush-soft" @click="toggleMute"><component :is="muted ? Bell : BellOff" class="size-4 text-rose-ink" aria-hidden="true" />{{ muted ? t('chat.unmute') : t('chat.mute') }}</button>
                        <p class="px-4 pb-2 pt-1 text-[0.6875rem] text-ink-muted">{{ t('chat.muteHint') }}</p>
                    </div>
                </div>
                <div class="shrink-0 pr-1">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[0.6875rem] font-semibold ring-1" :class="live ? 'bg-sage text-sage-ink ring-sage-ink/20' : 'bg-ivory text-ink-muted ring-line'" role="status" :aria-label="live ? t('chat.live') : t('chat.periodic')">
                        <span class="size-2 rounded-full" :class="live ? 'bg-sage-ink' : 'bg-ink-muted/50'" aria-hidden="true" /><span class="hidden min-[400px]:inline">{{ live ? t('chat.live') : t('chat.periodic') }}</span>
                    </span>
                </div>
            </template>
            <h1 v-else class="font-display text-lg font-semibold text-plum">{{ t('chat.title') }}</h1>
        </header>

        <div v-if="e2e.status === 'waiting' || e2e.passwordPrompt === 'enable'" class="mx-3 mt-2 max-h-[55dvh] overflow-y-auto [&>*]:!mb-0"><E2eBanner /></div>

        <div class="relative"><ChatSearch v-model:open="searchOpen" /></div>

        <button v-if="pinnedList.length" type="button" class="glass mx-3 mt-2 flex items-center gap-2 rounded-[1rem] px-3 py-2 text-left text-xs" :aria-label="t('chat.pinned')" @click="pinnedOpen = !pinnedOpen">
            <Pin class="size-4 shrink-0 text-berry" aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate text-plum"><span class="font-semibold">{{ t('chat.pinned') }} ({{ pinnedList.length }}):</span> {{ previewOf(pinnedList[0]) }}</span>
        </button>
        <ul v-if="pinnedOpen && pinnedList.length" class="glass mx-3 mt-1 divide-y divide-line overflow-hidden rounded-[1rem] text-sm">
            <li v-for="m in pinnedList" :key="m.id" class="flex items-center gap-2 px-3 py-2">
                <span class="min-w-0 flex-1 break-words text-plum">{{ previewOf(m) }}</span>
                <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-blush-soft" :aria-label="t('chat.unpin')" @click="togglePin(m)"><X class="size-4" aria-hidden="true" /></button>
            </li>
        </ul>

        <OfflineBanner />
        <div v-if="failing && !$page.props.offline" class="flex items-center justify-center gap-2 bg-warning-soft px-4 py-2 text-xs font-semibold text-warning" role="status">
            <WifiOff class="size-4" aria-hidden="true" />{{ t('chat.offline') }}
        </div>

        <template v-if="partner">
            <div ref="scroller" class="relative flex-1 overflow-y-auto overscroll-contain px-4 pb-4" @scroll.passive="onScroll">
                <div class="mx-auto max-w-2xl">
                    <p v-if="loadingOlder" class="py-3 text-center text-xs text-ink-muted" role="status">{{ t('chat.loadingOlder') }}</p>
                    <div v-else-if="hasMore" class="py-3 text-center">
                        <button type="button" class="min-h-9 rounded-full px-4 text-xs font-semibold text-rose-ink ring-1 ring-line hover:bg-blush-soft" @click="loadOlder">
                            {{ t('chat.loadOlder') }}
                        </button>
                    </div>
                    <p v-else-if="messages.length" class="py-4 text-center text-xs text-ink-muted">{{ t('chat.start') }}</p>

                    <EmptyState
                        v-if="!messages.length && !pending.length"
                        :title="t('chat.emptyTitle', { name: partner.firstName })"
                        :body="t('chat.emptyBody')"
                        :icon="MessageCircleHeart"
                        class="mt-10"
                    />

                    <template v-for="row in rows" :key="row.key">
                        <div v-if="row.type === 'day'" class="sticky top-2 z-[1] my-3 flex justify-center">
                            <span class="glass rounded-full px-3 py-1 text-[0.6875rem] font-semibold text-ink-muted">{{ row.label }}</span>
                        </div>
                        <ChatBubble
                            v-else
                            :message="row.message"
                            :class="(arrived.has(row.message.id) || typeof row.message.id === 'string') && 'chat-in'"
                            :first-in-group="row.first"
                            :show-meta="row.last || statusFor(row.message) !== null"
                            :status="statusFor(row.message)"
                            :active="activeId === row.message.id"
                            @toggle="activeId = activeId === row.message.id ? null : row.message.id"
                            @react="(e) => react(row.message, e)"
                            @react-more="reactTarget = row.message"
                            @reply="startReply(row.message)"
                            @retry="retry(row.message)"
                            @discard="discard(row.message)"
                            :can-edit="canEdit(row.message)"
                            @open-photo="viewing = row.message.photo"
                            @edit="startEdit(row.message)"
                            @remove="removeMessage(row.message)"
                            @pin="togglePin(row.message)"
                        />
                    </template>
                    <div v-if="presence.typing" class="mt-3 flex justify-start" :aria-label="t('chat.typing')" role="status">
                        <span class="inline-flex items-center gap-1 rounded-[1.375rem] rounded-bl-md bg-surface px-4 py-3 shadow-soft">
                            <span v-for="i in 3" :key="i" class="size-1.5 rounded-full bg-ink-muted/60 motion-safe:animate-bounce" :style="{ animationDelay: `${i * 120}ms` }" />
                        </span>
                    </div>
                </div>
            </div>

            <div class="relative">
                <button
                    v-if="showNewPill"
                    type="button"
                    class="absolute -top-14 left-1/2 inline-flex h-10 -translate-x-1/2 items-center gap-1.5 rounded-full bg-brand px-4 text-sm font-semibold text-white shadow-lift"
                    @click="scrollToBottom(true)"
                >
                    <ArrowDown class="size-4" aria-hidden="true" />{{ t('chat.newMessages') }}
                </button>
                <!-- A div, not a form: inside a form iOS shows its grey arrows/check bar above the keyboard. -->
                <div class="px-3 pb-safe pt-2 chat-bar">
                    <div v-if="stickersOpen" class="mx-auto mb-2 max-w-2xl">
                        <div class="mb-1.5 flex gap-1 rounded-full bg-plum/[0.07] p-1" role="tablist">
                            <button type="button" role="tab" :aria-selected="panelTab === 'emoji'" class="h-8 flex-1 rounded-full text-sm font-semibold transition" :class="panelTab === 'emoji' ? 'bg-surface text-berry-ink shadow-soft' : 'text-ink-muted'" @click="panelTab = 'emoji'">{{ t('chat.emoji') }}</button>
                            <button type="button" role="tab" :aria-selected="panelTab === 'sticker'" class="h-8 flex-1 rounded-full text-sm font-semibold transition" :class="panelTab === 'sticker' ? 'bg-surface text-berry-ink shadow-soft' : 'text-ink-muted'" @click="panelTab = 'sticker'">{{ t('chat.stickers') }}</button>
                        </div>
                        <EmojiPicker v-show="panelTab === 'emoji'" ref="emojiPicker" @pick="insertEmoji" />
                        <StickerPicker v-if="panelTab === 'sticker'" :stickers="stickers" @pick="sendSticker" />
                    </div>
                    <div v-if="editing" class="mx-auto mb-2 flex max-w-2xl items-center gap-2 rounded-[0.875rem] bg-lavender px-3 py-2 text-xs text-lavender-ink">
                        <span class="min-w-0 flex-1 font-semibold">{{ t('chat.editing') }}</span>
                        <button type="button" class="grid size-7 place-items-center rounded-full hover:bg-blush" :aria-label="t('common.cancel')" @click="cancelEdit"><X class="size-4" aria-hidden="true" /></button>
                    </div>
                    <div v-if="replyTo" class="mx-auto mb-2 flex max-w-2xl items-center gap-2 rounded-[0.875rem] bg-blush px-3 py-2 text-xs text-ink-muted">
                        <CornerUpLeft class="size-4 shrink-0 text-rose-ink" aria-hidden="true" />
                        <p class="line-clamp-1 min-w-0 flex-1"><span class="font-semibold text-plum">{{ replyTo.mine ? t('mood.you') : partner.firstName }}</span> {{ previewOf(replyTo) }}</p>
                        <button type="button" class="grid size-7 place-items-center rounded-full hover:bg-blush" :aria-label="t('common.cancel')" @click="replyTo = null"><X class="size-4" aria-hidden="true" /></button>
                    </div>
                    <div v-if="voice.recording.value" class="mx-auto flex max-w-2xl items-center gap-2" role="status">
                        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full text-danger hover:bg-danger-soft" :aria-label="t('chat.voiceCancel')" @click="voice.cancel()"><X class="size-5" aria-hidden="true" /></button>
                        <div class="flex min-h-11 flex-1 items-center gap-3 rounded-[1.375rem] glass px-4">
                            <span class="size-3 rounded-full bg-danger motion-safe:animate-pulse" aria-hidden="true" />
                            <span class="text-base font-semibold tabular-nums text-plum">{{ Math.floor(voice.seconds.value / 60) }}:{{ String(voice.seconds.value % 60).padStart(2, '0') }}</span>
                            <span class="text-sm text-ink-muted">{{ t('chat.voiceRecording') }}</span>
                        </div>
                        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full bg-brand text-white shadow-glow transition active:scale-95 disabled:opacity-40" :disabled="voiceBusy" :aria-label="t('chat.send')" @click="sendVoice"><SendHorizontal class="size-5" aria-hidden="true" /></button>
                    </div>
                    <div v-else class="mx-auto flex max-w-2xl items-end gap-2">
                        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass text-rose-ink" :aria-label="t('chat.emojiAndStickers')" :aria-expanded="stickersOpen" @click="stickersOpen = !stickersOpen"><Smile class="size-5" aria-hidden="true" /></button>
                        <button v-if="!editing" type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass text-rose-ink" :aria-label="t('chat.sendPhoto')" @click="photoInput?.click()">
                            <ImagePlus class="size-5" aria-hidden="true" />
                        </button>
                        <button v-if="!editing" type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass text-rose-ink" :aria-label="t('chat.takePhoto')" @click="cameraInput?.click()">
                            <Camera class="size-5" aria-hidden="true" />
                        </button>
                        <!-- contenteditable, not a textarea: iOS shows its arrows/check bar above the keyboard for form fields only. -->
                        <div
                            id="chat-input"
                            ref="composer"
                            role="textbox"
                            aria-multiline="true"
                            :aria-label="t('chat.placeholder')"
                            contenteditable="plaintext-only"
                            :data-placeholder="t('chat.placeholder')"
                            class="composer max-h-36 min-h-11 min-w-0 flex-1 overflow-y-auto whitespace-pre-wrap break-words rounded-[1.375rem] glass px-4 py-2.5 text-base text-plum focus:outline-none"
                            enterkeyhint="send"
                            @input="syncDraft(); signalTyping()"
                            @compositionupdate="syncDraft"
                            @compositionend="syncDraft"
                            @keyup="syncDraft"
                            @blur="syncDraft"
                            @keydown="onKeydown"
                        />
                        <button
                            v-if="draft.trim() || editing || !voice.supported"
                            type="button"
                            class="grid size-11 shrink-0 place-items-center rounded-full bg-brand text-white shadow-glow transition active:scale-95 disabled:opacity-40"
                            @click="send()"
                            :disabled="!draft.trim() || remaining < 0"
                            :aria-label="t('chat.send')"
                        >
                            <SendHorizontal class="size-5" aria-hidden="true" />
                        </button>
                        <button v-else type="button" class="grid size-11 shrink-0 place-items-center rounded-full bg-brand text-white shadow-glow transition active:scale-95" :aria-label="t('chat.voiceRecord')" @click="startVoice">
                            <Mic class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                    <p v-if="remaining < 200" class="mx-auto mt-1 max-w-2xl px-3 text-xs" :class="remaining < 0 ? 'text-danger' : 'text-ink-muted'">
                        {{ remaining < 0 ? t('chat.tooLong', { max: maxLength }) : remaining }}
                    </p>
                </div>
                <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" tabindex="-1" aria-hidden="true" @change="pickPhoto" />
                <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" tabindex="-1" aria-hidden="true" @change="pickPhoto" />
            </div>
        </template>

        <div v-else class="flex flex-1 items-center justify-center px-6">
            <EmptyState :title="t('chat.noPartnerTitle')" :body="t('chat.noPartnerBody')" :icon="MessageCircleHeart">
                <Button href="/couple/invite" variant="soft">{{ t('dashboard.invite') }}</Button>
            </EmptyState>
        </div>

        <LoveNoteDialog v-model:open="noteOpen" />
        <ConfirmDialog v-model:open="deleteOpen" :title="t('chat.delete')" :description="t('chat.deleteConfirm')" :confirm-label="t('common.delete')" danger @confirm="confirmRemove" />
        <Teleport to="body">
            <div v-if="reactTarget" class="fixed inset-0 z-50 flex items-end justify-center bg-[rgb(24_12_20/0.45)] p-3 pb-safe" role="dialog" aria-modal="true" :aria-label="t('chat.moreEmoji')" @click.self="reactTarget = null" @keydown.esc="reactTarget = null">
                <div class="w-full max-w-md"><EmojiPicker @pick="reactWithPicked" /></div>
            </div>
        </Teleport>
        <Teleport to="body"><div v-if="viewing" class="fixed inset-0 z-50 grid place-items-center bg-[rgb(24_12_20/0.92)] p-4" role="dialog" aria-modal="true" :aria-label="t('chat.photo')" @click="viewing = null" @keydown.esc="viewing = null">
            <img :src="viewing.full ?? viewing.thumb" alt="" class="max-h-full max-w-full rounded-lg object-contain" />
            <a :href="viewing.full ?? viewing.thumb" :download="`loveynest-${Date.now()}.jpg`" class="absolute bottom-[max(1.5rem,env(safe-area-inset-bottom))] inline-flex h-11 items-center gap-2 rounded-full bg-[rgb(255_255_255/0.18)] px-5 text-sm font-semibold text-[#fff]" @click.stop><Download class="size-4" aria-hidden="true" />{{ t('albums.save') }}</a>
            <button type="button" class="absolute right-4 top-[max(1rem,env(safe-area-inset-top))] grid size-11 place-items-center rounded-full bg-[rgb(255_255_255/0.16)] text-[#fff]" :aria-label="t('common.close')" @click.stop="viewing = null"><X class="size-6" aria-hidden="true" /></button>
        </div></Teleport>

        <ToastRegion />
        <ShareSheet v-if="NATIVE" />
    </div>
</template>
