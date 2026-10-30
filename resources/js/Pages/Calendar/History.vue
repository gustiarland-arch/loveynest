<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Star, CalendarHeart } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import TextField from '@/Components/ui/TextField.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

defineProps({ dates: Array, average: Number });

const target = ref(null);
const form = useForm({ rating: 0, rating_note: '' });
function open(d) {
    target.value = d;
    form.clearErrors();
    form.defaults({ rating: d.rating ?? 0, rating_note: d.rating_note ?? '' });
    form.reset();
}
function save() {
    submitSealed(form, ['rating_note'], (f) => f.patch(`/events/${target.value.id}/rating`, { preserveScroll: true, onSuccess: () => (target.value = null) }), (d) => ({ rating: d.rating, rating_note: d.rating_note || null }));
}
</script>

<template>
    <Head :title="t('dateHistory.title')" />
    <PageHeader :title="t('dateHistory.title')" :lead="t('dateHistory.lead')" back="/calendar" />
    <p v-if="average" class="glass mb-3 rounded-[1.25rem] px-4 py-3 text-sm font-semibold text-plum">{{ t('dateHistory.average', { value: average }) }}</p>

    <ul v-if="dates.length" class="space-y-2">
        <li v-for="d in dates" :key="d.id" class="glass rounded-[1.25rem] p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0"><p class="break-words font-semibold text-plum">{{ d.title }}</p><p class="text-xs text-ink-muted">{{ formatDate(d.starts_at) }}</p></div>
                <Button size="sm" variant="secondary" @click="open(d)">{{ d.rating ? t('dateHistory.rated') : t('dateHistory.rate') }}</Button>
            </div>
            <p v-if="d.rating" class="mt-1 flex items-center gap-0.5 text-berry" :aria-label="t('dateHistory.star', { count: d.rating })"><Star v-for="n in 5" :key="n" class="size-4" :fill="n <= d.rating ? 'currentColor' : 'none'" aria-hidden="true" /></p>
            <p v-if="d.rating_note" class="mt-1 break-words text-sm text-plum">{{ d.rating_note }}</p>
        </li>
    </ul>
    <Card v-else :padded="false"><EmptyState :title="t('dateHistory.empty')" :body="t('dateHistory.emptyBody')" :icon="CalendarHeart" /></Card>

    <Dialog :open="!!target" :title="target?.title ?? ''" @update:open="(v) => !v && (target = null)">
        <form id="rate-form" class="space-y-4" novalidate @submit.prevent="save">
            <div class="flex justify-center gap-1" role="group" :aria-label="t('dateHistory.rate')">
                <button v-for="n in 5" :key="n" type="button" class="grid size-12 place-items-center rounded-full text-berry hover:bg-blush-soft" :aria-pressed="form.rating === n" :aria-label="t('dateHistory.star', { count: n })" @click="form.rating = n"><Star class="size-7" :fill="n <= form.rating ? 'currentColor' : 'none'" aria-hidden="true" /></button>
            </div>
            <p v-if="form.errors.rating" class="text-center text-xs text-danger" role="alert">{{ form.errors.rating }}</p>
            <TextField v-model="form.rating_note" :label="t('dateHistory.note')" :maxlength="200" />
        </form>
        <template #footer><Button variant="secondary" @click="target = null">{{ t('common.cancel') }}</Button><Button type="submit" form="rate-form" :loading="form.processing" :disabled="!form.rating">{{ t('common.save') }}</Button></template>
    </Dialog>
</template>
