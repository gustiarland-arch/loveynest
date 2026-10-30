<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Dices, ListPlus } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import { ideas } from '@/lib/ideas';
import { useToast } from '@/composables/useToast';
import { t } from '@/i18n';
import { sealedVisit } from '@/lib/e2e';

const toast = useToast();
const page = usePage();
const hasCouple = computed(() => page.props.auth.user?.hasSpace);
const tag = ref('semua');
const index = ref(Math.floor(Math.random() * ideas.length));
const tags = ['semua', 'santai', 'rumah', 'seru', 'romantis'];
const cost = ref('semua');
const costs = ['semua', 'gratis', 'murah', 'sedang', 'spesial'];
const pool = computed(() => {
    const found = ideas.filter((i) => (tag.value === 'semua' || i.tag === tag.value) && (cost.value === 'semua' || i.cost === cost.value));
    return found.length ? found : ideas.filter((i) => tag.value === 'semua' || i.tag === tag.value);
});
const poolEmpty = computed(() => !ideas.some((i) => (tag.value === 'semua' || i.tag === tag.value) && (cost.value === 'semua' || i.cost === cost.value)));
const current = computed(() => pool.value[index.value % pool.value.length]);

function shuffle() {
    let next = index.value;
    while (pool.value.length > 1 && next % pool.value.length === index.value % pool.value.length) next = Math.floor(Math.random() * pool.value.length);
    index.value = next;
}

function save() {
    sealedVisit('post', '/daftar', { kind: 'bucket', space: hasCouple.value ? 'ours' : 'mine', title: current.value.text, note: '' }, ['title'], {
        preserveScroll: true,
        onSuccess: () => toast.success(t('ideas.saved')),
    });
}
</script>

<template>
    <Head :title="t('ideas.title')" />
    <PageHeader :title="t('ideas.title')" :lead="t('ideas.lead')" back="/berdua" />

    <div class="mb-4 flex flex-wrap gap-2" role="group" :aria-label="t('ideas.filter')">
        <button v-for="g in tags" :key="g" type="button" class="h-9 rounded-full px-4 text-sm font-semibold capitalize transition" :aria-pressed="tag === g"
            :class="tag === g ? 'bg-berry text-white shadow-soft' : 'bg-surface text-ink-muted ring-1 ring-blush/70'" @click="tag = g; shuffle()">{{ g }}</button>
    </div>

    <div class="mb-4 flex flex-wrap gap-2" role="group" :aria-label="t('ideas.budget')">
        <button v-for="c in costs" :key="c" type="button" class="h-9 rounded-full px-4 text-sm font-semibold capitalize transition" :aria-pressed="cost === c"
            :class="cost === c ? 'bg-berry text-white shadow-soft' : 'bg-surface text-ink-muted ring-1 ring-blush/70'" @click="cost = c; shuffle()">{{ c === 'semua' ? t('ideas.anyBudget') : t(`ideas.cost.${c}`) }}</button>
    </div>
    <p v-if="poolEmpty" class="mb-3 text-xs text-ink-muted">{{ t('ideas.noneForBudget') }}</p>

    <Card tone="blush">
        <p class="text-xs font-semibold uppercase tracking-wider text-rose-ink">{{ current.tag }} · {{ t(`ideas.cost.${current.cost}`) }}</p>
        <p class="mt-2 font-display text-2xl font-semibold leading-snug text-plum">{{ current.text }}</p>
        <div class="mt-5 flex flex-wrap gap-2">
            <Button @click="shuffle"><Dices class="size-4" aria-hidden="true" />{{ t('ideas.shuffle') }}</Button>
            <Button variant="secondary" @click="save"><ListPlus class="size-4" aria-hidden="true" />{{ t('ideas.save') }}</Button>
        </div>
    </Card>
</template>
