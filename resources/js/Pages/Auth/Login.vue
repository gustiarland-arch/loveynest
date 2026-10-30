<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextField from '@/Components/ui/TextField.vue';
import Button from '@/Components/ui/Button.vue';
import { AtSign, LockKeyhole } from '@lucide/vue';
import { t } from '@/i18n';
import { forgetPassword, rememberPassword } from '@/lib/e2e';

const form = useForm({ email: '', password: '', remember: true });

function submit() {
    rememberPassword(form.password);
    form.post('/login', { onError: forgetPassword, onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t('auth.login')" />
    <div class="flex flex-1 flex-col justify-center py-10">
        <Link href="/" class="enter mx-auto block" style="--d: 0ms" aria-label="LoveyNest">
            <img src="/img/loveynest-logo.png" alt="LoveyNest" width="260" height="205" class="logo-float mx-auto h-auto w-56 max-w-full" draggable="false" />
        </Link>
        <h1 class="enter mt-4 text-balance text-center text-[1.75rem] font-semibold leading-[1.15] text-plum" style="--d: 120ms">{{ $t('auth.loginTitle') }}</h1>
        <p class="enter mx-auto mt-2 max-w-72 text-center text-sm text-ink-muted" style="--d: 180ms">{{ $t('auth.loginLead') }}</p>

        <form class="enter glass mt-6 space-y-4 rounded-[1.5rem] p-5" style="--d: 260ms" novalidate @submit.prevent="submit">
            <TextField v-model="form.email" :label="$t('auth.email')" type="email" :icon="AtSign" autocomplete="email" inputmode="email" :error="form.errors.email" required />
            <TextField v-model="form.password" :label="$t('auth.password')" type="password" :icon="LockKeyhole" autocomplete="current-password" :error="form.errors.password" required />
            <label class="flex min-h-11 items-center gap-3 text-sm text-plum">
                <input v-model="form.remember" type="checkbox" class="size-5 rounded border-line-strong accent-berry" />
                {{ $t('auth.remember') }}
            </label>
            <Button type="submit" size="lg" block :loading="form.processing">{{ $t('auth.login') }}</Button>
            <p class="text-center text-xs text-ink-muted">{{ $t('auth.forgot') }}</p>
        </form>

        <p class="enter mt-6 text-center text-sm text-ink-muted" style="--d: 360ms">
            {{ $t('auth.noAccount') }}
            <Link href="/register" class="font-semibold text-berry-ink underline-offset-4 hover:underline">{{ $t('auth.register') }}</Link>
        </p>
    </div>
</template>
