<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, Share2, RefreshCw, HeartHandshake, Clock } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Emblem from '@/Components/brand/Emblem.vue';
import { useToast } from '@/composables/useToast';
import { formatDateTime } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    space: { type: Object, required: true },
    members: { type: Array, required: true },
    invite: { type: Object, default: null },
    hasOpenInvite: { type: Boolean, default: false },
});

const toast = useToast();
const connected = computed(() => props.members.length >= 2);
const canShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function';
const confirmRevoke = ref(false);
const busy = ref(false);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.invite.code);
        toast.success(t('invite.copied'));
    } catch {
        toast.info(props.invite.code);
    }
}

async function share() {
    try {
        await navigator.share({ text: t('invite.shareText', { code: props.invite.code }) });
    } catch {
        // The user closed the share sheet; nothing to do.
    }
}

// Check whether the partner has joined when the user comes back to this tab,
// instead of polling in the background.
function refreshOnReturn() {
    if (document.visibilityState === 'visible' && !connected.value) router.reload({ only: ['members', 'invite', 'hasOpenInvite'] });
}
onMounted(() => document.addEventListener('visibilitychange', refreshOnReturn));
onBeforeUnmount(() => document.removeEventListener('visibilitychange', refreshOnReturn));

function regenerate() {
    router.post('/couple/invitations', {}, { onStart: () => (busy.value = true), onFinish: () => (busy.value = false) });
}

function revoke() {
    router.delete('/couple/invitations', { onFinish: () => (confirmRevoke.value = false) });
}
</script>

<template>
    <Head :title="t('invite.title')" />
    <div class="flex flex-1 flex-col py-10">
        <template v-if="connected">
            <div class="flex flex-1 flex-col items-center justify-center text-center">
                <Emblem :size="140" />
                <div class="mt-6 flex -space-x-3">
                    <Avatar v-for="(member, i) in members" :key="member.id" :name="member.name" :src="member.avatarUrl" :frame="member.avatarFrame" size="lg" :tone="i ? 'lavender' : 'blush'" />
                </div>
                <h1 class="mt-5 text-3xl font-semibold text-plum">{{ $t('invite.connectedTitle') }}</h1>
                <p class="mt-2 max-w-xs text-sm text-ink-muted">{{ $t('invite.connectedBody') }}</p>
                <Button href="/dashboard" size="lg" class="mt-8">{{ $t('invite.toDashboard') }}</Button>
            </div>
        </template>

        <template v-else>
            <span class="grid size-12 place-items-center rounded-md bg-blush"><HeartHandshake class="size-6 text-rose-ink" aria-hidden="true" /></span>
            <h1 class="mt-4 text-3xl font-semibold text-plum">{{ $t('invite.title') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ $t('invite.lead') }}</p>

            <div v-if="invite" class="mt-8 rounded-xl bg-ivory p-6 text-center shadow-soft ring-1 ring-line">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-ink">{{ $t('onboarding.code') }}</p>
                <p class="mt-3 select-all font-mono text-[2.25rem] font-semibold tracking-[0.18em] text-plum" data-testid="invite-code">{{ invite.code }}</p>
                <p class="mt-2 inline-flex items-center gap-1.5 text-xs text-ink-muted">
                    <Clock class="size-3.5" aria-hidden="true" />
                    {{ $t('invite.expires', { time: formatDateTime(invite.expires_at) }) }}
                </p>
                <div class="mt-5 grid gap-2" :class="canShare ? 'grid-cols-2' : 'grid-cols-1'">
                    <Button variant="soft" @click="copy"><Copy class="size-4" aria-hidden="true" />{{ $t('invite.copy') }}</Button>
                    <Button v-if="canShare" variant="soft" @click="share"><Share2 class="size-4" aria-hidden="true" />{{ $t('invite.share') }}</Button>
                </div>
            </div>

            <div v-else class="mt-8 rounded-lg bg-ivory p-5 text-sm text-ink-muted ring-1 ring-line">
                {{ hasOpenInvite ? $t('invite.hidden') : $t('invite.none') }}
            </div>

            <p class="mt-6 text-sm text-ink-muted">{{ $t('invite.steps') }}</p>
            <p v-if="invite" class="mt-4 flex items-center gap-2 text-sm font-semibold text-rose-ink">
                <span class="size-2 animate-pulse rounded-full bg-rose" aria-hidden="true" />{{ $t('invite.waiting') }}
            </p>

            <div class="mt-auto flex flex-col gap-2 pt-10">
                <Button variant="secondary" block :loading="busy" @click="regenerate"><RefreshCw class="size-4" aria-hidden="true" />{{ $t('invite.regenerate') }}</Button>
                <Button v-if="hasOpenInvite" variant="ghost" block @click="confirmRevoke = true">{{ $t('invite.revoke') }}</Button>
                <Link href="/dashboard" class="mx-auto mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-ink-muted hover:text-plum">
                    {{ $t('onboarding.skip') }}
                </Link>
            </div>

            <ConfirmDialog v-model:open="confirmRevoke" :title="$t('invite.revoke') + '?'" :confirm-label="$t('invite.revoke')" danger @confirm="revoke" />
        </template>
    </div>
</template>
