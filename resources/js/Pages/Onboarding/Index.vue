<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { HeartHandshake, KeyRound } from '@lucide/vue';
import TextField from '@/Components/ui/TextField.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/i18n';

const page = usePage();
const firstName = (page.props.auth.user?.name ?? '').split(' ')[0];

const createForm = useForm({ name: '' });
const joinForm = useForm({ code: '' });
</script>

<template>
    <Head :title="t('onboarding.createTitle')" />
    <div class="flex flex-1 flex-col py-10">
        <h1 class="text-3xl font-semibold text-plum">{{ $t('onboarding.title', { name: firstName }) }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ $t('onboarding.lead') }}</p>

        <Card tone="blush" class="mt-8">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-md bg-ivory"><HeartHandshake class="size-5 text-rose-ink" aria-hidden="true" /></span>
                <div>
                    <h2 class="text-xl font-semibold">{{ $t('onboarding.createTitle') }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">{{ $t('onboarding.createBody') }}</p>
                </div>
            </div>
            <form class="mt-5 space-y-4" @submit.prevent="createForm.post('/couple')">
                <TextField
                    v-model="createForm.name"
                    :label="$t('onboarding.spaceName')"
                    :hint="$t('onboarding.spaceNameHint')"
                    :maxlength="60"
                    :error="createForm.errors.name"
                />
                <Button type="submit" block :loading="createForm.processing">{{ $t('onboarding.create') }}</Button>
            </form>
        </Card>

        <div class="my-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-widest text-ink-muted" aria-hidden="true">
            <span class="h-px flex-1 bg-line-strong" />{{ $t('onboarding.or') }}<span class="h-px flex-1 bg-line-strong" />
        </div>

        <Card tone="lavender">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-md bg-ivory"><KeyRound class="size-5 text-lavender-ink" aria-hidden="true" /></span>
                <div>
                    <h2 class="text-xl font-semibold">{{ $t('onboarding.joinTitle') }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">{{ $t('onboarding.joinBody') }}</p>
                </div>
            </div>
            <form class="mt-5 space-y-4" @submit.prevent="joinForm.post('/couple/join')">
                <TextField
                    v-model="joinForm.code"
                    :label="$t('onboarding.code')"
                    placeholder="XXXX-XXXX"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    :maxlength="20"
                    class="font-mono uppercase tracking-[0.2em]"
                    :error="joinForm.errors.code"
                />
                <Button type="submit" variant="secondary" block :loading="joinForm.processing" :disabled="joinForm.code.trim().length < 8">
                    {{ $t('onboarding.check') }}
                </Button>
            </form>
        </Card>

        <Link href="/dashboard" class="mx-auto mt-8 inline-flex min-h-11 items-center text-sm font-semibold text-ink-muted underline-offset-4 hover:text-plum hover:underline">
            {{ $t('onboarding.skip') }}
        </Link>
    </div>
</template>
