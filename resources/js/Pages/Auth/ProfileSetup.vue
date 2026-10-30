<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Camera } from '@lucide/vue';
import TextField from '@/Components/ui/TextField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import DateField from '@/Components/ui/DateField.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import { fitImage } from '@/lib/image';
import { t } from '@/i18n';

const props = defineProps({ profile: { type: Object, required: true } });

const form = useForm({ name: props.profile.name, nickname: props.profile.nickname, gender: props.profile.gender, birthday: props.profile.birthday, timezone: props.profile.timezone });
const genderOptions = [{ value: '', label: t('profileSetup.genderPick') }, ...['perempuan', 'laki-laki', 'lainnya'].map((value) => ({ value, label: t(`settings.genders.${value}`) }))];

const fileInput = ref(null);
const avatarError = ref('');
const uploading = ref(false);
async function pickPhoto(event) {
    const picked = event.target.files?.[0];
    event.target.value = '';
    avatarError.value = '';
    if (!picked) return;
    uploading.value = true;
    try {
        const file = await fitImage(picked, 1_800_000, 1600);
        if (file.size > 10 * 1024 * 1024) {
            avatarError.value = t('settings.photoTooBig');
            return;
        }
        router.post('/settings/avatar', { avatar: file }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => (avatarError.value = errors.avatar ?? t('settings.photoFailed')),
            onFinish: () => (uploading.value = false),
        });
    } catch {
        avatarError.value = t('settings.photoFailed');
        uploading.value = false;
    }
}

function submit() {
    form.transform((d) => ({ ...d, nickname: d.nickname || null, birthday: d.birthday || null })).post('/profil/lengkapi');
}
</script>

<template>
    <Head :title="t('profileSetup.title')" />
    <div class="flex flex-1 flex-col justify-center py-10">
        <h1 class="text-center text-3xl font-semibold text-plum">{{ $t('profileSetup.title') }}</h1>
        <p class="mx-auto mt-2 max-w-sm text-center text-sm text-ink-muted">{{ $t('profileSetup.lead') }}</p>

        <form class="glass mt-7 space-y-4 rounded-[1.5rem] p-5" novalidate @submit.prevent="submit">
            <div class="flex flex-col items-center gap-2">
                <div class="relative">
                    <Avatar :name="form.name || '?'" :src="$page.props.auth.user.avatarUrl" :frame="$page.props.auth.user.avatarFrame" size="lg" />
                    <button type="button" class="absolute -bottom-1 -right-1 grid size-8 place-items-center rounded-full bg-berry text-white ring-2 ring-surface transition active:scale-95 disabled:opacity-50" :disabled="uploading" :aria-label="$t('profileSetup.photoChange')" @click="fileInput.click()">
                        <Camera class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p class="text-xs text-ink-muted">{{ $t('profileSetup.photo') }}. {{ $t('profileSetup.photoHint') }}</p>
                <p v-if="avatarError" class="text-sm font-semibold text-danger" role="alert">{{ avatarError }}</p>
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" :aria-label="$t('profileSetup.photoChange')" @change="pickPhoto" />
            </div>

            <TextField v-model="form.name" :label="$t('profileSetup.name')" :hint="$t('profileSetup.nameHint')" autocomplete="name" :maxlength="60" :error="form.errors.name" required />
            <TextField v-model="form.nickname" :label="$t('profileSetup.nickname')" :maxlength="30" :error="form.errors.nickname" />
            <SelectField v-model="form.gender" :label="$t('profileSetup.gender')" :options="genderOptions" :error="form.errors.gender" />
            <DateField v-model="form.birthday" :label="$t('profileSetup.birthday')" clearable :error="form.errors.birthday" />
            <Button type="submit" size="lg" block :loading="form.processing" class="!mt-6">{{ $t('profileSetup.next') }}</Button>
        </form>
    </div>
</template>
