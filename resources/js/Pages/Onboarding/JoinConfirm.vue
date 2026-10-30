<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Emblem from '@/Components/brand/Emblem.vue';
import { t } from '@/i18n';

const props = defineProps({
    code: { type: String, required: true },
    inviter: { type: String, required: true },
    spaceName: { type: String, default: null },
});

const form = useForm({ code: props.code });
</script>

<template>
    <Head :title="t('join.title')" />
    <div class="flex flex-1 flex-col justify-center py-10 text-center">
        <Emblem :size="120" class="mx-auto" />
        <h1 class="mt-6 text-3xl font-semibold text-plum">{{ $t('join.title') }}</h1>
        <p class="mt-2 text-base text-ink-muted">
            {{ spaceName ? $t('join.leadNamed', { name: inviter, space: spaceName }) : $t('join.lead', { name: inviter }) }}
        </p>

        <div class="mt-8 rounded-lg bg-ivory p-5 text-left ring-1 ring-line">
            <p class="text-sm font-semibold text-plum">{{ $t('join.what') }}</p>
            <ul class="mt-3 space-y-3 text-sm text-ink-muted">
                <li v-for="key in ['point1', 'point2', 'point3']" :key="key" class="flex gap-3">
                    <Check class="mt-0.5 size-4 shrink-0 text-sage-ink" aria-hidden="true" />
                    {{ $t(`join.${key}`) }}
                </li>
            </ul>
        </div>

        <div class="mt-8 flex flex-col gap-3">
            <Button size="lg" block :loading="form.processing" @click="form.post('/couple/join/confirm')">{{ $t('join.confirm') }}</Button>
            <Button href="/onboarding" variant="ghost" size="lg" block>{{ $t('join.cancel') }}</Button>
        </div>
    </div>
</template>
