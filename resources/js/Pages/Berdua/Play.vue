<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Check, Shuffle, Award, Lock, SkipForward, Gift, Trash2, Sparkles } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import TextField from '@/Components/ui/TextField.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Button from '@/Components/ui/Button.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { t } from '@/i18n';
import { sealedVisit } from '@/lib/e2e';

const props = defineProps({ tab: String, partner: Object, quiz: Array, score: Object, challenges: Array, cards: Object, badges: Array, rewards: Array, ai: { type: Object, default: () => ({ enabled: false, count: 0 }) } });

const current = ref(props.tab);
const tabs = ['quiz', 'challenge', 'cards', 'badges', 'rewards'].map((value) => ({ value, label: t(`play.tab.${value}`) }));

// Quiz: answer about yourself, then guess your partner's answer.
const draft = ref({});
const open = computed(() => props.quiz.find((q) => !q.mine));
function pickSelf(q, i) { draft.value[q.index] = { ...(draft.value[q.index] ?? {}), self: i }; }
function pickGuess(q, i) { draft.value[q.index] = { ...(draft.value[q.index] ?? {}), guess: i }; }
const ready = (q) => draft.value[q.index]?.self !== undefined && draft.value[q.index]?.guess !== undefined;
function submit(q) {
    router.post('/main/kuis', { question_index: q.index, self_choice: draft.value[q.index].self, guess_choice: draft.value[q.index].guess }, { preserveScroll: true, only: ['quiz', 'score', 'badges'] });
}
const answered = computed(() => props.quiz.filter((q) => q.mine));

function toggleChallenge(c) {
    router.post('/main/tantangan', { key: c.key }, { preserveScroll: true, only: ['challenges', 'badges'] });
}

// New questions written by the AI.
const aiTopic = ref('');
const aiBusy = ref(false);
const aiError = ref('');
function makeAiQuestions() {
    aiBusy.value = true;
    aiError.value = '';
    router.post('/main/kuis/ai', { topic: aiTopic.value }, {
        preserveScroll: true,
        only: ['quiz', 'ai', 'score', 'flash', 'errors'],
        onSuccess: () => (aiTopic.value = ''),
        onError: (errors) => (aiError.value = errors.ai ?? errors.topic ?? t('play.aiFailed')),
        onFinish: () => (aiBusy.value = false),
    });
}
const aiConfirm = ref(false);
function clearAi() {
    aiConfirm.value = false;
    router.delete('/main/kuis/ai', { preserveScroll: true, only: ['quiz', 'ai', 'score', 'flash'] });
}

// Rewards you made up yourselves.
const rewardTitle = ref('');
function addReward() {
    if (!rewardTitle.value.trim()) return;
    sealedVisit('post', '/main/hadiah', { title: rewardTitle.value.trim() }, ['title'], { preserveScroll: true, only: ['rewards'], onSuccess: () => { rewardTitle.value = ''; } });
}
const toggleReward = (r) => router.patch(`/main/hadiah/${r.id}`, {}, { preserveScroll: true, only: ['rewards'] });
const removeReward = (r) => router.delete(`/main/hadiah/${r.id}`, { preserveScroll: true, only: ['rewards'] });

// Cards are drawn on the device; nothing is stored.
const kinds = ['would', 'truth', 'dare'];
const kind = ref('would');
const drawn = ref(null);
function draw() {
    const pool = props.cards[kind.value];
    let next = pool[Math.floor(Math.random() * pool.length)];
    if (pool.length > 1) while (next === drawn.value) next = pool[Math.floor(Math.random() * pool.length)];
    drawn.value = next;
}
function setKind(k) { kind.value = k; drawn.value = null; }
</script>

