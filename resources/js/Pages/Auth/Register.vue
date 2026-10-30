<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextField from '@/Components/ui/TextField.vue';
import Button from '@/Components/ui/Button.vue';
import { browserTimezone } from '@/lib/dates';
import { t } from '@/i18n';
import { forgetPassword, rememberPassword } from '@/lib/e2e';

const form = useForm({
    email: '',
    password: '',
    timezone: browserTimezone(),
});

function submit() {
    rememberPassword(form.password);
    form.post('/register', { onError: forgetPassword, onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t('auth.registerTitle')" />
    <div class="flex flex-1 flex-col justify-center py-10">
        <Link href="/" class="enter mx-auto mb-4 block" style="--d: 0ms" aria-label="LoveyNest"><img src="/img/loveynest-logo.png" alt="LoveyNest" width="200" height="158" class="logo-float mx-auto h-auto w-48" draggable="false" /></Link>
        <h1 class="text-center text-3xl font-semibold text-plum">{{ $t('auth.registerTitle') }}</h1>
        <p class="mx-auto mt-2 max-w-sm text-center text-sm text-ink-muted">{{ $t('auth.registerLead') }}</p>

        <form class="glass mt-7 space-y-4 rounded-[1.5rem] p-5" novalidate @submit.prevent="submit">
            <TextField v-model="form.email" :label="$t('auth.email')" type="email" autocomplete="email" inputmode="email" :error="form.errors.email" required />
            <TextField
                v-model="form.password"
                :label="$t('auth.password')"
                type="password"
                autocomplete="new-password"
                :hint="$t('auth.passwordHint')"
                :error="form.errors.password"
                required
            />
            <Button type="submit" size="lg" block :loading="form.processing" class="!mt-6">{{ $t('auth.register') }}</Button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            {{ $t('auth.haveAccount') }}
            <Link href="/login" class="font-semibold text-berry-ink underline-offset-4 hover:underline">{{ $t('auth.login') }}</Link>
        </p>
    </div>
</template>
