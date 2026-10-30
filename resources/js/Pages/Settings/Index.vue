<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { LogOut, Heart, Camera, Trash2, Settings, Sparkles, Image as ImageIcon } from '@lucide/vue';
import Dialog from '@/Components/ui/Dialog.vue';
import DateField from '@/Components/ui/DateField.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import TextField from '@/Components/ui/TextField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import Button from '@/Components/ui/Button.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import { disable as disablePush } from '@/lib/push';
import { t } from '@/i18n';
import { fitImage } from '@/lib/image';
import { FRAMES, frameUrl } from '@/lib/frames';
import ProfileCardBackdrop, { CARDS, cardTone } from '@/Components/settings/ProfileCardBackdrop.vue';
import { refresh as refreshE2e, rememberPassword } from '@/lib/e2e';

const props = defineProps({
    profile: { type: Object, required: true },
    couple: { type: Object, default: null },
    timezones: { type: Array, required: true },
});

const form = useForm({ name: props.profile.name, timezone: props.profile.timezone, nickname: props.profile.nickname ?? '', gender: props.profile.gender ?? '', birthday: props.profile.birthday ?? '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
// A device that holds the couple key stores it again under the new password, so password recovery keeps working.
function changePassword() {
    const next = passwordForm.password;
    passwordForm.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => {
            rememberPassword(next);
            refreshE2e();
            passwordForm.reset();
        },
    });
}
const genderOptions = [{ value: '', label: t('settings.genderNone') }, ...['perempuan', 'laki-laki', 'lainnya'].map((value) => ({ value, label: t(`settings.genders.${value}`) }))];
const zoneOptions = props.timezones.map((zone) => ({ value: zone, label: zone.replaceAll('_', ' ') }));

const frameOpen = ref(false);
const cardOpen = ref(false);
const pickedFrame = ref(props.profile.avatarFrame ?? null);
function pickFrame(id) {
    const before = pickedFrame.value;
    pickedFrame.value = id;
    router.patch('/settings/avatar-frame', { frame: id }, { preserveScroll: true, onError: () => (pickedFrame.value = before) });
}
const pickedCard = ref(props.profile.card ?? null);
const cardError = ref('');
const onDark = computed(() => cardTone(pickedCard.value) === 'light');
function pickCard(id) {
    const before = pickedCard.value;
    pickedCard.value = id;
    cardError.value = '';
    router.patch('/settings/profile-card', { card: id }, { preserveScroll: true, onError: (errors) => { pickedCard.value = before; cardError.value = errors.card ?? ''; } });
}
const fileInput = ref(null);
const avatarError = ref('');
async function uploadAvatar(event) {
    const picked = event.target.files?.[0];
    event.target.value = '';
    avatarError.value = '';
    if (!picked) return;
    const file = await fitImage(picked, 1_800_000, 1600);
    if (file.size > 10 * 1024 * 1024) {
        avatarError.value = t('settings.photoTooBig');
        return;
    }
    router.post('/settings/avatar', { avatar: file }, { forceFormData: true, preserveScroll: true, onError: (errors) => (avatarError.value = errors.avatar ?? t('settings.photoFailed')) });
}
function removeAvatar() {
    router.delete('/settings/avatar', { preserveScroll: true });
}

async function logout() {
    // The next person to sign in on this browser must not inherit this device's notifications.
    await disablePush().catch(() => {});
    router.post('/logout');
}
</script>

