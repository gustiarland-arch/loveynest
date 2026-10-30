<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Lock, MessageCircleHeart } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import SectionTitle from '@/Components/layout/SectionTitle.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import { dayLabel } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const props = defineProps({
    question: { type: String, required: true },
    partner: { type: Object, default: null },
    mine: { type: String, default: null },
    theirs: { type: String, default: null },
    partnerAnswered: { type: Boolean, default: false },
    history: { type: Array, default: () => [] },
});

const form = useForm({ answer: props.mine ?? '' });
const submit = () => submitSealed(form, ['answer'], (f) => f.post('/pertanyaan', { preserveScroll: true }));
</script>

<template>
    <Head :title="t('question.title')" />
    <PageHeader :title="t('question.title')" :lead="t('question.lead')" back="/berdua" />

    <Card tone="lavender">
        <p class="flex items-center gap-1.5 text-xs font-semibold text-lavender-ink"><MessageCircleHeart class="size-4" aria-hidden="true" />{{ t('question.today') }}</p>
        <p class="mt-2 font-display text-2xl font-semibold leading-snug text-plum">{{ question }}</p>
    </Card>

    <Card class="mt-4"><form class="space-y-3" novalidate @submit.prevent="submit">
        <TextArea v-model="form.answer" :label="t('question.yours')" :rows="3" :maxlength="500" :error="form.errors.answer" />
        <Button type="submit" block :loading="form.processing">{{ mine ? t('mood.update') : t('question.send') }}</Button>
    </form></Card>

    <Card class="mt-4">
        <p class="text-sm font-semibold text-plum">{{ t('question.theirs', { name: partner?.firstName ?? '' }) }}</p>
        <p v-if="theirs" class="mt-2 whitespace-pre-wrap break-words text-plum">{{ theirs }}</p>
        <p v-else-if="!mine" class="mt-2 flex items-center gap-2 text-sm text-ink-muted"><Lock class="size-4" aria-hidden="true" />{{ partnerAnswered ? t('question.lockedAnswered') : t('question.locked') }}</p>
        <p v-else class="mt-2 text-sm text-ink-muted">{{ t('question.waiting') }}</p>
    </Card>

    <template v-if="history.length">
        <SectionTitle :title="t('question.history')" />
        <ul class="space-y-3">
            <li v-for="h in history" :key="h.date" class="glass rounded-[1.25rem] p-4 ">
                <p class="text-xs text-ink-muted">{{ dayLabel(h.date, t) }}</p>
                <p class="mt-1 font-semibold text-plum">{{ h.question }}</p>
                <p v-if="h.mine" class="mt-2 text-sm text-plum"><span class="font-semibold">{{ t('mood.you') }}:</span> {{ h.mine }}</p>
                <p v-if="h.theirs && h.mine" class="mt-1 text-sm text-plum"><span class="font-semibold">{{ partner?.firstName }}:</span> {{ h.theirs }}</p>
                <p v-else-if="h.theirs" class="mt-1 text-xs text-ink-muted">{{ t('question.missed') }}</p>
            </li>
        </ul>
    </template>
</template>
