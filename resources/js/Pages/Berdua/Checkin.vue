<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Lock, Users } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import TextArea from '@/Components/ui/TextArea.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import { formatDate } from '@/lib/dates';
import { t } from '@/i18n';
import { submitSealed } from '@/lib/e2e';

const props = defineProps({ week: String, current: Object, history: Array, partner: Object, partnerRows: Array });

const faces = ['😞', '😕', '😐', '🙂', '🥰'];
const form = useForm({
    feeling: props.current?.feeling ?? 3,
    appreciation: props.current?.appreciation ?? '',
    improve: props.current?.improve ?? '',
    shared: props.current?.shared ?? false,
});
function save() {
    submitSealed(form, ['appreciation', 'improve'], (f) => f.put('/checkin', { preserveScroll: true }), (d) => ({ ...d, appreciation: d.appreciation || null, improve: d.improve || null }));
}
</script>

<template>
    <Head :title="t('checkin.title')" />
    <PageHeader :title="t('checkin.title')" :lead="t('checkin.lead')" back="/berdua" />

    <Card>
        <form class="space-y-5" novalidate @submit.prevent="save">
            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-plum">{{ t('checkin.feeling') }}</legend>
                <div class="grid grid-cols-5 gap-2">
                    <button v-for="(f, i) in faces" :key="i" type="button" class="grid h-14 place-items-center rounded-[1rem] text-2xl ring-1 transition" :class="form.feeling === i + 1 ? 'bg-blush ring-berry' : 'bg-surface ring-line'" :aria-pressed="form.feeling === i + 1" :aria-label="t(`checkin.f${i + 1}`)" @click="form.feeling = i + 1">{{ f }}</button>
                </div>
            </fieldset>
            <TextArea v-model="form.appreciation" :label="t('checkin.appreciation')" :maxlength="300" :error="form.errors.appreciation" />
            <TextArea v-model="form.improve" :label="t('checkin.improve')" :maxlength="300" :error="form.errors.improve" />
            <Toggle v-model="form.shared" :label="t('checkin.share')" />
            <p class="flex items-start gap-1.5 text-xs text-ink-muted"><component :is="form.shared ? Users : Lock" class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />{{ form.shared ? t('checkin.sharedNote', { name: partner?.name ?? '' }) : t('checkin.privateNote') }}</p>
            <Button type="submit" block :loading="form.processing">{{ current ? t('checkin.update') : t('common.save') }}</Button>
        </form>
    </Card>

    <section v-if="partnerRows.length" class="mt-6">
        <h2 class="mb-2 text-lg font-semibold text-plum">{{ t('checkin.fromPartner', { name: partner.name }) }}</h2>
        <ul class="space-y-2">
            <li v-for="r in partnerRows" :key="r.week" class="glass rounded-[1.25rem] p-4">
                <p class="text-xs font-semibold text-ink-muted">{{ t('checkin.week', { date: formatDate(r.week) }) }} · {{ faces[r.feeling - 1] }}</p>
                <p v-if="r.appreciation" class="mt-1 break-words text-sm text-plum">💗 {{ r.appreciation }}</p>
                <p v-if="r.improve" class="mt-1 break-words text-sm text-plum">🌱 {{ r.improve }}</p>
            </li>
        </ul>
    </section>

    <section v-if="history.length" class="mt-6">
        <h2 class="mb-2 text-lg font-semibold text-plum">{{ t('checkin.history') }}</h2>
        <ul class="space-y-2">
            <li v-for="r in history" :key="r.week" class="glass rounded-[1.25rem] p-4">
                <p class="text-xs font-semibold text-ink-muted">{{ t('checkin.week', { date: formatDate(r.week) }) }} · {{ faces[r.feeling - 1] }}<template v-if="!r.shared"> · {{ t('checkin.onlyMe') }}</template></p>
                <p v-if="r.appreciation" class="mt-1 break-words text-sm text-plum">💗 {{ r.appreciation }}</p>
                <p v-if="r.improve" class="mt-1 break-words text-sm text-plum">🌱 {{ r.improve }}</p>
            </li>
        </ul>
    </section>
</template>