<template>
    <Head :title="t('settings.title')" />
    <PageHeader :title="t('settings.title')">
        <template #action>
            <Link href="/pengaturan" class="grid size-11 place-items-center rounded-full glass text-plum transition active:scale-95" :aria-label="t('pengaturan.open')"><Settings class="size-5" aria-hidden="true" /></Link>
        </template>
    </PageHeader>

    <div class="space-y-4">
        <Card class="relative isolate overflow-hidden !p-4">
            <ProfileCardBackdrop :id="pickedCard" class="-z-10" />
            <div v-if="pickedCard" class="absolute inset-0 -z-10" :class="onDark ? 'bg-black/30' : 'bg-white/35'" aria-hidden="true" />
            <div class="flex items-center gap-4">
                <div class="relative shrink-0">
                    <Avatar :name="profile.name" :src="profile.avatarUrl" :frame="pickedFrame" size="hero" class="m-4" />
                    <button
                        type="button"
                        class="absolute bottom-[1.1rem] right-[1.1rem] z-10 grid size-8 place-items-center rounded-full bg-berry text-white ring-2 ring-surface shadow-soft transition active:scale-95"
                        :aria-label="$t('settings.photoChange')"
                        @click="fileInput.click()"
                    >
                        <Camera class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <div class="min-w-0">
                    <p class="truncate font-display text-[1.1875rem] font-semibold" :class="pickedCard ? (onDark ? 'text-white [text-shadow:0_1px_6px_rgb(0_0_0/0.35)]' : 'text-[#2a1722]') : 'text-plum'">{{ profile.name }}</p>
                    <p class="truncate text-xs" :class="pickedCard ? (onDark ? 'text-white/85' : 'text-[#4a3340]') : 'text-ink-muted'">{{ profile.email }}</p>
                    <span v-if="couple?.partner" class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-sage px-2.5 py-1 text-[0.72rem] font-semibold text-sage-ink">
                        <Heart class="size-3" aria-hidden="true" />{{ $t('settings.connectedWith', { name: couple.partner }) }}
                    </span>
                    <Button v-if="profile.avatarUrl" size="sm" variant="danger-ghost" class="-ml-3 mt-1 !h-8" @click="removeAvatar"><Trash2 class="size-4" aria-hidden="true" />{{ $t('settings.photoRemove') }}</Button>
                </div>
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" :aria-label="$t('settings.photoChange')" @change="uploadAvatar" />
            </div>
            <p v-if="avatarError" class="mt-3 text-sm font-semibold text-danger" role="alert">{{ avatarError }}</p>
        </Card>
        <div class="flex gap-2">
            <button type="button" class="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full glass px-3 text-sm font-semibold text-plum transition active:scale-95" @click="frameOpen = true"><Sparkles class="size-4" aria-hidden="true" />{{ $t('settings.frameButton') }}</button>
            <button type="button" class="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full glass px-3 text-sm font-semibold text-plum transition active:scale-95" @click="cardOpen = true"><ImageIcon class="size-4" aria-hidden="true" />{{ $t('settings.cardButton') }}</button>
        </div>

        <Dialog v-model:open="frameOpen" :title="$t('settings.frameTitle')" :description="$t('settings.frameHint')">
            <div class="grid grid-cols-3 gap-2.5" role="radiogroup" :aria-label="$t('settings.frameTitle')">
                <button
                    v-for="id in [null, ...FRAMES]"
                    :key="id ?? 'none'"
                    type="button"
                    role="radio"
                    :aria-checked="pickedFrame === id"
                    class="flex flex-col items-center gap-1.5 rounded-2xl border-2 px-1 pb-2 pt-3 text-center transition active:scale-95"
                    :class="pickedFrame === id ? 'border-berry bg-blush/40' : 'border-transparent bg-cream'"
                    @click="pickFrame(id)"
                >
                    <Avatar :name="profile.name" :src="profile.avatarUrl" :frame="id" size="lg" class="m-3" />
                    <span class="text-[0.72rem] font-semibold text-plum">{{ id ? $t(`settings.frames.${id}`) : $t('settings.frameNone') }}</span>
                </button>
            </div>
        </Dialog>

        <Dialog v-model:open="cardOpen" :title="$t('settings.cardTitle')" :description="$t('settings.cardHint')">
            <div class="grid grid-cols-2 gap-2.5" role="radiogroup" :aria-label="$t('settings.cardTitle')">
                <button
                    v-for="id in [null, ...CARDS.map((c) => c.id)]"
                    :key="id ?? 'none'"
                    type="button"
                    role="radio"
                    :aria-checked="pickedCard === id"
                    class="overflow-hidden rounded-2xl border-2 text-left transition active:scale-[0.98]"
                    :class="pickedCard === id ? 'border-berry' : 'border-transparent'"
                    @click="pickCard(id)"
                >
                    <span class="relative block h-20 bg-cream">
                        <ProfileCardBackdrop :id="id" />
                        <span v-if="!id" class="absolute inset-0 grid place-items-center text-xs font-semibold text-ink-muted">{{ $t('settings.cardNone') }}</span>
                        <span v-else class="absolute inset-x-2 bottom-1.5 text-[0.72rem] font-semibold" :class="cardTone(id) === 'light' ? 'text-white [text-shadow:0_1px_4px_rgb(0_0_0/0.45)]' : 'text-[#2a1722]'">{{ $t(`settings.cards.${id}`) }}</span>
                    </span>
                </button>
            </div>
            <p v-if="cardError" class="mt-2 text-sm font-semibold text-danger" role="alert">{{ cardError }}</p>
        </Dialog>

        <h2 class="!mb-2 !mt-6 px-1 font-sans text-[0.72rem] font-bold uppercase tracking-[0.1em] text-ink-muted">{{ $t('settings.groupAccount') }}</h2>
        <Card>
            <form class="space-y-4" @submit.prevent="form.transform((d) => ({ ...d, nickname: d.nickname || null, gender: d.gender || null, birthday: d.birthday || null })).patch('/settings/profile', { preserveScroll: true })">
                <TextField v-model="form.name" :label="$t('settings.name')" :maxlength="60" :error="form.errors.name" />
                <TextField v-model="form.nickname" :label="$t('settings.nickname')" :hint="$t('settings.nicknameHint')" :maxlength="30" :error="form.errors.nickname" />
                <SelectField v-model="form.gender" :label="$t('settings.gender')" :options="genderOptions" :error="form.errors.gender" />
                <DateField v-model="form.birthday" :label="$t('settings.birthday')" clearable :error="form.errors.birthday" />
                <SelectField v-model="form.timezone" :label="$t('settings.timezone')" :options="zoneOptions" :error="form.errors.timezone" />
                <p class="-mt-2 text-xs text-ink-muted">{{ $t('settings.timezoneHint') }}</p>
                <Button type="submit" :loading="form.processing" :disabled="!form.isDirty">{{ $t('common.save') }}</Button>
            </form>
        </Card>

        <h2 class="!mb-2 !mt-6 px-1 font-sans text-[0.72rem] font-bold uppercase tracking-[0.1em] text-ink-muted">{{ $t('settings.groupSecurity') }}</h2>
        <Card>
            <h3 class="font-display text-lg font-semibold text-plum">{{ $t('settings.password') }}</h3>
            <form
                class="mt-4 space-y-4"
                @submit.prevent="changePassword"
            >
                <TextField
                    v-model="passwordForm.current_password"
                    :label="$t('settings.currentPassword')"
                    type="password"
                    autocomplete="current-password"
                    :error="passwordForm.errors.current_password"
                />
                <TextField
                    v-model="passwordForm.password"
                    :label="$t('settings.newPassword')"
                    type="password"
                    autocomplete="new-password"
                    :hint="$t('auth.passwordHint')"
                    :error="passwordForm.errors.password"
                />
                <TextField v-model="passwordForm.password_confirmation" :label="$t('auth.passwordConfirm')" type="password" autocomplete="new-password" />
                <Button type="submit" variant="secondary" :loading="passwordForm.processing">{{ $t('common.save') }}</Button>
            </form>
        </Card>

        <Button variant="secondary" block class="!text-danger" @click="logout"><LogOut class="size-4" aria-hidden="true" />{{ $t('auth.logout') }}</Button>
    </div>
</template>