<template>
    <Head :title="t('play.title')" />
    <PageHeader :title="t('play.title')" :lead="t('play.lead')" back="/arena" />
    <SegmentedControl v-model="current" :options="tabs" :label="t('play.title')" class="mb-4" />

    <section v-if="current === 'quiz'" class="space-y-3">
        <p class="rounded-[1rem] bg-lavender px-4 py-3 text-xs text-lavender-ink">{{ t('play.quizNote') }}</p>
        <p v-if="!partner" class="text-sm text-ink-muted">{{ t('play.needPartner') }}</p>

        <Card v-if="open">
            <p class="text-xs font-semibold text-ink-muted">{{ t('play.questionOf', { n: open.number, total: quiz.length }) }}</p>
            <h2 class="mt-1 break-words font-display text-xl font-semibold text-plum">{{ open.question }}</h2>
            <p class="mb-1.5 mt-4 text-sm font-semibold text-plum">{{ t('play.aboutMe') }}</p>
            <div class="grid gap-2"><button v-for="(c, i) in open.choices" :key="`s${i}`" type="button" class="min-h-11 rounded-[1rem] px-4 text-left text-sm font-semibold ring-1 transition" :class="draft[open.index]?.self === i ? 'bg-blush ring-berry text-plum' : 'bg-surface ring-line text-ink-muted'" :aria-pressed="draft[open.index]?.self === i" @click="pickSelf(open, i)">{{ c }}</button></div>
            <p class="mb-1.5 mt-4 text-sm font-semibold text-plum">{{ t('play.guess', { name: partner?.name ?? t('chat.partner') }) }}</p>
            <div class="grid gap-2"><button v-for="(c, i) in open.choices" :key="`g${i}`" type="button" class="min-h-11 rounded-[1rem] px-4 text-left text-sm font-semibold ring-1 transition" :class="draft[open.index]?.guess === i ? 'bg-lavender ring-berry text-plum' : 'bg-surface ring-line text-ink-muted'" :aria-pressed="draft[open.index]?.guess === i" @click="pickGuess(open, i)">{{ c }}</button></div>
            <Button class="mt-5" block :disabled="!ready(open)" @click="submit(open)">{{ t('play.save') }}</Button>
        </Card>
        <Card v-else tone="sage"><p class="font-semibold text-plum">{{ t('play.allDone') }}</p></Card>

        <p v-if="score.of" class="glass rounded-[1.25rem] px-4 py-3 text-sm font-semibold text-plum">{{ t('play.score', { matched: score.matched, of: score.of }) }}</p>

        <Card>
            <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-plum"><Sparkles class="size-5 text-berry" aria-hidden="true" />{{ t('play.aiTitle') }}</h2>
            <template v-if="ai.enabled">
                <p class="mt-1 text-sm text-ink-muted">{{ t('play.aiLead') }}</p>
                <form class="mt-3 flex items-end gap-2" @submit.prevent="makeAiQuestions">
                    <div class="min-w-0 flex-1"><TextField v-model="aiTopic" :label="t('play.aiTopic')" :maxlength="60" :placeholder="t('play.aiTopicHint')" /></div>
                    <Button type="submit" :loading="aiBusy">{{ aiBusy ? t('play.aiWorking') : t('play.aiMake') }}</Button>
                </form>
                <p v-if="aiError" class="mt-2 text-sm text-danger" role="alert">{{ aiError }}</p>
                <p v-if="ai.count" class="mt-3 text-xs text-ink-muted">{{ t('play.aiCount', { count: ai.count }) }} <button type="button" class="font-semibold text-berry-ink underline-offset-4 hover:underline" @click="aiConfirm = true">{{ t('play.aiClear') }}</button></p>
            </template>
            <p v-else class="mt-1 text-sm text-ink-muted">{{ t('play.aiOff') }}</p>
        </Card>

        <ul class="space-y-2">
            <li v-for="q in answered" :key="q.index" class="glass rounded-[1.25rem] p-4">
                <p class="break-words text-sm font-semibold text-plum">{{ q.question }}</p>
                <template v-if="q.reveal">
                    <p class="mt-1 text-xs text-ink-muted">{{ t('play.you') }}: {{ q.choices[q.mine.self] }} · {{ t('play.yourGuess') }}: {{ q.choices[q.mine.guess] }}</p>
                    <p class="text-xs text-ink-muted">{{ partner?.name }}: {{ q.choices[q.reveal.partnerSelf] }}</p>
                    <p class="mt-1 text-xs font-semibold" :class="q.mine.guess === q.reveal.partnerSelf ? 'text-sage-ink' : 'text-ink-muted'">{{ q.mine.guess === q.reveal.partnerSelf ? t('play.hit') : t('play.miss') }}</p>
                </template>
                <p v-else class="mt-1 flex items-center gap-1 text-xs text-ink-muted"><Lock class="size-3.5" aria-hidden="true" />{{ t('play.waiting') }}</p>
            </li>
        </ul>
    </section>

    <section v-else-if="current === 'challenge'" class="space-y-2">
        <p class="text-sm text-ink-muted">{{ t('play.challengeNote') }}</p>
        <button v-for="c in challenges" :key="c.key" type="button" class="glass flex w-full items-center gap-3 rounded-[1.25rem] p-3.5 text-left" :aria-pressed="c.today" @click="toggleChallenge(c)">
            <span class="grid size-8 shrink-0 place-items-center rounded-full ring-1" :class="c.today ? 'bg-sage text-sage-ink ring-transparent' : 'bg-surface ring-line'"><Check v-if="c.today" class="size-4" aria-hidden="true" /></span>
            <span class="min-w-0 flex-1"><span class="block break-words text-sm font-semibold text-plum">{{ c.text }}</span><span v-if="c.times" class="block text-xs text-ink-muted">{{ t('play.times', { count: c.times }) }}</span></span>
        </button>
    </section>

    <section v-else-if="current === 'cards'" class="space-y-4">
        <SegmentedControl :model-value="kind" :options="kinds.map((k) => ({ value: k, label: t(`play.kind.${k}`) }))" :label="t('play.cards')" @update:model-value="setKind" />
        <Card class="grid min-h-44 place-items-center text-center">
            <p v-if="drawn" class="break-words font-display text-2xl font-semibold leading-snug text-plum" aria-live="polite">{{ drawn }}</p>
            <p v-else class="text-sm text-ink-muted">{{ t('play.drawHint') }}</p>
        </Card>
        <div class="grid grid-cols-2 gap-2"><Button @click="draw"><Shuffle class="size-4" aria-hidden="true" />{{ drawn ? t('play.next') : t('play.draw') }}</Button><Button variant="secondary" :disabled="!drawn" @click="draw"><SkipForward class="size-4" aria-hidden="true" />{{ t('play.skip') }}</Button></div>
        <p class="text-center text-xs text-ink-muted">{{ t('play.skipNote') }}</p>
    </section>

    <section v-else-if="current === 'rewards'" class="space-y-3">
        <p class="text-sm text-ink-muted">{{ t('play.rewardNote') }}</p>
        <form class="flex items-end gap-2" @submit.prevent="addReward"><div class="min-w-0 flex-1"><TextField v-model="rewardTitle" :label="t('play.rewardField')" :maxlength="120" /></div><Button type="submit" :disabled="!rewardTitle.trim()">{{ t('growth.add') }}</Button></form>
        <ul class="space-y-2">
            <li v-for="r in rewards" :key="r.id" class="glass flex items-center gap-3 rounded-[1.25rem] p-3">
                <Gift class="size-5 shrink-0 text-berry" aria-hidden="true" />
                <p class="min-w-0 flex-1 break-words text-sm font-semibold text-plum" :class="r.given && 'line-through opacity-60'">{{ r.title }}</p>
                <button type="button" class="min-h-10 rounded-full px-3 text-xs font-semibold ring-1 ring-line" :class="r.given ? 'bg-blush text-plum' : 'bg-surface text-ink-muted'" @click="toggleReward(r)">{{ r.given ? t('play.rewardGiven') : t('play.rewardOpen') }}</button>
                <button type="button" class="grid size-10 place-items-center rounded-full text-danger" :aria-label="t('play.rewardDelete')" @click="removeReward(r)"><Trash2 class="size-4" aria-hidden="true" /></button>
            </li>
        </ul>
        <p v-if="!rewards.length" class="text-sm text-ink-muted">{{ t('play.rewardEmpty') }}</p>
    </section>

    <section v-else class="space-y-3">
        <p class="text-sm text-ink-muted">{{ t('play.badgeNote') }}</p>
        <ul class="grid grid-cols-2 gap-3">
            <li v-for="b in badges" :key="b.key" class="glass rounded-[1.25rem] p-4 text-center" :class="!b.earned && 'opacity-60'">
                <span class="mx-auto grid size-12 place-items-center rounded-full" :class="b.earned ? 'bg-brand text-white' : 'bg-plum/[0.08] text-ink-muted'"><Award v-if="b.earned" class="size-6" aria-hidden="true" /><Lock v-else class="size-5" aria-hidden="true" /></span>
                <p class="mt-2 break-words text-sm font-semibold text-plum">{{ b.label }}</p>
                <p class="text-xs text-ink-muted">{{ b.earned ? t('play.earned') : t('play.locked') }}</p>
            </li>
        </ul>
    </section>

    <ConfirmDialog v-model:open="aiConfirm" :title="t('play.aiClearTitle')" :description="t('play.aiClearBody')" :confirm-label="t('play.aiClear')" danger @confirm="clearAi" />
</template>
